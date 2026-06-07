<?php
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$flashMessages = pull_flash_messages('shop_flash');
$search = trim($_GET['search'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);
$returnTo = 'menu.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
$where = ["p.active = 'Yes'"];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "p.title LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($categoryFilter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

$sql = "SELECT p.*, c.title AS category_name
        FROM tbl_product p
        LEFT JOIN tbl_category c ON c.id = p.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.featured DESC, p.product_id DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    bind_dynamic_params($stmt, $types, $params);
}
$stmt->execute();
$products = $stmt->get_result();
$categoryOptions = $conn->query("SELECT id, title FROM tbl_category WHERE active = 'Yes' ORDER BY title ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/public/navbar.php'; ?>

    <section class="page-hero">
        <div class="container hero-shell">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="hero-kicker">Product Catalog</span>
                    <h1 class="hero-title">Browse current products with clear pricing and stock visibility.</h1>
                    <p class="hero-copy">Search by name, filter by category, and add available products directly to your cart.</p>
                </div>
                <div class="col-lg-5">
                    <div class="summary-panel">
                        <div class="summary-panel-title">Catalog Results</div>
                        <div class="summary-panel-value"><?php echo (int)$products->num_rows; ?></div>
                        <p class="text-secondary mb-0">
                            <?php
                                $resultCount = (int)$products->num_rows;
                                echo $resultCount . ' ' . ($resultCount === 1 ? 'product' : 'products');
                                echo $search !== '' ? ' matching "' . e($search) . '"' : ' available now';
                            ?>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main class="container page-shell">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endforeach; ?>

        <form method="get" class="surface-card search-card mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label" for="search">Search products</label>
                    <input class="form-control" id="search" type="search" name="search" value="<?php echo e($search); ?>" placeholder="Search by product name">
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="category">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0">All categories</option>
                        <?php while ($category = $categoryOptions->fetch_assoc()): ?>
                            <option value="<?php echo (int)$category['id']; ?>" <?php if ($categoryFilter === (int)$category['id']) echo 'selected'; ?>>
                                <?php echo e($category['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-2 d-grid">
                    <button class="btn btn-smartstock" type="submit">Apply</button>
                </div>
            </div>
        </form>

        <div class="catalog-product-grid">
            <?php if ($products->num_rows > 0): ?>
                <?php while ($row = $products->fetch_assoc()): ?>
                    <?php
                        $stock = max(0, (int)$row['stock_quantity']);
                        $description = trim((string)($row['description'] ?? ''));
                        $shortDescription = strlen($description) > 74
                            ? substr($description, 0, 71) . '...'
                            : $description;
                    ?>
                    <article class="catalog-product-card <?php echo $stock <= 0 ? 'is-out' : ''; ?>" data-product-card>
                        <div class="catalog-product-image-wrap">
                            <img
                                class="catalog-product-image"
                                src="<?php echo e(product_image_src($row['image_name'])); ?>"
                                alt="<?php echo e($row['title']); ?>"
                                loading="lazy"
                                decoding="async"
                            >
                            <div class="catalog-product-status">
                                <?php if (($row['featured'] ?? '') === 'Yes'): ?>
                                    <span class="badge rounded-pill text-bg-primary">Featured</span>
                                <?php endif; ?>

                                <?php echo stock_badge($stock); ?>
                            </div>
                        </div>
                        <div class="catalog-product-content">
                            <div class="catalog-product-category">
                                <?php echo e(!empty($row['category_name']) ? $row['category_name'] : 'General'); ?>
                            </div>

                            <h2 class="catalog-product-title"><?php echo e($row['title']); ?></h2>

                            <p class="catalog-product-desc">
                                <?php echo e($shortDescription !== '' ? $shortDescription : 'Product details are available during ordering.'); ?>
                            </p>

                            <div class="catalog-product-price-row">
                                <span class="catalog-product-price"><?php echo format_bdt($row['price']); ?></span>
                                <?php if (!empty($row['original_price']) && (float)$row['original_price'] > (float)$row['price']): ?>
                                    <span class="catalog-product-old-price"><?php echo format_bdt($row['original_price']); ?></span>
                                <?php endif; ?>
                            </div>

                            <form method="post" action="cart.php" class="catalog-product-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="product_id" value="<?php echo (int)$row['product_id']; ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo e($returnTo); ?>">
                                <?php if ($stock > 0): ?>
                                    <div class="compact-quantity-box menu-quantity-box" data-qty-group>
                                        <div class="compact-quantity-top">
                                            <label for="menu-qty-<?php echo (int)$row['product_id']; ?>">Quantity</label>
                                            <span><?php echo $stock; ?> available</span>
                                        </div>

                                        <div class="compact-quantity-control">
                                            <button type="button" class="qty-btn" data-qty-minus aria-label="Decrease quantity">
                                                -
                                            </button>

                                            <input
                                                class="form-control compact-quantity-input"
                                                id="menu-qty-<?php echo (int)$row['product_id']; ?>"
                                                type="number"
                                                name="quantity"
                                                value="1"
                                                min="1"
                                                max="<?php echo $stock; ?>"
                                                inputmode="numeric"
                                                data-stock="<?php echo $stock; ?>"
                                            >

                                            <button type="button" class="qty-btn" data-qty-plus aria-label="Increase quantity">
                                                +
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="quantity" value="1">
                                    <div class="compact-quantity-box is-disabled menu-quantity-box">
                                        <div class="compact-quantity-top">
                                            <label>Quantity</label>
                                            <span class="text-danger">Unavailable</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="catalog-product-actions">
                                    <button class="btn btn-smartstock" type="submit" name="add_to_cart" <?php if ($stock <= 0) echo 'disabled'; ?>>
                                        <?php echo $stock > 0 ? 'Add to Cart' : 'Out of Stock'; ?>
                                    </button>

                                    <?php if (!empty($_SESSION['customer_logged_in'])): ?>
                                        <button class="btn btn-outline-primary" type="submit" name="buy_now" <?php if ($stock <= 0) echo 'disabled'; ?>>
                                            Buy Now
                                        </button>
                                    <?php else: ?>
                                            <a class="btn btn-outline-primary" href="customer-login.php">
                                                Sign in to Buy
                                            </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h2>No products found</h2>
                    <p>Try a different search or category filter, or check back later.</p>
                    <a class="btn btn-outline-primary" href="menu.php">Reset Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/public/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clampQuantity(input) {
            const min = parseInt(input.min || '1', 10);
            const max = parseInt(input.max || input.dataset.stock || '1', 10);
            let value = parseInt(input.value || min, 10);

            if (Number.isNaN(value) || value < min) {
                value = min;
            }

            if (value > max) {
                value = max;
            }

            input.value = value;
        }

        document.querySelectorAll('[data-qty-group]').forEach(group => {
            const input = group.querySelector('.compact-quantity-input');
            const minus = group.querySelector('[data-qty-minus]');
            const plus = group.querySelector('[data-qty-plus]');

            if (!input) {
                return;
            }

            input.addEventListener('input', () => clampQuantity(input));
            input.addEventListener('blur', () => clampQuantity(input));

            if (minus) {
                minus.addEventListener('click', () => {
                    input.value = parseInt(input.value || '1', 10) - 1;
                    clampQuantity(input);
                });
            }

            if (plus) {
                plus.addEventListener('click', () => {
                    input.value = parseInt(input.value || '1', 10) + 1;
                    clampQuantity(input);
                });
            }
        });

        document.querySelectorAll('[data-product-card]').forEach(card => {
            const form = card.querySelector('form');
            const addBtn = form ? form.querySelector('button[name="add_to_cart"]') : null;
            if (!form || !addBtn) {
                return;
            }

            card.addEventListener('click', event => {
                if (event.target.closest('button, a, input, select, textarea, label')) {
                    return;
                }
                if (addBtn.disabled) {
                    return;
                }
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(addBtn);
                    return;
                }
                addBtn.click();
            });
        });
    </script>
</body>
</html>
