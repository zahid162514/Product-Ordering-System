<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

require_customer();

$customerId = (int)$_SESSION['customer_id'];
$flashMessages = pull_flash_messages('shop_flash');
$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    require_valid_csrf();

    $orderId = intval($_POST['order_id'] ?? 0);
    if ($orderId <= 0) {
        $errorMessage = "Invalid order request.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, status, payment_status, stock_restored FROM tbl_orders WHERE id = ? AND customer_id = ? FOR UPDATE");
            $stmt->bind_param("ii", $orderId, $customerId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new RuntimeException("Order not found.");
            }

            if (!in_array($order['status'], ['Pending', 'Confirmed'], true)) {
                throw new RuntimeException("This order can no longer be cancelled.");
            }

            if ($order['payment_status'] === 'Paid') {
                throw new RuntimeException("Paid orders cannot be cancelled from this page. Please contact support.");
            }

            if ((int)$order['stock_restored'] !== 1) {
                $itemsStmt = $conn->prepare("SELECT product_id, quantity FROM tbl_order_items WHERE order_id = ? AND product_id IS NOT NULL");
                $itemsStmt->bind_param("i", $orderId);
                $itemsStmt->execute();
                $items = $itemsStmt->get_result();

                while ($item = $items->fetch_assoc()) {
                    $quantity = (int)$item['quantity'];
                    $productId = (int)$item['product_id'];
                    $stockStmt = $conn->prepare("UPDATE tbl_product SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
                    $stockStmt->bind_param("ii", $quantity, $productId);
                    $stockStmt->execute();
                    $stockStmt->close();

                    $stockAfterStmt = $conn->prepare("SELECT stock_quantity FROM tbl_product WHERE product_id = ?");
                    $stockAfterStmt->bind_param("i", $productId);
                    $stockAfterStmt->execute();
                    $stockAfter = (int)($stockAfterStmt->get_result()->fetch_assoc()['stock_quantity'] ?? 0);
                    $stockAfterStmt->close();

                    record_inventory_adjustment(
                        $conn,
                        $productId,
                        'customer_cancel',
                        $quantity,
                        $stockAfter,
                        'Stock returned after customer cancellation.',
                        $orderId,
                        null,
                        $customerId
                    );
                }
                $itemsStmt->close();
            }

            $update = $conn->prepare("UPDATE tbl_orders SET status = 'Cancelled', cancelled_at = NOW(), stock_restored = 1 WHERE id = ? AND customer_id = ?");
            $update->bind_param("ii", $orderId, $customerId);
            $update->execute();
            $update->close();

            record_order_status_history($conn, $orderId, $order['status'], 'Cancelled', 'Cancelled by customer.', null, $customerId);

            $conn->commit();
            notify_customer_order($conn, $orderId, 'SmartStock order cancelled', 'Your order has been cancelled and stock was returned.');
            $successMessage = "Order cancelled and stock returned.";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("Customer grouped order cancellation failed: " . $e->getMessage());
            $errorMessage = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder'])) {
    require_valid_csrf();

    $orderId = intval($_POST['order_id'] ?? 0);
    if ($orderId <= 0) {
        $errorMessage = "Invalid reorder request.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM tbl_orders WHERE id = ? AND customer_id = ? LIMIT 1");
        $stmt->bind_param("ii", $orderId, $customerId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            $errorMessage = "Order not found.";
        } else {
            $itemsStmt = $conn->prepare(
                "SELECT oi.product_id, oi.quantity, p.title, p.price, p.stock_quantity, p.image_name, p.active
                 FROM tbl_order_items oi
                 JOIN tbl_product p ON p.product_id = oi.product_id
                 WHERE oi.order_id = ?"
            );
            $itemsStmt->bind_param("i", $orderId);
            $itemsStmt->execute();
            $items = $itemsStmt->get_result();

            $added = 0;
            $cartItems = array_values($_SESSION['cart'] ?? []);

            while ($item = $items->fetch_assoc()) {
                $availableStock = max(0, (int)$item['stock_quantity']);
                $quantity = min((int)$item['quantity'], $availableStock);
                if ($item['active'] !== 'Yes' || $quantity <= 0) {
                    continue;
                }

                $merged = false;
                foreach ($cartItems as &$cartItem) {
                    if ((int)$cartItem['id'] !== (int)$item['product_id']) {
                        continue;
                    }

                    $newQuantity = min($availableStock, (int)$cartItem['quantity'] + $quantity);
                    if ($newQuantity > (int)$cartItem['quantity']) {
                        $cartItem['quantity'] = $newQuantity;
                        $cartItem['title'] = $item['title'];
                        $cartItem['price'] = (float)$item['price'];
                        $cartItem['stock_quantity'] = $availableStock;
                        $cartItem['image_name'] = $item['image_name'];
                        $added++;
                    }

                    $merged = true;
                    break;
                }
                unset($cartItem);

                if ($merged) {
                    continue;
                }

                $cartItems[] = [
                    'id' => (int)$item['product_id'],
                    'title' => $item['title'],
                    'price' => (float)$item['price'],
                    'quantity' => $quantity,
                    'stock_quantity' => $availableStock,
                    'image_name' => $item['image_name'],
                ];
                $added++;
            }
            $itemsStmt->close();

            if ($added > 0) {
                $_SESSION['cart'] = $cartItems;
                push_flash_message('shop_flash', 'success', 'Previous order items were merged into your cart with current price and stock.');
                header('Location: cart.php');
                exit;
            }

            $errorMessage = "No available products from that order could be reordered.";
        }
    }
}

