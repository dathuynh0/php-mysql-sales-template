<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $connection->query($sqlCategories);

$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $connection->query($sqlSuppliers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['product_image'] ?? null;
    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');

    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($productCode === '') {
        $error = 'Mã sản phẩm không được để trống.';

    } elseif ($productName === '') {
        $error = 'Tên sản phẩm không được để trống.';

    } elseif ($price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';

    } elseif ($stockQuantity < 0) {
        $error = 'Số lượng tồn kho không hợp lệ.';

    } elseif ($categoryID <= 0) {
        $error = 'Vui lòng chọn danh mục.';

    } elseif ($supplierID <= 0) {
        $error = 'Vui lòng chọn nhà cung cấp.';

    } else {

        $sql = "
            INSERT INTO products
            (
                ProductCode,
                ProductName,
                Description,
                Unit,
                Price,
                StockQuantity,
                IsActive,
                SupplierID,
                CategoryID
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $connection->prepare($sql);

        $stmt->bind_param(
            'ssssdiiii',
            $productCode,
            $productName,
            $description,
            $unit,
            $price,
            $stockQuantity,
            $isActive,
            $supplierID,
            $categoryID
        );

        $stmt->execute();
        $stmt->close();

        if (
            $_FILES['product_image']['error'] === UPLOAD_ERR_OK
        ) {
            $maxSize = 2 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                die('File ảnh không được vượt quá 2 MB.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            $allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!in_array($mimeType, $allowedTypes, true)) {
                die('Chỉ cho phép file JPG, JPEG, PNG hoặc WebP.');
            }

            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $extension = $extensionMap[$mimeType];

            $newFileName =
            'product-'
            . bin2hex(random_bytes(8))
            . '.'
            . $extension;

            $destination = '/var/www/html/upload/products/' . $newFileName;
            
            if (move_uploaded_file(
                $file['tmp_name'],
                $destination
            )) {
                $productID = $connection->insert_id;
                $altText = $productName;
                $sqlImage = "
                    INSERT INTO product_images
                    (
                        ProductID,
                        ImageFile,
                        AltText
                    )
                    VALUES
                    (?, ?, ?)
                ";

                $stmtImage = $connection->prepare($sqlImage);
                $stmtImage->bind_param(
                    'iss',
                    $productID,
                    $newFileName,
                    $altText
                );
                $stmtImage->execute();
                $stmtImage->close();
            } else {
                $error = 'Không thể thêm hình ảnh của sản phẩm.';
            }
        }
        header('Location: /products/');
        exit;
    }
}

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<h2 class="my-4 text-center">Thêm danh mục</h2>

<form method="post" enctype="multipart/form-data" class='container py-4'>
    <input
        type="file"
        class="form-control"
        id="productImage"
        name="product_image"
        accept="image/jpeg,image/png,image/webp"
        required
    >
    <div class='d-flex justify-content-center align-item-center gap-2 mb-3'>
        <select name="category_id" class="form-select" required>
            <option value="">
                -- Chọn danh mục --
            </option>

            <?php while ($category = $categories->fetch_assoc()): ?>

                <option
                    value="<?= htmlspecialchars($_POST['category_id'] ?? '') ?>"
                >
                    <?= htmlspecialchars($category['CategoryName']) ?>
                </option>

            <?php endwhile; ?>
        </select>
        <!-- supplier -->
        <select name='supplier_id' class='form-select' require>
            <option value="">
                -- Chọn nhà cung cấp --
            </option>
            
            <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($_POST['supplier_id'] ?? '') ?>">
                    <?= htmlspecialchars($supplier['SupplierName']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class='d-flex align-items-center justify-content-between gap-2 mb-3'>
        <div class="mb-3 w-100">
            <label for="product_code" class="form-label">
                Mã sản phẩm
            </label>

            <input
                type="text"
                class="form-control"
                id="product_code"
                name="product_code"
                required
                value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>"
            >
        </div>
        <div class="mb-3 w-100">
            <label for="product_name" class="form-label">
                Tên sản phẩm
            </label>

            <input
                type="text"
                class="form-control"
                id="product_name"
                name="product_name"
                required
                value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
            >
        </div>
    </div>
    <div class="mb-3 w-100">
        <label for="description" class="form-label">
            Mô tả sản phẩm
        </label>

        <textarea
            type="text"
            class="form-control"
            id="description"
            name="description"
            required
            value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
        ></textarea>
    </div>
    <div class="mb-3 w-100">
        <label for="unit" class="form-label">
            Đơn vị
        </label>

        <input
            type="text"
            class="form-control"
            id="unit"
            name="unit"
            required
            value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>"
        >
    </div>
    <div class="mb-3 w-100">
        <label for="price" class="form-label">
            Giá
        </label>

        <input
            type="number"
            class="form-control"
            id="price"
            name="price"
            required
            value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
        >
    </div>
    <div class="mb-3 w-100">
        <label for="stock_quantity" class="form-label">
            Tồn kho
        </label>

        <input
            type="number"
            class="form-control"
            id="stock_quantity"
            name="stock_quantity"
            required
            value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '') ?>"
        >
    </div>
    <div class="mb-3 w-100">
        <label for="is_active" class="form-label">
            Đang kinh doanh
        </label>

        <select
            name='is_active' class='form-select' require
            value="<?= htmlspecialchars($_POST['is_active'] ?? '') ?>"
        >
            <option selected value='1'>Đang kinh doanh</option>
            <option value='0'>Ngừng kinh doanh</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">
        Lưu
    </button>

    <a href="/products/" class="btn btn-secondary">
        Hủy
    </a>
</form>

<?php

require_once '/var/www/src/includes/footer.php';