<?php
require_once __DIR__ . '/mailer.php';

function sslcommerz_config(): array
{
    $config = smartstock_config();
    return $config['sslcommerz'] ?? [];
}

function sslcommerz_is_ready(): bool
{
    $config = sslcommerz_config();
    return !empty($config['enabled'])
        && !empty($config['store_id'])
        && !empty($config['store_password'])
        && !empty($config['session_api'])
        && !empty($config['validation_api']);
}

function sslcommerz_create_session(mysqli $conn, int $orderId): array
{
    if (!sslcommerz_is_ready()) {
        throw new RuntimeException('SSLCOMMERZ is not configured.');
    }

    $stmt = $conn->prepare(
        "SELECT o.*, c.customer_name, c.customer_email, c.phone
         FROM tbl_orders o
         JOIN customer_registration c ON c.customer_id = o.customer_id
         WHERE o.id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new RuntimeException('Order not found for SSLCOMMERZ session.');
    }

    $config = sslcommerz_config();
    $tranId = 'SSLCZ-' . $orderId . '-' . strtoupper(bin2hex(random_bytes(4)));
    $amount = number_format((float)$order['total_amount'], 2, '.', '');
    $currency = (string)($config['currency'] ?? 'BDT');

    $payload = [
        'store_id' => $config['store_id'],
        'store_passwd' => $config['store_password'],
        'total_amount' => $amount,
        'currency' => $currency,
        'tran_id' => $tranId,
        'success_url' => app_url('sslcommerz-success.php'),
        'fail_url' => app_url('sslcommerz-fail.php'),
        'cancel_url' => app_url('sslcommerz-cancel.php'),
        'ipn_url' => app_url('sslcommerz-ipn.php'),
        'cus_name' => $order['customer_name'] ?: $order['delivery_name'],
        'cus_email' => $order['customer_email'],
        'cus_add1' => $order['delivery_address'] ?: 'N/A',
        'cus_city' => $order['delivery_city'] ?: 'Dhaka',
        'cus_postcode' => '1000',
        'cus_country' => 'Bangladesh',
        'cus_phone' => $order['delivery_phone'] ?: ($order['phone'] ?: 'N/A'),
        'shipping_method' => 'Courier',
        'ship_name' => $order['delivery_name'] ?: $order['customer_name'],
        'ship_add1' => $order['delivery_address'] ?: 'N/A',
        'ship_city' => $order['delivery_city'] ?: 'Dhaka',
        'ship_postcode' => '1000',
        'ship_country' => 'Bangladesh',
        'product_name' => 'SmartStock Order #' . $orderId,
        'product_category' => 'Products',
        'product_profile' => 'general',
    ];

    $insert = $conn->prepare(
        "INSERT INTO tbl_payment_transactions
         (order_id, gateway, transaction_id, amount, currency, status)
         VALUES (?, 'sslcommerz', ?, ?, ?, 'Initiated')"
    );
    $insert->bind_param("isds", $orderId, $tranId, $amount, $currency);
    $insert->execute();
    $transactionId = (int)$conn->insert_id;
    $insert->close();

    $response = sslcommerz_post_form($config['session_api'], $payload);
    $responseStatus = strtoupper((string)($response['status'] ?? ''));
    $gatewayUrl = $response['GatewayPageURL'] ?? '';
    $sessionKey = $response['sessionkey'] ?? '';

    $updateStatus = ($responseStatus === 'SUCCESS' && $gatewayUrl !== '') ? 'Session Created' : 'Session Failed';
    $rawResponse = json_encode($response);
    $update = $conn->prepare(
        "UPDATE tbl_payment_transactions
         SET status = ?, session_key = ?, gateway_url = ?, raw_init_response = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $update->bind_param("ssssi", $updateStatus, $sessionKey, $gatewayUrl, $rawResponse, $transactionId);
    $update->execute();
    $update->close();

    if ($updateStatus !== 'Session Created') {
        throw new RuntimeException('SSLCOMMERZ session creation failed.');
    }

    return [
        'transaction_id' => $tranId,
        'gateway_url' => $gatewayUrl,
        'response' => $response,
    ];
}

function sslcommerz_validate_payment(mysqli $conn, array $payload): array
{
    $tranId = trim((string)($payload['tran_id'] ?? ''));
    $valId = trim((string)($payload['val_id'] ?? ''));
    if ($tranId === '' || $valId === '') {
        return ['paid' => false, 'order_id' => null, 'changed' => false];
    }

    $config = sslcommerz_config();
    $validationUrl = $config['validation_api'] . '?' . http_build_query([
        'val_id' => $valId,
        'store_id' => $config['store_id'],
        'store_passwd' => $config['store_password'],
        'v' => 1,
        'format' => 'json',
    ]);

    $validation = sslcommerz_get_json($validationUrl);
    $status = strtoupper((string)($validation['status'] ?? ''));
    $validatedTranId = (string)($validation['tran_id'] ?? $tranId);
    $validatedAmount = (float)($validation['amount'] ?? 0);
    $validatedCurrency = (string)($validation['currency'] ?? ($config['currency'] ?? 'BDT'));
    $isValidGatewayStatus = in_array($status, ['VALID', 'VALIDATED'], true);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT pt.id, pt.order_id, pt.amount, pt.currency, pt.status, o.total_amount, o.payment_status
             FROM tbl_payment_transactions pt
             JOIN tbl_orders o ON o.id = pt.order_id
             WHERE pt.transaction_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->bind_param("s", $tranId);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$transaction || $validatedTranId !== $tranId) {
            throw new RuntimeException('Payment transaction mismatch.');
        }

        $alreadyPaid = $transaction['payment_status'] === 'Paid' || $transaction['status'] === 'Paid';
        $expectedAmount = (float)$transaction['amount'];
        $expectedCurrency = (string)$transaction['currency'];
        $amountMatches = abs($validatedAmount - $expectedAmount) < 0.01;
        $currencyMatches = strtoupper($validatedCurrency) === strtoupper($expectedCurrency);
        $newTransactionStatus = ($isValidGatewayStatus && $amountMatches && $currencyMatches) ? 'Paid' : 'Validation Failed';
        $rawPayload = json_encode($payload);
        $rawValidation = json_encode($validation);
        $bankTranId = $validation['bank_tran_id'] ?? ($payload['bank_tran_id'] ?? null);
        $cardType = $validation['card_type'] ?? ($payload['card_type'] ?? null);
        $cardIssuer = $validation['card_issuer'] ?? null;
        $cardBrand = $validation['card_brand'] ?? null;
        $riskLevel = $validation['risk_level'] ?? null;

        $updateTransaction = $conn->prepare(
            "UPDATE tbl_payment_transactions
             SET status = ?, val_id = ?, bank_tran_id = ?, card_type = ?, card_issuer = ?, card_brand = ?,
                 risk_level = ?, raw_ipn_payload = ?, raw_validation_response = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $updateTransaction->bind_param(
            "sssssssssi",
            $newTransactionStatus,
            $valId,
            $bankTranId,
            $cardType,
            $cardIssuer,
            $cardBrand,
            $riskLevel,
            $rawPayload,
            $rawValidation,
            $transaction['id']
        );
        $updateTransaction->execute();
        $updateTransaction->close();

        if ($newTransactionStatus === 'Paid') {
            $orderId = (int)$transaction['order_id'];
            $paidAmount = (float)$transaction['amount'];
            $updateOrder = $conn->prepare(
                "UPDATE tbl_orders
                 SET payment_status = 'Paid', paid_amount = ?, due_amount = 0
                 WHERE id = ?"
            );
            $updateOrder->bind_param("di", $paidAmount, $orderId);
            $updateOrder->execute();
            $updateOrder->close();
        }

        $conn->commit();
        return [
            'paid' => $newTransactionStatus === 'Paid',
            'order_id' => (int)$transaction['order_id'],
            'changed' => $newTransactionStatus === 'Paid' && !$alreadyPaid,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('SSLCOMMERZ validation failed: ' . $e->getMessage());
        return ['paid' => false, 'order_id' => null, 'changed' => false];
    }
}

function sslcommerz_mark_unpaid_terminal(mysqli $conn, string $tranId, string $status, string $reason): ?int
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT pt.id, pt.order_id, o.payment_status
             FROM tbl_payment_transactions pt
             JOIN tbl_orders o ON o.id = pt.order_id
             WHERE pt.transaction_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->bind_param("s", $tranId);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$transaction || $transaction['payment_status'] === 'Paid') {
            $conn->commit();
            return $transaction ? (int)$transaction['order_id'] : null;
        }

        $rawPayload = json_encode($_POST ?: $_GET);
        $updateTransaction = $conn->prepare(
            "UPDATE tbl_payment_transactions
             SET status = ?, raw_ipn_payload = ?, updated_at = NOW()
             WHERE id = ? AND status <> 'Paid'"
        );
        $updateTransaction->bind_param("ssi", $status, $rawPayload, $transaction['id']);
        $updateTransaction->execute();
        $updateTransaction->close();

        $orderStatus = $status === 'Cancelled' ? 'Gateway Cancelled' : 'Gateway Failed';
        $orderId = (int)$transaction['order_id'];
        $updateOrder = $conn->prepare(
            "UPDATE tbl_orders
             SET payment_status = ?
             WHERE id = ? AND payment_status <> 'Paid'"
        );
        $updateOrder->bind_param("si", $orderStatus, $orderId);
        $updateOrder->execute();
        $updateOrder->close();

        sslcommerz_restore_order_stock($conn, $orderId, $reason);
        $conn->commit();
        return $orderId;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('SSLCOMMERZ terminal update failed: ' . $e->getMessage());
        return null;
    }
}

