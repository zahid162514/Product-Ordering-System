<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

$productId = intval($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
if ($productId <= 0) {
    header('Location: manage-products.php');
    exit;
}

$productStmt = $conn->prepare("SELECT product_id, title, sku FROM tbl_product WHERE product_id = ? LIMIT 1");
$productStmt->bind_param("i", $productId);
$productStmt->execute();
$product = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

if (!$product) {
    die('Product not found.');
}

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_variant'])) {
    require_valid_csrf();

    $sku = strtoupper(trim($_POST['sku'] ?? ''));
    $variantName = trim($_POST['variant_name'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $packSize = trim($_POST['pack_size'] ?? '');
    $stockQuantity = max(0, intval($_POST['stock_quantity'] ?? 0));
    $priceAdjustment = (float)($_POST['price_adjustment'] ?? 0);
    $active = trim($_POST['active'] ?? 'Yes');

    if ($variantName === '' || !in_array($active, ['Yes', 'No'], true)) {
        $errorMessage = "Variant name and active status are required.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO tbl_product_variants
             (product_id, sku, variant_name, size, color, pack_size, stock_quantity, price_adjustment, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssssids", $productId, $sku, $variantName, $size, $color, $packSize, $stockQuantity, $priceAdjustment, $active);
        if ($stmt->execute()) {
            $successMessage = "Variant added.";
        } else {
            $errorMessage = "Unable to add variant.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_variant'])) {
    require_valid_csrf();

    $variantId = intval($_POST['variant_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM tbl_product_variants WHERE id = ? AND product_id = ?");
    $stmt->bind_param("ii", $variantId, $productId);
    $stmt->execute();
    $stmt->close();
    $successMessage = "Variant deleted.";
}

$variantsStmt = $conn->prepare(
    "SELECT id, sku, variant_name, size, color, pack_size, stock_quantity, price_adjustment, active, created_at
     FROM tbl_product_variants
     WHERE product_id = ?
     ORDER BY id DESC"
);
$variantsStmt->bind_param("i", $productId);
$variantsStmt->execute();
$variants = $variantsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Variants | SmartStock Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container admin-shell">
        <div class="admin-page-header">
            <div>
                <span class="admin-page-eyebrow">Product Options</span>
                <h1 class="admin-page-title h3 mb-1">Variants for <?php echo e($product['title']); ?></h1>
                <p class="text-secondary mb-0">Maintain optional SKUs for size, color, and pack combinations.</p>
            </div>
            <a class="btn btn-outline-primary" href="manage-products.php">Products</a>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>

        <form method="post" class="card admin-surface-card mb-4">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Add Variant</h2></div>
            <div class="card-body row g-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="product_id" value="<?php echo (int)$productId; ?>">
                <div class="col-md-3">
                    <label class="form-label" for="variant_name">Variant Name</label>
                    <input class="form-control" id="variant_name" name="variant_name" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="sku">SKU</label>
                    <input class="form-control" id="sku" name="sku">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="size">Size</label>
                    <input class="form-control" id="size" name="size">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="color">Color</label>
                    <input class="form-control" id="color" name="color">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="pack_size">Pack Size</label>
                    <input class="form-control" id="pack_size" name="pack_size">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="stock_quantity">Variant Stock</label>
                    <input class="form-control" id="stock_quantity" type="number" min="0" name="stock_quantity" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="price_adjustment">Price Adjustment</label>
                    <input class="form-control" id="price_adjustment" type="number" step="0.01" name="price_adjustment" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="active">Active</label>
                    <select class="form-select" id="active" name="active">
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid align-items-end">
                    <button class="btn btn-smartstock" type="submit" name="add_variant">Add Variant</button>
                </div>
            </div>
        </form>

        <div class="card admin-surface-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th>Options</th>
                            <th>Stock</th>
                            <th>Price Adjustment</th>
                            <th>Active</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($variants && $variants->num_rows > 0): ?>
                            <?php while ($variant = $variants->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e($variant['variant_name']); ?></td>
                                    <td><?php echo e($variant['sku'] ?: 'N/A'); ?></td>
                                    <td><?php echo e(trim(($variant['size'] ?: '') . ' ' . ($variant['color'] ?: '') . ' ' . ($variant['pack_size'] ?: '')) ?: 'N/A'); ?></td>
                                    <td><?php echo (int)$variant['stock_quantity']; ?></td>
                                    <td><?php echo format_bdt($variant['price_adjustment']); ?></td>
                                    <td><?php echo yes_no_badge($variant['active'], 'Active', 'Inactive'); ?></td>
                                    <td class="text-end">
                                        <form method="post" onsubmit="return confirm('Delete this variant?')">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="product_id" value="<?php echo (int)$productId; ?>">
                                            <input type="hidden" name="variant_id" value="<?php echo (int)$variant['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_variant">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-secondary py-5">No variants added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
