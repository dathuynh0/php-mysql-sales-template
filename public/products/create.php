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

        if ($stmt->execute()) {
            header('Location: /products/');
            exit;
        }

        $error = 'Không thể thêm sản phẩm.';
        $stmt->close();
    }
}

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<form action="" class='container py-4'>
    <div class='d-flex justify-content-center align-item-center gap-2'>
        <select name="category_id" class="form-select" required>
            <option value="">
                -- Chọn danh mục --
            </option>

            <?php while ($category = $categories->fetch_assoc()): ?>

                <option
                    value="<?= $category['CategoryID'] ?>"
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
                <option value='<?= $supplier['SupplierID'] ?>'>
                    <?= htmlspecialchars($supplier['SupplierName']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
</form>