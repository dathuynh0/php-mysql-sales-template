<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$productID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productID <= 0) {
    header('Location: /products.php');
    exit;
}

$sql = "
    SELECT
        p.ProductID,
        p.CategoryID,
        p.ProductCode,
        p.ProductName,
        p.Description,
        p.Unit,
        p.Price,
        p.StockQuantity,
        c.CategoryName,
        s.SupplierName

    FROM
        products p,
        categories c,
        suppliers s

    WHERE
        p.CategoryID = c.CategoryID
        AND p.SupplierID = s.SupplierID
        AND p.ProductID = ?
        AND p.IsActive = 1
";

$stmt = $connection->prepare($sql);

$stmt->bind_param(
    'i',
    $productID
);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$result->free();
$stmt->close();

if (!$product) {
    header('Location: /products.php');
    exit;
}

/* ------------------------------------------------------------
 * Thêm vào giỏ hàng
 * ---------------------------------------------------------- */
$cartMessage = '';
$cartSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_to_cart'])) {

    $quantity = isset($_POST['quantity'])
        ? (int) $_POST['quantity']
        : 1;

    if ($quantity < 1) {
        $quantity = 1;
    }

    $stockQuantity = (int) $product['StockQuantity'];

    if ($stockQuantity <= 0) {
        $cartMessage = 'Sản phẩm hiện đã hết hàng.';
    } else {

        $currentQuantity =
            $_SESSION['cart'][$productID] ?? 0;

        $newQuantity =
            $currentQuantity + $quantity;

        if ($newQuantity > $stockQuantity) {
            $newQuantity = $stockQuantity;
        }

        $_SESSION['cart'][$productID] =
            $newQuantity;

        $cartMessage =
            'Đã thêm sản phẩm vào giỏ hàng.';
        $cartSuccess = true;
    }
}

/* ------------------------------------------------------------
 * Hình ảnh sản phẩm
 * ---------------------------------------------------------- */
$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        IsPrimary

    FROM
        product_images

    WHERE
        ProductID = ?

    ORDER BY
        IsPrimary DESC,
        ProductImageID ASC
";

$stmtImages = $connection->prepare($sqlImages);

$stmtImages->bind_param(
    'i',
    $productID
);

$stmtImages->execute();

$imageResult = $stmtImages->get_result();

$images = [];

while ($image = $imageResult->fetch_assoc()) {
    $images[] = $image;
}

$imageResult->free();
$stmtImages->close();

/* ------------------------------------------------------------
 * Dữ liệu hiển thị
 * ---------------------------------------------------------- */
$stock = (int) $product['StockQuantity'];

if ($stock <= 0) {
    $stockClass = 'text-bg-danger';
    $stockLabel = 'Hết hàng';
} elseif ($stock <= 5) {
    $stockClass = 'text-bg-warning';
    $stockLabel = 'Chỉ còn ' . $stock . ' sản phẩm';
} else {
    $stockClass = 'text-bg-success';
    $stockLabel = 'Còn hàng';
}

