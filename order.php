<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/sslcommerz.php';

require_customer();

$flashMessages = pull_flash_messages('shop_flash');
$message = "";
$isError = false;
$couponMessage = "";
$customerId = (int)$_SESSION['customer_id'];
$uploadedReceiptPath = null;

$customerStmt = $conn->prepare(
    "SELECT customer_name, company_name, phone, customer_email, customer_address, city
     FROM customer_registration
     WHERE customer_id = ?
     LIMIT 1"
);
$customerStmt->bind_param("i", $customerId);
$customerStmt->execute();
$customer = $customerStmt->get_result()->fetch_assoc();
$customerStmt->close();

if (!$customer) {
    app_destroy_session();
    header('Location: customer-login.php');
    exit;
}

$checkoutOld = [
    'delivery_name' => $customer['customer_name'] ?? '',
    'delivery_phone' => $customer['phone'] ?? '',
    'delivery_address' => $customer['customer_address'] ?? '',
    'delivery_city' => $customer['city'] ?? '',
    'payment_method' => 'Cash on Delivery',
    'coupon_code' => '',
    'customer_note' => '',
];
$paymentMethods = ['Cash on Delivery', 'Bank Transfer', 'bKash/Nagad'];
if (sslcommerz_is_ready()) {
    $paymentMethods[] = 'SSLCOMMERZ';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_valid_csrf();
    $checkoutOld = array_merge($checkoutOld, $_POST);

    if (empty($_SESSION['cart'])) {
        $message = "Your cart is empty.";
        $isError = true;
    } else {
        $deliveryName = trim($_POST['delivery_name'] ?? '');
        $deliveryPhone = trim($_POST['delivery_phone'] ?? '');
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $deliveryCity = trim($_POST['delivery_city'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash on Delivery');
        $customerNote = trim($_POST['customer_note'] ?? '');
        $couponCodeInput = strtoupper(trim($_POST['coupon_code'] ?? ''));

        if ($deliveryName === '' || $deliveryPhone === '' || $deliveryAddress === '') {
            $message = "Delivery name, phone, and address are required.";
            $isError = true;
        } elseif (!in_array($paymentMethod, $paymentMethods, true)) {
            $message = "Please select a valid payment method.";
            $isError = true;
        } else {
            $conn->begin_transaction();

            try {
                $cartQuantities = [];
                foreach ($_SESSION['cart'] as $item) {
                    $productId = (int)($item['id'] ?? 0);
                    $quantity = (int)($item['quantity'] ?? 0);

                    if ($productId <= 0 || $quantity <= 0) {
                        throw new RuntimeException("Invalid cart item.");
                    }

                    $cartQuantities[$productId] = ($cartQuantities[$productId] ?? 0) + $quantity;
                }

                $orderItems = [];
                $subtotal = 0.0;

                foreach ($cartQuantities as $productId => $quantity) {
                    $productStmt = $conn->prepare(
                        "SELECT product_id, title, price, stock_quantity, active, image_name
                         FROM tbl_product
                         WHERE product_id = ?
                         FOR UPDATE"
                    );
                    $productStmt->bind_param("i", $productId);
                    $productStmt->execute();
                    $product = $productStmt->get_result()->fetch_assoc();
                    $productStmt->close();

                    if (!$product || $product['active'] !== 'Yes') {
                        throw new RuntimeException("A product in your cart is no longer available.");
                    }

                    $availableStock = (int)$product['stock_quantity'];
                    if ($quantity > $availableStock) {
                        throw new RuntimeException($product['title'] . " has only " . $availableStock . " item(s) available.");
                    }

                    $unitPrice = (float)$product['price'];
                    $lineTotal = $unitPrice * $quantity;
                    $subtotal += $lineTotal;

                    $orderItems[] = [
                        'product_id' => $productId,
                        'title' => $product['title'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'image_name' => $product['image_name'],
                    ];

                    $stockStmt = $conn->prepare(
                        "UPDATE tbl_product
                         SET stock_quantity = stock_quantity - ?
                         WHERE product_id = ? AND stock_quantity >= ?"
                    );
                    $stockStmt->bind_param("iii", $quantity, $productId, $quantity);
                    $stockStmt->execute();

                    if ($stockStmt->affected_rows !== 1) {
                        throw new RuntimeException($product['title'] . " stock changed while placing your order.");
                    }
                    $stockStmt->close();
                }

                $coupon = calculate_coupon_discount($conn, $couponCodeInput, $subtotal);
                if ($couponCodeInput !== '' && !$coupon['code']) {
                    throw new RuntimeException($coupon['message']);
                }

                $discountAmount = normalize_money($coupon['discount']);
                $deliveryFee = default_delivery_fee($subtotal - $discountAmount);
                $orderTotal = normalize_money($subtotal - $discountAmount + $deliveryFee);
                $paymentStatus = $paymentMethod === 'SSLCOMMERZ' ? 'Gateway Pending' : 'Unpaid';
                $paidAmount = 0.0;
                $receiptImage = null;

                if (!empty($_FILES['receipt']['name'])) {
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                    $fileName = basename($_FILES['receipt']['name']);
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    if (!in_array($fileExt, $allowedExtensions, true) || $_FILES['receipt']['size'] > 3 * 1024 * 1024) {
                        throw new RuntimeException("Receipt must be jpg, png, webp, or pdf up to 3MB.");
                    }

                    $uploadDir = __DIR__ . "/uploads/receipts/";
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                    $receiptImage = "uploads/receipts/" . time() . "_" . bin2hex(random_bytes(6)) . "_" . $safeBaseName . "." . $fileExt;

                    if (!move_uploaded_file($_FILES['receipt']['tmp_name'], __DIR__ . "/" . $receiptImage)) {
                        throw new RuntimeException("Unable to upload receipt.");
                    }

                    $uploadedReceiptPath = __DIR__ . "/" . $receiptImage;
                    $paymentStatus = 'Pending Review';
                }

                $dueAmount = normalize_money($orderTotal - $paidAmount);
                $expectedDeliveryDate = date('Y-m-d', strtotime('+3 days'));
                $couponCode = $coupon['code'];

                $orderStmt = $conn->prepare(
                    "INSERT INTO tbl_orders
                     (customer_id, subtotal_amount, discount_amount, delivery_fee, coupon_code, total_amount, status,
                      delivery_name, delivery_phone, delivery_address, delivery_city, expected_delivery_date,
                      payment_method, payment_status, paid_amount, due_amount, receipt_image, customer_note)
                     VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $orderStmt->bind_param(
                    "idddsdsssssssddss",
                    $customerId,
                    $subtotal,
                    $discountAmount,
                    $deliveryFee,
                    $couponCode,
                    $orderTotal,
                    $deliveryName,
                    $deliveryPhone,
                    $deliveryAddress,
                    $deliveryCity,
                    $expectedDeliveryDate,
                    $paymentMethod,
                    $paymentStatus,
                    $paidAmount,
                    $dueAmount,
                    $receiptImage,
                    $customerNote
                );
                $orderStmt->execute();
                $newOrderId = $conn->insert_id;
                $orderStmt->close();

                $itemStmt = $conn->prepare(
                    "INSERT INTO tbl_order_items
                     (order_id, product_id, product_name_snapshot, quantity, unit_price, line_total)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                foreach ($orderItems as $orderItem) {
                    $itemProductId = (int)$orderItem['product_id'];
                    $itemTitle = (string)$orderItem['title'];
                    $itemQuantity = (int)$orderItem['quantity'];
                    $itemUnitPrice = (float)$orderItem['unit_price'];
                    $itemLineTotal = (float)$orderItem['line_total'];

                    $itemStmt->bind_param(
                        "iisidd",
                        $newOrderId,
                        $itemProductId,
                        $itemTitle,
                        $itemQuantity,
                        $itemUnitPrice,
                        $itemLineTotal
                    );
                    $itemStmt->execute();

                    $stockAfterStmt = $conn->prepare("SELECT stock_quantity FROM tbl_product WHERE product_id = ?");
                    $stockAfterStmt->bind_param("i", $itemProductId);
                    $stockAfterStmt->execute();
                    $stockAfter = (int)($stockAfterStmt->get_result()->fetch_assoc()['stock_quantity'] ?? 0);
                    $stockAfterStmt->close();

                    record_inventory_adjustment(
                        $conn,
                        $itemProductId,
                        'checkout',
                        -$itemQuantity,
                        $stockAfter,
                        'Stock reserved by customer checkout.',
                        $newOrderId,
                        null,
                        $customerId
                    );
                    notify_low_stock($conn, $itemProductId);
                }
                $itemStmt->close();

                record_order_status_history($conn, $newOrderId, null, 'Pending', 'Order placed by customer.', null, $customerId);

                if ($couponCode) {
                    $couponUpdate = $conn->prepare("UPDATE tbl_coupons SET used_count = used_count + 1 WHERE code = ?");
                    $couponUpdate->bind_param("s", $couponCode);
                    $couponUpdate->execute();
                    $couponUpdate->close();
                }

                $conn->commit();

                if ($paymentMethod === 'SSLCOMMERZ') {
                    try {
                        $session = sslcommerz_create_session($conn, $newOrderId);
                        $_SESSION['cart'] = [];
                        header('Location: ' . $session['gateway_url']);
                        exit;
                    } catch (Throwable $gatewayException) {
                        sslcommerz_restore_order_stock($conn, $newOrderId, 'Stock returned after SSLCOMMERZ session failure.');
                        $gatewayFailUpdate = $conn->prepare("UPDATE tbl_orders SET payment_status = 'Gateway Failed' WHERE id = ?");
                        $gatewayFailUpdate->bind_param("i", $newOrderId);
                        $gatewayFailUpdate->execute();
                        $gatewayFailUpdate->close();
                        error_log("SSLCOMMERZ session failed: " . $gatewayException->getMessage());
                        $message = "Online payment could not be started. Please try again or choose another payment method.";
                        $isError = true;
                    }
                } else {
                    $_SESSION['cart'] = [];
                    notify_customer_order($conn, $newOrderId, 'SmartStock order received', 'Your order has been placed successfully and is awaiting review.');
                    $message = "Your order #" . $newOrderId . " has been placed successfully.";
                }
            } catch (Throwable $e) {
                $conn->rollback();
                if ($uploadedReceiptPath !== null && file_exists($uploadedReceiptPath)) {
                    unlink($uploadedReceiptPath);
                }
                error_log("Checkout failed: " . $e->getMessage());
                $message = $e->getMessage();
                $isError = true;
            }
        }
    }
}

$cartTotal = 0;
foreach ($_SESSION['cart'] ?? [] as $item) {
    $cartTotal += (float)$item['price'] * (int)$item['quantity'];
}

$previewCoupon = calculate_coupon_discount($conn, $checkoutOld['coupon_code'] ?? '', $cartTotal);
$previewDiscount = $previewCoupon['code'] ? (float)$previewCoupon['discount'] : 0.0;
$deliveryFeePreview = !empty($_SESSION['cart']) ? default_delivery_fee($cartTotal - $previewDiscount) : 0.0;
$grandTotalPreview = normalize_money($cartTotal - $previewDiscount + $deliveryFeePreview);
if (($checkoutOld['coupon_code'] ?? '') !== '') {
    $couponMessage = $previewCoupon['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/public/navbar.php'; ?>

    <main class="container page-shell">
        <div class="section-header">
            <div>
                <span class="section-eyebrow text-bg-primary">Checkout</span>
                <h1 class="section-title">Review and place your order</h1>
                <p class="section-copy">Confirm delivery, payment, order items, and total before submitting.</p>
            </div>
            <a class="btn btn-outline-primary" href="cart.php">Back to Cart</a>
        </div>

        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endforeach; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $isError ? 'danger' : 'success'; ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        <?php if (!$message && !empty($_SESSION['cart'])): ?>
            <form method="POST" enctype="multipart/form-data" class="checkout-layout">
                <?php echo csrf_field(); ?>

                <section class="checkout-card">
                    <div class="checkout-card-header">
                        <div>
                            <h2 class="h5 mb-1">Order items</h2>
                            <p class="text-secondary mb-0">A final review of the products that will be submitted.</p>
                        </div>
                    </div>
                    <div class="cart-items">
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                            <?php $subtotal = (float)$item['price'] * (int)$item['quantity']; ?>
                            <article class="cart-item">
                                <img class="cart-item-image" src="<?php echo e(product_image_src($item['image_name'] ?? null)); ?>" alt="<?php echo e($item['title']); ?>">
                                <div>
                                    <div class="cart-item-meta">
                                        <div>
                                            <h3 class="cart-item-title"><?php echo e($item['title']); ?></h3>
                                            <p class="cart-item-subtitle">Quantity: <?php echo (int)$item['quantity']; ?> unit(s)</p>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-secondary small">Unit price</div>
                                            <div class="fw-semibold"><?php echo format_bdt($item['price']); ?></div>
                                            <div class="mt-2 fw-semibold"><?php echo format_bdt($subtotal); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="p-4 border-top">
                        <h2 class="h5 mb-3">Delivery details</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="delivery_name">Delivery name</label>
                                <input class="form-control" id="delivery_name" name="delivery_name" value="<?php echo e($checkoutOld['delivery_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="delivery_phone">Delivery phone</label>
                                <input class="form-control" id="delivery_phone" name="delivery_phone" value="<?php echo e($checkoutOld['delivery_phone']); ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="delivery_address">Delivery address</label>
                                <input class="form-control" id="delivery_address" name="delivery_address" value="<?php echo e($checkoutOld['delivery_address']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="delivery_city">City</label>
                                <input class="form-control" id="delivery_city" name="delivery_city" value="<?php echo e($checkoutOld['delivery_city']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="customer_note">Order note</label>
                                <textarea class="form-control" id="customer_note" name="customer_note" rows="3"><?php echo e($checkoutOld['customer_note']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="checkout-summary p-4">
                    <h2 class="h5 mb-3">Payment</h2>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="payment_method">Payment method</label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?php echo e($method); ?>" <?php if ($checkoutOld['payment_method'] === $method) echo 'selected'; ?>>
                                        <?php echo e($method); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="receipt">Receipt upload</label>
                            <input class="form-control" id="receipt" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <div class="form-text">Optional for bank or mobile payments.</div>
                        </div>
                        <div>
                            <label class="form-label" for="coupon_code">Coupon code</label>
                            <input class="form-control" id="coupon_code" name="coupon_code" value="<?php echo e($checkoutOld['coupon_code']); ?>" placeholder="Optional">
                            <?php if ($couponMessage): ?>
                                <div class="form-text"><?php echo e($couponMessage); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>

                    <div class="summary-list">
                        <div class="summary-row"><span>Subtotal</span><strong><?php echo format_bdt($cartTotal); ?></strong></div>
                        <div class="summary-row"><span>Discount</span><strong><?php echo format_bdt($previewDiscount); ?></strong></div>
                        <div class="summary-row"><span>Delivery fee</span><strong><?php echo format_bdt($deliveryFeePreview); ?></strong></div>
                        <div class="summary-row summary-total"><span>Total</span><strong><?php echo format_bdt($grandTotalPreview); ?></strong></div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-smartstock" type="submit">Place Order</button>
                        <a class="btn btn-outline-secondary" href="cart.php">Back to Cart</a>
                    </div>
                </aside>
            </form>
        <?php elseif (!$message): ?>
            <div class="empty-state">
                <h2>Your cart is empty</h2>
                <p>Add products from the catalog before moving to checkout.</p>
                <a class="btn btn-smartstock" href="menu.php">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="surface-card">
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-smartstock" href="my-orders.php">View My Orders</a>
                    <a class="btn btn-outline-primary" href="menu.php">Continue Shopping</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/public/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
