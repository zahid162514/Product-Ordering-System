<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

function manage_products_scalar_query(mysqli $conn, string $sql)
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? reset($row) : 0;
}

$search = trim($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$active_filter = trim($_GET['active'] ?? '');
$stock_filter = trim($_GET['stock'] ?? '');

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "p.title LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($category_filter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if (in_array($active_filter, ['Yes', 'No'], true)) {
    $where[] = "p.active = ?";
    $params[] = $active_filter;
    $types .= 's';
}

if ($stock_filter === 'in') {
    $where[] = "p.stock_quantity > p.reorder_level";
} elseif ($stock_filter === 'low') {
    $where[] = "p.stock_quantity > 0 AND p.stock_quantity <= p.reorder_level";
} elseif ($stock_filter === 'out') {
    $where[] = "p.stock_quantity <= 0";
}

$countSql = "SELECT COUNT(*) AS total
        FROM tbl_product p
        LEFT JOIN tbl_category c ON p.category_id = c.id";

if ($where) {
    $countSql .= " WHERE " . implode(' AND ', $where);
}

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

$sql = "SELECT p.product_id, p.sku, p.title, p.price, p.original_price, p.stock_quantity, p.reorder_level,
               p.image_name, p.featured, p.active, p.created_at, c.title AS category
        FROM tbl_product p
        LEFT JOIN tbl_category c ON p.category_id = c.id";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY p.product_id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
bind_dynamic_params($stmt, $types, $params);

$stmt->execute();
$products = $stmt->get_result();

$categories_result = $conn->query("SELECT id, title FROM tbl_category ORDER BY title ASC");
$categories = [];

while ($cat = $categories_result->fetch_assoc()) {
    $categories[] = $cat;
}

$summary = [
    'total' => (int) manage_products_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product"),
    'active' => (int) manage_products_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE active = 'Yes'"),
    'low' => (int) manage_products_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE stock_quantity > 0 AND stock_quantity <= reorder_level"),
    'out' => (int) manage_products_scalar_query($conn, "SELECT COUNT(*) FROM tbl_product WHERE stock_quantity <= 0"),
];

$hasFilters = $search !== '' || $category_filter > 0 || $active_filter !== '' || $stock_filter !== '';

