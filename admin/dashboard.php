<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    require_valid_csrf();
    app_destroy_session();
    header("Location: admin-login.php");
    exit;
}

function scalar_query(mysqli $conn, string $sql)
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? reset($row) : 0;
}

$stats = [
    'products' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_product"),
    'categories' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_category"),
    'customers' => (int) scalar_query($conn, "SELECT COUNT(*) FROM customer_registration"),
    'orders' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders"),
    'pending' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders WHERE status = 'Pending'"),
    'delivered' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders WHERE status = 'Delivered'"),
    'cancelled' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_orders WHERE status = 'Cancelled'"),
    'low_stock' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE stock_quantity > 0 AND stock_quantity <= reorder_level"),
    'out_stock' => (int) scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE stock_quantity <= 0"),
    'sales' => (float) scalar_query($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM tbl_orders WHERE status IN ('Confirmed', 'Delivered')"),
];

$recentOrders = $conn->query(
    "SELECT
        o.id,
        o.total_amount,
        o.status,
        o.order_date,
        c.customer_name,
        COUNT(oi.id) AS item_count,
        GROUP_CONCAT(CONCAT(COALESCE(oi.product_name_snapshot, 'Product'), ' x ', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS item_summary
     FROM tbl_orders o
     LEFT JOIN customer_registration c ON o.customer_id = c.customer_id
     LEFT JOIN tbl_order_items oi ON oi.order_id = o.id
     GROUP BY o.id, o.total_amount, o.status, o.order_date, c.customer_name
     ORDER BY o.order_date DESC
     LIMIT 6"
);

$adminName = $_SESSION['admin_username'] ?? 'Admin';

$mainCards = [
    [
        'title' => 'Sales Revenue',
        'value' => format_bdt($stats['sales']),
        'note' => 'Confirmed and delivered orders',
        'tone' => 'success',
    ],
    [
        'title' => 'Total Orders',
        'value' => $stats['orders'],
        'note' => 'Grouped customer orders',
        'tone' => 'primary',
    ],
    [
        'title' => 'Pending Orders',
        'value' => $stats['pending'],
        'note' => 'Need review or action',
        'tone' => 'warning',
    ],
    [
        'title' => 'Stock Alerts',
        'value' => $stats['low_stock'] + $stats['out_stock'],
        'note' => $stats['low_stock'] . ' low, ' . $stats['out_stock'] . ' out of stock',
        'tone' => 'danger',
    ],
];

$quickActions = [
    ['title' => 'Add Product', 'copy' => 'Create a catalog item.', 'href' => 'add-product.php'],
    ['title' => 'Manage Products', 'copy' => 'Edit products and stock.', 'href' => 'manage-products.php'],
    ['title' => 'Inventory', 'copy' => 'Review stock status.', 'href' => 'inventory.php'],
    ['title' => 'Manage Orders', 'copy' => 'Update order status.', 'href' => 'manage-order.php'],
    ['title' => 'Categories', 'copy' => 'Organize product groups.', 'href' => 'manage-categories.php'],
    ['title' => 'Support', 'copy' => 'Review messages.', 'href' => 'support.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SmartStock</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.admin-dashboard-page {
            background: #f8fafc;
            color: #0f172a;
        }

        .dashboard-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .dashboard-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
            padding: 30px;
            border-radius: 24px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            box-shadow: 0 20px 50px rgba(37, 99, 235, 0.22);
        }

        .dashboard-eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255,255,255,.75);
        }

        .dashboard-heading {
            margin: 0;
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .dashboard-lead {
            max-width: 720px;
            margin: 10px 0 0;
            color: rgba(255,255,255,.82);
            line-height: 1.7;
        }

        .dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .mini-stat-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .mini-stat {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.22);
            color: rgba(255,255,255,.86);
            font-size: 13px;
            font-weight: 600;
        }

        .mini-stat strong {
            color: #fff;
            font-weight: 800;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .kpi-card {
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .07);
        }

        .kpi-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .kpi-title {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
        }

        .kpi-dot {
            width: 38px;
            height: 38px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-weight: 900;
        }

        .tone-success .kpi-dot {
            background: #dcfce7;
            color: #16a34a;
        }

        .tone-primary .kpi-dot {
            background: #eff6ff;
            color: #2563eb;
        }

        .tone-warning .kpi-dot {
            background: #fef3c7;
            color: #d97706;
        }

        .tone-danger .kpi-dot {
            background: #fee2e2;
            color: #dc2626;
        }

        .kpi-value {
            margin: 0;
            color: #0f172a;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .kpi-note {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 24px;
            margin-bottom: 24px;
        }

        .dashboard-panel {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .07);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 20px 22px;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .panel-header p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .quick-card {
            display: block;
            min-height: 122px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #f8fafc;
            color: inherit;
            text-decoration: none;
            transition: .18s ease;
        }

        .quick-card:hover {
            transform: translateY(-3px);
            background: #fff;
            border-color: rgba(37, 99, 235, .35);
            box-shadow: 0 14px 28px rgba(15, 23, 42, .10);
        }

        .quick-card h3 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 15px;
            font-weight: 800;
        }

        .quick-card p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .stock-list {
            display: grid;
            gap: 12px;
            padding: 18px;
        }

        .stock-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #f8fafc;
        }

        .stock-row strong {
            display: block;
            color: #0f172a;
            font-size: 14px;
        }

        .stock-row span {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
        }

        .stock-number {
            min-width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 18px;
            font-weight: 900;
        }

        .recent-orders-panel {
            margin-bottom: 20px;
        }

        .dashboard-table {
            margin: 0;
        }

        .dashboard-table thead th {
            padding: 14px 16px;
            background: #f8fafc;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .dashboard-table tbody td {
            padding: 16px;
            border-color: #eef2f7;
            vertical-align: middle;
        }

        .order-link {
            color: #2563eb;
            font-weight: 800;
            text-decoration: none;
        }

        .table-main {
            color: #0f172a;
            font-weight: 700;
        }

        .table-muted {
            max-width: 420px;
            color: #64748b;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .empty-dashboard {
            padding: 34px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 1199.98px) {
            .kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 991.98px) {
            .dashboard-top {
                flex-direction: column;
            }

            .dashboard-actions {
                width: 100%;
            }

            .dashboard-actions .btn {
                width: 100%;
            }

            .quick-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .dashboard-shell {
                padding: 20px 14px 48px;
            }

            .dashboard-top {
                padding: 24px 18px;
                border-radius: 20px;
            }

            .dashboard-heading {
                font-size: 30px;
            }

            .kpi-grid,
            .quick-grid {
                grid-template-columns: 1fr;
            }

            .panel-header {
                flex-direction: column;
            }

            .panel-header .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="admin-dashboard-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="dashboard-shell">
        <section class="dashboard-top">
            <div>
                <span class="dashboard-eyebrow">Admin Overview</span>
                <h1 class="dashboard-heading">Dashboard</h1>
                <p class="dashboard-lead">
                    Welcome, <?php echo e($adminName); ?>. Monitor products, stock, orders, and customer activity from one workspace.
                </p>

                <div class="mini-stat-row">
                    <div class="mini-stat">Products <strong><?php echo (int)$stats['products']; ?></strong></div>
                    <div class="mini-stat">Categories <strong><?php echo (int)$stats['categories']; ?></strong></div>
                    <div class="mini-stat">Customers <strong><?php echo (int)$stats['customers']; ?></strong></div>
                    <div class="mini-stat">Delivered <strong><?php echo (int)$stats['delivered']; ?></strong></div>
                    <div class="mini-stat">Cancelled <strong><?php echo (int)$stats['cancelled']; ?></strong></div>
                </div>
            </div>

            <div class="dashboard-actions">
                <a href="add-product.php" class="btn btn-light rounded-pill px-4">Add Product</a>
                <a href="manage-order.php" class="btn btn-outline-light rounded-pill px-4">Manage Orders</a>
            </div>
        </section>

        <section class="kpi-grid">
            <?php foreach ($mainCards as $card): ?>
                <article class="kpi-card tone-<?php echo e($card['tone']); ?>">
                    <div class="kpi-card-top">
                        <p class="kpi-title"><?php echo e($card['title']); ?></p>
                        <div class="kpi-dot">●</div>
                    </div>

                    <h2 class="kpi-value"><?php echo e((string)$card['value']); ?></h2>
                    <p class="kpi-note"><?php echo e($card['note']); ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-panel">
                <div class="panel-header">
                    <div>
                        <h2>Quick Actions</h2>
                        <p>Frequently used admin tasks.</p>
                    </div>
                </div>

                <div class="quick-grid">
                    <?php foreach ($quickActions as $action): ?>
                        <a class="quick-card" href="<?php echo e($action['href']); ?>">
                            <h3><?php echo e($action['title']); ?></h3>
                            <p><?php echo e($action['copy']); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="dashboard-panel">
                <div class="panel-header">
                    <div>
                        <h2>Stock Attention</h2>
                        <p>Products requiring inventory review.</p>
                    </div>
                    <a href="inventory.php" class="btn btn-sm btn-outline-primary rounded-pill">View</a>
                </div>

                <div class="stock-list">
                    <div class="stock-row">
                        <div>
                            <strong>Low Stock</strong>
                            <span>Products at or below reorder level</span>
                        </div>
                        <div class="stock-number"><?php echo (int)$stats['low_stock']; ?></div>
                    </div>

                    <div class="stock-row">
                        <div>
                            <strong>Out of Stock</strong>
                            <span>Unavailable products</span>
                        </div>
                        <div class="stock-number"><?php echo (int)$stats['out_stock']; ?></div>
                    </div>

                    <div class="stock-row">
                        <div>
                            <strong>Total Products</strong>
                            <span>Products in catalog</span>
                        </div>
                        <div class="stock-number"><?php echo (int)$stats['products']; ?></div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="dashboard-panel recent-orders-panel">
            <div class="panel-header">
                <div>
                    <h2>Recent Orders</h2>
                    <p>Latest grouped customer orders.</p>
                </div>

                <a href="manage-order.php" class="btn btn-outline-primary rounded-pill px-4">View All Orders</a>
            </div>

            <div class="table-responsive">
                <table class="table dashboard-table align-middle">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>
                            <?php while ($row = $recentOrders->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a class="order-link" href="order-details.php?id=<?php echo (int)$row['id']; ?>">
                                            #<?php echo (int)$row['id']; ?>
                                        </a>
                                    </td>

                                    <td>
                                        <div class="table-main">
                                            <?php echo e($row['customer_name'] ?? 'Unknown Customer'); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="table-main"><?php echo (int)$row['item_count']; ?> item(s)</div>
                                        <div class="table-muted"><?php echo e($row['item_summary'] ?? 'No items'); ?></div>
                                    </td>

                                    <td class="text-end fw-bold">
                                        <?php echo format_bdt($row['total_amount']); ?>
                                    </td>

                                    <td>
                                        <?php echo order_status_badge($row['status']); ?>
                                    </td>

                                    <td class="table-muted">
                                        <?php echo e(date('M d, Y', strtotime($row['order_date']))); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-dashboard">
                                        No recent orders found. New customer orders will appear here after checkout.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
