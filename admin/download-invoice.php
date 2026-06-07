<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager']);

$orderId = intval($_GET['id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order.');
}

$stmt = $conn->prepare(
    "SELECT o.*, c.customer_name, c.customer_email, c.phone, c.customer_address
     FROM tbl_orders o
     LEFT JOIN customer_registration c ON c.customer_id = o.customer_id
     WHERE o.id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemsStmt = $conn->prepare(
    "SELECT product_name_snapshot, quantity, unit_price, line_total
     FROM tbl_order_items
     WHERE order_id = ?
     ORDER BY id ASC"
);
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$items = $itemsStmt->get_result();

$lines = [
    'SmartStock - Laobaan Bangladesh LTD.',
    'Invoice / Order #' . $orderId,
    'Date: ' . date('M d, Y h:i A', strtotime($order['order_date'])),
    'Status: ' . $order['status'],
    '',
    'Customer: ' . ($order['customer_name'] ?? 'Unknown'),
    'Email: ' . ($order['customer_email'] ?? 'N/A'),
    'Phone: ' . ($order['phone'] ?? 'N/A'),
    'Delivery: ' . ($order['delivery_address'] ?: ($order['customer_address'] ?? 'N/A')),
    'Courier: ' . ($order['courier_name'] ?: 'Not assigned'),
    'Tracking: ' . ($order['tracking_number'] ?: 'N/A'),
    'Payment: ' . ($order['payment_method'] ?: 'N/A') . ' / ' . $order['payment_status'],
    '',
    'Items:',
];

while ($item = $items->fetch_assoc()) {
    $lines[] = '- ' . ($item['product_name_snapshot'] ?: 'Product')
        . ' | Qty ' . (int)$item['quantity']
        . ' | Unit ' . format_bdt($item['unit_price'])
        . ' | Total ' . format_bdt($item['line_total']);
}
$itemsStmt->close();

$lines[] = '';
$lines[] = 'Subtotal: ' . format_bdt($order['subtotal_amount']);
$lines[] = 'Discount: ' . format_bdt($order['discount_amount']);
$lines[] = 'Delivery Fee: ' . format_bdt($order['delivery_fee']);
$lines[] = 'Grand Total: ' . format_bdt($order['total_amount']);
$lines[] = 'Paid: ' . format_bdt($order['paid_amount']);
$lines[] = 'Due: ' . format_bdt($order['due_amount']);

function pdf_escape_text(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$content = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";
foreach ($lines as $line) {
    $content .= '(' . pdf_escape_text(substr($line, 0, 110)) . ") Tj\nT*\n";
}
$content .= "ET";

$objects = [];
$objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
$objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
$objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
$objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $index => $object) {
    $offsets[] = strlen($pdf);
    $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
}

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="smartstock-invoice-' . $orderId . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
