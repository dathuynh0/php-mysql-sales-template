<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

/* ------------------------------------------------------------
 * Danh mục
 * ---------------------------------------------------------- */
$sqlCategories = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categoryResult = $connection->query($sqlCategories);

$categories = [];

while ($category = $categoryResult->fetch_assoc()) {
    $categories[] = $category;
}

$categoryResult->free();

/* ------------------------------------------------------------
 * Tham số lọc & phân trang
 * ---------------------------------------------------------- */
$categoryID = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$itemsPerPage = 12; // 12 chia hết cho 2, 3, 4 cột nên lưới luôn đầy hàng

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$searchKeyword = '%' . $keyword . '%';

/**
 * Tạo URL phân trang, bỏ các tham số rỗng cho URL gọn.
 */
function buildProductsUrl(string $keyword, int $categoryID, int $page): string
{
    $params = [];

    if ($keyword !== '') {
        $params['keyword'] = $keyword;
    }

    if ($categoryID > 0) {
        $params['category'] = $categoryID;
    }

    if ($page > 1) {
        $params['page'] = $page;
    }

    $query = http_build_query($params);

    return '/products.php' . ($query !== '' ? '?' . $query : '');
}

/* ------------------------------------------------------------
 * Đếm tổng sản phẩm
 * ---------------------------------------------------------- */
$sqlCount = "
    SELECT
        COUNT(*) AS TotalProducts
    FROM
        products p,
        categories c
    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
";

if ($categoryID > 0) {
    $sqlCount .= "
        AND p.CategoryID = ?
    ";
}

if ($keyword !== '') {
    $sqlCount .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}

$stmtCount = $connection->prepare($sqlCount);

if ($categoryID > 0 && $keyword !== '') {

    $stmtCount->bind_param(
        'iss',
        $categoryID,
        $searchKeyword,
        $searchKeyword
    );

} elseif ($categoryID > 0) {

    $stmtCount->bind_param(
        'i',
        $categoryID
    );

} elseif ($keyword !== '') {

    $stmtCount->bind_param(
        'ss',
        $searchKeyword,
        $searchKeyword
    );
}

$stmtCount->execute();

$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();

$totalProducts = (int) $countRow['TotalProducts'];

$countResult->free();
$stmtCount->close();

$totalPages = (int) ceil(
    $totalProducts / $itemsPerPage
);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $itemsPerPage;

/* ------------------------------------------------------------
 * Lấy danh sách sản phẩm của trang hiện tại
 * ---------------------------------------------------------- */
$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        c.CategoryName,

        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile

    FROM
        products p,
        categories c

    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
";

if ($categoryID > 0) {
    $sql .= "
        AND p.CategoryID = ?
    ";
}

if ($keyword !== '') {
    $sql .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}

$sql .= "
    ORDER BY
        p.ProductID DESC
    LIMIT ?
    OFFSET ?
";

$stmt = $connection->prepare($sql);

if ($categoryID > 0 && $keyword !== '') {

    $stmt->bind_param(
        'issii',
        $categoryID,
        $searchKeyword,
        $searchKeyword,
        $itemsPerPage,
        $offset
    );

} elseif ($categoryID > 0) {

    $stmt->bind_param(
        'iii',
        $categoryID,
        $itemsPerPage,
        $offset
    );

} elseif ($keyword !== '') {

    $stmt->bind_param(
        'ssii',
        $searchKeyword,
        $searchKeyword,
        $itemsPerPage,
        $offset
    );

} else {

    $stmt->bind_param(
        'ii',
        $itemsPerPage,
        $offset
    );
}

$stmt->execute();

$result = $stmt->get_result();

/* ------------------------------------------------------------
 * Dữ liệu hiển thị
 * ---------------------------------------------------------- */
$hasFilter = ($keyword !== '' || $categoryID > 0);

$selectedCategoryName = '';

foreach ($categories as $category) {
    if ((int) $category['CategoryID'] === $categoryID) {
        $selectedCategoryName = $category['CategoryName'];
        break;
    }
}

