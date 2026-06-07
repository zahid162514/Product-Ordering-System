<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager']);

$allowedStatuses = ['Pending', 'Confirmed', 'Processing', 'Delivered', 'Cancelled'];
$allowedPaymentStatuses = ['Unpaid', 'Gateway Pending', 'Gateway Failed', 'Gateway Cancelled', 'Pending Review', 'Paid', 'Partially Paid', 'Refunded', 'Refund Pending'];
$successMessage = "";
$errorMessage = "";

function orders_scalar_query(mysqli $conn, string $sql)
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? reset($row) : 0;
}

/*
|--------------------------------------------------------------------------
| Update Order Status
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    require_valid_csrf();

    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        $errorMessage = "Invalid order status request.";
    } else {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT id, status, stock_restored FROM tbl_orders WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new RuntimeException("Order not found.");
            }

            $oldIsCancelled = $order['status'] === 'Cancelled';

            if ($oldIsCancelled && $newStatus !== 'Cancelled') {
                throw new RuntimeException("Cancelled orders cannot be reopened in this version.");
            }

            $stockRestored = (int)$order['stock_restored'];

            if ($newStatus === 'Cancelled' && !$oldIsCancelled && $stockRestored !== 1) {
                $itemsStmt = $conn->prepare(
                    "SELECT product_id, quantity 
                     FROM tbl_order_items 
                     WHERE order_id = ? AND product_id IS NOT NULL"
                );
                $itemsStmt->bind_param("i", $orderId);
                $itemsStmt->execute();
                $items = $itemsStmt->get_result();

                while ($item = $items->fetch_assoc()) {
                    $quantity = (int)$item['quantity'];
                    $productId = (int)$item['product_id'];

                    $stockStmt = $conn->prepare(
                        "UPDATE tbl_product 
                         SET stock_quantity = stock_quantity + ? 
                         WHERE product_id = ?"
                    );
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
                        'admin_cancel',
                        $quantity,
                        $stockAfter,
                        'Stock returned after admin cancellation.',
                        $orderId,
                        (int)($_SESSION['admin_id'] ?? 0),
                        null
                    );
                }

                $itemsStmt->close();
                $stockRestored = 1;
            }

            $cancelledAtSql = $newStatus === 'Cancelled'
                ? "cancelled_at = COALESCE(cancelled_at, NOW()),"
                : "";

            $updateSql = "UPDATE tbl_orders 
                          SET status = ?, $cancelledAtSql stock_restored = ? 
                          WHERE id = ?";

            $update = $conn->prepare($updateSql);
            $update->bind_param("sii", $newStatus, $stockRestored, $orderId);
            $update->execute();
            $update->close();

            if ($order['status'] !== $newStatus) {
                record_order_status_history(
                    $conn,
                    $orderId,
                    $order['status'],
                    $newStatus,
                    'Status updated from admin order management.',
                    (int)($_SESSION['admin_id'] ?? 0),
                    null
                );
            }

            $conn->commit();
            notify_customer_order($conn, $orderId, 'SmartStock order status updated', 'Your order status is now ' . $newStatus . '.');
            $successMessage = "Order status updated successfully.";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("Grouped order status update failed: " . $e->getMessage());
            $errorMessage = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fulfillment'])) {
    require_valid_csrf();

    $orderId = intval($_POST['order_id'] ?? 0);
    $expectedDeliveryDateInput = trim($_POST['expected_delivery_date'] ?? '');
    $courierName = trim($_POST['courier_name'] ?? '');
    $trackingNumber = trim($_POST['tracking_number'] ?? '');
    $paymentStatus = trim($_POST['payment_status'] ?? '');
    $paidAmount = normalize_money($_POST['paid_amount'] ?? 0);

    if ($orderId <= 0 || !in_array($paymentStatus, $allowedPaymentStatuses, true)) {
        $errorMessage = "Invalid fulfillment or payment request.";
    } elseif ($expectedDeliveryDateInput !== '' && !DateTime::createFromFormat('Y-m-d', $expectedDeliveryDateInput)) {
        $errorMessage = "Expected delivery date must be a valid date.";
    } else {
        try {
            $orderStmt = $conn->prepare("SELECT total_amount FROM tbl_orders WHERE id = ? LIMIT 1");
            $orderStmt->bind_param("i", $orderId);
            $orderStmt->execute();
            $order = $orderStmt->get_result()->fetch_assoc();
            $orderStmt->close();

            if (!$order) {
                throw new RuntimeException("Order not found.");
            }

            $paidAmount = min($paidAmount, (float)$order['total_amount']);
            $dueAmount = normalize_money((float)$order['total_amount'] - $paidAmount);
            if ($paymentStatus === 'Paid') {
                $paidAmount = (float)$order['total_amount'];
                $dueAmount = 0.0;
            } elseif ($paidAmount > 0 && $paymentStatus === 'Unpaid') {
                $paymentStatus = 'Partially Paid';
            }

            $expectedDeliveryDate = $expectedDeliveryDateInput !== '' ? $expectedDeliveryDateInput : null;
            $update = $conn->prepare(
                "UPDATE tbl_orders
                 SET expected_delivery_date = ?, courier_name = ?, tracking_number = ?,
                     payment_status = ?, paid_amount = ?, due_amount = ?
                 WHERE id = ?"
            );
            $update->bind_param(
                "ssssddi",
                $expectedDeliveryDate,
                $courierName,
                $trackingNumber,
                $paymentStatus,
                $paidAmount,
                $dueAmount,
                $orderId
            );
            $update->execute();
            $update->close();

            notify_customer_order($conn, $orderId, 'SmartStock order details updated', 'Your payment or delivery details have been updated.');
            $successMessage = "Fulfillment and payment details updated.";
        } catch (Throwable $e) {
            error_log("Fulfillment update failed: " . $e->getMessage());
            $errorMessage = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types = '';

if (in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = "o.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($search !== '') {
    if (ctype_digit($search)) {
        $where[] = "(o.id = ? OR c.customer_name LIKE ? OR c.customer_email LIKE ?)";
        $params[] = (int)$search;
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'iss';
    } else {
        $where[] = "(c.customer_name LIKE ? OR c.customer_email LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'ss';
    }
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
$countSql = "SELECT COUNT(DISTINCT o.id) AS total
             FROM tbl_orders o
             LEFT JOIN customer_registration c ON o.customer_id = c.customer_id
             $whereSql";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countParams = $params;
    bind_dynamic_params($countStmt, $types, $countParams);
}
$countStmt->execute();
$totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$pagination = pagination_values($totalRows, (int)($_GET['page'] ?? 1), 12);
$limit = $pagination['per_page'];
$offset = $pagination['offset'];

$summary = [
    'total' => (int) orders_scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders"),
    'pending' => (int) orders_scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders WHERE status = 'Pending'"),
    'processing' => (int) orders_scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders WHERE status = 'Processing'"),
    'delivered' => (int) orders_scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders WHERE status = 'Delivered'"),
    'cancelled' => (int) orders_scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders WHERE status = 'Cancelled'"),
    'sales' => (float) orders_scalar_query($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM tbl_orders WHERE status IN ('Confirmed', 'Delivered')"),
];

$summaryCards = [
    [
        'label' => 'Total Orders',
        'value' => $summary['total'],
        'note' => 'All grouped orders',
        'tone' => 'primary',
    ],
    [
        'label' => 'Pending',
        'value' => $summary['pending'],
        'note' => 'Awaiting review',
        'tone' => 'warning',
    ],
    [
        'label' => 'Processing',
        'value' => $summary['processing'],
        'note' => 'Currently being handled',
        'tone' => 'info',
    ],
    [
        'label' => 'Sales Revenue',
        'value' => format_bdt($summary['sales']),
        'note' => 'Confirmed or delivered',
        'tone' => 'success',
    ],
];

$sql = "SELECT
            o.id,
            o.total_amount,
            o.payment_status,
            o.paid_amount,
            o.due_amount,
            o.expected_delivery_date,
            o.courier_name,
            o.tracking_number,
            o.status,
            o.order_date,
            o.cancelled_at,
            (SELECT pt.transaction_id FROM tbl_payment_transactions pt WHERE pt.order_id = o.id ORDER BY pt.id DESC LIMIT 1) AS gateway_transaction_id,
            c.customer_name,
            c.customer_email,
            COUNT(oi.id) AS item_count,
            GROUP_CONCAT(CONCAT(COALESCE(oi.product_name_snapshot, 'Product'), ' x ', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS item_summary
        FROM tbl_orders o
        LEFT JOIN customer_registration c ON o.customer_id = c.customer_id
        LEFT JOIN tbl_order_items oi ON oi.order_id = o.id
        $whereSql
        GROUP BY o.id, o.total_amount, o.payment_status, o.paid_amount, o.due_amount, o.expected_delivery_date,
                 o.courier_name, o.tracking_number, o.status, o.order_date, o.cancelled_at, c.customer_name, c.customer_email
        ORDER BY o.order_date DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
bind_dynamic_params($stmt, $types, $params);

$stmt->execute();
$result = $stmt->get_result();

$hasFilters = $statusFilter !== '' || $search !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | SmartStock Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.manage-orders-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .orders-shell {
            max-width: 1240px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .orders-hero {
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

        .orders-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .orders-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .orders-subtitle {
            max-width: 740px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .orders-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .orders-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .orders-summary-card {
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
        }

        .summary-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .summary-label {
            margin: 0;
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 750;
        }

        .summary-dot {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 14px;
            font-weight: 900;
        }

        .tone-primary .summary-dot {
            background: #eff6ff;
            color: #2563eb;
        }

        .tone-warning .summary-dot {
            background: #fef3c7;
            color: #d97706;
        }

        .tone-info .summary-dot {
            background: #e0f2fe;
            color: #0284c7;
        }

        .tone-success .summary-dot {
            background: #dcfce7;
            color: #16a34a;
        }

        .summary-value {
            margin: 0;
            color: #0f172a;
            font-size: 2rem;
            font-weight: 850;
            letter-spacing: -0.045em;
        }

        .summary-note {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 0.84rem;
        }

        .orders-panel {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .orders-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .orders-panel-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .orders-panel-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .orders-filter-panel {
            margin-bottom: 24px;
        }

        .orders-filter-body {
            padding: 22px 24px;
        }

        .form-label {
            color: #0f172a;
            font-size: 0.86rem;
            font-weight: 750;
        }

        .form-control,
        .form-select {
            min-height: 44px;
            border-color: #d8e0ec;
            border-radius: 13px;
            font-size: 0.94rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12);
        }

        .orders-table {
            margin: 0;
        }

        .orders-table thead th {
            padding: 14px 12px;
            background: #f8fafc;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.72rem;
            font-weight: 850;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .orders-table tbody td {
            padding: 16px 12px;
            border-color: #eef2f7;
            vertical-align: middle;
        }

        .orders-table tbody tr:hover {
            background: #f8fafc;
        }

        .order-id-link {
            color: #2563eb;
            font-weight: 850;
            text-decoration: none;
            white-space: nowrap;
        }

        .order-id-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }

        .customer-cell {
            min-width: 165px;
        }

        .customer-name {
            color: #0f172a;
            font-weight: 850;
            line-height: 1.35;
        }

        .customer-email {
            margin-top: 4px;
            color: #64748b;
            font-size: 0.82rem;
            max-width: 190px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .items-cell {
            min-width: 220px;
            max-width: 320px;
        }

        .items-count {
            color: #0f172a;
            font-weight: 800;
        }

        .items-summary {
            margin-top: 4px;
            color: #64748b;
            font-size: 0.82rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .total-text {
            color: #0f172a;
            font-weight: 850;
            white-space: nowrap;
        }

        .date-text {
            color: #334155;
            font-weight: 650;
            white-space: nowrap;
        }

        .order-actions-cell {
            min-width: 230px;
        }

        .order-actions-wrap {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            white-space: nowrap;
        }

        .order-status-form {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .order-status-form .form-select {
            width: 122px;
            min-height: 34px;
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
            border-radius: 999px;
            font-size: 0.82rem;
        }

        .order-status-form .btn,
        .order-actions-wrap > .btn {
            min-height: 34px;
        }

        .cancelled-lock {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .orders-empty-state {
            padding: 52px 20px;
            text-align: center;
        }

        .orders-empty-state h3 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 1.12rem;
            font-weight: 850;
        }

        .orders-empty-state p {
            margin: 0;
            color: #64748b;
        }

        .btn-smartstock {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #ffffff;
            font-weight: 750;
        }

        .btn-smartstock:hover {
            border-color: #1e3a8a;
            background: #1e3a8a;
            color: #ffffff;
        }

        @media (max-width: 1199.98px) {
            .orders-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .orders-shell {
                padding: 20px 14px 48px;
            }

            .orders-hero {
                flex-direction: column;
                padding: 24px 18px;
                border-radius: 20px;
            }

            .orders-hero-actions {
                width: 100%;
            }

            .orders-hero-actions .btn {
                width: 100%;
            }

            .orders-summary-grid {
                grid-template-columns: 1fr;
            }

            .orders-panel-header {
                flex-direction: column;
                padding: 20px;
            }

            .orders-panel-header .btn {
                width: 100%;
            }

            .order-actions-wrap,
            .order-status-form {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }

            .order-actions-wrap .btn,
            .order-status-form .form-select,
            .order-status-form .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="manage-orders-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="orders-shell">
        <section class="orders-hero">
            <div>
                <span class="orders-eyebrow">Order Control</span>
                <h1 class="orders-title">Manage Orders</h1>
                <p class="orders-subtitle">
                    Review grouped customer orders, update fulfillment status, open invoice details, and safely cancel orders with stock restoration.
                </p>
            </div>

            <div class="orders-hero-actions">
                <a href="inventory.php" class="btn btn-light rounded-pill px-4">
                    Inventory
                </a>

                <a href="manage-products.php" class="btn btn-outline-light rounded-pill px-4">
                    Products
                </a>

                <a href="export.php?type=orders" class="btn btn-outline-light rounded-pill px-4">
                    Export CSV
                </a>
            </div>
        </section>

        <?php if ($successMessage): ?>
            <div class="alert alert-success shadow-sm border-0 mb-4">
                <?php echo e($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger shadow-sm border-0 mb-4">
                <?php echo e($errorMessage); ?>
            </div>
        <?php endif; ?>

        <section class="orders-summary-grid">
            <?php foreach ($summaryCards as $card): ?>
                <article class="orders-summary-card tone-<?php echo e($card['tone']); ?>">
                    <div class="summary-top">
                        <p class="summary-label"><?php echo e($card['label']); ?></p>
                        <div class="summary-dot">●</div>
                    </div>

                    <h2 class="summary-value">
                        <?php echo e((string)$card['value']); ?>
                    </h2>

                    <p class="summary-note">
                        <?php echo e($card['note']); ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </section>

        <form method="get" class="orders-panel orders-filter-panel">
            <div class="orders-panel-header">
                <div>
                    <h2>Filter Orders</h2>
                    <p>Search by order ID, customer name, or customer email.</p>
                </div>

                <?php if ($hasFilters): ?>
                    <a href="manage-order.php" class="btn btn-outline-secondary rounded-pill px-4">
                        Clear Filters
                    </a>
                <?php endif; ?>
            </div>

            <div class="orders-filter-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-6">
                        <label for="search" class="form-label">Search Orders</label>
                        <input
                            class="form-control"
                            id="search"
                            type="text"
                            name="search"
                            value="<?php echo e($search); ?>"
                            placeholder="Order ID, customer name, or email"
                        >
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <label for="status" class="form-label">Order Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All statuses</option>
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php if ($statusFilter === $status) echo 'selected'; ?>>
                                    <?php echo e($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3 d-grid">
                        <button class="btn btn-smartstock rounded-pill" type="submit">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <section class="orders-panel">
            <div class="orders-panel-header">
                <div>
                    <h2>Order List</h2>
                    <p>
                        <?php echo (int)$totalRows; ?> order(s) match the current view.
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table orders-table align-middle">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Delivery</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php $isCancelled = $row['status'] === 'Cancelled'; ?>

                                <tr>
                                    <td>
                                        <a
                                            class="order-id-link"
                                            href="order-details.php?id=<?php echo (int)$row['id']; ?>"
                                        >
                                            #<?php echo (int)$row['id']; ?>
                                        </a>
                                    </td>

                                    <td class="customer-cell">
                                        <div class="customer-name">
                                            <?php echo e($row['customer_name'] ?? 'Unknown Customer'); ?>
                                        </div>

                                        <div class="customer-email">
                                            <?php echo e($row['customer_email'] ?? 'N/A'); ?>
                                        </div>
                                    </td>

                                    <td class="items-cell">
                                        <div class="items-count">
                                            <?php echo (int)$row['item_count']; ?> item(s)
                                        </div>

                                        <div class="items-summary">
                                            <?php echo e($row['item_summary'] ?? 'No items'); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="total-text">
                                            <?php echo format_bdt($row['total_amount']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['payment_status']); ?></div>
                                        <div class="small text-secondary">Due <?php echo format_bdt($row['due_amount']); ?></div>
                                        <?php if (!empty($row['gateway_transaction_id'])): ?>
                                            <div class="small text-secondary"><?php echo e($row['gateway_transaction_id']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['courier_name'] ?: 'Not assigned'); ?></div>
                                        <div class="small text-secondary"><?php echo e($row['tracking_number'] ?: ($row['expected_delivery_date'] ?: 'TBD')); ?></div>
                                    </td>

                                    <td>
                                        <?php echo order_status_badge($row['status']); ?>
                                    </td>

                                    <td>
                                        <span class="date-text">
                                            <?php echo e(date('M d, Y', strtotime($row['order_date']))); ?>
                                        </span>
                                    </td>

                                    <td class="text-end order-actions-cell">
                                        <div class="order-actions-wrap">
                                            <a
                                                href="order-details.php?id=<?php echo (int)$row['id']; ?>"
                                                class="btn btn-sm btn-outline-secondary rounded-pill px-3"
                                            >
                                                View
                                            </a>

                                            <?php if (!$isCancelled): ?>
                                                <form method="post" class="order-status-form">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="order_id" value="<?php echo (int)$row['id']; ?>">

                                                    <select class="form-select form-select-sm" name="status" aria-label="Order status">
                                                        <?php foreach ($allowedStatuses as $status): ?>
                                                            <option value="<?php echo e($status); ?>" <?php if ($row['status'] === $status) echo 'selected'; ?>>
                                                                <?php echo e($status); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button type="submit" name="update_status" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                        Update
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="cancelled-lock">Locked</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="bg-light">
                                        <form method="post" class="row g-2 align-items-end">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="order_id" value="<?php echo (int)$row['id']; ?>">
                                            <div class="col-md-2">
                                                <label class="form-label small" for="expected-<?php echo (int)$row['id']; ?>">ETA</label>
                                                <input class="form-control form-control-sm" id="expected-<?php echo (int)$row['id']; ?>" type="date" name="expected_delivery_date" value="<?php echo e($row['expected_delivery_date'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small" for="courier-<?php echo (int)$row['id']; ?>">Courier</label>
                                                <input class="form-control form-control-sm" id="courier-<?php echo (int)$row['id']; ?>" name="courier_name" value="<?php echo e($row['courier_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small" for="tracking-<?php echo (int)$row['id']; ?>">Tracking</label>
                                                <input class="form-control form-control-sm" id="tracking-<?php echo (int)$row['id']; ?>" name="tracking_number" value="<?php echo e($row['tracking_number'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small" for="payment-<?php echo (int)$row['id']; ?>">Payment</label>
                                                <select class="form-select form-select-sm" id="payment-<?php echo (int)$row['id']; ?>" name="payment_status">
                                                    <?php foreach ($allowedPaymentStatuses as $paymentStatus): ?>
                                                        <option value="<?php echo e($paymentStatus); ?>" <?php if ($row['payment_status'] === $paymentStatus) echo 'selected'; ?>>
                                                            <?php echo e($paymentStatus); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small" for="paid-<?php echo (int)$row['id']; ?>">Paid</label>
                                                <input class="form-control form-control-sm" id="paid-<?php echo (int)$row['id']; ?>" type="number" step="0.01" min="0" name="paid_amount" value="<?php echo e($row['paid_amount']); ?>">
                                            </div>
                                            <div class="col-md-2 d-grid">
                                                <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit" name="update_fulfillment">Save Details</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="orders-empty-state">
                                        <h3>No orders found</h3>
                                        <p>No grouped orders match the current filter selection.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
                $paginationQuery = $_GET;
                unset($paginationQuery['page']);
                echo render_pagination('manage-order.php', $pagination['page'], $pagination['total_pages'], $paginationQuery);
            ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
