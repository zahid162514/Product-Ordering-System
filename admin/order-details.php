
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager']);

$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header("Location: manage-order.php");
    exit;
}

$sql = "SELECT
            o.id,
            o.subtotal_amount,
            o.discount_amount,
            o.delivery_fee,
            o.total_amount,
            o.status,
            o.order_date,
            o.cancelled_at,
            o.delivery_name,
            o.delivery_phone,
            o.delivery_address,
            o.delivery_city,
            o.expected_delivery_date,
            o.courier_name,
            o.tracking_number,
            o.payment_method,
            o.payment_status,
            o.paid_amount,
            o.due_amount,
            o.receipt_image,
            o.customer_note,
            (SELECT pt.transaction_id FROM tbl_payment_transactions pt WHERE pt.order_id = o.id ORDER BY pt.id DESC LIMIT 1) AS gateway_transaction_id,
            (SELECT pt.status FROM tbl_payment_transactions pt WHERE pt.order_id = o.id ORDER BY pt.id DESC LIMIT 1) AS gateway_transaction_status,
            c.customer_name,
            c.customer_email,
            c.phone,
            c.customer_address
        FROM tbl_orders o
        LEFT JOIN customer_registration c ON o.customer_id = c.customer_id
        WHERE o.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found.");
}

$itemsStmt = $conn->prepare(
    "SELECT
        oi.product_id,
        oi.product_name_snapshot,
        oi.quantity,
        oi.unit_price,
        oi.line_total,
        p.title AS current_title
     FROM tbl_order_items oi
     LEFT JOIN tbl_product p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id ASC"
);

$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$orderItems = [];
$totalQuantity = 0;

while ($item = $itemsResult->fetch_assoc()) {
    $orderItems[] = $item;
    $totalQuantity += (int)$item['quantity'];
}

$itemsStmt->close();

