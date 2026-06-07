
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

$successMessage = "";
$errorMessage = "";

function inventory_scalar_query(mysqli $conn, string $sql)
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? reset($row) : 0;
}

$stockFilter = trim($_GET['stock'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    require_valid_csrf();

    $productId = intval($_POST['product_id'] ?? 0);
    $quantityChange = intval($_POST['quantity_change'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $type = trim($_POST['adjustment_type'] ?? 'manual_adjustment');

    if ($productId <= 0 || $quantityChange === 0 || $reason === '') {
        $errorMessage = "Product, non-zero quantity change, and reason are required.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT product_id, stock_quantity FROM tbl_product WHERE product_id = ? FOR UPDATE");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                throw new RuntimeException("Product not found.");
            }

            $newStock = max(0, (int)$product['stock_quantity'] + $quantityChange);
            $actualChange = $newStock - (int)$product['stock_quantity'];
            $update = $conn->prepare("UPDATE tbl_product SET stock_quantity = ? WHERE product_id = ?");
            $update->bind_param("ii", $newStock, $productId);
            $update->execute();
            $update->close();

            record_inventory_adjustment(
                $conn,
                $productId,
                $type,
                $actualChange,
                $newStock,
                $reason,
                null,
                (int)($_SESSION['admin_id'] ?? 0),
                null
            );

            $conn->commit();
            notify_low_stock($conn, $productId);
            $successMessage = "Inventory adjustment saved.";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("Inventory adjustment failed: " . $e->getMessage());
            $errorMessage = $e->getMessage();
        }
    }
}

$where = '';

if ($stockFilter === 'low') {
    $where = "WHERE p.stock_quantity > 0 AND p.stock_quantity <= p.reorder_level";
} elseif ($stockFilter === 'out') {
    $where = "WHERE p.stock_quantity <= 0";
} elseif ($stockFilter === 'in') {
    $where = "WHERE p.stock_quantity > p.reorder_level";
}

$summary = [
    'total' => (int) inventory_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product"),
    'healthy' => (int) inventory_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE stock_quantity > reorder_level"),
    'low' => (int) inventory_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE stock_quantity > 0 AND stock_quantity <= reorder_level"),
    'out' => (int) inventory_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE stock_quantity <= 0"),
];

$sql = "SELECT 
            p.product_id, 
            p.sku,
            p.title, 
            p.price, 
            p.stock_quantity, 
            p.reorder_level,
            p.active, 
            c.title AS category
        FROM tbl_product p
        LEFT JOIN tbl_category c ON p.category_id = c.id
        $where
        ORDER BY 
            CASE 
                WHEN p.stock_quantity <= 0 THEN 0
                WHEN p.stock_quantity <= p.reorder_level THEN 1
                ELSE 2
            END,
            p.stock_quantity ASC, 
            p.title ASC";

$products = $conn->query($sql);

$filterTitle = 'All Products';

if ($stockFilter === 'in') {
    $filterTitle = 'In Stock Products';
} elseif ($stockFilter === 'low') {
    $filterTitle = 'Low Stock Products';
} elseif ($stockFilter === 'out') {
    $filterTitle = 'Out of Stock Products';
}

