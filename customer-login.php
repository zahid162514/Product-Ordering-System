<?php
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$flashMessages = pull_flash_messages('shop_flash');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $error = "Invalid email or password.";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $result = false;
    } else {
        $stmt = $conn->prepare("SELECT customer_id, customer_email, customer_name, company_name, customer_address, city, password FROM customer_registration WHERE customer_email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['customer_logged_in'] = true;
            $_SESSION['customer_id'] = $row['customer_id'];
            $_SESSION['customer_email'] = $row['customer_email'];
            $_SESSION['customer_name'] = $row['customer_name'];
            $_SESSION['customer_company_name'] = $row['company_name'] ?? '';
            $_SESSION['customer_address'] = $row['customer_address'];
            $_SESSION['customer_city'] = $row['city'] ?? '';

            $nextPage = safe_local_path($_SESSION['post_login_redirect'] ?? 'index.php', ['index.php', 'cart.php', 'order.php', 'menu.php', 'my-orders.php'], 'index.php');
            unset($_SESSION['post_login_redirect']);

            header("Location: " . $nextPage);
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
    <title>Customer Login | SmartStock</title>
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
                        <h1>Welcome back</h1>
                        <p>Sign in to review your cart, place orders, and track your latest purchases with Laobaan Bangladesh LTD.</p>
                        <div class="auth-points">
                            <span>Access your current cart from any device</span>
                            <span>Review grouped orders and status updates</span>
                            <span>Place stock-aware orders with confidence</span>
                        </div>
                    </section>
                </div>
                <div class="col-lg-7">
                    <section class="auth-content auth-panel">
                        <div class="auth-heading">
                            <h2>Customer Login</h2>
                            <p class="auth-subtitle">Use your registered email address to access your account.</p>
                        </div>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
                        <?php endif; ?>
                        <?php foreach ($flashMessages as $flash): ?>
                            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
                        <?php endforeach; ?>

                        <form method="POST" class="vstack gap-3">
                            <?php echo csrf_field(); ?>
                            <div>
                                <label class="form-label" for="email">Email address</label>
                                <input class="form-control" id="email" type="email" name="email" placeholder="you@company.com" required>
                            </div>
                            <div>
                                <label class="form-label" for="password">Password</label>
                                <input class="form-control" id="password" type="password" name="password" placeholder="Enter your password" required>
                            </div>
                            <button class="btn btn-smartstock w-100" type="submit">Login</button>
                        </form>

                        <div class="auth-links">
                            <a href="forgot_password.php">Forgot password?</a>
                            <a href="customer-register.php">Create account</a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
