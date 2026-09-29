<?php

$pageTitle = 'Sửa sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$productID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productID <= 0) {
    die('Mã sản phẩm không hợp lệ.');
}

/*
 * Đọc dữ liệu hiện tại của sản phẩm
 */
$sql = "
    SELECT
        ProductCode,
        ProductName,
        Description,
        Unit,
        Price,
        StockQuantity,
        IsActive,
        SupplierID,
        CategoryID
    FROM products
    WHERE ProductID = ?
";

$stmt = $connection->prepare($sql);
$stmt->bind_param('i', $productID);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    die('Không tìm thấy sản phẩm.');
}

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

    $sql = "
        UPDATE products
        SET
            ProductCode = ?,
            ProductName = ?,
            Description = ?,
            Unit = ?,
            Price = ?,
            StockQuantity = ?,
            IsActive = ?,
            SupplierID = ?,
            CategoryID = ?
        WHERE ProductID = ?
    ";

    $stmt = $connection->prepare($sql);

    $stmt->bind_param(
        'ssssdiiiii',
        $productCode,
        $productName,
        $description,
        $unit,
        $price,
        $stockQuantity,
        $isActive,
        $supplierID,
        $categoryID,
        $productID
    );

    if ($stmt->execute()) {
            header('Location: /products/');
            exit;
    }

    $error = 'Không thể sửa sản phẩm.';
    $stmt->close();
}

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<form method="post" class='container py-4'>
    <div class='d-flex justify-content-center align-item-center gap-2 mb-3'>
        <select name="category_id" class='w-100' class='form-select'>
    
            <?php while ($categorie = $categories->fetch_assoc()): ?>
                <?php 
                // Kiểm tra điều kiện selected
                    $current_category_id = $_POST['category_id'] ?? $product['CategoryID'] ?? '';
                    $selected = ($categorie['CategoryID'] == $current_category_id) ? 'selected' : '';
                ?>
                
                
                <option value="<?= htmlspecialchars($categorie['CategoryID']) ?>" <?= $selected ?>>
                    <?= htmlspecialchars($categorie['CategoryName']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <!-- supplier -->
        <select name="supplier_id" class='w-100' class='form-select'>
    
            <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                <?php 
                // Kiểm tra điều kiện selected
                    $current_supplier_id = $_POST['supplier_id'] ?? $product['SupplierID'] ?? '';
                    $selected = ($supplier['SupplierID'] == $current_supplier_id) ? 'selected' : '';
                ?>
                
                <option value="<?= htmlspecialchars($supplier['SupplierID']) ?>" <?= $selected ?>>
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
                value="<?= htmlspecialchars($_POST['product_code'] ?? $product['ProductCode']) ?>"
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
                value="<?= htmlspecialchars($_POST['product_name'] ?? $product['ProductName']) ?>"
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
            value="<?= htmlspecialchars($_POST['description'] ?? $product['Description']) ?>"
        ><?= htmlspecialchars($_POST['description'] ?? $product['Description'])?></textarea>
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
            value="<?= htmlspecialchars($_POST['unit'] ?? $product['Unit']) ?>"
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
            value="<?= htmlspecialchars($_POST['price'] ?? $product['Price']) ?>"
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
            value="<?= htmlspecialchars($_POST['stock_quantity'] ?? $product['StockQuantity']) ?>"
        >
    </div>
    <div class="mb-3 w-100">
        <label for="is_active" class="form-label">
            Đang kinh doanh
        </label>
        <select name='is_active' class='form-select'
        >
            <option value="<?= htmlspecialchars($_POST['is_active'] ?? '1') ?>" <?= ($product['IsActive'] == 1) ? 'selected' : '' ?>>
                Đang kinh doanh
            </option>
            
            <!-- Kiểm tra nếu IsActive bằng 0 thì selected -->
            <option value="<?= htmlspecialchars($_POST['is_active'] ?? '0') ?>" <?= ($product['IsActive'] == 0) ? 'selected' : '' ?>>
                Ngừng kinh doanh
            </option>
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
