<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

require_customer();

$customerId = (int)$_SESSION['customer_id'];
$successMessage = "";
$errorMessage = "";

$stmt = $conn->prepare(
    "SELECT customer_name, password
     FROM customer_registration
     WHERE customer_id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    app_destroy_session();
    header('Location: customer-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errorMessage = "All password fields are required.";
    } elseif (!password_verify($currentPassword, $customer['password'])) {
        $errorMessage = "Current password is incorrect.";
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = "New password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = "New password confirmation does not match.";
    } elseif (hash_equals($currentPassword, $newPassword)) {
        $errorMessage = "New password must be different from your current password.";
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE customer_registration SET password = ? WHERE customer_id = ?");
        $update->bind_param("si", $hash, $customerId);

        if ($update->execute()) {
            session_regenerate_id(true);
            $customer['password'] = $hash;
            $successMessage = "Password updated successfully.";
        } else {
            $errorMessage = "Unable to update your password right now.";
        }

        $update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/public/navbar.php'; ?>

    <main class="container page-shell">
        <div class="section-header">
            <div>
                <span class="section-eyebrow text-bg-primary">Account Security</span>
                <h1 class="section-title">Change Password</h1>
                <p class="section-copy">Update your password from a dedicated security page without mixing it into your profile details.</p>
            </div>

            <div class="page-action-group">
                <a class="btn btn-page-action btn-page-action-light" href="profile.php">Back to Profile</a>
                <a class="btn btn-page-action btn-page-action-secondary" href="my-orders.php">Order History</a>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>

        <div class="password-page-layout">
            <section class="surface-card password-form-card">
                <div class="profile-form-intro">
                    <h2>Security Update</h2>
                    <p>Choose a strong password with at least 8 characters and keep it different from the current one.</p>
                </div>

                <form method="post" class="row g-3">
                    <?php echo csrf_field(); ?>

                    <div class="col-12">
                        <label class="form-label" for="current_password">Current password</label>
                        <input class="form-control" id="current_password" type="password" name="current_password" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="new_password">New password</label>
                        <input class="form-control" id="new_password" type="password" name="new_password" minlength="8" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Confirm new password</label>
                        <input class="form-control" id="confirm_password" type="password" name="confirm_password" minlength="8" required>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                        <a class="btn btn-outline-secondary" href="profile.php">Cancel</a>
                        <button class="btn btn-smartstock" type="submit">Update Password</button>
                    </div>
                </form>
            </section>

            <aside class="account-panel password-side-panel">
                <span class="profile-side-kicker">Signed in as</span>
                <h2><?php echo e($customer['customer_name'] ?? 'Customer'); ?></h2>
                <p>Password changes apply immediately after save. Your other profile fields remain available on the main profile page.</p>
            </aside>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/public/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
