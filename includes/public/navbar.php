<?php
require_once __DIR__ . '/../session.php';

$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $cartItem) {
    $cartCount += (int)($cartItem['quantity'] ?? 0);
}

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<nav class="navbar navbar-expand-lg public-navbar">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="navbar-brand-mark">S</span>
            <span>
                <span class="navbar-brand-title">SmartStock</span>
                <span class="navbar-brand-subtitle">Laobaan Bangladesh LTD.</span>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'menu.php' ? 'active' : ''; ?>" href="menu.php">Products</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'cart.php' ? 'active' : ''; ?>" href="cart.php">
                        Cart
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-pill"><?php echo (int)$cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'my-orders.php' ? 'active' : ''; ?>" href="my-orders.php">My Orders</a></li>

                <?php if (!empty($_SESSION['customer_logged_in'])): ?>
                    <li class="nav-item"><a class="nav-link <?php echo in_array($currentPage, ['profile.php', 'change-password.php'], true) ? 'active' : ''; ?>" href="profile.php">Profile</a></li>
                    <li class="nav-item"><span class="nav-link navbar-user">Hi, <?php echo htmlspecialchars($_SESSION['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm nav-action ms-lg-2" href="customer-logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'customer-login.php' ? 'active' : ''; ?>" href="customer-login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm nav-action ms-lg-2" href="customer-register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
