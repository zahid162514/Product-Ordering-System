<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

$type = trim($_GET['type'] ?? '');
$rows = [];

if ($type === 'products') {
    require_admin_role(['manager', 'inventory']);
    $result = $conn->query(
        "SELECT p.product_id, p.sku, p.title, c.title AS category, p.price, p.original_price,
                p.stock_quantity, p.reorder_level, p.featured, p.active, p.created_at
         FROM tbl_product p
         LEFT JOIN tbl_category c ON c.id = p.category_id
         ORDER BY p.product_id DESC"
    );
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    stream_csv_download('smartstock-products.csv', ['ID', 'SKU', 'Title', 'Category', 'Price', 'Original Price', 'Stock', 'Reorder Level', 'Featured', 'Active', 'Created'], $rows);
}

if ($type === 'orders') {
    require_admin_role(['manager']);
    $result = $conn->query(
        "SELECT o.id, c.customer_name, c.customer_email, o.status, o.payment_method, o.payment_status,
                o.subtotal_amount, o.discount_amount, o.delivery_fee, o.total_amount, o.paid_amount,
                o.due_amount, o.courier_name, o.tracking_number, o.expected_delivery_date, o.order_date
         FROM tbl_orders o
         LEFT JOIN customer_registration c ON c.customer_id = o.customer_id
         ORDER BY o.order_date DESC"
    );
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    stream_csv_download('smartstock-orders.csv', ['Order ID', 'Customer', 'Email', 'Status', 'Payment Method', 'Payment Status', 'Subtotal', 'Discount', 'Delivery Fee', 'Total', 'Paid', 'Due', 'Courier', 'Tracking', 'Expected Delivery', 'Order Date'], $rows);
}

if ($type === 'customers') {
    require_admin_role(['manager', 'support']);
    $result = $conn->query(
        "SELECT customer_id, customer_name, company_name, phone, customer_email, customer_address, city, registration_date
         FROM customer_registration
         ORDER BY registration_date DESC"
    );
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    stream_csv_download('smartstock-customers.csv', ['ID', 'Name', 'Company', 'Phone', 'Email', 'Address', 'City', 'Registered'], $rows);
}

if ($type === 'inventory') {
    require_admin_role(['manager', 'inventory']);
    $result = $conn->query(
        "SELECT ia.id, p.title, p.sku, ia.adjustment_type, ia.quantity_change, ia.stock_after,
                ia.reason, ia.related_order_id, a.username AS admin_username, ia.created_at
         FROM tbl_inventory_adjustments ia
         LEFT JOIN tbl_product p ON p.product_id = ia.product_id
         LEFT JOIN tbl_admin a ON a.id = ia.admin_id
         ORDER BY ia.created_at DESC"
    );
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    stream_csv_download('smartstock-inventory-ledger.csv', ['ID', 'Product', 'SKU', 'Type', 'Quantity Change', 'Stock After', 'Reason', 'Order ID', 'Admin', 'Created'], $rows);
}

if ($type === 'support') {
    require_admin_role(['manager', 'support']);
    $result = $conn->query(
        "SELECT 'contact' AS source, id, name, email, NULL AS subject, status, message, admin_notes, reply_message, submitted_at AS created_at
         FROM contact_messages
         UNION ALL
         SELECT 'customer' AS source, id, name, email, subject, status, message, admin_notes, reply_message, created_at
         FROM customers_sms
         ORDER BY created_at DESC"
    );
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    stream_csv_download('smartstock-support.csv', ['Source', 'ID', 'Name', 'Email', 'Subject', 'Status', 'Message', 'Admin Notes', 'Reply', 'Created'], $rows);
}

http_response_code(404);
exit('Unknown export type.');