$summaryCards = [
    [
        'label' => 'Total Products',
        'value' => $summary['total'],
        'note' => 'All catalog records',
        'tone' => 'primary',
    ],
    [
        'label' => 'Active Products',
        'value' => $summary['active'],
        'note' => 'Visible in customer catalog',
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
    <title>Manage Products | SmartStock Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.manage-products-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .products-shell {
            max-width: 1240px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .products-hero {
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

        .products-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .products-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .products-subtitle {
            max-width: 700px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .products-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .products-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .products-summary-card {
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

        .products-panel {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .products-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .products-panel-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .products-panel-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .products-filter-panel {
            margin-bottom: 24px;
        }

        .products-filter-body {
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

        .products-table {
            margin: 0;
            width: 100%;
        }

        .products-table thead th {
            padding: 14px 14px;
            background: #f8fafc;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.72rem;
            font-weight: 850;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .products-table tbody td {
            padding: 16px 14px;
            border-color: #eef2f7;
            vertical-align: middle;
        }

        .products-table tbody tr:hover {
            background: #f8fafc;
        }

        .product-info-cell {
            min-width: 260px;
            max-width: 360px;
        }

        .product-info-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .product-thumb {
            flex: 0 0 58px;
            width: 58px;
            height: 58px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #eff6ff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }

        .product-text-wrap {
            min-width: 0;
        }

        .product-title {
            color: #0f172a;
            font-weight: 850;
            line-height: 1.35;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-id {
            margin-top: 4px;
            color: #64748b;
            font-size: 0.82rem;
        }

        .category-pill {
            display: inline-flex;
            max-width: 170px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 0.82rem;
            font-weight: 750;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .price-cell {
            min-width: 130px;
        }

        .price-text {
            color: #0f172a;
            font-weight: 850;
            white-space: nowrap;
        }

        .old-price-text {
            display: block;
            margin-top: 4px;
            color: #94a3b8;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: line-through;
            white-space: nowrap;
        }

        .stock-cell {
            min-width: 105px;
        }

        .stock-number {
            margin-bottom: 6px;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 850;
        }

        .visibility-cell {
            min-width: 120px;
        }

        .visibility-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }

        .created-cell {
            min-width: 100px;
            color: #334155;
            font-weight: 650;
            white-space: nowrap;
        }

        .actions-cell {
            min-width: 145px;
        }

        .action-group {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            white-space: nowrap;
        }

        .action-group form {
            margin: 0;
        }

        .products-empty-state {
            padding: 52px 20px;
            text-align: center;
        }

        .products-empty-state h3 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 1.12rem;
            font-weight: 850;
        }

        .products-empty-state p {
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
            .products-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .products-shell {
                padding: 20px 14px 48px;
            }

            .products-hero {
                flex-direction: column;
                padding: 24px 18px;
                border-radius: 20px;
            }

            .products-hero-actions {
                width: 100%;
            }

            .products-hero-actions .btn {
                width: 100%;
            }

            .products-summary-grid {
                grid-template-columns: 1fr;
            }

            .products-panel-header {
                flex-direction: column;
                padding: 20px;
            }

            .products-panel-header .btn {
                width: 100%;
            }

            .product-info-cell {
                min-width: 240px;
            }

            .action-group {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }

            .action-group .btn,
            .action-group form,
            .action-group button {
                width: 100%;
            }
        }
    </style>
</head>

<body class="manage-products-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="products-shell">
        <section class="products-hero">
            <div>
                <span class="products-eyebrow">Catalog Control</span>
                <h1 class="products-title">Manage Products</h1>
                <p class="products-subtitle">
                    Search, filter, update, and maintain product visibility, pricing, stock, and catalog presentation.
                </p>
            </div>

            <div class="products-hero-actions">
                <a href="add-product.php" class="btn btn-light rounded-pill px-4">
                    Add New Product
                </a>
                <a href="inventory.php" class="btn btn-outline-light rounded-pill px-4">
                    Inventory
                </a>
                <a href="export.php?type=products" class="btn btn-outline-light rounded-pill px-4">
                    Export CSV
                </a>
            </div>
        </section>

        <section class="products-summary-grid">
            <?php foreach ($summaryCards as $card): ?>
                <article class="products-summary-card tone-<?php echo e($card['tone']); ?>">
                    <div class="summary-top">
                        <p class="summary-label"><?php echo e($card['label']); ?></p>
                        <div class="summary-dot">●</div>
                    </div>

                    <h2 class="summary-value"><?php echo (int)$card['value']; ?></h2>
                    <p class="summary-note"><?php echo e($card['note']); ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <form method="get" class="products-panel products-filter-panel">
            <div class="products-panel-header">
                <div>
                    <h2>Filter Products</h2>
                    <p>Use search and filters to quickly locate catalog items.</p>
                </div>

                <?php if ($hasFilters): ?>
                    <a href="manage-products.php" class="btn btn-outline-secondary rounded-pill px-4">
                        Clear Filters
                    </a>
                <?php endif; ?>
            </div>

            <div class="products-filter-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label for="search" class="form-label">Search Product Title</label>
                        <input
                            type="text"
                            class="form-control"
                            id="search"
                            name="search"
                            value="<?php echo e($search); ?>"
                            placeholder="Search by product name"
                        >
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <label for="category" class="form-label">Category</label>
                        <select name="category" id="category" class="form-select">
                            <option value="0">All categories</option>

                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>" <?php if ((int)$cat['id'] === $category_filter) echo 'selected'; ?>>
                                    <?php echo e($cat['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="active" class="form-label">Visibility</label>
                        <select name="active" id="active" class="form-select">
                            <option value="">All</option>
                            <option value="Yes" <?php if ($active_filter === 'Yes') echo 'selected'; ?>>Active</option>
                            <option value="No" <?php if ($active_filter === 'No') echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="stock" class="form-label">Stock</label>
                        <select name="stock" id="stock" class="form-select">
                            <option value="">All</option>
                            <option value="in" <?php if ($stock_filter === 'in') echo 'selected'; ?>>In stock</option>
                            <option value="low" <?php if ($stock_filter === 'low') echo 'selected'; ?>>Low stock</option>
                            <option value="out" <?php if ($stock_filter === 'out') echo 'selected'; ?>>Out of stock</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-1 d-grid">
                        <button class="btn btn-smartstock rounded-pill" type="submit">
                            Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <section class="products-panel">
            <div class="products-panel-header">
                <div>
                    <h2>Product List</h2>
                    <p>
                        <?php echo (int)$totalRows; ?> product(s) match the current view.
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table products-table align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Pricing</th>
                            <th>Stock</th>
                            <th>Visibility</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($products->num_rows > 0): ?>
                            <?php while ($row = $products->fetch_assoc()): ?>
                                <tr>
                                    <td class="product-info-cell">
                                        <div class="product-info-wrap">
                                            <img
                                                class="product-thumb"
                                                src="<?php echo e(product_image_src($row['image_name'], '../')); ?>"
                                                alt="<?php echo e($row['title']); ?>"
                                            >

                                            <div class="product-text-wrap">
                                                <div class="product-title" title="<?php echo e($row['title']); ?>">
                                                    <?php echo e($row['title']); ?>
                                                </div>

                                                <div class="product-id">
                                                    Product ID: #<?php echo (int)$row['product_id']; ?>
                                                    <?php if (!empty($row['sku'])): ?>
                                                        &middot; SKU: <?php echo e($row['sku']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="category-pill">
                                            <?php echo e($row['category'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>

                                    <td class="price-cell">
                                        <span class="price-text">
                                            <?php echo format_bdt($row['price']); ?>
                                        </span>

                                        <?php if ($row['original_price'] !== null && $row['original_price'] !== ''): ?>
                                            <span class="old-price-text">
                                                <?php echo format_bdt($row['original_price']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="d-block text-secondary small mt-1">No original price</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="stock-cell">
                                        <div class="stock-number">
                                            <?php echo (int)$row['stock_quantity']; ?>
                                        </div>

                                        <?php echo stock_badge((int)$row['stock_quantity']); ?>
                                        <div class="small text-secondary mt-1">
                                            Reorder at <?php echo (int)$row['reorder_level']; ?>
                                        </div>
                                    </td>

                                    <td class="visibility-cell">
                                        <div class="visibility-stack">
                                            <?php echo yes_no_badge($row['active'], 'Active', 'Inactive'); ?>
                                            <?php echo yes_no_badge($row['featured'], 'Featured', 'Normal'); ?>
                                        </div>
                                    </td>

                                    <td class="created-cell">
                                        <?php if (!empty($row['created_at'])): ?>
                                            <?php echo e(date('M d, Y', strtotime($row['created_at']))); ?>
                                        <?php else: ?>
                                            <span class="text-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end actions-cell">
                                        <div class="action-group">
                                            <a
                                                class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                                href="edit-products.php?id=<?php echo (int)$row['product_id']; ?>"
                                            >
                                                Edit
                                            </a>

                                            <a
                                                class="btn btn-sm btn-outline-secondary rounded-pill px-3"
                                                href="product-variants.php?product_id=<?php echo (int)$row['product_id']; ?>"
                                            >
                                                Variants
                                            </a>

                                            <form method="post" action="delete-product.php" onsubmit="return confirm('Delete this product?')">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?php echo (int)$row['product_id']; ?>">

                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="products-empty-state">
                                        <h3>No products found</h3>
                                        <p>No products match your current search or filter selection.</p>
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
                echo render_pagination('manage-products.php', $pagination['page'], $pagination['total_pages'], $paginationQuery);
            ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