$summaryCards = [
    [
        'label' => 'Total Products',
        'value' => $summary['total'],
        'note' => 'All catalog items',
        'tone' => 'primary',
    ],
    [
        'label' => 'Healthy Stock',
        'value' => $summary['healthy'],
        'note' => 'Above reorder level',
        'tone' => 'success',
    ],
    [
        'label' => 'Low Stock',
        'value' => $summary['low'],
        'note' => 'At or below reorder level',
        'tone' => 'warning',
    ],
    [
        'label' => 'Out of Stock',
        'value' => $summary['out'],
        'note' => 'Unavailable products',
        'tone' => 'danger',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | SmartStock Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.inventory-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .inventory-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .inventory-hero {
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

        .inventory-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .inventory-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .inventory-subtitle {
            max-width: 680px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .inventory-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .inventory-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .inventory-summary-card {
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
        }

        .inventory-summary-card .summary-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .inventory-summary-card .summary-label {
            margin: 0;
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 750;
        }

        .inventory-summary-card .summary-dot {
            width: 38px;
            height: 38px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-weight: 900;
        }

        .tone-primary .summary-dot {
            background: #eff6ff;
            color: #2563eb;
        }

        .tone-success .summary-dot {
            background: #dcfce7;
            color: #16a34a;
        }

        .tone-warning .summary-dot {
            background: #fef3c7;
            color: #d97706;
        }

        .tone-danger .summary-dot {
            background: #fee2e2;
            color: #dc2626;
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

        .inventory-panel {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .inventory-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .inventory-panel-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .inventory-panel-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .inventory-filter-panel {
            margin-bottom: 24px;
        }

        .inventory-filter-body {
            padding: 22px 24px;
        }

        .form-label {
            color: #0f172a;
            font-size: 0.86rem;
            font-weight: 750;
        }

        .form-select {
            min-height: 44px;
            border-color: #d8e0ec;
            border-radius: 13px;
            font-size: 0.94rem;
        }

        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12);
        }

        .inventory-table {
            margin: 0;
        }

        .inventory-table thead th {
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

        .inventory-table tbody td {
            padding: 16px;
            border-color: #eef2f7;
            vertical-align: middle;
        }

        .inventory-table tbody tr:hover {
            background: #f8fafc;
        }

        .product-name-cell {
            min-width: 220px;
        }

        .product-title {
            color: #0f172a;
            font-weight: 800;
            line-height: 1.35;
        }

        .product-id {
            margin-top: 4px;
            color: #64748b;
            font-size: 0.82rem;
        }

        .category-text {
            color: #334155;
            font-weight: 650;
        }

        .inventory-stock-number {
            font-size: 1.05rem;
            font-weight: 850;
            color: #0f172a;
        }

        .inventory-empty-state {
            padding: 48px 20px;
            text-align: center;
        }

        .inventory-empty-state h3 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 1.1rem;
            font-weight: 850;
        }

        .inventory-empty-state p {
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
            .inventory-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .inventory-shell {
                padding: 20px 14px 48px;
            }

            .inventory-hero {
                flex-direction: column;
                padding: 24px 18px;
                border-radius: 20px;
            }

            .inventory-hero-actions {
                width: 100%;
            }

            .inventory-hero-actions .btn {
                width: 100%;
            }

            .inventory-summary-grid {
                grid-template-columns: 1fr;
            }

            .inventory-panel-header {
                flex-direction: column;
                padding: 20px;
            }

            .inventory-panel-header .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="inventory-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="inventory-shell">
        <section class="inventory-hero">
            <div>
                <span class="inventory-eyebrow">Stock Control</span>
                <h1 class="inventory-title">Inventory</h1>
                <p class="inventory-subtitle">
                    Monitor product availability, identify low-stock items, and quickly update products that need attention.
                </p>
            </div>

            <div class="inventory-hero-actions">
                <a href="manage-products.php" class="btn btn-light rounded-pill px-4">
                    Manage Products
                </a>
                <a href="add-product.php" class="btn btn-outline-light rounded-pill px-4">
                    Add Product
                </a>
                <a href="inventory-ledger.php" class="btn btn-outline-light rounded-pill px-4">
                    Ledger
                </a>
                <a href="export.php?type=inventory" class="btn btn-outline-light rounded-pill px-4">
                    Export CSV
                </a>
            </div>
        </section>

        <?php if ($successMessage): ?>
            <div class="alert alert-success shadow-sm border-0 mb-4"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger shadow-sm border-0 mb-4"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>

        <section class="inventory-summary-grid">
            <?php foreach ($summaryCards as $card): ?>
                <article class="inventory-summary-card tone-<?php echo e($card['tone']); ?>">
                    <div class="summary-top">
                        <p class="summary-label"><?php echo e($card['label']); ?></p>
                        <div class="summary-dot">&bull;</div>
                    </div>

                    <h2 class="summary-value"><?php echo (int)$card['value']; ?></h2>
                    <p class="summary-note"><?php echo e($card['note']); ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <form method="get" class="inventory-panel inventory-filter-panel">
            <div class="inventory-panel-header">
                <div>
                    <h2>Filter Inventory</h2>
                    <p>Currently viewing: <?php echo e($filterTitle); ?></p>
                </div>

                <?php if ($stockFilter !== ''): ?>
                    <a href="inventory.php" class="btn btn-outline-secondary rounded-pill px-4">
                        Clear Filter
                    </a>
                <?php endif; ?>
            </div>

            <div class="inventory-filter-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="stock">Stock Status</label>
                        <select class="form-select" id="stock" name="stock">
                            <option value="">All products</option>
                            <option value="in" <?php if ($stockFilter === 'in') echo 'selected'; ?>>In stock</option>
                            <option value="low" <?php if ($stockFilter === 'low') echo 'selected'; ?>>Low stock</option>
                            <option value="out" <?php if ($stockFilter === 'out') echo 'selected'; ?>>Out of stock</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3 d-grid">
                        <button class="btn btn-smartstock rounded-pill" type="submit">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <section class="inventory-panel">
            <div class="inventory-panel-header">
                <div>
                    <h2><?php echo e($filterTitle); ?></h2>
                    <p>Products are sorted by stock urgency, then by product name.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table inventory-table align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Stock Status</th>
                            <th>Price</th>
                            <th>Visibility</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($products && $products->num_rows > 0): ?>
                            <?php while ($row = $products->fetch_assoc()): ?>
                                <tr>
                                    <td class="product-name-cell">
                                        <div class="product-title">
                                            <?php echo e($row['title']); ?>
                                        </div>
                                        <div class="product-id">
                                            Product ID: #<?php echo (int)$row['product_id']; ?>
                                            <?php if (!empty($row['sku'])): ?>
                                                &middot; SKU: <?php echo e($row['sku']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="category-text">
                                            <?php echo e($row['category'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="inventory-stock-number">
                                            <?php echo (int)$row['stock_quantity']; ?>
                                        </span>
                                        <div class="small text-secondary">Reorder at <?php echo (int)$row['reorder_level']; ?></div>
                                    </td>

                                    <td>
                                        <?php echo stock_badge((int)$row['stock_quantity']); ?>
                                    </td>

                                    <td class="fw-semibold">
                                        <?php echo format_bdt($row['price']); ?>
                                    </td>

                                    <td>
                                        <?php echo yes_no_badge($row['active'], 'Active', 'Inactive'); ?>
                                    </td>

                                    <td class="text-end">
                                        <form method="post" class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="product_id" value="<?php echo (int)$row['product_id']; ?>">
                                            <select class="form-select form-select-sm" name="adjustment_type" aria-label="Adjustment type">
                                                <option value="restock">Restock</option>
                                                <option value="manual_adjustment">Manual</option>
                                                <option value="damaged">Damaged</option>
                                            </select>
                                            <input class="form-control form-control-sm" type="number" name="quantity_change" placeholder="+/- qty" style="width: 95px;" required>
                                            <input class="form-control form-control-sm" name="reason" placeholder="Reason" style="width: 150px;" required>
                                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" type="submit" name="adjust_stock">
                                                Save
                                            </button>
                                            <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="edit-products.php?id=<?php echo (int)$row['product_id']; ?>">
                                                Edit
                                            </a>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="inventory-empty-state">
                                        <h3>No inventory records found</h3>
                                        <p>No products match the selected stock filter.</p>
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
