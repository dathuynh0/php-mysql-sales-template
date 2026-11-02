<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Trang chủ';

/* ---------- Hàm dùng chung ---------- */

function fetchAll(mysqli $connection, string $sql): array
{
    $rows = [];

    $result = $connection->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $result->free();
    }

    return $rows;
}

function renderProductCard(
    array $product,
    string $badgeText = '',
    string $badgeClass = 'success'
): void {

    $outOfStock =
        (int) $product['StockQuantity'] === 0;

?>

    <div class="col-6 col-md-4 col-lg-3">

        <div class="card h-100 border-0 shadow-sm overflow-hidden hover-lift">

            <div class="product-img-wrap position-relative">

                <?php if (!empty($product['ImageFile'])): ?>

                    <img
                        src="/upload/products/<?= htmlspecialchars($product['ImageFile']) ?>"
                        alt="<?= htmlspecialchars($product['ProductName']) ?>"
                    >

                <?php else: ?>

                    <div class="h-100 d-flex align-items-center justify-content-center">
                        <i class="bi bi-image fs-1 text-muted"></i>
                    </div>

                <?php endif; ?>

                <?php if ($outOfStock): ?>

                    <span class="badge bg-secondary position-absolute top-0 end-0 m-2">
                        Hết hàng
                    </span>

                <?php elseif ($badgeText !== ''): ?>

                    <span class="badge bg-<?= htmlspecialchars($badgeClass) ?> position-absolute top-0 start-0 m-2">
                        <?= htmlspecialchars($badgeText) ?>
                    </span>

                <?php endif; ?>

            </div>

            <div class="card-body d-flex flex-column">

                <h3 class="h6 card-title product-title">
                    <?= htmlspecialchars($product['ProductName']) ?>
                </h3>

                <div class="h5 text-danger fw-bold mb-3">
                    <?= number_format(
                        (float) $product['Price'],
                        0,
                        ',',
                        '.'
                    ) ?> ₫
                </div>

                <a
                    href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                    class="btn btn-primary btn-sm mt-auto rounded-pill"
                >
                    <i class="bi bi-eye me-1"></i>
                    Xem chi tiết
                </a>

            </div>

        </div>

    </div>

    <?php
}

/* Truy vấn con lấy ảnh chính của sản phẩm */
$imageSubquery = "
    (
        SELECT pi.ImageFile
        FROM product_images pi
        WHERE pi.ProductID = p.ProductID
        ORDER BY pi.IsPrimary DESC, pi.SortOrder ASC
        LIMIT 1
    ) AS ImageFile
";

/* ---------- Dữ liệu ---------- */