// Cửa sổ phân trang: trang hiện tại ± 2
$windowStart = max(1, $page - 2);
$windowEnd   = min($totalPages, $page + 2);

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<style>
    .products-hero {
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
    }

    .filter-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .product-card {
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        overflow: hidden;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .product-card:hover,
    .product-card:focus-within {
        border-color: #94a3b8;
        box-shadow: 0 .5rem 1.25rem rgba(15, 23, 42, .08);
    }

    .product-thumb {
        background: #f8fafc;
        padding: 1rem;
    }

    .product-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .product-name {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.8em;
        line-height: 1.4;
        font-size: 1rem;
        font-weight: 600;
    }

    .product-name a {
        color: #0f172a;
        text-decoration: none;
    }

    /* Cả thẻ có thể bấm được, nhưng chỉ có một liên kết thật cho bàn phím */
    .product-name a::after {
        content: "";
        position: absolute;
        inset: 0;
    }

    .product-price {
        color: #be123c;
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .pagination .page-link {
        color: #334155;
        border-radius: .5rem;
        margin: 0 .15rem;
        border-color: #e2e8f0;
    }

    .pagination .page-item.active .page-link {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
    }

    .pagination .page-item.disabled .page-link {
        background: transparent;
    }

    @media (prefers-reduced-motion: reduce) {
        .product-card {
            transition: none;
        }
    }
</style>

<!-- Tiêu đề trang -->
<section class="products-hero">
    <div class="container py-4 py-lg-5">

        <h1 class="h2 fw-bold mb-1">Sản phẩm</h1>

        <p class="text-secondary mb-0">
            Tìm theo tên hoặc mã sản phẩm, hoặc lọc theo danh mục.
        </p>

    </div>
</section>

<main class="container py-4 py-lg-5">

    <!-- Bộ lọc -->
    <form
        method="get"
        action="/products.php"
        class="filter-panel p-3 p-lg-4 mb-4"
    >

        <div class="row g-3 align-items-end">

            <div class="col-12 col-lg-5">

                <label
                    for="keyword"
                    class="form-label fw-semibold"
                >
                    Tìm sản phẩm
                </label>

                <div class="input-group">

                    <span class="input-group-text bg-white text-secondary">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </span>

                    <input
                        type="search"
                        name="keyword"
                        id="keyword"
                        class="form-control"
                        value="<?= htmlspecialchars($keyword) ?>"
                        placeholder="Nhập tên hoặc mã sản phẩm"
                    >

                </div>

            </div>

            <div class="col-12 col-sm-6 col-lg-4">

                <label
                    for="category"
                    class="form-label fw-semibold"
                >
                    Danh mục
                </label>

                <select
                    name="category"
                    id="category"
                    class="form-select"
                >

                    <option value="0">
                        Tất cả danh mục
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int) $category['CategoryID'] ?>"
                            <?= $categoryID === (int) $category['CategoryID']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars($category['CategoryName']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-12 col-sm-6 col-lg-3">

                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-dark flex-grow-1"
                    >
                        Tìm kiếm
                    </button>

                    <?php if ($hasFilter): ?>
                        <a
                            href="/products.php"
                            class="btn btn-outline-secondary"
                        >
                            Xóa lọc
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        </div>

    </form>

    <!-- Kết quả & bộ lọc đang áp dụng -->
    <div
        class="d-flex flex-wrap align-items-center
               justify-content-between gap-2 mb-4"
    >

        <p class="mb-0 text-secondary">
            <strong class="text-dark"><?= $totalProducts ?></strong>
            sản phẩm
            <?php if ($totalPages > 1): ?>
                <span class="ms-1">
                    (trang <?= $page ?>/<?= $totalPages ?>)
                </span>
            <?php endif; ?>
        </p>

        <?php if ($hasFilter): ?>

            <div class="d-flex flex-wrap gap-2">

                <?php if ($keyword !== ''): ?>
                    <a
                        href="<?= htmlspecialchars(buildProductsUrl('', $categoryID, 1)) ?>"
                        class="badge rounded-pill text-bg-light border
                               text-decoration-none fw-normal py-2 px-3"
                        title="Bỏ từ khóa này"
                    >
                        Từ khóa: <?= htmlspecialchars($keyword) ?> &times;
                    </a>
                <?php endif; ?>

                <?php if ($categoryID > 0 && $selectedCategoryName !== ''): ?>
                    <a
                        href="<?= htmlspecialchars(buildProductsUrl($keyword, 0, 1)) ?>"
                        class="badge rounded-pill text-bg-light border
                               text-decoration-none fw-normal py-2 px-3"
                        title="Bỏ danh mục này"
                    >
                        <?= htmlspecialchars($selectedCategoryName) ?> &times;
                    </a>
                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

    <!-- Lưới sản phẩm -->
    <?php if ($result->num_rows > 0): ?>

        <div class="row g-3 g-lg-4 row-cols-2 row-cols-md-3 row-cols-xl-4">

            <?php while ($product = $result->fetch_assoc()): ?>

                <div class="col">

                    <article class="card product-card h-100 position-relative">

                        <div class="product-thumb ratio ratio-1x1">

                            <?php if (!empty($product['ImageFile'])): ?>

                                <img
                                    src="/upload/products/<?= htmlspecialchars($product['ImageFile']) ?>"
                                    alt="<?= htmlspecialchars($product['ProductName']) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div
                                    class="d-flex align-items-center
                                           justify-content-center
                                           text-secondary small"
                                >
                                    Chưa có hình ảnh
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="card-body d-flex flex-column p-3">

                            <p class="text-secondary small mb-1">
                                <?= htmlspecialchars($product['CategoryName']) ?>
                            </p>

                            <h2 class="product-name mb-2">
                                <a href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>">
                                    <?= htmlspecialchars($product['ProductName']) ?>
                                </a>
                            </h2>

                            <p class="text-secondary small mb-3">
                                Mã: <?= htmlspecialchars($product['ProductCode']) ?>
                            </p>

                            <p class="product-price mt-auto mb-0">
                                <?= number_format(
                                    (float) $product['Price'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </p>

                        </div>

                    </article>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="text-center py-5 border rounded-3 bg-light">

            <h2 class="h5 fw-semibold">
                <?= $hasFilter
                    ? 'Không tìm thấy sản phẩm phù hợp'
                    : 'Chưa có sản phẩm nào' ?>
            </h2>

            <p class="text-secondary mb-4">
                <?= $hasFilter
                    ? 'Thử đổi từ khóa hoặc chọn danh mục khác.'
                    : 'Sản phẩm sẽ xuất hiện ở đây khi cửa hàng cập nhật.' ?>
            </p>

            <?php if ($hasFilter): ?>
                <a href="/products.php" class="btn btn-dark">
                    Xem tất cả sản phẩm
                </a>
            <?php endif; ?>

        </div>

    <?php endif; ?>

    <!-- Phân trang -->
    <?php if ($totalPages > 1): ?>

        <nav
            class="mt-5"
            aria-label="Phân trang sản phẩm"
        >
            <ul class="pagination justify-content-center flex-wrap mb-0">

                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(buildProductsUrl($keyword, $categoryID, max(1, $page - 1))) ?>"
                        <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>
                    >
                        &laquo; Trước
                    </a>
                </li>

                <?php if ($windowStart > 1): ?>

                    <li class="page-item">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars(buildProductsUrl($keyword, $categoryID, 1)) ?>"
                        >1</a>
                    </li>

                    <?php if ($windowStart > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">&hellip;</span>
                        </li>
                    <?php endif; ?>

                <?php endif; ?>

                <?php for ($pageNumber = $windowStart; $pageNumber <= $windowEnd; $pageNumber++): ?>

                    <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars(buildProductsUrl($keyword, $categoryID, $pageNumber)) ?>"
                            <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>
                        >
                            <?= $pageNumber ?>
                        </a>
                    </li>

                <?php endfor; ?>

                <?php if ($windowEnd < $totalPages): ?>

                    <?php if ($windowEnd < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">&hellip;</span>
                        </li>
                    <?php endif; ?>

                    <li class="page-item">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars(buildProductsUrl($keyword, $categoryID, $totalPages)) ?>"
                        ><?= $totalPages ?></a>
                    </li>

                <?php endif; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(buildProductsUrl($keyword, $categoryID, min($totalPages, $page + 1))) ?>"
                        <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>
                    >
                        Sau &raquo;
                    </a>
                </li>

            </ul>
        </nav>

    <?php endif; ?>

</main>

<?php

$result->free();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';