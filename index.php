
<?php
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$flashMessages = pull_flash_messages('shop_flash');

$featured = $conn->query(
    "SELECT p.*, c.title AS category_name
     FROM tbl_product p
     LEFT JOIN tbl_category c ON c.id = p.category_id
     WHERE p.active = 'Yes'
     ORDER BY p.featured DESC, p.product_id DESC
     LIMIT 8"
);

$categories = $conn->query(
    "SELECT c.id, c.title, c.image_name, COUNT(p.product_id) AS product_count
     FROM tbl_category c
     LEFT JOIN tbl_product p ON p.category_id = c.id AND p.active = 'Yes'
     WHERE c.active = 'Yes'
     GROUP BY c.id, c.title, c.image_name
     ORDER BY c.featured DESC, c.title ASC
     LIMIT 6"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartStock | Laobaan Bangladesh LTD.</title>
    <meta name="description" content="SmartStock ordering portal for Laobaan Bangladesh LTD.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="shop-page smartstock-home">
    <?php require_once __DIR__ . '/includes/public/navbar.php'; ?>

    <section class="home-hero-clean">
        <div class="container-xl">
            <div class="home-hero-panel">
                <div class="home-hero-content">
                    <span class="home-eyebrow">Laobaan Bangladesh LTD.</span>

                    <h1 class="home-hero-title">
                        Simple product ordering for business customers.
                    </h1>

                    <p class="home-hero-subtitle">
                        Browse available products, select quantities, and place orders through a clean customer portal.
                    </p>

                    <div class="home-hero-actions">
                        <a href="menu.php" class="btn btn-light btn-lg home-hero-btn-primary">
                            Browse Products
                        </a>

                        <a href="cart.php" class="btn btn-outline-light btn-lg home-hero-btn-secondary">
                            View Cart
                        </a>

                        <a href="admin/contact.php" class="btn btn-outline-light btn-lg home-hero-btn-secondary">
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main class="container-xl home-page-shell">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?> shadow-sm border-0 mb-4">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endforeach; ?>

        <section class="home-section-block">
            <div class="home-section-heading">
                <div>
                    <span class="home-section-kicker">Catalog</span>
                    <h2>Featured Products</h2>
                    <p>Select quantity and add items directly to your cart.</p>
                </div>

                <a href="menu.php" class="btn btn-outline-primary home-section-btn">
                    View All Products
                </a>
            </div>

            <?php if ($featured && $featured->num_rows > 0): ?>
                <div class="catalog-product-grid">
                    <?php while ($product = $featured->fetch_assoc()): ?>
                        <?php
                            $productId = (int)$product['product_id'];
                            $stock = max(0, (int)$product['stock_quantity']);
                            $description = trim((string)($product['description'] ?? ''));
                            $shortDescription = strlen($description) > 74
                                ? substr($description, 0, 71) . '...'
                                : $description;
                        ?>

                        <article class="catalog-product-card <?php echo $stock <= 0 ? 'is-out' : ''; ?>">
                            <div class="catalog-product-image-wrap">
                                <img
                                    src="<?php echo e(product_image_src($product['image_name'])); ?>"
                                    alt="<?php echo e($product['title']); ?>"
                                    class="catalog-product-image"
                                    loading="lazy"
                                    decoding="async"
                                >

                                <div class="catalog-product-status">
                                    <?php if (($product['featured'] ?? '') === 'Yes'): ?>
                                        <span class="badge rounded-pill text-bg-primary">Featured</span>
                                    <?php endif; ?>

                                    <?php echo stock_badge($stock); ?>
                                </div>
                            </div>

                            <div class="catalog-product-content">
                                <div class="catalog-product-category">
                                    <?php echo e(!empty($product['category_name']) ? $product['category_name'] : 'General'); ?>
                                </div>

                                <h3 class="catalog-product-title">
                                    <?php echo e($product['title']); ?>
                                </h3>

                                <p class="catalog-product-desc">
                                    <?php echo e($shortDescription !== '' ? $shortDescription : 'Product details are available during ordering.'); ?>
                                </p>

                                <div class="catalog-product-price-row">
                                    <span class="catalog-product-price">
                                        <?php echo format_bdt($product['price']); ?>
                                    </span>

                                    <?php if (!empty($product['original_price']) && (float)$product['original_price'] > (float)$product['price']): ?>
                                        <span class="catalog-product-old-price">
                                            <?php echo format_bdt($product['original_price']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <form method="post" action="cart.php" class="catalog-product-form">
                                    <?php echo csrf_field(); ?>

                                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                    <input type="hidden" name="redirect_to" value="index.php">

                                    <?php if ($stock > 0): ?>
                                        <div class="compact-quantity-box" data-qty-group>
                                            <div class="compact-quantity-top">
                                                <label for="qty-<?php echo $productId; ?>">Quantity</label>
                                                <span><?php echo $stock; ?> available</span>
                                            </div>

                                            <div class="compact-quantity-control">
                                                <button type="button" class="qty-btn" data-qty-minus aria-label="Decrease quantity">
                                                    -
                                                </button>

                                                <input
                                                    type="number"
                                                    id="qty-<?php echo $productId; ?>"
                                                    name="quantity"
                                                    value="1"
                                                    min="1"
                                                    max="<?php echo $stock; ?>"
                                                    step="1"
                                                    inputmode="numeric"
                                                    class="form-control compact-quantity-input"
                                                    data-stock="<?php echo $stock; ?>"
                                                >

                                                <button type="button" class="qty-btn" data-qty-plus aria-label="Increase quantity">
                                                    +
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="quantity" value="1">

                                        <div class="compact-quantity-box is-disabled">
                                            <div class="compact-quantity-top">
                                                <label>Quantity</label>
                                                <span class="text-danger">Unavailable</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="catalog-product-actions">
                                        <button
                                            class="btn btn-smartstock"
                                            type="submit"
                                            name="add_to_cart"
                                            <?php if ($stock <= 0) echo 'disabled'; ?>
                                        >
                                            <?php echo $stock > 0 ? 'Add to Cart' : 'Out of Stock'; ?>
                                        </button>

                                        <?php if (!empty($_SESSION['customer_logged_in'])): ?>
                                            <button
                                                class="btn btn-outline-primary"
                                                type="submit"
                                                name="buy_now"
                                                <?php if ($stock <= 0) echo 'disabled'; ?>
                                            >
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
                </div>
            <?php else: ?>
                <div class="clean-empty-panel">
                    <h2>No featured products available</h2>
                    <p>Products will appear here after they are added and activated by the admin.</p>
                    <a href="menu.php" class="btn btn-primary rounded-pill px-4">Browse Catalog</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="home-section-block">
            <div class="home-section-heading">
                <div>
                    <span class="home-section-kicker">Categories</span>
                    <h2>Browse by Category</h2>
                    <p>Find products faster by choosing a category.</p>
                </div>
            </div>

            <?php if ($categories && $categories->num_rows > 0): ?>
                <div class="catalog-category-grid">
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <a class="catalog-category-card" href="menu.php?category=<?php echo (int)$category['id']; ?>">
                            <div class="catalog-category-image-wrap">
                                <img
                                    src="<?php echo e(product_image_src($category['image_name'])); ?>"
                                    alt="<?php echo e($category['title']); ?>"
                                    class="catalog-category-image"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </div>

                            <div>
                                <h3><?php echo e($category['title']); ?></h3>
                                <p>
                                    <?php
                                        $categoryCount = (int)$category['product_count'];
                                        echo $categoryCount . ' ' . ($categoryCount === 1 ? 'product' : 'products');
                                    ?>
                                </p>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="clean-empty-panel">
                    <h2>No active categories yet</h2>
                    <p>Categories will appear here after they are created and activated by the admin.</p>
                </div>
            <?php endif; ?>
        </section>
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
    </script>
</body>
</html>