$historyStmt = $conn->prepare(
    "SELECT h.old_status, h.new_status, h.note, h.created_at, a.username AS admin_username, c.customer_name AS customer_name
     FROM tbl_order_status_history h
     LEFT JOIN tbl_admin a ON a.id = h.changed_by_admin_id
     LEFT JOIN customer_registration c ON c.customer_id = h.changed_by_customer_id
     WHERE h.order_id = ?
     ORDER BY h.created_at ASC"
);
$historyStmt->bind_param("i", $orderId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$statusHistory = [];
while ($history = $historyResult->fetch_assoc()) {
    $statusHistory[] = $history;
}
$historyStmt->close();

$orderDate = $order['order_date'] ? date('M d, Y h:i A', strtotime($order['order_date'])) : 'N/A';
$cancelledAt = $order['cancelled_at'] ? date('M d, Y h:i A', strtotime($order['cancelled_at'])) : 'N/A';
$customerName = $order['customer_name'] ?? 'Unknown Customer';
$customerEmail = $order['customer_email'] ?? 'N/A';
$customerPhone = $order['delivery_phone'] ?: ($order['phone'] ?? 'N/A');
$customerAddress = $order['delivery_address'] ?: ($order['customer_address'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo (int)$order['id']; ?> | SmartStock Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.order-details-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .order-details-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .order-details-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
            padding: 30px;
            border-radius: 26px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #ffffff;
            box-shadow: 0 20px 52px rgba(37, 99, 235, 0.22);
        }

        .order-details-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .order-details-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .order-details-subtitle {
            max-width: 680px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .order-details-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .invoice-card {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 26px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .invoice-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding: 28px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .invoice-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .invoice-logo {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: #2563eb;
            color: #ffffff;
            font-size: 1.15rem;
            font-weight: 900;
        }

        .invoice-brand h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .invoice-brand p {
            margin: 3px 0 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .invoice-status-box {
            text-align: right;
        }

        .invoice-status-box .invoice-id {
            margin-bottom: 8px;
            color: #0f172a;
            font-size: 1.1rem;
            font-weight: 850;
        }

        .invoice-body {
            padding: 28px;
        }

        .invoice-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .invoice-summary-card {
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            background: #f8fafc;
        }

        .invoice-summary-card span {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .invoice-summary-card strong {
            color: #0f172a;
            font-size: 1.08rem;
            font-weight: 850;
            word-break: break-word;
        }

        .invoice-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .invoice-panel {
            height: 100%;
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            background: #ffffff;
        }

        .invoice-panel h3 {
            margin: 0 0 16px;
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 850;
            letter-spacing: -0.02em;
        }

        .invoice-detail-list {
            display: grid;
            gap: 12px;
        }

        .invoice-detail-row {
            display: grid;
            grid-template-columns: 140px minmax(0, 1fr);
            gap: 14px;
            color: #334155;
            font-size: 0.92rem;
        }

        .invoice-detail-row span {
            color: #64748b;
            font-weight: 700;
        }

        .invoice-detail-row strong {
            color: #0f172a;
            font-weight: 750;
            word-break: break-word;
        }

        .invoice-section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        .invoice-section-title h3 {
            margin: 0;
            color: #0f172a;
            font-size: 1.08rem;
            font-weight: 850;
            letter-spacing: -0.02em;
        }

        .invoice-table-wrap {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            background: #ffffff;
        }

        .invoice-table {
            margin: 0;
        }

        .invoice-table thead th {
            padding: 14px 16px;
            background: #f8fafc;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.74rem;
            font-weight: 850;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .invoice-table tbody td {
            padding: 16px;
            border-color: #eef2f7;
            vertical-align: middle;
        }

        .invoice-table tfoot th {
            padding: 18px 16px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #0f172a;
            font-size: 1rem;
        }

        .item-name {
            color: #0f172a;
            font-weight: 800;
            line-height: 1.35;
        }

        .item-note {
            margin-top: 4px;
            color: #64748b;
            font-size: 0.82rem;
        }

        .invoice-total-text {
            color: #2563eb !important;
            font-size: 1.2rem !important;
            font-weight: 900 !important;
        }

        .invoice-footer-note {
            margin-top: 24px;
            padding: 18px;
            border-radius: 18px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .empty-invoice-row {
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 991.98px) {
            .order-details-hero,
            .invoice-card-header {
                flex-direction: column;
            }

            .order-details-actions {
                width: 100%;
            }

            .order-details-actions .btn {
                width: 100%;
            }

            .invoice-status-box {
                text-align: left;
            }

            .invoice-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .invoice-info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .order-details-shell {
                padding: 20px 14px 48px;
            }

            .order-details-hero,
            .invoice-card {
                border-radius: 20px;
            }

            .order-details-hero,
            .invoice-card-header,
            .invoice-body {
                padding: 22px 18px;
            }

            .invoice-summary-grid {
                grid-template-columns: 1fr;
            }

            .invoice-detail-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }

        @media print {
            body {
                background: #ffffff !important;
            }

            .no-print,
            nav,
            .navbar,
            .admin-navbar,
            .order-details-hero {
                display: none !important;
            }

            .order-details-shell {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .invoice-card {
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }

            .invoice-card-header,
            .invoice-body {
                padding: 18px 0 !important;
            }

            .invoice-summary-card,
            .invoice-panel,
            .invoice-table-wrap {
                break-inside: avoid;
            }

            .invoice-footer-note {
                background: #ffffff !important;
                border: 1px solid #e2e8f0;
            }
        }
    </style>
</head>

<body class="order-details-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="order-details-shell">
        <section class="order-details-hero no-print">
            <div>
                <span class="order-details-eyebrow">Order Management</span>
                <h1 class="order-details-title">Order Details</h1>
                <p class="order-details-subtitle">
                    Review customer information, order status, purchased items, and invoice-ready totals.
                </p>
            </div>

            <div class="order-details-actions">
                <a href="manage-order.php" class="btn btn-light rounded-pill px-4">
                    Back to Orders
                </a>

                <button type="button" class="btn btn-outline-light rounded-pill px-4" onclick="window.print()">
                    Print Invoice
                </button>

                <a href="download-invoice.php?id=<?php echo (int)$order['id']; ?>" class="btn btn-outline-light rounded-pill px-4">
                    Download PDF
                </a>
            </div>
        </section>

        <section class="invoice-card invoice-print-area">
            <div class="invoice-card-header">
                <div class="invoice-brand">
                    <div class="invoice-logo">S</div>
                    <div>
                        <h2>SmartStock</h2>
                        <p>Laobaan Bangladesh LTD.</p>
                    </div>
                </div>

                <div class="invoice-status-box">
                    <div class="invoice-id">
                        Invoice / Order #<?php echo (int)$order['id']; ?>
                    </div>

                    <?php echo order_status_badge($order['status']); ?>
                </div>
            </div>

            <div class="invoice-body">
                <div class="invoice-summary-grid">
                    <div class="invoice-summary-card">
                        <span>Order Date</span>
                        <strong><?php echo e($orderDate); ?></strong>
                    </div>

                    <div class="invoice-summary-card">
                        <span>Status</span>
                        <strong><?php echo e($order['status']); ?></strong>
                    </div>

                    <div class="invoice-summary-card">
                        <span>Total Items</span>
                        <strong><?php echo (int)$totalQuantity; ?></strong>
                    </div>

                    <div class="invoice-summary-card">
                        <span>Total Amount</span>
                        <strong><?php echo format_bdt($order['total_amount']); ?></strong>
                    </div>
                </div>

                <div class="invoice-info-grid">
                    <div class="invoice-panel">
                        <h3>Customer Information</h3>

                        <div class="invoice-detail-list">
                            <div class="invoice-detail-row">
                                <span>Name</span>
                                <strong><?php echo e($customerName); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Email</span>
                                <strong><?php echo e($customerEmail); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Phone</span>
                                <strong><?php echo e($customerPhone); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Address</span>
                                <strong><?php echo e($customerAddress); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Delivery City</span>
                                <strong><?php echo e($order['delivery_city'] ?: 'N/A'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="invoice-panel">
                        <h3>Order Summary</h3>

                        <div class="invoice-detail-list">
                            <div class="invoice-detail-row">
                                <span>Order ID</span>
                                <strong>#<?php echo (int)$order['id']; ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Order Status</span>
                                <strong><?php echo e($order['status']); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Cancelled At</span>
                                <strong><?php echo e($cancelledAt); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Expected Delivery</span>
                                <strong><?php echo e($order['expected_delivery_date'] ?: 'TBD'); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Courier</span>
                                <strong><?php echo e($order['courier_name'] ?: 'Not assigned'); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Tracking</span>
                                <strong><?php echo e($order['tracking_number'] ?: 'N/A'); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Payment</span>
                                <strong><?php echo e(($order['payment_method'] ?: 'N/A') . ' / ' . $order['payment_status']); ?></strong>
                            </div>

                            <?php if (!empty($order['gateway_transaction_id'])): ?>
                                <div class="invoice-detail-row">
                                    <span>Gateway Ref</span>
                                    <strong><?php echo e($order['gateway_transaction_id']); ?></strong>
                                </div>

                                <div class="invoice-detail-row">
                                    <span>Gateway State</span>
                                    <strong><?php echo e($order['gateway_transaction_status'] ?: 'N/A'); ?></strong>
                                </div>
                            <?php endif; ?>

                            <div class="invoice-detail-row">
                                <span>Grand Total</span>
                                <strong><?php echo format_bdt($order['total_amount']); ?></strong>
                            </div>

                            <div class="invoice-detail-row">
                                <span>Due</span>
                                <strong><?php echo format_bdt($order['due_amount']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="invoice-section-title">
                    <h3>Order Items</h3>
                    <span class="text-secondary small">
                        <?php echo count($orderItems); ?> line item(s)
                    </span>
                </div>

                <div class="table-responsive invoice-table-wrap">
                    <table class="table invoice-table align-middle">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Line Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($orderItems)): ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <?php
                                        $itemName = $item['product_name_snapshot'] ?: ($item['current_title'] ?? 'Deleted product');
                                    ?>

                                    <tr>
                                        <td>
                                            <div class="item-name">
                                                <?php echo e($itemName); ?>
                                            </div>

                                            <?php if (!empty($item['current_title']) && $item['current_title'] !== $item['product_name_snapshot']): ?>
                                                <div class="item-note">
                                                    Current name: <?php echo e($item['current_title']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($item['product_id'])): ?>
                                                <div class="item-note">
                                                    Product ID: #<?php echo (int)$item['product_id']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end">
                                            <?php echo format_bdt($item['unit_price']); ?>
                                        </td>

                                        <td class="text-end fw-semibold">
                                            <?php echo (int)$item['quantity']; ?>
                                        </td>

                                        <td class="text-end fw-bold">
                                            <?php echo format_bdt($item['line_total']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-invoice-row">
                                            No items found for this order.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Subtotal</th>
                                <th class="text-end"><?php echo format_bdt($order['subtotal_amount']); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Discount</th>
                                <th class="text-end"><?php echo format_bdt($order['discount_amount']); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Delivery Fee</th>
                                <th class="text-end"><?php echo format_bdt($order['delivery_fee']); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">
                                    Order Total
                                </th>
                                <th class="text-end invoice-total-text">
                                    <?php echo format_bdt($order['total_amount']); ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="invoice-section-title mt-4">
                    <h3>Status Timeline</h3>
                    <span class="text-secondary small"><?php echo count($statusHistory); ?> event(s)</span>
                </div>

                <div class="invoice-panel">
                    <?php if ($statusHistory): ?>
                        <div class="vstack gap-3">
                            <?php foreach ($statusHistory as $history): ?>
                                <div class="d-flex flex-wrap justify-content-between gap-3 border-bottom pb-3">
                                    <div>
                                        <strong><?php echo e($history['new_status']); ?></strong>
                                        <?php if (!empty($history['old_status'])): ?>
                                            <span class="text-secondary small">from <?php echo e($history['old_status']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($history['note'])): ?>
                                            <div class="text-secondary small"><?php echo e($history['note']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end small text-secondary">
                                        <div><?php echo e(date('M d, Y h:i A', strtotime($history['created_at']))); ?></div>
                                        <div><?php echo e($history['admin_username'] ?: ($history['customer_name'] ?: 'System')); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary mb-0">No status history has been recorded for this order yet.</p>
                    <?php endif; ?>
                </div>

                <div class="invoice-footer-note">
                    This invoice was generated from the SmartStock admin panel. Please verify payment and fulfillment status according to company procedure before final delivery.
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
