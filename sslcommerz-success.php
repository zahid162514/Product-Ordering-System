<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/sslcommerz.php';

$result = sslcommerz_validate_payment($conn, $_POST ?: $_GET);

if ($result['paid']) {
    if ($result['changed'] && $result['order_id']) {
        notify_customer_order($conn, (int)$result['order_id'], 'SmartStock payment confirmed', 'Your online payment has been confirmed.');
    }
    push_flash_message('shop_flash', 'success', 'Payment confirmed. Your order is now marked as paid.');
} else {
    push_flash_message('shop_flash', 'danger', 'Payment could not be validated. Please contact support if money was deducted.');
}

header('Location: my-orders.php');
exit;
?>
