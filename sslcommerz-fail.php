<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/sslcommerz.php';

$tranId = trim((string)(($_POST ?: $_GET)['tran_id'] ?? ''));
$orderId = $tranId !== ''
    ? sslcommerz_mark_unpaid_terminal($conn, $tranId, 'Failed', 'Stock returned after SSLCOMMERZ payment failure.')
    : null;

if ($orderId) {
    notify_customer_order($conn, $orderId, 'SmartStock payment failed', 'Your online payment did not complete, so the order was cancelled.');
}

push_flash_message('shop_flash', 'danger', 'Online payment failed. Your order was not paid.');
header('Location: my-orders.php');
exit;
?>
