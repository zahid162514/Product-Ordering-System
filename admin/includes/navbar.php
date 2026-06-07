<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth.php';

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$role = current_admin_role();
$canCatalog = in_array($role, ['super_admin', 'manager', 'inventory'], true);
$canOrders = in_array($role, ['super_admin', 'manager'], true);
$canSupport = in_array($role, ['super_admin', 'manager', 'support'], true);
?>
<nav class="navbar navbar-expand-lg navbar-dark admin-navbar shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <span class="admin-brand-mark">S</span>
            <span class="admin-brand-copy">
                <span>SmartStock Admin</span>
                <small>Laobaan Bangladesh LTD.</small>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a></li>
                <?php if ($canCatalog): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'manage-products.php' || $currentPage === 'add-product.php' || $currentPage === 'edit-products.php' ? 'active' : ''; ?>" href="manage-products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'manage-categories.php' || $currentPage === 'add-category.php' || $currentPage === 'edit-category.php' ? 'active' : ''; ?>" href="manage-categories.php">Categories</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'inventory.php' || $currentPage === 'inventory-ledger.php' ? 'active' : ''; ?>" href="inventory.php">Inventory</a></li>
                <?php endif; ?>
                <?php if ($canOrders): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'manage-order.php' || $currentPage === 'order-details.php' ? 'active' : ''; ?>" href="manage-order.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'coupons.php' ? 'active' : ''; ?>" href="coupons.php">Coupons</a></li>
                <?php endif; ?>
                <?php if ($role === 'super_admin'): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'manage-admin.php' || $currentPage === 'add-admin.php' || $currentPage === 'update-admin.php' ? 'active' : ''; ?>" href="manage-admin.php">Admins</a></li>
                <?php endif; ?>
                <?php if ($canSupport): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'support.php' ? 'active' : ''; ?>" href="support.php">Support</a></li>
                <?php endif; ?>
            </ul>
            <div class="admin-actions ms-lg-auto">
                <?php if (!empty($_SESSION['admin_username'])): ?>
                    <span class="navbar-text text-white-50"><?php echo htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <form method="post" action="dashboard.php" class="d-flex">
                    <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
                    <button class="btn btn-outline-light btn-sm" type="submit" name="logout">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>
