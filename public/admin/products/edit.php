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

$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        AltText,
        IsPrimary,
        SortOrder
    FROM product_images
    WHERE ProductID = ?
    ORDER BY SortOrder, ProductImageID
";

$stmtImages = $connection->prepare($sqlImages);
$stmtImages->bind_param('i', $productID);
$stmtImages->execute();
$productImages = $stmtImages->get_result();

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

if (isset($_POST['set_primary_image'])) {

    $imageID = (int) $_POST['set_primary_image'];

    try {
        $connection->begin_transaction();

        $sqlResetPrimary = "
            UPDATE product_images
            SET IsPrimary = 0
            WHERE ProductID = ?
        ";

        $stmtResetPrimary = $connection->prepare($sqlResetPrimary);
        $stmtResetPrimary->bind_param('i', $productID);
        $stmtResetPrimary->execute();
        $stmtResetPrimary->close();

        $sqlSetPrimary = "
            UPDATE product_images
            SET IsPrimary = 1
            WHERE ProductImageID = ?
              AND ProductID = ?
        ";

        $stmtSetPrimary = $connection->prepare($sqlSetPrimary);
        $stmtSetPrimary->bind_param('ii', $imageID, $productID);
        $stmtSetPrimary->execute();

        if ($stmtSetPrimary->affected_rows !== 1) {
            throw new Exception('Không thể đặt ảnh chính.');
        }

        $stmtSetPrimary->close();
        $connection->commit();

        header(
            'Location: /products/edit.php?id='
            . $productID
            . '&primary_updated=1'
        );
        exit;

    } catch (Throwable $e) {
        $connection->rollback();
        $error = $e->getMessage();
    }
}

if (isset($_POST['add_images'])) {
    $files = $_FILES['product_images'] ?? null;

    if (
        !$files
        || !isset($files['name'])
        || !is_array($files['name'])
    ) {
        $error = 'Vui lòng chọn ít nhất một ảnh.';
    } else {
        $maxSize = 2 * 1024 * 1024;

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        $validImages = [];
        $fileCount = count($files['name']);

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $error = 'Có lỗi xảy ra khi upload ảnh.';
                break;
            }

            if ($files['size'][$i] > $maxSize) {
                $error = 'Mỗi ảnh chỉ được có kích thước tối đa 2 MB.';
                break;
            }

            $mimeType = $finfo->file($files['tmp_name'][$i]);

            if (!isset($extensionMap[$mimeType])) {
                $error = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.';
                break;
            }

            $extension = $extensionMap[$mimeType];

            $fileName =
                'product-'
                . bin2hex(random_bytes(8))
                . '.'
                . $extension;

            $validImages[] = [
                'tmp_name'  => $files['tmp_name'][$i],
                'file_name' => $fileName
            ];
        }

        if (!$error && count($validImages) === 0) {
            $error = 'Vui lòng chọn ít nhất một ảnh.';
        }

        if (!$error) {
            $sqlImageState = "
                SELECT
                    COUNT(*) AS ImageCount,
                    COALESCE(MAX(SortOrder), 0) AS MaxSortOrder
                FROM product_images
                WHERE ProductID = ?
            ";

            $stmtImageState = $connection->prepare($sqlImageState);
            $stmtImageState->bind_param('i', $productID);
            $stmtImageState->execute();

            $imageState =
                $stmtImageState->get_result()->fetch_assoc();

            $stmtImageState->close();

            $imageCount = (int) $imageState['ImageCount'];
            $nextSortOrder =
                (int) $imageState['MaxSortOrder'] + 1;
        }

        if (!$error) {
            $movedFiles = [];

            try {
                $connection->begin_transaction();

                $sqlInsertImage = "
                    INSERT INTO product_images
                    (
                        ProductID,
                        ImageFile,
                        AltText,
                        IsPrimary,
                        SortOrder
                    )
                    VALUES (?, ?, ?, ?, ?)
                ";

                $stmtInsertImage =
                    $connection->prepare($sqlInsertImage);

                foreach ($validImages as $index => $image) {
                    $destination =
                        '/var/www/html/upload/products/'
                        . $image['file_name'];

                    if (!move_uploaded_file(
                        $image['tmp_name'],
                        $destination
                    )) {
                        throw new Exception(
                            'Không thể lưu một trong các ảnh.'
                        );
                    }

                    $movedFiles[] = $destination;

                    $isPrimary =
                        ($imageCount === 0 && $index === 0)
                        ? 1
                        : 0;

                    $sortOrder = $nextSortOrder + $index;

                    $altText =
                        $product['ProductName']
                        . (
                            $isPrimary === 1
                            ? ' - ảnh chính'
                            : ' - ảnh ' . $sortOrder
                        );

                    $stmtInsertImage->bind_param(
                        'issii',
                        $productID,
                        $image['file_name'],
                        $altText,
                        $isPrimary,
                        $sortOrder
                    );

                    if (!$stmtInsertImage->execute()) {
                        throw new Exception(
                            'Không thể lưu thông tin ảnh.'
                        );
                    }
                }

                $stmtInsertImage->close();
                $connection->commit();

                header(
                    'Location: /products/edit.php?id='
                    . $productID
                    . '&images_added=1'
                );
                exit;

            } catch (Throwable $e) {
                $connection->rollback();

                foreach ($movedFiles as $movedFile) {
                    if (file_exists($movedFile)) {
                        unlink($movedFile);
                    }
                }

                $error = $e->getMessage();
            }
        }
    }
}

