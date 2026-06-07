<?php
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    require_valid_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $error = "Invalid username or password.";

    $stmt = $conn->prepare("SELECT id, full_name, username, email, password, role FROM tbl_admin WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $storedPassword = (string) $row['password'];
        $passwordMatches = password_verify($password, $storedPassword);

        if (!$passwordMatches && hash_equals($storedPassword, $password)) {
            $passwordMatches = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE tbl_admin SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newHash, $row['id']);
            $updateStmt->execute();
        }

        if ($passwordMatches) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['admin_username'] = $row['username'];
            $_SESSION['admin_full_name'] = $row['full_name'] ?? '';
            $_SESSION['admin_role'] = $row['role'] ?: 'manager';
            header("Location: dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="admin-login-shell">
        <section class="admin-login-panel" aria-label="Admin login">
            <div class="admin-login-brand">
                <span class="admin-login-mark">S</span>
                <span>SmartStock Admin</span>
            </div>
            <div class="admin-login-copy">
                <p class="admin-login-kicker">SmartStock Admin</p>
                <h1>Manage products, inventory, orders, and customer activity.</h1>
                <p>Secure access for Laobaan Bangladesh LTD. operations teams.</p>
            </div>
            <div class="admin-login-meta">
                <div>
                    <strong>Catalog</strong>
                    <span>Products and categories</span>
                </div>
                <div>
                    <strong>Fulfillment</strong>
                    <span>Orders and stock flow</span>
                </div>
            </div>
        </section>

        <section class="admin-login-card" aria-label="Login form">
            <div class="admin-login-card-header">
                <p class="admin-login-kicker">Secure access</p>
                <h2>Admin Login</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="post" class="vstack gap-3" autocomplete="off">
                <?php echo csrf_field(); ?>
                <div>
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control form-control-lg" id="username" type="text" name="username" required autofocus>
                </div>
                <div>
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control form-control-lg" id="password" type="password" name="password" required>
                </div>
                <button class="btn btn-smartstock btn-lg w-100" type="submit" name="login">Login</button>
            </form>

            <div class="admin-login-support">
                <span>Need account help?</span>
                <a href="contact.php">Contact support</a>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
