<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/sslcommerz.php';

$tranId = trim((string)(($_POST ?: $_GET)['tran_id'] ?? ''));
$orderId = $tranId !== ''
    ? sslcommerz_mark_unpaid_terminal($conn, $tranId, 'Cancelled', 'Stock returned after SSLCOMMERZ payment cancellation.')
    : null;

if ($orderId) {
    notify_customer_order($conn, $orderId, 'SmartStock payment cancelled', 'Your online payment was cancelled, so the order was cancelled.');
}

push_flash_message('shop_flash', 'warning', 'Online payment was cancelled.');
header('Location: my-orders.php');
exit;
?>