$pageTitle = $product['ProductName'];

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<style>
    .gallery-main {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        height: 340px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .gallery-main img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .gallery-thumb {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: .5rem;
        padding: .25rem;
        width: 72px;
        height: 72px;
        cursor: pointer;
        transition: border-color .15s ease;
    }

    .gallery-thumb:hover {
        border-color: #94a3b8;
    }

    .gallery-thumb.active {
        border-color: #0f172a;
    }

    .gallery-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .detail-price {
        color: #be123c;
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: -.02em;
    }

    .buy-panel {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
    }

    .qty-group {
        width: 9.5rem;
    }

    .qty-group .form-control {
        text-align: center;
        font-weight: 600;
    }

    /* Ẩn mũi tên mặc định của input number vì đã có nút +/- */
    .qty-group input[type=number]::-webkit-outer-spin-button,
    .qty-group input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .qty-group input[type=number] {
        -moz-appearance: textfield;
    }

    .spec-list > div {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .75rem 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .spec-list > div:last-child {
        border-bottom: 0;
    }

    .spec-list dt {
        font-weight: 400;
        color: #64748b;
    }

    .spec-list dd {
        margin: 0;
        font-weight: 600;
        text-align: right;
    }

    .description-body {
        max-width: 75ch;
        line-height: 1.75;
    }

    @media (max-width: 575.98px) {
        .gallery-main {
            height: 280px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .gallery-thumb {
            transition: none;
        }
    }
</style>

<main class="container py-4 py-lg-5">

    <!-- Đường dẫn -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">

            <li class="breadcrumb-item">
                <a href="/products.php" class="text-decoration-none">
                    Sản phẩm
                </a>
            </li>

            <li class="breadcrumb-item">
                <a
                    href="/products.php?category=<?= (int) $product['CategoryID'] ?>"
                    class="text-decoration-none"
                >
                    <?= htmlspecialchars($product['CategoryName']) ?>
                </a>
            </li>

            <li
                class="breadcrumb-item active text-truncate"
                aria-current="page"
                style="max-width: 16rem;"
            >
                <?= htmlspecialchars($product['ProductName']) ?>
            </li>

        </ol>
    </nav>

    <?php if ($cartMessage !== ''): ?>

        <div
            class="alert <?= $cartSuccess ? 'alert-success' : 'alert-warning' ?>
                   d-flex flex-wrap align-items-center
                   justify-content-between gap-2"
            role="alert"
        >
            <span><?= htmlspecialchars($cartMessage) ?></span>

            <?php if ($cartSuccess): ?>
                <a href="/cart.php" class="btn btn-sm btn-dark">
                    Xem giỏ hàng
                </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <div class="row g-4 g-lg-5">

        <!-- Hình ảnh -->
        <div class="col-lg-6">

            <?php if (!empty($images)): ?>

                <div class="gallery-main mb-3">
                    <img
                        id="mainImage"
                        src="/upload/products/<?= htmlspecialchars($images[0]['ImageFile']) ?>"
                        alt="<?= htmlspecialchars($product['ProductName']) ?>"
                    >
                </div>

                <?php if (count($images) > 1): ?>

                    <div class="d-flex flex-wrap gap-2">

                        <?php foreach ($images as $index => $image): ?>

                            <button
                                    type="button"
                                    class="gallery-thumb
                                           <?= $index === 0 ? 'active' : '' ?>"
                                    data-src="/upload/products/<?= htmlspecialchars($image['ImageFile']) ?>"
                                    aria-label="Xem ảnh <?= $index + 1 ?>"
                                    aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                                >
                                    <img
                                        src="/upload/products/<?= htmlspecialchars($image['ImageFile']) ?>"
                                        alt=""
                                        loading="lazy"
                                    >
                                </button>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div
                    class="gallery-main text-secondary"
                >
                    <span class="d-flex align-items-center justify-content-center">
                        Chưa có hình ảnh
                    </span>
                </div>

            <?php endif; ?>

        </div>

        <!-- Thông tin & mua hàng -->
        <div class="col-lg-6">

            <p class="text-secondary mb-2">
                <?= htmlspecialchars($product['CategoryName']) ?>
            </p>

            <h1 class="h2 fw-bold mb-3">
                <?= htmlspecialchars($product['ProductName']) ?>
            </h1>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">

                <span class="badge <?= $stockClass ?> fw-medium">
                    <?= htmlspecialchars($stockLabel) ?>
                </span>

                <span class="text-secondary small">
                    Mã: <?= htmlspecialchars($product['ProductCode']) ?>
                </span>

            </div>

            <p class="detail-price mb-4">
                <?= number_format(
                    (float) $product['Price'],
                    0,
                    ',',
                    '.'
                ) ?> đ
                <?php if (!empty($product['Unit'])): ?>
                    <span class="fs-6 fw-normal text-secondary">
                        / <?= htmlspecialchars($product['Unit']) ?>
                    </span>
                <?php endif; ?>
            </p>

            <!-- Khu vực mua hàng -->
            <?php if ($stock > 0): ?>

                <form method="post" class="buy-panel p-3 p-lg-4 mb-4">

                    <label for="quantity" class="form-label fw-semibold">
                        Số lượng
                    </label>

                    <div class="d-flex flex-wrap align-items-center gap-3">

                        <div class="input-group qty-group">

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="qtyMinus"
                                aria-label="Giảm số lượng"
                            >&minus;</button>

                            <input
                                type="number"
                                name="quantity"
                                id="quantity"
                                class="form-control"
                                value="1"
                                min="1"
                                max="<?= $stock ?>"
                            >

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="qtyPlus"
                                aria-label="Tăng số lượng"
                            >+</button>

                        </div>

                        <button
                            type="submit"
                            name="add_to_cart"
                            class="btn btn-dark btn-lg flex-grow-1"
                        >
                            Thêm vào giỏ hàng
                        </button>

                    </div>

                    <p class="text-secondary small mt-3 mb-0">
                        Còn <?= $stock ?> sản phẩm trong kho.
                    </p>

                </form>

            <?php else: ?>

                <div class="alert alert-warning mb-4" role="alert">
                    Sản phẩm hiện đã hết hàng.
                </div>

            <?php endif; ?>

            <!-- Thông tin chi tiết -->
            <h2 class="h6 fw-bold mb-1">Thông tin sản phẩm</h2>

            <dl class="spec-list mb-0">

                <div>
                    <dt>Danh mục</dt>
                    <dd><?= htmlspecialchars($product['CategoryName']) ?></dd>
                </div>

                <div>
                    <dt>Nhà cung cấp</dt>
                    <dd><?= htmlspecialchars($product['SupplierName']) ?></dd>
                </div>

                <?php if (!empty($product['Unit'])): ?>
                    <div>
                        <dt>Đơn vị tính</dt>
                        <dd><?= htmlspecialchars($product['Unit']) ?></dd>
                    </div>
                <?php endif; ?>

                <div>
                    <dt>Tồn kho</dt>
                    <dd><?= $stock ?></dd>
                </div>

            </dl>

        </div>

    </div>

    <!-- Mô tả -->
    <?php if (!empty($product['Description'])): ?>

        <section class="mt-5 pt-4 border-top">

            <h2 class="h4 fw-bold mb-3">Mô tả sản phẩm</h2>

            <div class="description-body">
                <?= nl2br(htmlspecialchars($product['Description'])) ?>
            </div>

        </section>

    <?php endif; ?>

    <div class="mt-5">
        <a href="/products.php" class="btn btn-outline-secondary">
            &larr; Quay lại danh sách sản phẩm
        </a>
    </div>

</main>

<script>
    (function () {
        // Đổi ảnh chính khi bấm ảnh nhỏ
        var mainImage = document.getElementById('mainImage');
        var thumbs = document.querySelectorAll('.gallery-thumb');

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                mainImage.src = thumb.getAttribute('data-src');

                thumbs.forEach(function (item) {
                    item.classList.remove('active');
                    item.setAttribute('aria-pressed', 'false');
                });

                thumb.classList.add('active');
                thumb.setAttribute('aria-pressed', 'true');
            });
        });

        // Nút tăng / giảm số lượng
        var input = document.getElementById('quantity');
        var minus = document.getElementById('qtyMinus');
        var plus = document.getElementById('qtyPlus');

        if (!input) {
            return;
        }

        var min = parseInt(input.min, 10) || 1;
        var max = parseInt(input.max, 10) || min;

        function clamp(value) {
            if (isNaN(value) || value < min) {
                return min;
            }

            return value > max ? max : value;
        }

        minus.addEventListener('click', function () {
            input.value = clamp(parseInt(input.value, 10) - 1);
        });

        plus.addEventListener('click', function () {
            input.value = clamp(parseInt(input.value, 10) + 1);
        });

        input.addEventListener('change', function () {
            input.value = clamp(parseInt(input.value, 10));
        });
    })();
</script>

<?php
require_once '/var/www/src/includes/frontend/footer.php';