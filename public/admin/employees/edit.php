<?php

$pageTitle = 'Sửa nhân viên';

require_once '/var/www/src/config/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = (int) ($_GET['id'] ?? 0);

/* ---------- Lấy nhân viên hiện tại ---------- */
$stmt = $connection->prepare(
    "SELECT EmployeeID, LastName, FirstName, BirthDate, Notes FROM employees WHERE EmployeeID = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

$errors = [];
$data   = ['LastName' => '', 'FirstName' => '', 'BirthDate' => '', 'Notes' => ''];

if (!$employee) {
    http_response_code(404);
} else {
    $data = [
        'LastName'  => $employee['LastName'],
        'FirstName' => $employee['FirstName'],
        'BirthDate' => $employee['BirthDate'] ?? '',
        'Notes'     => $employee['Notes'] ?? '',
    ];
}

/* =====================================================
 * CẬP NHẬT
 * ===================================================== */
if ($employee && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'LastName'  => trim($_POST['LastName'] ?? ''),
        'FirstName' => trim($_POST['FirstName'] ?? ''),
        'BirthDate' => trim($_POST['BirthDate'] ?? ''),
        'Notes'     => trim($_POST['Notes'] ?? ''),
    ];

    if ($data['LastName'] === '') {
        $errors[] = 'Vui lòng nhập họ.';
    } elseif (mb_strlen($data['LastName']) > 50) {
        $errors[] = 'Họ không được dài quá 50 ký tự.';
    }

    if ($data['FirstName'] === '') {
        $errors[] = 'Vui lòng nhập tên.';
    } elseif (mb_strlen($data['FirstName']) > 50) {
        $errors[] = 'Tên không được dài quá 50 ký tự.';
    }

    if ($data['BirthDate'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $data['BirthDate']);
        if (!$d || $d->format('Y-m-d') !== $data['BirthDate']) {
            $errors[] = 'Ngày sinh không hợp lệ.';
        } elseif ($d > new DateTime('today')) {
            $errors[] = 'Ngày sinh không được ở tương lai.';
        }
    }

    if (!$errors) {
        try {
            $birth = $data['BirthDate'] !== '' ? $data['BirthDate'] : null;
            $notes = $data['Notes'] !== '' ? $data['Notes'] : null;

            $stmt = $connection->prepare(
                "UPDATE employees
                 SET LastName = ?, FirstName = ?, BirthDate = ?, Notes = ?
                 WHERE EmployeeID = ?"
            );
            $stmt->bind_param('ssssi', $data['LastName'], $data['FirstName'], $birth, $notes, $id);
            $stmt->execute();
            $stmt->close();

            header('Location: /admin/employees/?msg=updated');
            exit;
        } catch (Throwable $e) {
            error_log('Employee update failed: ' . $e->getMessage());
            $errors[] = 'Có lỗi hệ thống khi lưu thay đổi. Vui lòng thử lại.';
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<?php if (!$employee): ?>

    <div class="alert alert-warning">Không tìm thấy nhân viên.</div>
    <a href="/admin/employees/" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
    </a>

<?php else: ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Sửa nhân viên NV<?= str_pad((string) $employee['EmployeeID'], 3, '0', STR_PAD_LEFT) ?></h2>
            <p class="text-muted mb-0">Cập nhật thông tin nhân viên</p>
        </div>
        <a href="/admin/employees/" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="LastName">Họ <span class="text-danger">*</span></label>
                    <input type="text" name="LastName" id="LastName" class="form-control" maxlength="50" required
                           value="<?= htmlspecialchars($data['LastName']) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="FirstName">Tên <span class="text-danger">*</span></label>
                    <input type="text" name="FirstName" id="FirstName" class="form-control" maxlength="50" required
                           value="<?= htmlspecialchars($data['FirstName']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="BirthDate">Ngày sinh</label>
                    <input type="date" name="BirthDate" id="BirthDate" class="form-control"
                           max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($data['BirthDate']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="Notes">Ghi chú / Vị trí công việc</label>
                    <textarea name="Notes" id="Notes" rows="4"
                              class="form-control"><?= htmlspecialchars($data['Notes']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="/admin/employees/" class="btn btn-outline-secondary">Hủy</a>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg me-1"></i>Lưu thay đổi
            </button>
        </div>
    </form>

<?php endif; ?>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$connection->close();