$row = $connection->query("
    SELECT
        (SELECT COUNT(*) FROM products WHERE IsActive = 1) AS products,
        (SELECT COUNT(*) FROM customers) AS customers,
        (SELECT COUNT(*) FROM orders) AS orders
")->fetch_assoc();

if ($row) {
    $stats = array_map('intval', $row);
}

/* Danh mục */
$categories = fetchAll($connection, "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
    LIMIT 6
");

/* Sản phẩm mới */
$newProducts = fetchAll($connection, "
    SELECT
        p.ProductID,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        $imageSubquery
    FROM products p
    WHERE p.IsActive = 1
    ORDER BY p.ProductID DESC
    LIMIT 8
");

/* Sản phẩm bán chạy */
$bestSellers = fetchAll($connection, "
    SELECT
        p.ProductID,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        SUM(od.Quantity) AS TotalSold,
        $imageSubquery
    FROM orderdetail od
    JOIN products p ON p.ProductID = od.ProductID
    WHERE p.IsActive = 1
    GROUP BY p.ProductID
    ORDER BY TotalSold DESC
    LIMIT 4
");

/* Sản phẩm theo từng danh mục (4 danh mục đầu, mỗi danh mục 4 sản phẩm) */
$productsByCategory = [];

foreach (array_slice($categories, 0, 4) as $category) {

    $categoryId = (int) $category['CategoryID'];

    $items = fetchAll($connection, "
        SELECT
            p.ProductID,
            p.ProductName,
            p.Price,
            p.StockQuantity,
            $imageSubquery
        FROM products p
        WHERE p.IsActive = 1
          AND p.CategoryID = $categoryId
        ORDER BY p.ProductID DESC
        LIMIT 4
    ");

    if ($items) {
        $productsByCategory[] = [
            'category' => $category,
            'products' => $items,
        ];
    }
}

/* Nhà cung cấp */
$suppliers = fetchAll($connection, "
    SELECT SupplierName
    FROM suppliers
    ORDER BY SupplierName
    LIMIT 8
");

/* Icon và màu cho danh mục */
$categoryStyles = [
    ['icon' => 'bi-laptop',     'color' => 'primary'],
    ['icon' => 'bi-phone',      'color' => 'success'],
    ['icon' => 'bi-headphones', 'color' => 'danger'],
    ['icon' => 'bi-smartwatch', 'color' => 'warning'],
    ['icon' => 'bi-keyboard',   'color' => 'info'],
    ['icon' => 'bi-cpu',        'color' => 'secondary'],
];

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<style>
    .hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #4f46e5 100%);
    }

    .hero-icon {
        font-size: 11rem;
        opacity: .9;
        filter: drop-shadow(0 10px 30px rgba(0, 0, 0, .35));
    }

    .section-title {
        position: relative;
        padding-bottom: .6rem;
        font-weight: 700;
    }

    .section-title::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: 0;
        width: 48px;
        height: 4px;
        border-radius: 2px;
        background: var(--bs-primary);
    }

    .hover-lift {
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .hover-lift:hover {
        transform: translateY(-6px);
        box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .12) !important;
    }

    .icon-circle {
        width: 64px;
        height: 64px;
    }

    .icon-circle-sm {
        width: 48px;
        height: 48px;
    }

    .product-img-wrap {
        height: 210px;
        background: #f8f9fa;
        padding: 1rem;
    }

    .product-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .product-title {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.8em;
    }

    .promo-1 {
        background: linear-gradient(135deg, #f59e0b, #ef4444);
    }

    .promo-2 {
        background: linear-gradient(135deg, #06b6d4, #3b82f6);
    }

    .step-number {
        width: 40px;
        height: 40px;
        line-height: 40px;
    }

    .cta {
        background: linear-gradient(135deg, #4f46e5, #0d6efd);
    }
</style>

<!-- Banner -->
<section class="hero text-white py-5">

    <div class="container py-lg-4">

        <div class="row align-items-center g-4">

            <div class="col-lg-7">

                <span class="badge rounded-pill text-bg-light text-primary mb-3 px-3 py-2">
                    <i class="bi bi-stars me-1"></i>
                    Hàng mới về mỗi tuần
                </span>

                <h1 class="display-4 fw-bold mb-3">
                    Thiết bị công nghệ
                    <span class="text-warning">chính hãng</span>,
                    giá tốt mỗi ngày
                </h1>

                <p class="lead text-white-50 mb-4">
                    Laptop, điện thoại, phụ kiện và nhiều hơn nữa.
                    Giao hàng nhanh, bảo hành rõ ràng.
                </p>

                <div class="d-flex flex-wrap gap-2 mb-5">

                    <a
                        href="/products.php"
                        class="btn btn-warning btn-lg fw-semibold px-4"
                    >
                        <i class="bi bi-bag me-1"></i>
                        Mua sắm ngay
                    </a>

                </div>
            </div>

            <div class="col-lg-5 d-none d-lg-block text-center">

                <i class="bi bi-laptop hero-icon"></i>

            </div>

        </div>

    </div>

</section>

<!-- Thanh chính sách -->
<section class="bg-white border-bottom">

    <div class="container py-4">

        <div class="row g-3">

            <div class="col-6 col-lg-3 d-flex align-items-center gap-3">
                <i class="bi bi-truck fs-2 text-primary"></i>
                <div>
                    <div class="fw-semibold">Giao hàng toàn quốc</div>
                    <small class="text-muted">Nhanh chóng, an toàn</small>
                </div>
            </div>

            <div class="col-6 col-lg-3 d-flex align-items-center gap-3">
                <i class="bi bi-arrow-repeat fs-2 text-success"></i>
                <div>
                    <div class="fw-semibold">Đổi trả dễ dàng</div>
                    <small class="text-muted">Trong 7 ngày</small>
                </div>
            </div>

            <div class="col-6 col-lg-3 d-flex align-items-center gap-3">
                <i class="bi bi-shield-check fs-2 text-danger"></i>
                <div>
                    <div class="fw-semibold">Bảo hành chính hãng</div>
                    <small class="text-muted">Theo nhà sản xuất</small>
                </div>
            </div>

            <div class="col-6 col-lg-3 d-flex align-items-center gap-3">
                <i class="bi bi-credit-card fs-2 text-warning"></i>
                <div>
                    <div class="fw-semibold">Thanh toán linh hoạt</div>
                    <small class="text-muted">Tiền mặt hoặc chuyển khoản</small>
                </div>
            </div>

        </div>

    </div>

</section>

<!-- Danh mục -->
<?php if ($categories): ?>

    <section class="container py-5">

        <h2 class="h3 section-title mb-4">
            Danh mục sản phẩm
        </h2>

        <div class="row g-3 g-lg-4">

            <?php foreach ($categories as $index => $category): ?>

                <?php $style = $categoryStyles[$index % count($categoryStyles)]; ?>

                <div class="col-6 col-md-4 col-lg-2">

                    <a
                        href="/products.php?category=<?= (int) $category['CategoryID'] ?>"
                        class="card h-100 border-0 shadow-sm text-center text-decoration-none hover-lift"
                    >

                        <div class="card-body py-4">

                            <div
                                class="icon-circle rounded-circle bg-<?= $style['color'] ?> bg-opacity-10 text-<?= $style['color'] ?> d-inline-flex align-items-center justify-content-center mb-3"
                            >
                                <i class="bi <?= $style['icon'] ?> fs-2"></i>
                            </div>

                            <div class="fw-semibold text-dark">
                                <?= htmlspecialchars($category['CategoryName']) ?>
                            </div>

                        </div>

                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

<?php endif; ?>

<!-- Sản phẩm mới -->
<section class="bg-light py-5">

    <div class="container">

        <div class="d-flex justify-content-between align-items-end mb-4">

            <h2 class="h3 section-title mb-0">
                Sản phẩm mới
            </h2>

            <a
                href="/products.php"
                class="btn btn-outline-primary btn-sm rounded-pill px-3"
            >
                Xem tất cả
                <i class="bi bi-arrow-right ms-1"></i>
            </a>

        </div>

        <?php if (!$newProducts): ?>

            <div class="text-center text-muted py-5">
                <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                Chưa có sản phẩm nào.
            </div>

        <?php else: ?>

            <div class="row g-3 g-lg-4">

                <?php foreach ($newProducts as $product): ?>

                    <?php renderProductCard($product, 'Mới', 'success'); ?>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>

<!-- Banner khuyến mãi -->
<section class="container py-5">

    <div class="row g-4">

        <div class="col-md-6">

            <div class="promo-1 text-white rounded-4 p-4 p-lg-5 h-100 hover-lift">

                <span class="badge text-bg-light text-danger mb-3">
                    Ưu đãi
                </span>

                <h3 class="fw-bold">Laptop học tập &amp; làm việc</h3>

                <p class="mb-4">
                    Cấu hình mạnh, pin bền, giá hợp lý cho sinh viên và dân văn phòng.
                </p>

                <a href="/products.php" class="btn btn-light rounded-pill px-4">
                    Khám phá ngay
                </a>

            </div>

        </div>

        <div class="col-md-6">

            <div class="promo-2 text-white rounded-4 p-4 p-lg-5 h-100 hover-lift">

                <span class="badge text-bg-light text-primary mb-3">
                    Phụ kiện
                </span>

                <h3 class="fw-bold">Phụ kiện công nghệ</h3>

                <p class="mb-4">
                    Tai nghe, chuột, bàn phím, sạc dự phòng và nhiều món khác.
                </p>

                <a href="/products.php" class="btn btn-light rounded-pill px-4">
                    Xem phụ kiện
                </a>

            </div>

        </div>

    </div>

</section>

<!-- Bán chạy -->
<?php if ($bestSellers): ?>

    <section class="bg-light py-5">

        <div class="container">

            <h2 class="h3 section-title mb-4">
                <i class="bi bi-fire text-danger me-1"></i>
                Bán chạy nhất
            </h2>

            <div class="row g-3 g-lg-4">

                <?php foreach ($bestSellers as $product): ?>

                    <?php renderProductCard($product, 'Bán chạy', 'danger'); ?>

                <?php endforeach; ?>

            </div>

        </div>

    </section>

<?php endif; ?>

<!-- Sản phẩm theo danh mục -->
<?php if ($productsByCategory): ?>

    <section class="container py-5">

        <h2 class="h3 section-title mb-4">
            Gợi ý theo danh mục
        </h2>

        <ul class="nav nav-pills gap-2 mb-4" role="tablist">

            <?php foreach ($productsByCategory as $index => $group): ?>

                <li class="nav-item" role="presentation">

                    <button
                        class="nav-link rounded-pill <?= $index === 0 ? 'active' : '' ?>"
                        data-bs-toggle="pill"
                        data-bs-target="#tab-category-<?= (int) $group['category']['CategoryID'] ?>"
                        type="button"
                        role="tab"
                    >
                        <?= htmlspecialchars($group['category']['CategoryName']) ?>
                    </button>

                </li>

            <?php endforeach; ?>

        </ul>

        <div class="tab-content">

            <?php foreach ($productsByCategory as $index => $group): ?>

                <div
                    class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>"
                    id="tab-category-<?= (int) $group['category']['CategoryID'] ?>"
                    role="tabpanel"
                >

                    <div class="row g-3 g-lg-4">

                        <?php foreach ($group['products'] as $product): ?>

                            <?php renderProductCard($product); ?>

                        <?php endforeach; ?>

                    </div>

                    <div class="text-center mt-4">

                        <a
                            href="/products.php?category=<?= (int) $group['category']['CategoryID'] ?>"
                            class="btn btn-outline-primary rounded-pill px-4"
                        >
                            Xem thêm
                            <?= htmlspecialchars($group['category']['CategoryName']) ?>
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

<?php endif; ?>

<!-- Nhà cung cấp -->
<?php if ($suppliers): ?>

    <section class="bg-light py-5">

        <div class="container">

            <h2 class="h3 section-title mb-4">
                Đối tác cung cấp
            </h2>

            <div class="row g-3">

                <?php foreach ($suppliers as $supplier): ?>

                    <div class="col-6 col-md-3">

                        <div class="bg-white rounded-3 shadow-sm text-center fw-semibold text-secondary py-3 px-2 hover-lift">
                            <i class="bi bi-building me-1"></i>
                            <?= htmlspecialchars($supplier['SupplierName']) ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </section>

<?php endif; ?>

<!-- Quy trình mua hàng -->
<section class="container py-5">

    <h2 class="h3 section-title mb-4">
        Mua hàng chỉ với 4 bước
    </h2>

    <div class="row g-4 text-center">

        <?php
        $steps = [
            ['icon' => 'bi-search',      'title' => 'Chọn sản phẩm',  'text' => 'Tìm kiếm và chọn thiết bị bạn cần.'],
            ['icon' => 'bi-cart-plus',   'title' => 'Thêm vào giỏ',   'text' => 'Kiểm tra số lượng và giá trong giỏ hàng.'],
            ['icon' => 'bi-pencil-square', 'title' => 'Đặt hàng',     'text' => 'Điền thông tin nhận hàng và xác nhận.'],
            ['icon' => 'bi-box2-heart',  'title' => 'Nhận hàng',      'text' => 'Nhận hàng tận nơi và kiểm tra sản phẩm.'],
        ];
        ?>

        <?php foreach ($steps as $index => $step): ?>

            <div class="col-6 col-lg-3">

                <div class="position-relative d-inline-block mb-3">

                    <div class="icon-circle rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center">
                        <i class="bi <?= $step['icon'] ?> fs-2"></i>
                    </div>

                    <span class="step-number position-absolute top-0 start-100 translate-middle badge rounded-circle text-bg-primary">
                        <?= $index + 1 ?>
                    </span>

                </div>

                <h3 class="h6 fw-bold">
                    <?= htmlspecialchars($step['title']) ?>
                </h3>

                <p class="text-muted mb-0">
                    <?= htmlspecialchars($step['text']) ?>
                </p>

            </div>

        <?php endforeach; ?>

    </div>

</section>

<!-- Đánh giá khách hàng -->
<section class="bg-light py-5">

    <div class="container">

        <h2 class="h3 section-title mb-4">
            Khách hàng nói gì về chúng tôi
        </h2>

        <div class="row g-4">

            <?php
            $reviews = [
                ['name' => 'Nguyễn Minh Anh', 'text' => 'Giao hàng nhanh, sản phẩm đúng mô tả. Mình rất hài lòng khi mua laptop ở đây.'],
                ['name' => 'Trần Quốc Huy',   'text' => 'Nhân viên tư vấn nhiệt tình, bảo hành rõ ràng. Sẽ tiếp tục ủng hộ cửa hàng.'],
                ['name' => 'Lê Thu Hà',       'text' => 'Giá tốt hơn nhiều nơi khác, đóng gói cẩn thận. Rất đáng tin cậy.'],
            ];
            ?>

            <?php foreach ($reviews as $review): ?>

                <div class="col-md-4">

                    <div class="card h-100 border-0 shadow-sm p-4">

                        <div class="text-warning mb-2">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>

                        <p class="text-muted fst-italic">
                            “<?= htmlspecialchars($review['text']) ?>”
                        </p>

                        <div class="d-flex align-items-center gap-3 mt-auto">

                            <div class="icon-circle-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold">
                                <?= htmlspecialchars(mb_substr($review['name'], 0, 1)) ?>
                            </div>

                            <div class="fw-semibold">
                                <?= htmlspecialchars($review['name']) ?>
                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<!-- Câu hỏi thường gặp -->
<section class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <h2 class="h3 section-title mb-4">
                Câu hỏi thường gặp
            </h2>

            <div class="accordion" id="faqAccordion">

                <?php
                $faqs = [
                    ['q' => 'Sản phẩm có chính hãng không?',      'a' => 'Tất cả sản phẩm đều có nguồn gốc rõ ràng từ nhà cung cấp uy tín và được bảo hành theo chính sách của nhà sản xuất.'],
                    ['q' => 'Thời gian giao hàng là bao lâu?',    'a' => 'Thông thường từ 1 đến 3 ngày làm việc tùy khu vực. Bạn sẽ được liên hệ trước khi giao hàng.'],
                    ['q' => 'Tôi có thể đổi trả sản phẩm không?', 'a' => 'Bạn có thể đổi trả trong vòng 7 ngày nếu sản phẩm lỗi do nhà sản xuất hoặc không đúng mô tả.'],
                    ['q' => 'Tôi có cần tài khoản để đặt hàng?',  'a' => 'Có. Bạn cần đăng ký tài khoản để thêm sản phẩm vào giỏ hàng và theo dõi đơn hàng của mình.'],
                ];
                ?>

                <?php foreach ($faqs as $index => $faq): ?>

                    <div class="accordion-item">

                        <h3 class="accordion-header">

                            <button
                                class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#faq-<?= $index ?>"
                            >
                                <?= htmlspecialchars($faq['q']) ?>
                            </button>

                        </h3>

                        <div
                            id="faq-<?= $index ?>"
                            class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                            data-bs-parent="#faqAccordion"
                        >

                            <div class="accordion-body text-muted">
                                <?= htmlspecialchars($faq['a']) ?>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

</section>

<!-- Kêu gọi hành động -->
<section class="container pb-5">

    <div class="cta text-white rounded-4 p-4 p-md-5 text-center">

        <h2 class="h3 fw-bold mb-2">
            Sẵn sàng nâng cấp thiết bị của bạn?
        </h2>

        <p class="mb-4 text-white-50">
            Khám phá sản phẩm công nghệ với mức giá hợp lý.
        </p>

        <a
            href="/products.php"
            class="btn btn-light btn-lg rounded-pill px-4 fw-semibold"
        >
            Xem tất cả sản phẩm
            <i class="bi bi-arrow-right ms-1"></i>
        </a>

    </div>

</section>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$connection->close();