function sslcommerz_restore_order_stock(mysqli $conn, int $orderId, string $reason): void
{
    $stmt = $conn->prepare("SELECT id, stock_restored FROM tbl_orders WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || (int)$order['stock_restored'] === 1) {
        return;
    }

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

        record_inventory_adjustment($conn, $productId, 'payment_failed', $quantity, $stockAfter, $reason, $orderId, null, null);
    }
    $itemsStmt->close();

    $update = $conn->prepare("UPDATE tbl_orders SET status = 'Cancelled', cancelled_at = COALESCE(cancelled_at, NOW()), stock_restored = 1 WHERE id = ?");
    $update->bind_param("i", $orderId);
    $update->execute();
    $update->close();
}

function sslcommerz_post_form(string $url, array $payload): array
{
    return sslcommerz_http_json($url, [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($payload),
    ]);
}

function sslcommerz_get_json(string $url): array
{
    return sslcommerz_http_json($url, ['method' => 'GET']);
}

function sslcommerz_http_json(string $url, array $options): array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        if (($options['method'] ?? 'GET') === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $options['content'] ?? '');
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }
        $body = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . $error);
        }
    } else {
        $context = stream_context_create(['http' => $options]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('HTTP request failed.');
        }
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON response from SSLCOMMERZ.');
    }

    return $decoded;
}
?>
