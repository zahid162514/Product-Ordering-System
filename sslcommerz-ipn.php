<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/sslcommerz.php';

$result = sslcommerz_validate_payment($conn, $_POST ?: $_GET);

if ($result['paid']) {
    if ($result['changed'] && $result['order_id']) {
        notify_customer_order($conn, (int)$result['order_id'], 'SmartStock payment confirmed', 'Your online payment has been confirmed.');
    }

    http_response_code(200);
    echo 'VALID';
    exit;
}

http_response_code(400);
echo 'INVALID';
?>
