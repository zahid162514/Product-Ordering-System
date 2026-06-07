<?php
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$flashMessages = pull_flash_messages('shop_flash');
$cartMessages = [];

function cart_redirect_path(string $requested, string $fallback = 'cart.php'): string
{
    return safe_local_path(
        $requested,
        ['index.php', 'menu.php', 'cart.php', 'customer-login.php', 'order.php', 'my-orders.php'],
        $fallback
    );
}

function redirect_with_cart_flash(string $target, string $type, string $message): never
{
    push_flash_message('shop_flash', $type, $message);
    header('Location: ' . $target);
    exit;
}

/*
|--------------------------------------------------------------------------
| Add to Cart / Buy Now
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !isset($_POST['update_cart'])
    && !isset($_POST['remove_from_cart'])
    && (isset($_POST['add_to_cart']) || isset($_POST['buy_now']) || isset($_POST['product_id']))
) {
    require_valid_csrf();

    $isBuyNow = isset($_POST['buy_now']);
    $redirectTarget = cart_redirect_path($_POST['redirect_to'] ?? 'cart.php');
    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));

    $stmt = $conn->prepare(
        "SELECT product_id, title, price, stock_quantity, image_name
         FROM tbl_product
         WHERE product_id = ? AND active = 'Yes'"
    );
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        redirect_with_cart_flash($redirectTarget, 'danger', 'Product is unavailable.');
    }

    $availableStock = max(0, (int)$product['stock_quantity']);

    if ($availableStock < 1) {
        redirect_with_cart_flash($redirectTarget, 'danger', 'This product is currently out of stock.');
    }

    $currentQuantity = 0;

    foreach ($_SESSION['cart'] as $item) {
        if ((int)$item['id'] === $productId) {
            $currentQuantity = (int)$item['quantity'];
            break;
        }
    }

    $remainingStock = max(0, $availableStock - $currentQuantity);
    $quantity = min($quantity, $remainingStock);

    if ($quantity < 1) {
        redirect_with_cart_flash($redirectTarget, 'warning', 'You already have the available stock for this product in your cart.');
    }

    $found = false;

    foreach ($_SESSION['cart'] as &$item) {
        if ((int)$item['id'] === $productId) {
            $item['quantity'] += $quantity;
            $item['title'] = $product['title'];
            $item['price'] = (float)$product['price'];
            $item['stock_quantity'] = $availableStock;
            $item['image_name'] = $product['image_name'];
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        $_SESSION['cart'][] = [
            'id' => (int)$product['product_id'],
            'title' => $product['title'],
            'price' => (float)$product['price'],
            'quantity' => $quantity,
            'stock_quantity' => $availableStock,
            'image_name' => $product['image_name'],
        ];
    }

    if ($isBuyNow) {
        if (!empty($_SESSION['customer_logged_in'])) {
            redirect_with_cart_flash('order.php', 'success', 'Item added. Review your checkout details below.');
        }

        $_SESSION['post_login_redirect'] = 'order.php';
        redirect_with_cart_flash('customer-login.php', 'warning', 'Item added to cart. Log in to continue checkout.');
    }

    redirect_with_cart_flash($redirectTarget, 'success', 'Product added to cart.');
}

/*
|--------------------------------------------------------------------------
| Update Cart
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    require_valid_csrf();

    foreach ($_POST['quantities'] ?? [] as $productId => $quantity) {
        $productId = (int)$productId;
        $quantity = (int)$quantity;

        foreach ($_SESSION['cart'] as $index => &$item) {
            if ((int)$item['id'] !== $productId) {
                continue;
            }

            $stmt = $conn->prepare(
                "SELECT product_id, title, price, stock_quantity, active, image_name
                 FROM tbl_product
                 WHERE product_id = ?"
            );
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product || $product['active'] !== 'Yes') {
                unset($_SESSION['cart'][$index]);
                $cartMessages[] = ['warning', 'An unavailable product was removed from your cart.'];
                break;
            }

            $availableStock = max(0, (int)$product['stock_quantity']);

            if ($availableStock < 1) {
                unset($_SESSION['cart'][$index]);
                $cartMessages[] = ['warning', $product['title'] . ' is now out of stock and was removed from your cart.'];
                break;
            }

            if ($quantity <= 0) {
                unset($_SESSION['cart'][$index]);
                $cartMessages[] = ['warning', $product['title'] . ' was removed from your cart.'];
                break;
            }

            if ($quantity > $availableStock) {
                $quantity = $availableStock;
                $cartMessages[] = ['warning', $product['title'] . ' quantity was reduced to available stock.'];
            }

            $item = [
                'id' => (int)$product['product_id'],
                'title' => $product['title'],
                'price' => (float)$product['price'],
                'quantity' => $quantity,
                'stock_quantity' => $availableStock,
                'image_name' => $product['image_name'],
            ];

            break;
        }
        unset($item);
    }

    $_SESSION['cart'] = array_values($_SESSION['cart']);

    foreach ($cartMessages as [$type, $message]) {
        push_flash_message('shop_flash', $type, $message);
    }

    header('Location: cart.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Remove Item
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart'])) {
    require_valid_csrf();

    $removeId = intval($_POST['remove_from_cart'] ?? 0);

    foreach ($_SESSION['cart'] as $index => $item) {
        if ((int)$item['id'] === $removeId) {
            unset($_SESSION['cart'][$index]);
            break;
        }
    }

    $_SESSION['cart'] = array_values($_SESSION['cart']);

    redirect_with_cart_flash('cart.php', 'success', 'Item removed from cart.');
}

/*
|--------------------------------------------------------------------------
| Refresh Cart Against Database
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $index => &$item) {
        $productId = (int)($item['id'] ?? 0);

        $stmt = $conn->prepare(
            "SELECT product_id, title, price, stock_quantity, active, image_name
             FROM tbl_product
             WHERE product_id = ?"
        );
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product || $product['active'] !== 'Yes') {
            unset($_SESSION['cart'][$index]);
            $cartMessages[] = ['warning', 'An unavailable product was removed from your cart.'];
            continue;
        }

        $availableStock = max(0, (int)$product['stock_quantity']);

        if ($availableStock < 1) {
            unset($_SESSION['cart'][$index]);
            $cartMessages[] = ['warning', $product['title'] . ' is now out of stock and was removed from your cart.'];
            continue;
        }

        $requestedQuantity = max(1, (int)($item['quantity'] ?? 1));

        if ($requestedQuantity > $availableStock) {
            $requestedQuantity = $availableStock;
            $cartMessages[] = ['warning', $product['title'] . ' quantity was reduced to available stock.'];
        }

        $item = [
            'id' => (int)$product['product_id'],
            'title' => $product['title'],
            'price' => (float)$product['price'],
            'quantity' => $requestedQuantity,
            'stock_quantity' => $availableStock,
            'image_name' => $product['image_name'],
        ];
    }

    unset($item);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}

$cartTotal = 0;
$totalQuantity = 0;

foreach ($_SESSION['cart'] as $item) {
    $quantity = (int)$item['quantity'];
    $cartTotal += (float)$item['price'] * $quantity;
    $totalQuantity += $quantity;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | SmartStock</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="shop-page cart-page">
    <?php require_once __DIR__ . '/includes/public/navbar.php'; ?>

    <main class="container-xl page-shell">
        <div class="section-header cart-page-header">
            <div>
                <span class="section-eyebrow text-bg-primary">Cart Review</span>
                <h1 class="section-title">Your Cart</h1>
                <p class="section-copy">
                    Review your selected products, adjust quantities, and continue to checkout.
                </p>
            </div>

            <div class="page-action-group">
                <a class="btn btn-page-action btn-page-action-light" href="menu.php">
                    Keep Shopping
                </a>

                <?php if (!empty($_SESSION['customer_logged_in'])): ?>
                    <a class="btn btn-page-action btn-page-action-primary" href="order.php">
                        Continue to Checkout
                    </a>
                <?php else: ?>
                    <a class="btn btn-page-action btn-page-action-secondary" href="customer-login.php">
                        Login for Checkout
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?> shadow-sm border-0 mb-4">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($cartMessages as [$type, $message]): ?>
            <div class="alert alert-<?php echo e($type); ?> shadow-sm border-0 mb-4">
                <?php echo e($message); ?>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($_SESSION['cart'])): ?>
            <form method="post" action="cart.php">
                <?php echo csrf_field(); ?>

                <div class="cart-flow-stack">
                    <section class="cart-card">
                        <div class="cart-card-header">
                            <div>
                                <h2 class="h5 mb-1">Order Items</h2>
                                <p class="text-secondary mb-0">
                                    Update quantities or remove products before checkout.
                                </p>
                            </div>

                            <div class="cart-header-actions">
                                <button class="btn btn-page-action btn-page-action-secondary" type="submit" name="update_cart">
                                    Save Cart Changes
                                </button>
                            </div>
                        </div>

                        <div class="cart-items">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <?php
                                    $itemId = (int)$item['id'];
                                    $itemQuantity = (int)$item['quantity'];
                                    $itemStock = (int)$item['stock_quantity'];
                                    $itemPrice = (float)$item['price'];
                                    $subtotal = $itemPrice * $itemQuantity;
                                ?>

                                <article
                                    class="cart-item modern-cart-item"
                                    data-cart-item
                                    data-price="<?php echo e((string)$itemPrice); ?>"
                                >
                                    <img
                                        class="cart-item-image"
                                        src="<?php echo e(product_image_src($item['image_name'] ?? null)); ?>"
                                        alt="<?php echo e($item['title']); ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >

                                    <div class="cart-item-content">
                                        <div class="cart-item-meta">
                                            <div>
                                                <h3 class="cart-item-title">
                                                    <?php echo e($item['title']); ?>
                                                </h3>

                                                <p class="cart-item-subtitle">
                                                    Unit price: <?php echo format_bdt($itemPrice); ?>
                                                </p>
                                            </div>

                                            <div class="cart-item-price-box text-end">
                                                <?php echo stock_badge($itemStock); ?>

                                                <div class="mt-2 fw-semibold cart-line-total">
                                                    <?php echo format_bdt($subtotal); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="cart-item-actions">
                                            <div>
                                                <label class="form-label" for="qty-<?php echo $itemId; ?>">
                                                    Quantity
                                                </label>

                                                <input
                                                    class="form-control cart-quantity-input"
                                                    id="qty-<?php echo $itemId; ?>"
                                                    type="number"
                                                    name="quantities[<?php echo $itemId; ?>]"
                                                    value="<?php echo $itemQuantity; ?>"
                                                    min="1"
                                                    max="<?php echo $itemStock; ?>"
                                                    data-stock="<?php echo $itemStock; ?>"
                                                    inputmode="numeric"
                                                >
                                            </div>

                                            <div>
                                                <label class="form-label">Available Stock</label>
                                                <div class="form-control cart-stock-display">
                                                    <?php echo $itemStock; ?> unit(s)
                                                </div>
                                            </div>

                                            <div class="d-grid">
                                                <label class="form-label d-none d-md-block">&nbsp;</label>

                                                <button
                                                    class="btn btn-outline-danger"
                                                    type="submit"
                                                    name="remove_from_cart"
                                                    value="<?php echo $itemId; ?>"
                                                    formnovalidate
                                                >
                                                    Remove Item
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="checkout-summary cart-summary-block p-4">
                        <div class="checkout-summary-header">
                            <h2 class="h5 mb-1">Order Summary</h2>
                            <p class="text-secondary mb-0">Final totals are confirmed during checkout.</p>
                        </div>

                        <div class="summary-list mt-4">
                            <div class="summary-row">
                                <span>Products</span>
                                <strong><?php echo count($_SESSION['cart']); ?></strong>
                            </div>

                            <div class="summary-row">
                                <span>Total quantity</span>
                                <strong id="cart-total-quantity"><?php echo $totalQuantity; ?></strong>
                            </div>

                            <div class="summary-row">
                                <span>Subtotal</span>
                                <strong id="cart-subtotal"><?php echo format_bdt($cartTotal); ?></strong>
                            </div>

                            <div class="summary-row summary-total">
                                <span>Total</span>
                                <strong id="cart-total"><?php echo format_bdt($cartTotal); ?></strong>
                            </div>
                        </div>

                        <hr class="my-4">

                        <?php if (!empty($_SESSION['customer_logged_in'])): ?>
                            <div class="cart-customer-panel mb-4">
                                <span class="cart-customer-kicker">Checkout Account</span>
                                <div class="cart-customer-name">
                                    <?php echo e($_SESSION['customer_name'] ?? 'Customer'); ?>
                                </div>
                                <div class="cart-customer-email">
                                    <?php echo e($_SESSION['customer_email'] ?? ''); ?>
                                </div>
                            </div>

                            <div class="summary-actions">
                                <a class="btn btn-smartstock w-100" href="order.php">
                                    Proceed to Checkout
                                </a>
                                <a class="btn btn-outline-secondary w-100" href="profile.php">
                                    Review Profile Details
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary">
                                Log in or create an account to continue with checkout.
                            </p>

                            <div class="summary-actions">
                                <a class="btn btn-smartstock" href="customer-login.php">
                                    Login to Checkout
                                </a>

                                <a class="btn btn-outline-primary" href="customer-register.php">
                                    Register
                                </a>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </form>
        <?php else: ?>
            <div class="empty-state cart-empty-state">
                <h2>Your cart is empty</h2>
                <p>Add products from the catalog to begin your next order.</p>

                <a class="btn btn-smartstock rounded-pill px-4" href="menu.php">
                    Browse Products
                </a>
            </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/public/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const formatBDT = (amount) => {
            return '৳' + Number(amount).toLocaleString('en-BD', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        function updateCartPreviewTotals() {
            let total = 0;
            let quantityTotal = 0;

            document.querySelectorAll('[data-cart-item]').forEach(item => {
                const price = parseFloat(item.dataset.price || '0');
                const input = item.querySelector('.cart-quantity-input');
                const lineTotal = item.querySelector('.cart-line-total');

                if (!input || !lineTotal) {
                    return;
                }

                const min = parseInt(input.min || '1', 10);
                const max = parseInt(input.max || input.dataset.stock || '1', 10);
                let quantity = parseInt(input.value || min, 10);

                if (Number.isNaN(quantity) || quantity < min) {
                    quantity = min;
                }

                if (quantity > max) {
                    quantity = max;
                }

                input.value = quantity;

                const subtotal = price * quantity;
                total += subtotal;
                quantityTotal += quantity;

                lineTotal.textContent = formatBDT(subtotal);
            });

            const subtotalEl = document.getElementById('cart-subtotal');
            const totalEl = document.getElementById('cart-total');
            const quantityEl = document.getElementById('cart-total-quantity');

            if (subtotalEl) {
                subtotalEl.textContent = formatBDT(total);
            }

            if (totalEl) {
                totalEl.textContent = formatBDT(total);
            }

            if (quantityEl) {
                quantityEl.textContent = quantityTotal;
            }
        }

        document.querySelectorAll('.cart-quantity-input').forEach(input => {
            input.addEventListener('input', updateCartPreviewTotals);
            input.addEventListener('blur', updateCartPreviewTotals);
        });
    </script>
</body>
</html>
