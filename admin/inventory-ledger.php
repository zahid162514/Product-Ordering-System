<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

$page = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)($conn->query("SELECT COUNT(*) AS total FROM tbl_inventory_adjustments")->fetch_assoc()['total'] ?? 0);
$pagination = pagination_values($totalRows, $page, 20);
$limit = $pagination['per_page'];
$offset = $pagination['offset'];

$stmt = $conn->prepare(
    "SELECT ia.*, p.title, p.sku, a.username AS admin_username, c.customer_name
     FROM tbl_inventory_adjustments ia
     LEFT JOIN tbl_product p ON p.product_id = ia.product_id
     LEFT JOIN tbl_admin a ON a.id = ia.admin_id
     LEFT JOIN customer_registration c ON c.customer_id = ia.customer_id
     ORDER BY ia.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Ledger | SmartStock Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container-fluid admin-shell">
        <div class="admin-page-header">
            <div>
                <span class="admin-page-eyebrow">Stock Audit</span>
                <h1 class="admin-page-title h3 mb-1">Inventory Ledger</h1>
                <p class="text-secondary mb-0">Every recorded stock change with reason, actor, and related order.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="inventory.php">Inventory</a>
                <a class="btn btn-smartstock" href="export.php?type=inventory">Export CSV</a>
            </div>
        </div>

        <div class="card admin-surface-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Change</th>
                            <th>Stock After</th>
                            <th>Reason</th>
                            <th>Actor</th>
                            <th>Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e(date('M d, Y h:i A', strtotime($row['created_at']))); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['title'] ?: 'Deleted product'); ?></div>
                                        <div class="small text-secondary"><?php echo e($row['sku'] ?: 'No SKU'); ?></div>
                                    </td>
                                    <td><?php echo e($row['adjustment_type']); ?></td>
                                    <td class="<?php echo (int)$row['quantity_change'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo (int)$row['quantity_change']; ?>
                                    </td>
                                    <td><?php echo e((string)$row['stock_after']); ?></td>
                                    <td><?php echo e($row['reason']); ?></td>
                                    <td><?php echo e($row['admin_username'] ?: ($row['customer_name'] ?: 'System')); ?></td>
                                    <td>
                                        <?php if (!empty($row['related_order_id'])): ?>
                                            <a href="order-details.php?id=<?php echo (int)$row['related_order_id']; ?>">#<?php echo (int)$row['related_order_id']; ?></a>
                                        <?php else: ?>
                                            <span class="text-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-secondary py-5">No inventory movements recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo render_pagination('inventory-ledger.php', $pagination['page'], $pagination['total_pages']); ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
