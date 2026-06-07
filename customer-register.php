<?php
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$showLogin = false;
$old = [
    'name' => '',
    'company_name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'city' => '',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf();

    $old = array_merge($old, $_POST);
    $name = trim($_POST['name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $plainPassword = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($name === '' || $phone === '' || $address === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide valid registration details.";
    } elseif (strlen($plainPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($confirmPassword === '' || $plainPassword !== $confirmPassword) {
        $error = "Password confirmation does not match.";
    } else {
        $password = password_hash($plainPassword, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("SELECT customer_id FROM customer_registration WHERE customer_email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $check = $stmt->get_result();

        if ($check && $check->num_rows > 0) {
            $error = "Email already registered.";
            $showLogin = true;
        } else {
            $sql = "INSERT INTO customer_registration (customer_name, company_name, phone, customer_email, customer_address, city, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $name, $companyName, $phone, $email, $address, $city, $password);

            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                session_regenerate_id(true);
                $_SESSION['customer_logged_in'] = true;
                $_SESSION['customer_id'] = $newId;
                $_SESSION['customer_email'] = $email;
                $_SESSION['customer_name'] = $name;
                $_SESSION['customer_company_name'] = $companyName;
                $_SESSION['customer_address'] = $address;
                $_SESSION['customer_city'] = $city;

                header("Location: index.php");
                exit;
            }

            $error = "Registration failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Register | SmartStock</title>
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
                        <h1>Create Customer Account</h1>
                        <p>Set up your customer portal once, then place orders faster and track everything in one clean space.</p>
                        <div class="auth-points">
                            <span>Store your contact and delivery details</span>
                            <span>Keep repeat ordering simple</span>
                            <span>Track grouped orders from your dashboard</span>
                        </div>
                    </section>
                </div>
                <div class="col-lg-7">
                    <section class="auth-content auth-panel">
                        <div class="auth-heading">
                            <h2>Create Customer Account</h2>
                            <p class="auth-subtitle">Enter your business and contact details to open a customer account.</p>
                        </div>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="row g-3">
                            <?php echo csrf_field(); ?>
                            <div class="col-12">
                                <div class="auth-section">
                                    <div class="auth-section-title">Personal information</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="name">Customer name</label>
                                            <input class="form-control" id="name" type="text" name="name" value="<?php echo e($old['name']); ?>" placeholder="Your full name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="company_name">Company name</label>
                                            <input class="form-control" id="company_name" type="text" name="company_name" value="<?php echo e($old['company_name']); ?>" placeholder="Your business name">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="auth-section">
                                    <div class="auth-section-title">Contact information</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="phone">Phone</label>
                                            <input class="form-control" id="phone" type="text" name="phone" value="<?php echo e($old['phone']); ?>" placeholder="+8801XXXXXXXXX" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="email">Email</label>
                                            <input class="form-control" id="email" type="email" name="email" value="<?php echo e($old['email']); ?>" placeholder="you@company.com" required>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label" for="address">Address</label>
                                            <input class="form-control" id="address" type="text" name="address" value="<?php echo e($old['address']); ?>" placeholder="Street, area, or office location" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="city">City</label>
                                            <input class="form-control" id="city" type="text" name="city" value="<?php echo e($old['city']); ?>" placeholder="Dhaka">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="auth-section">
                                    <div class="auth-section-title">Account credentials</div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="password">Password</label>
                                            <input class="form-control" id="password" type="password" name="password" placeholder="At least 8 characters" required minlength="8">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="confirm_password">Confirm password</label>
                                            <input class="form-control" id="confirm_password" type="password" name="confirm_password" placeholder="Re-enter your password" required minlength="8">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <button class="btn btn-smartstock w-100" type="submit">Create Account</button>
                            </div>
                        </form>

                        <div class="auth-links">
                            <?php if ($showLogin): ?>
                                <a href="customer-login.php">Go to login</a>
                            <?php else: ?>
                                <span>Already have an account?</span>
                                <a href="customer-login.php">Login</a>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