if (isset($_POST['delete_image'])) {
    $imageID = (int) $_POST['delete_image'];

    try {
        $connection->begin_transaction();

        $sqlImage = "
            SELECT
                ProductImageID,
                ImageFile,
                IsPrimary,
                SortOrder
            FROM product_images
            WHERE ProductImageID = ?
              AND ProductID = ?
        ";

        $stmtImage = $connection->prepare($sqlImage);
        $stmtImage->bind_param(
            'ii',
            $imageID,
            $productID
        );
        $stmtImage->execute();

        $imageToDelete =
            $stmtImage->get_result()->fetch_assoc();

        $stmtImage->close();

        if (!$imageToDelete) {
            throw new Exception(
                'Không tìm thấy ảnh cần xóa.'
            );
        }

        $sqlDelete = "
            DELETE FROM product_images
            WHERE ProductImageID = ?
              AND ProductID = ?
        ";

        $stmtDelete = $connection->prepare($sqlDelete);
        $stmtDelete->bind_param(
            'ii',
            $imageID,
            $productID
        );
        $stmtDelete->execute();

        if ($stmtDelete->affected_rows !== 1) {
            throw new Exception(
                'Không thể xóa ảnh.'
            );
        }

        $stmtDelete->close();

        if ((int) $imageToDelete['IsPrimary'] === 1) {
            $sqlNewPrimary = "
                UPDATE product_images
                SET IsPrimary = 1
                WHERE ProductImageID = (
                    SELECT ProductImageID
                    FROM (
                        SELECT ProductImageID
                        FROM product_images
                        WHERE ProductID = ?
                        ORDER BY
                            SortOrder,
                            ProductImageID
                        LIMIT 1
                    ) AS remaining_images
                )
            ";

            $stmtNewPrimary =
                $connection->prepare($sqlNewPrimary);

            $stmtNewPrimary->bind_param(
                'i',
                $productID
            );

            $stmtNewPrimary->execute();
            $stmtNewPrimary->close();
        }

        $deletedSortOrder =
            (int) $imageToDelete['SortOrder'];

        $sqlReorder = "
            UPDATE product_images
            SET SortOrder = SortOrder - 1
            WHERE ProductID = ?
              AND SortOrder > ?
        ";

        $stmtReorder = $connection->prepare($sqlReorder);
        $stmtReorder->bind_param(
            'ii',
            $productID,
            $deletedSortOrder
        );
        $stmtReorder->execute();
        $stmtReorder->close();

        $connection->commit();

        $filePath =
            '/var/www/html/uploads/products/'
            . $imageToDelete['ImageFile'];

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        header(
            'Location: /products/edit.php?id='
            . $productID
            . '&image_deleted=1'
        );
        exit;

    } catch (Throwable $e) {
        $connection->rollback();
        $error = $e->getMessage();
    }
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $productCode = trim($_POST['product_code'] ?? '');
//     $productName = trim($_POST['product_name'] ?? '');
//     $description = trim($_POST['description'] ?? '');
//     $unit = trim($_POST['unit'] ?? '');

//     $price = (float) ($_POST['price'] ?? 0);
//     $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

//     $categoryID = (int) ($_POST['category_id'] ?? 0);
//     $supplierID = (int) ($_POST['supplier_id'] ?? 0);

//     $isActive = isset($_POST['is_active']) ? 1 : 0;

//     $sql = "
//         UPDATE products
//         SET
//             ProductCode = ?,
//             ProductName = ?,
//             Description = ?,
//             Unit = ?,
//             Price = ?,
//             StockQuantity = ?,
//             IsActive = ?,
//             SupplierID = ?,
//             CategoryID = ?
//         WHERE ProductID = ?
//     ";

//     $stmt = $connection->prepare($sql);

//     $stmt->bind_param(
//         'ssssdiiiii',
//         $productCode,
//         $productName,
//         $description,
//         $unit,
//         $price,
//         $stockQuantity,
//         $isActive,
//         $supplierID,
//         $categoryID,
//         $productID
//     );

//     // if ($stmt->execute()) {
//     //     header('Location: /products/');
//     //     exit;
//     // }

//     // $error = 'Không thể sửa sản phẩm.';
//     $stmt->close();
// }

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<?php if ($error !== ''): ?>

    <div class="container">
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    </div>

<?php endif; ?>


<form method="post" enctype="multipart/form-data" class='container py-4'>
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

    <hr class="my-4">
    <h4 class="mb-3">Hình ảnh sản phẩm</h4>

    <div class="row">
        <?php while ($image = $productImages->fetch_assoc()): ?>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <img
                        src="/upload/products/<?= htmlspecialchars($image['ImageFile']) ?>"
                        class="card-img-top"
                        alt="<?= htmlspecialchars($image['AltText'] ?? '') ?>"
                    >

                    <div class="card-body">
                        <small class="text-muted">
                            Thứ tự: <?= $image['SortOrder'] ?>
                        </small>

                        <?php if ((int) $image['IsPrimary'] === 0): ?>
                            <div class='mt-2'>
                                <button
                                    type="submit"
                                    class="btn btn-outline-primary btn-sm"
                                    name="set_primary_image"
                                    value="<?= $image['ProductImageID'] ?>"
                                    formaction="/products/edit.php?id=<?= $productID ?>"
                                    formmethod="post"
                                >
                                    Đặt làm ảnh chính
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ((int) $image['IsPrimary'] === 1): ?>
                            <div class="mt-2">
                                <span class="badge bg-success">Ảnh chính</span>
                            </div>
                        <?php endif; ?>
                        <button
                            type="submit"
                            class="btn btn-outline-danger btn-sm ms-2"
                            name="delete_image"
                            value="<?= $image['ProductImageID'] ?>"
                            formaction="/products/edit.php?id=<?= $productID ?>"
                            formmethod="post"
                            onclick="return confirm('Bạn có chắc muốn xóa ảnh này?');"
                        >
                            Xóa ảnh
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="mb-3">
        <label for="productImages" class="form-label">
            Thêm hình ảnh
        </label>

        <input
            type="file"
            class="form-control"
            id="productImages"
            name="product_images[]"
            accept="image/jpeg,image/png,image/webp"
            multiple
        >

        <div class="form-text">
            Chấp nhận JPG, PNG hoặc WebP.
            Mỗi ảnh tối đa 2 MB.
        </div>
    </div>

    <button
        type="submit"
        class="btn btn-outline-success"
        name="add_images"
        formmethod="post"
    >
        Thêm ảnh
    </button>

    <!-- <button type="submit" class="btn btn-primary">
        Lưu
    </button>

    <a href="/products/" class="btn btn-secondary">
        Hủy
    </a> -->
</form>

<?php

require_once '/var/www/src/includes/admin/footer.php';
