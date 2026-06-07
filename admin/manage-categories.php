
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

function manage_categories_scalar_query(mysqli $conn, string $sql)
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? reset($row) : 0;
}

$summary = [
    'total' => (int) manage_categories_scalar_query($conn, "SELECT COUNT(*) FROM tbl_category"),
    'active' => (int) manage_categories_scalar_query($conn, "SELECT COUNT(*) FROM tbl_category WHERE active = 'Yes'"),
    'featured' => (int) manage_categories_scalar_query($conn, "SELECT COUNT(*) FROM tbl_category WHERE featured = 'Yes'"),
    'inactive' => (int) manage_categories_scalar_query($conn, "SELECT COUNT(*) FROM tbl_category WHERE active = 'No'"),
];

$summaryCards = [
    [
        'label' => 'Total Categories',
        'value' => $summary['total'],
        'note' => 'All category records',
        'tone' => 'primary',
    ],
    [
        'label' => 'Active Categories',
        'value' => $summary['active'],
        'note' => 'Visible to customers',
        'tone' => 'success',
    ],
    [
        'label' => 'Featured Categories',
        'value' => $summary['featured'],
        'note' => 'Prioritized in catalog',
        'tone' => 'warning',
    ],
    [
        'label' => 'Inactive Categories',
        'value' => $summary['inactive'],
        'note' => 'Hidden from storefront',
        'tone' => 'danger',
    ],
];

$categories = $conn->query("SELECT id, title, image_name, featured, active, created_at FROM tbl_category ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | SmartStock Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.manage-categories-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .categories-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .categories-hero {
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

        .categories-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .categories-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .categories-subtitle {
            max-width: 700px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .categories-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .categories-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .categories-summary-card {
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

        .categories-panel {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .categories-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .categories-panel-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .categories-panel-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .categories-table {
            margin: 0;
        }

        .categories-table thead th {
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

        .categories-table tbody td {
            padding: 16px;
            border-color: #eef2f7;
            vertical-align: middle;
        }

        .categories-table tbody tr:hover {
            background: #f8fafc;
        }

        .category-thumb {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #eff6ff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }

        .category-main-cell {
            min-width: 260px;
        }

        .category-title {
            color: #0f172a;
            font-weight: 850;
            line-height: 1.35;
        }

        .category-id {
            margin-top: 4px;
            color: #64748b;
            font-size: 0.82rem;
        }

        .date-text {
            color: #334155;
            font-weight: 650;
            white-space: nowrap;
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

        .categories-empty-state {
            padding: 52px 20px;
            text-align: center;
        }

        .categories-empty-state h3 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 1.12rem;
            font-weight: 850;
        }

        .categories-empty-state p {
            margin: 0 0 18px;
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
            .categories-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .categories-shell {
                padding: 20px 14px 48px;
            }

            .categories-hero {
                flex-direction: column;
                padding: 24px 18px;
                border-radius: 20px;
            }

            .categories-hero-actions {
                width: 100%;
            }

            .categories-hero-actions .btn {
                width: 100%;
            }

            .categories-summary-grid {
                grid-template-columns: 1fr;
            }

            .categories-panel-header {
                flex-direction: column;
                padding: 20px;
            }

            .categories-panel-header .btn {
                width: 100%;
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

<body class="manage-categories-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="categories-shell">
        <section class="categories-hero">
            <div>
                <span class="categories-eyebrow">Collection Control</span>
                <h1 class="categories-title">Manage Categories</h1>
                <p class="categories-subtitle">
                    Organize products into customer-friendly collections and control which categories appear in the storefront.
                </p>
            </div>

            <div class="categories-hero-actions">
                <a href="add-category.php" class="btn btn-light rounded-pill px-4">
                    Add New Category
                </a>

                <a href="manage-products.php" class="btn btn-outline-light rounded-pill px-4">
                    Manage Products
                </a>
            </div>
        </section>

        <section class="categories-summary-grid">
            <?php foreach ($summaryCards as $card): ?>
                <article class="categories-summary-card tone-<?php echo e($card['tone']); ?>">
                    <div class="summary-top">
                        <p class="summary-label"><?php echo e($card['label']); ?></p>
                        <div class="summary-dot">●</div>
                    </div>

                    <h2 class="summary-value"><?php echo (int)$card['value']; ?></h2>
                    <p class="summary-note"><?php echo e($card['note']); ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="categories-panel">
            <div class="categories-panel-header">
                <div>
                    <h2>Category List</h2>
                    <p>
                        <?php echo $categories ? (int)$categories->num_rows : 0; ?> category record(s) found.
                    </p>
                </div>

                <a href="add-category.php" class="btn btn-smartstock rounded-pill px-4">
                    Add Category
                </a>
            </div>

            <div class="table-responsive">
                <table class="table categories-table align-middle">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Category</th>
                            <th>Featured</th>
                            <th>Visibility</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($categories && $categories->num_rows > 0): ?>
                            <?php while ($row = $categories->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <img
                                            class="category-thumb"
                                            src="<?php echo e(product_image_src($row['image_name'], '../')); ?>"
                                            alt="<?php echo e($row['title']); ?>"
                                        >
                                    </td>

                                    <td class="category-main-cell">
                                        <div class="category-title">
                                            <?php echo e($row['title']); ?>
                                        </div>

                                        <div class="category-id">
                                            Category ID: #<?php echo (int)$row['id']; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php echo yes_no_badge($row['featured'], 'Featured', 'Normal'); ?>
                                    </td>

                                    <td>
                                        <?php echo yes_no_badge($row['active'], 'Active', 'Inactive'); ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($row['created_at'])): ?>
                                            <span class="date-text">
                                                <?php echo e(date('M d, Y', strtotime($row['created_at']))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end">
                                        <div class="action-group">
                                            <a
                                                class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                                href="edit-category.php?id=<?php echo (int)$row['id']; ?>"
                                            >
                                                Edit
                                            </a>

                                            <form method="post" action="delete-category.php" onsubmit="return confirm('Delete this category?')">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

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
                                <td colspan="6">
                                    <div class="categories-empty-state">
                                        <h3>No categories found</h3>
                                        <p>Create a category to organize your product catalog.</p>

                                        <a href="add-category.php" class="btn btn-smartstock rounded-pill px-4">
                                            Add Category
                                        </a>
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
