<?php
require_once __DIR__ . '/mailer.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stock_badge(int $quantity): string
{
    if ($quantity <= 0) {
        return '<span class="badge text-bg-danger">Out of Stock</span>';
    }

    if ($quantity <= 10) {
        return '<span class="badge text-bg-warning">Low Stock</span>';
    }

    return '<span class="badge text-bg-success">In Stock</span>';
}

function yes_no_badge($value, string $yesLabel = 'Yes', string $noLabel = 'No'): string
{
    $isYes = strtolower((string)$value) === 'yes';
    $class = $isYes ? 'text-bg-primary' : 'text-bg-secondary';
    $label = $isYes ? $yesLabel : $noLabel;

    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function order_status_badge(string $status): string
{
    $normalized = strtolower(trim($status));
    $classes = [
        'pending' => 'text-bg-warning',
        'confirmed' => 'text-bg-primary',
        'processing' => 'text-bg-info',
        'delivered' => 'text-bg-success',
        'cancelled' => 'text-bg-danger',
        'canceled' => 'text-bg-danger',
        'cooking' => 'text-bg-info',
        'on the way' => 'text-bg-info',
    ];

    $class = $classes[$normalized] ?? 'text-bg-secondary';

    return '<span class="badge ' . $class . '">' . e($status) . '</span>';
}

function product_image_src(?string $imageName, string $assetPrefix = ''): string
{
    $imageName = trim((string)$imageName);

    if ($imageName === '') {
        return 'https://placehold.co/600x400/EFF6FF/1E3A8A?text=SmartStock';
    }

    if (filter_var($imageName, FILTER_VALIDATE_URL)) {
        return $imageName;
    }

    $relativePath = local_asset_relative_path($imageName);
    if ($relativePath !== null) {
        return $assetPrefix . implode('/', array_map('rawurlencode', explode('/', $relativePath)));
    }

    return 'https://placehold.co/600x400/EFF6FF/1E3A8A?text=SmartStock';
}

function format_bdt($amount): string
{
    return 'BDT ' . number_format((float)$amount, 2);
}

function normalize_money($amount): float
{
    return round(max(0, (float)$amount), 2);
}

function default_delivery_fee(float $subtotal): float
{
    return $subtotal >= 5000 ? 0.0 : 80.0;
}

function calculate_coupon_discount(mysqli $conn, string $couponCode, float $subtotal): array
{
    $couponCode = strtoupper(trim($couponCode));

    if ($couponCode === '') {
        return ['code' => null, 'discount' => 0.0, 'message' => ''];
    }

    $stmt = $conn->prepare(
        "SELECT code, discount_type, discount_value, min_order_amount, starts_at, ends_at, usage_limit, used_count
         FROM tbl_coupons
         WHERE code = ? AND active = 'Yes'
         LIMIT 1"
    );
    $stmt->bind_param("s", $couponCode);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$coupon) {
        return ['code' => null, 'discount' => 0.0, 'message' => 'Coupon code is not valid.'];
    }

    $now = time();
    if (!empty($coupon['starts_at']) && strtotime($coupon['starts_at']) > $now) {
        return ['code' => null, 'discount' => 0.0, 'message' => 'Coupon is not active yet.'];
    }

    if (!empty($coupon['ends_at']) && strtotime($coupon['ends_at']) < $now) {
        return ['code' => null, 'discount' => 0.0, 'message' => 'Coupon has expired.'];
    }

    if ((float)$coupon['min_order_amount'] > $subtotal) {
        return ['code' => null, 'discount' => 0.0, 'message' => 'Coupon minimum order amount was not reached.'];
    }

    if ($coupon['usage_limit'] !== null && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
        return ['code' => null, 'discount' => 0.0, 'message' => 'Coupon usage limit has been reached.'];
    }

    $discount = (float)$coupon['discount_value'];
    if ($coupon['discount_type'] === 'percentage') {
        $discount = $subtotal * ((float)$coupon['discount_value'] / 100);
    }

    return [
        'code' => $coupon['code'],
        'discount' => min($subtotal, normalize_money($discount)),
        'message' => 'Coupon applied.',
    ];
}

