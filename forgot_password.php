<?php
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf();

    $email = trim($_POST['email'] ?? '');
    $message = "If this email address exists in our system, we have sent a 6-digit password reset code.";

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT customer_id, customer_email, customer_name FROM customer_registration WHERE customer_email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($customer) {
            $resetCode = (string)random_int(100000, 999999);
            $tokenHash = hash('sha256', $resetCode);

            $deleteOld = $conn->prepare("DELETE FROM password_resets WHERE customer_id = ? AND used_at IS NULL");
            $deleteOld->bind_param("i", $customer['customer_id']);
            $deleteOld->execute();
            $deleteOld->close();

            $expiresAt = date('Y-m-d H:i:s', time() + 600);
            $insert = $conn->prepare("INSERT INTO password_resets (customer_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $customer['customer_id'], $tokenHash, $expiresAt);
            $insert->execute();
            $insert->close();

            $subject = "SmartStock password reset";
            $body = "Hello " . $customer['customer_name'] . ",\n\n"
                . "Use this 6-digit password reset code within 10 minutes:\n"
                . $resetCode . "\n\n"
                . "Open this page to enter the code:\n"
                . app_url('reset_password.php') . "\n\n"
                . "If you did not request this reset, you can ignore this email.";

            $htmlBody = smartstock_email_template(
                $subject,
                "Hello " . e($customer['customer_name']) . ",",
                [
                    'Use this 6-digit password reset code within 10 minutes:',
                    '<span style="display:inline-block;padding:10px 14px;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-size:24px;font-weight:700;letter-spacing:4px;">' . e($resetCode) . '</span>',
                    'Open this page to enter the code: <a href="' . e(app_url('reset_password.php')) . '" style="color:#2563eb;">Reset password</a>',
                    'If you did not request this reset, you can ignore this email.',
                ]
            );

            if (!send_smartstock_mail($customer['customer_email'], $subject, $body, $htmlBody)) {
                error_log("Password reset mail could not be sent for customer ID " . (int)$customer['customer_id']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | SmartStock</title>
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
                        <h1>Reset access</h1>
                        <p>Request a secure 6-digit reset code to regain access to your customer portal.</p>
                    </section>
                </div>
                <div class="col-lg-7">
                    <section class="auth-content auth-panel">
                        <div class="auth-heading">
                            <h2>Forgot password</h2>
                            <p class="auth-subtitle">Enter the email address on your account and we'll send a 6-digit reset code if it matches our records.</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo e($message); ?></div>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="vstack gap-3">
                            <?php echo csrf_field(); ?>
                            <div>
                                <label class="form-label" for="email">Email address</label>
                                <input class="form-control" id="email" type="email" name="email" placeholder="you@example.com" required>
                            </div>
                            <button class="btn btn-smartstock" type="submit">Send Reset Instructions</button>
                        </form>

                        <div class="auth-links">
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