$sql = "SELECT
            o.id,
            o.total_amount,
            o.subtotal_amount,
            o.discount_amount,
            o.delivery_fee,
            o.status,
            o.order_date,
            o.expected_delivery_date,
            o.courier_name,
            o.tracking_number,
            o.payment_method,
            o.payment_status,
            o.due_amount,
            COUNT(oi.id) AS item_count,
            GROUP_CONCAT(CONCAT(COALESCE(oi.product_name_snapshot, 'Product'), ' x ', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS item_summary
        FROM tbl_orders o
        LEFT JOIN tbl_order_items oi ON oi.order_id = o.id
        WHERE o.customer_id = ?
        GROUP BY o.id, o.total_amount, o.subtotal_amount, o.discount_amount, o.delivery_fee, o.status, o.order_date,
                 o.expected_delivery_date, o.courier_name, o.tracking_number, o.payment_method, o.payment_status, o.due_amount
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/public/navbar.php'; ?>

    <main class="container page-shell">
        <div class="section-header">
            <div>
                <span class="section-eyebrow text-bg-primary">Customer Orders</span>
                <h1 class="section-title">My Orders</h1>
                <p class="section-copy">Review orders, payment status, delivery tracking, and reorder previous items.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="profile.php">Profile</a>
                <a class="btn btn-outline-primary" href="menu.php">Browse Products</a>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endforeach; ?>

        <div class="surface-card orders-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Delivery</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">#<?php echo (int)$row['id']; ?></div>
                                    <div class="small text-secondary"><?php echo e(date("M d, Y", strtotime($row['order_date']))); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo (int)$row['item_count']; ?> item(s)</div>
                                    <div class="small text-secondary"><?php echo e($row['item_summary'] ?? 'No items'); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo format_bdt($row['total_amount']); ?></div>
                                    <div class="small text-secondary">Due: <?php echo format_bdt($row['due_amount']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo e($row['payment_method'] ?: 'N/A'); ?></div>
                                    <div class="small text-secondary"><?php echo e($row['payment_status']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo e($row['courier_name'] ?: 'Not assigned'); ?></div>
                                    <div class="small text-secondary">
                                        <?php echo e($row['tracking_number'] ?: ('ETA ' . ($row['expected_delivery_date'] ?: 'TBD'))); ?>
                                    </div>
                                </td>
                                <td><?php echo order_status_badge($row['status']); ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="order_id" value="<?php echo (int)$row['id']; ?>">
                                            <button class="btn btn-sm btn-outline-primary" type="submit" name="reorder">Reorder</button>
                                        </form>

                                        <?php if (in_array($row['status'], ['Pending', 'Confirmed'], true) && $row['payment_status'] !== 'Paid'): ?>
                                            <form method="post" onsubmit="return confirm('Cancel this full order?')" class="d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int)$row['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" name="cancel_order">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-secondary py-5">No orders found yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/public/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