function record_order_status_history(
    mysqli $conn,
    int $orderId,
    ?string $oldStatus,
    string $newStatus,
    ?string $note = null,
    ?int $adminId = null,
    ?int $customerId = null
): void {
    $stmt = $conn->prepare(
        "INSERT INTO tbl_order_status_history
         (order_id, old_status, new_status, note, changed_by_admin_id, changed_by_customer_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isssii", $orderId, $oldStatus, $newStatus, $note, $adminId, $customerId);
    $stmt->execute();
    $stmt->close();
}

function record_inventory_adjustment(
    mysqli $conn,
    int $productId,
    string $type,
    int $quantityChange,
    ?int $stockAfter,
    string $reason,
    ?int $orderId = null,
    ?int $adminId = null,
    ?int $customerId = null
): void {
    $variantId = null;
    $stmt = $conn->prepare(
        "INSERT INTO tbl_inventory_adjustments
         (product_id, variant_id, adjustment_type, quantity_change, stock_after, reason, related_order_id, admin_id, customer_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iisiisiii",
        $productId,
        $variantId,
        $type,
        $quantityChange,
        $stockAfter,
        $reason,
        $orderId,
        $adminId,
        $customerId
    );
    $stmt->execute();
    $stmt->close();
}

function send_smartstock_mail(string $to, string $subject, string $body, ?string $htmlBody = null): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $sent = smartstock_send_email($to, $subject, $body, $htmlBody);

    if (!$sent) {
        error_log("SmartStock mail failed: " . $subject . " to " . $to);
    }

    return $sent;
}

function notify_customer_order(mysqli $conn, int $orderId, string $subject, string $message): void
{
    $stmt = $conn->prepare(
        "SELECT c.customer_email, c.customer_name, o.total_amount, o.status, o.payment_status
         FROM tbl_orders o
         JOIN customer_registration c ON c.customer_id = o.customer_id
         WHERE o.id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        return;
    }

    $body = "Hello " . ($customer['customer_name'] ?? 'Customer') . ",\n\n"
        . $message . "\n\n"
        . "Order: #" . $orderId . "\n"
        . "Order status: " . ($customer['status'] ?? 'Pending') . "\n"
        . "Payment status: " . ($customer['payment_status'] ?? 'Unpaid') . "\n"
        . "Total: " . format_bdt($customer['total_amount'] ?? 0) . "\n\n"
        . "SmartStock";

    $html = smartstock_email_template(
        $subject,
        "Hello " . e($customer['customer_name'] ?? 'Customer') . ",",
        [
            e($message),
            'Order: #' . (int)$orderId,
            'Order status: ' . e($customer['status'] ?? 'Pending'),
            'Payment status: ' . e($customer['payment_status'] ?? 'Unpaid'),
            'Total: ' . e(format_bdt($customer['total_amount'] ?? 0)),
        ]
    );

    send_smartstock_mail($customer['customer_email'], $subject, $body, $html);
}

function smartstock_email_template(string $title, string $greeting, array $lines): string
{
    $items = '';
    foreach ($lines as $line) {
        $items .= '<p style="margin:0 0 12px;color:#334155;line-height:1.55;">' . $line . '</p>';
    }

    return '<!doctype html><html><body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;">'
        . '<div style="max-width:620px;margin:0 auto;padding:28px;">'
        . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;">'
        . '<h1 style="margin:0 0 16px;color:#0f172a;font-size:22px;">' . e($title) . '</h1>'
        . '<p style="margin:0 0 12px;color:#334155;line-height:1.55;">' . $greeting . '</p>'
        . $items
        . '<p style="margin:22px 0 0;color:#64748b;font-size:13px;">SmartStock</p>'
        . '</div></div></body></html>';
}

function notify_low_stock(mysqli $conn, int $productId): void
{
    $stmt = $conn->prepare(
        "SELECT title, stock_quantity, reorder_level
         FROM tbl_product
         WHERE product_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product || (int)$product['stock_quantity'] > (int)$product['reorder_level']) {
        return;
    }

    $admins = $conn->query("SELECT email FROM tbl_admin WHERE email IS NOT NULL AND email <> ''");
    while ($admin = $admins->fetch_assoc()) {
        send_smartstock_mail(
            $admin['email'],
            'SmartStock low-stock alert',
            "Product " . $product['title'] . " has " . (int)$product['stock_quantity'] . " unit(s), at or below reorder level " . (int)$product['reorder_level'] . "."
        );
    }
}

function pagination_values(int $totalRows, int $page, int $perPage = 15): array
{
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min(max(1, $page), $totalPages);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'offset' => ($page - 1) * $perPage,
    ];
}

function render_pagination(string $baseUrl, int $page, int $totalPages, array $query = []): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav class="p-3"><ul class="pagination justify-content-end mb-0">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $query['page'] = $i;
        $url = $baseUrl . '?' . http_build_query($query);
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($url) . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';

    return $html;
}

function stream_csv_download(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($params === []) {
        return;
    }

    $refs = [$types];
    foreach ($params as &$param) {
        $refs[] = &$param;
    }

    $stmt->bind_param(...$refs);
}

function push_flash_message(string $key, string $type, string $message): void
{
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    $_SESSION[$key][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flash_messages(string $key): array
{
    $messages = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);

    return is_array($messages) ? $messages : [];
}

function safe_local_path(string $requested, array $allowed, string $fallback): string
{
    $requested = trim($requested);
    if ($requested === '' || preg_match('#^[a-z]+://#i', $requested)) {
        return $fallback;
    }

    $path = parse_url($requested, PHP_URL_PATH) ?? '';
    $query = parse_url($requested, PHP_URL_QUERY) ?? '';

    if ($path === '') {
        $path = $requested;
    }

    $path = str_replace('\\', '/', $path);
    $segments = array_values(array_filter(explode('/', $path)));
    $filename = $segments ? end($segments) : $path;

    if (!in_array($filename, $allowed, true)) {
        return $fallback;
    }

    return $query !== '' ? $filename . '?' . $query : $filename;
}

function local_asset_relative_path(?string $path): ?string
{
    $path = trim((string)$path);

    if ($path === '' || preg_match('#^[a-z]+://#i', $path)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '' || str_contains($normalized, '../')) {
        return null;
    }

    if (str_contains($normalized, '/')) {
        return $normalized;
    }

    $projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    $candidates = [
        'uploads/products/' . $normalized,
        'assets/images/' . $normalized,
    ];

    foreach ($candidates as $candidate) {
        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (file_exists($absolutePath)) {
            return $candidate;
        }
    }

    return 'assets/images/' . $normalized;
}

function local_asset_absolute_path(?string $path): ?string
{
    $relativePath = local_asset_relative_path($path);
    if ($relativePath === null) {
        return null;
    }

    $projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $absoluteDir = realpath(dirname($absolutePath));

    if ($absoluteDir === false || strpos($absoluteDir, $projectRoot) !== 0) {
        return null;
    }

    return file_exists($absolutePath) ? $absolutePath : null;
}
?>
