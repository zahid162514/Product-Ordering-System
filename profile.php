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
    "SELECT customer_name, company_name, phone, customer_email, customer_address, city
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

    $name = trim($_POST['customer_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['customer_address'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($name === '' || $phone === '' || $address === '') {
        $errorMessage = "Name, phone, and address are required.";
    } else {
        $update = $conn->prepare(
            "UPDATE customer_registration
             SET customer_name = ?, company_name = ?, phone = ?, customer_address = ?, city = ?
             WHERE customer_id = ?"
        );
        $update->bind_param("sssssi", $name, $companyName, $phone, $address, $city, $customerId);

        if ($update->execute()) {
            $_SESSION['customer_name'] = $name;
            $_SESSION['customer_company_name'] = $companyName;
            $_SESSION['customer_address'] = $address;
            $_SESSION['customer_city'] = $city;
            $customer = array_merge($customer, [
                'customer_name' => $name,
                'company_name' => $companyName,
                'phone' => $phone,
                'customer_address' => $address,
                'city' => $city,
            ]);
            $successMessage = "Profile updated successfully.";
        } else {
            $errorMessage = "Unable to update your profile right now.";
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
    <title>My Profile | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/public/navbar.php'; ?>

    <main class="container page-shell">
        <div class="section-header">
            <div>
                <span class="section-eyebrow text-bg-primary">Customer Account</span>
                <h1 class="section-title">My Profile</h1>
                <p class="section-copy">Keep your business and delivery details current for faster ordering and checkout.</p>
            </div>

            <div class="page-action-group">
                <a class="btn btn-page-action btn-page-action-light" href="my-orders.php">Order History</a>
                <a class="btn btn-page-action btn-page-action-primary" href="change-password.php">Change Password</a>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>

        <div class="profile-grid">
            <form method="post" class="surface-card row g-3">
                <?php echo csrf_field(); ?>

                <div class="col-12">
                    <div class="profile-form-intro">
                        <h2>Contact Details</h2>
                        <p>Update the information used across delivery, invoices, and account communication.</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="customer_name">Customer name</label>
                    <input class="form-control" id="customer_name" name="customer_name" value="<?php echo e($customer['customer_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="company_name">Company name</label>
                    <input class="form-control" id="company_name" name="company_name" value="<?php echo e($customer['company_name']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="<?php echo e($customer['phone']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="customer_email">Email</label>
                    <input class="form-control" id="customer_email" value="<?php echo e($customer['customer_email']); ?>" disabled>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="customer_address">Address</label>
                    <input class="form-control" id="customer_address" name="customer_address" value="<?php echo e($customer['customer_address']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" value="<?php echo e($customer['city']); ?>">
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                    <a class="btn btn-outline-secondary" href="index.php">Cancel</a>
                    <button class="btn btn-smartstock" type="submit">Save Profile</button>
                </div>
            </form>

            <aside class="account-panel profile-side-panel">
                <span class="profile-side-kicker">Account Overview</span>
                <h2>Keep your ordering details up to date</h2>
                <p>Use this page for contact and delivery information so checkout stays fast and your order records stay accurate.</p>
                <a class="btn btn-page-action btn-page-action-primary w-100" href="change-password.php">Open Password Page</a>
            </aside>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/public/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
