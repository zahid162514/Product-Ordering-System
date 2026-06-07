<?php
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $recipient = trim($_POST['recipient'] ?? '');
    $userMessage = trim($_POST['message'] ?? '');
    $allowedRecipients = ['Admin', 'HR'];

    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && in_array($recipient, $allowedRecipients, true) && $userMessage) {
        $message = "[To: $recipient] " . $userMessage;
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message, submitted_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $name, $email, $message);
        $stmt->execute();
        $successMessage = "Message sent successfully.";
    } else {
        $errorMessage = "Please fill in all fields with valid details.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="container contact-shell">
        <div class="admin-page-header text-center text-lg-start">
            <div>
                <span class="admin-page-eyebrow">Support</span>
                <h1 class="admin-page-title h3 mb-1">Contact Support</h1>
                <p class="text-secondary mb-0">Reach the SmartStock support team for admin or HR requests.</p>
            </div>
        </div>

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5">
                <div class="card admin-surface-card contact-info-card h-100">
                    <div class="card-body">
                        <h2 class="h5 admin-page-title mb-3">Laobaan Bangladesh LTD.</h2>
                        <div class="contact-meta">
                            <div>Email: <a href="mailto:info@smartstock.com.bd">info@smartstock.com.bd</a></div>
                            <div>Phone: <a href="tel:+8801736403736">+8801736403736</a></div>
                            <div>Location: Dhaka, Bangladesh</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card admin-surface-card">
                    <div class="card-body">
                        <?php if ($successMessage): ?>
                            <div class="alert alert-success"><?php echo e($successMessage); ?></div>
                        <?php elseif ($errorMessage): ?>
                            <div class="alert alert-danger"><?php echo e($errorMessage); ?></div>
                        <?php endif; ?>

                        <form method="post" class="row g-3">
                            <?php echo csrf_field(); ?>
                            <div class="col-md-6">
                                <label class="form-label" for="name">Name</label>
                                <input class="form-control" id="name" type="text" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control" id="email" type="email" name="email" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="recipient">Send To</label>
                                <select class="form-select" id="recipient" name="recipient" required>
                                    <option value="">Select recipient</option>
                                    <option value="Admin">Admin</option>
                                    <option value="HR">HR</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="message">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button class="btn btn-smartstock" type="submit">Send Message</button>
                                <a class="btn btn-outline-secondary" href="admin-login.php">Back to Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
