<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['super_admin']);

$allowedRoles = ['super_admin', 'manager', 'inventory', 'support'];

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Admin not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'manager');

    if ($fullName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide valid admin details.";
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = "Please select a valid admin role.";
    } else {
        $stmt = $conn->prepare("UPDATE tbl_admin SET full_name = ?, username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $fullName, $username, $email, $role, $id);

        if ($stmt->execute()) {
            header("Location: manage-admin.php");
            exit;
        }

        error_log("Update admin failed: " . $stmt->error);
        $error = "Unable to update admin.";
    }
}

$stmt = $conn->prepare("SELECT id, full_name, username, email, role FROM tbl_admin WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    die("Admin not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Admin | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container admin-shell">
        <div class="admin-page-header mx-auto form-card">
            <div>
                <span class="admin-page-eyebrow">Access Control</span>
                <h1 class="admin-page-title h3 mb-1">Update Admin</h1>
                <p class="text-secondary mb-0">Edit this administrator account’s core contact and login details.</p>
            </div>
        </div>

        <div class="card form-card admin-surface-card mx-auto">
            <div class="card-header bg-white"><h2 class="h5 admin-page-title mb-0">Administrator Details</h2></div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <?php echo csrf_field(); ?>
                    <div class="col-md-3">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input class="form-control" id="full_name" type="text" name="full_name" value="<?php echo e($admin['full_name']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" type="text" name="username" value="<?php echo e($admin['username']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" type="email" name="email" value="<?php echo e($admin['email']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role">
                            <?php foreach ($allowedRoles as $roleOption): ?>
                                <option value="<?php echo e($roleOption); ?>" <?php if (($admin['role'] ?? '') === $roleOption) echo 'selected'; ?>>
                                    <?php echo e(ucwords(str_replace('_', ' ', $roleOption))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-smartstock" type="submit">Update Admin</button>
                        <a class="btn btn-outline-secondary" href="manage-admin.php">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
