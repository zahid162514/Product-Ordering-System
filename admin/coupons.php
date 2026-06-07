<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager']);

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    require_valid_csrf();

    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $discountType = trim($_POST['discount_type'] ?? 'fixed');
    $discountValue = normalize_money($_POST['discount_value'] ?? 0);
    $minOrderAmount = normalize_money($_POST['min_order_amount'] ?? 0);
    $active = trim($_POST['active'] ?? 'Yes');
    $usageLimit = trim($_POST['usage_limit'] ?? '') === '' ? null : max(0, intval($_POST['usage_limit']));

    if ($code === '' || !preg_match('/^[A-Z0-9_-]+$/', $code) || !in_array($discountType, ['fixed', 'percentage'], true) || $discountValue <= 0 || !in_array($active, ['Yes', 'No'], true)) {
        $errorMessage = "Code, discount type, discount value, and active status are required.";
    } elseif ($discountType === 'percentage' && $discountValue > 100) {
        $errorMessage = "Percentage discount cannot be greater than 100.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO tbl_coupons (code, description, discount_type, discount_value, min_order_amount, active, usage_limit)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssddsi", $code, $description, $discountType, $discountValue, $minOrderAmount, $active, $usageLimit);
        if ($stmt->execute()) {
            $successMessage = "Coupon saved.";
        } else {
            $errorMessage = "Unable to save coupon. Code may already exist.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_coupon'])) {
    require_valid_csrf();

    $id = intval($_POST['coupon_id'] ?? 0);
    $active = $_POST['active'] === 'Yes' ? 'Yes' : 'No';
    $stmt = $conn->prepare("UPDATE tbl_coupons SET active = ? WHERE id = ?");
    $stmt->bind_param("si", $active, $id);
    $stmt->execute();
    $stmt->close();
    $successMessage = "Coupon status updated.";
}

$coupons = $conn->query("SELECT id, code, description, discount_type, discount_value, min_order_amount, active, usage_limit, used_count, created_at FROM tbl_coupons ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons | SmartStock Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container admin-shell">
        <div class="admin-page-header">
            <div>
                <span class="admin-page-eyebrow">Promotions</span>
                <h1 class="admin-page-title h3 mb-1">Coupons</h1>
                <p class="text-secondary mb-0">Create checkout discounts with minimum order and usage controls.</p>
            </div>
            <a class="btn btn-outline-primary" href="manage-order.php">Orders</a>
        </div>

        <?php if ($successMessage): ?><div class="alert alert-success"><?php echo e($successMessage); ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="alert alert-danger"><?php echo e($errorMessage); ?></div><?php endif; ?>

        <form method="post" class="card admin-surface-card mb-4">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Create Coupon</h2></div>
            <div class="card-body row g-3">
                <?php echo csrf_field(); ?>
                <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code" required></div>
                <div class="col-md-5"><label class="form-label">Description</label><input class="form-control" name="description"></div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="discount_type">
                        <option value="fixed">Fixed</option>
                        <option value="percentage">Percentage</option>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Value</label><input class="form-control" type="number" step="0.01" min="0" name="discount_value" required></div>
                <div class="col-md-3"><label class="form-label">Min Order</label><input class="form-control" type="number" step="0.01" min="0" name="min_order_amount" value="0"></div>
                <div class="col-md-3"><label class="form-label">Usage Limit</label><input class="form-control" type="number" min="0" name="usage_limit"></div>
                <div class="col-md-3">
                    <label class="form-label">Active</label>
                    <select class="form-select" name="active"><option value="Yes">Yes</option><option value="No">No</option></select>
                </div>
                <div class="col-md-3 d-grid align-items-end"><button class="btn btn-smartstock" type="submit" name="save_coupon">Save Coupon</button></div>
            </div>
        </form>

        <div class="card admin-surface-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Discount</th><th>Minimum</th><th>Usage</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        <?php if ($coupons && $coupons->num_rows > 0): ?>
                            <?php while ($coupon = $coupons->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo e($coupon['code']); ?></strong><div class="small text-secondary"><?php echo e($coupon['description']); ?></div></td>
                                    <td><?php echo $coupon['discount_type'] === 'percentage' ? e($coupon['discount_value'] . '%') : format_bdt($coupon['discount_value']); ?></td>
                                    <td><?php echo format_bdt($coupon['min_order_amount']); ?></td>
                                    <td><?php echo (int)$coupon['used_count']; ?> / <?php echo $coupon['usage_limit'] === null ? 'Unlimited' : (int)$coupon['usage_limit']; ?></td>
                                    <td><?php echo yes_no_badge($coupon['active'], 'Active', 'Inactive'); ?></td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="coupon_id" value="<?php echo (int)$coupon['id']; ?>">
                                            <input type="hidden" name="active" value="<?php echo $coupon['active'] === 'Yes' ? 'No' : 'Yes'; ?>">
                                            <button class="btn btn-sm btn-outline-primary" type="submit" name="toggle_coupon">
                                                <?php echo $coupon['active'] === 'Yes' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-secondary py-5">No coupons found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
