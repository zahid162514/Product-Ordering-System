<?php
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$message = "";
$error = "";
$formData = [
    'email' => trim($_POST['email'] ?? ''),
    'code' => trim($_POST['code'] ?? ''),
];

function find_reset_request_by_code(mysqli $conn, string $email, string $code): ?array
{
    $email = trim($email);
    $code = trim($code);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) {
        return null;
    }

    $tokenHash = hash('sha256', $code);
    $stmt = $conn->prepare(
        "SELECT pr.id, pr.customer_id, c.customer_email
         FROM password_resets pr
         JOIN customer_registration c ON c.customer_id = pr.customer_id
         WHERE c.customer_email = ?
           AND pr.token_hash = ?
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("ss", $email, $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $reset ?: null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf();

    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $reset = find_reset_request_by_code($conn, $email, $code);

    if (!$reset) {
        $error = "That reset code is invalid or has expired.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $conn->begin_transaction();

        try {
            $updatePassword = $conn->prepare("UPDATE customer_registration SET password = ? WHERE customer_id = ?");
            $updatePassword->bind_param("si", $hashedPassword, $reset['customer_id']);
            $updatePassword->execute();
            $updatePassword->close();

            $markUsed = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
            $markUsed->bind_param("i", $reset['id']);
            $markUsed->execute();

            if ($markUsed->affected_rows !== 1) {
                throw new RuntimeException('Password reset code was already used.');
            }

            $markUsed->close();
            $conn->commit();
            $message = "Password has been reset successfully. You can now log in.";
            $formData = ['email' => '', 'code' => ''];
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("Password reset failed: " . $e->getMessage());
            $error = "Unable to reset password. Please request a new code and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-wrapper">
    <main class="container auth-shell">
        <div class="auth-card mx-auto">
            <div class="row g-0">
                <div class="col-lg-5">
                    <section class="auth-side auth-panel h-100">
                        <div class="auth-brand">
                            <span class="auth-brand-mark">S</span>
                            <span>SmartStock</span>
                        </div>
                        <h1>Reset your password</h1>
                        <p>Enter the 6-digit code from your email, then choose a new password.</p>
                    </section>
                </div>
                <div class="col-lg-7">
                    <section class="auth-content auth-panel">
                        <div class="auth-heading">
                            <h2>Enter reset code</h2>
                            <p class="auth-subtitle">The code expires after 10 minutes.</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo e($message); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="vstack gap-3">
                            <?php echo csrf_field(); ?>
                            <div>
                                <label class="form-label" for="email">Email address</label>
                                <input class="form-control" id="email" type="email" name="email" value="<?php echo e($formData['email']); ?>" placeholder="you@example.com" required>
                            </div>
                            <div>
                                <label class="form-label" for="code">6-digit code</label>
                                <input class="form-control" id="code" type="text" name="code" value="<?php echo e($formData['code']); ?>" placeholder="123456" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
                            </div>
                            <div>
                                <label class="form-label" for="new_password">New password</label>
                                <input class="form-control" id="new_password" type="password" name="new_password" placeholder="At least 8 characters" required minlength="8">
                            </div>
                            <div>
                                <label class="form-label" for="confirm_password">Confirm password</label>
                                <input class="form-control" id="confirm_password" type="password" name="confirm_password" placeholder="Re-enter your new password" required minlength="8">
                            </div>
                            <button class="btn btn-smartstock" type="submit">Reset Password</button>
                        </form>

                        <div class="auth-links">
                            <a href="forgot_password.php">Request a new code</a>
                            <a href="customer-login.php">Back to login</a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
