<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Product not found.");
}

$stmt = $conn->prepare("SELECT product_id, sku, title, description, price, original_price, stock_quantity, reorder_level, image_name, category_id, featured, active, created_at FROM tbl_product WHERE product_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("Product not found.");
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $title = trim($_POST['title'] ?? '');
    $sku = strtoupper(trim($_POST['sku'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $originalPriceInput = $_POST['original_price'] ?? '';
    $stockQuantity = intval($_POST['stock_quantity'] ?? 0);
    $reorderLevel = intval($_POST['reorder_level'] ?? 10);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $featured = trim($_POST['featured'] ?? 'No');
    $active = trim($_POST['active'] ?? 'Yes');
    $imageSource = trim($_POST['image_source'] ?? 'keep');
    $imageName = $product['image_name'];
    $uploadedImagePath = null;

    if ($title === '') {
        $errors[] = "Product title is required.";
    }

    if (!is_numeric($price) || (float)$price <= 0) {
        $errors[] = "Price must be greater than zero.";
    }

    if ($originalPriceInput !== '' && (!is_numeric($originalPriceInput) || (float)$originalPriceInput < 0)) {
        $errors[] = "Original price cannot be negative.";
    }

    if ($stockQuantity < 0) {
        $errors[] = "Stock quantity cannot be negative.";
    }
    if ($reorderLevel < 0) {
        $errors[] = "Reorder level cannot be negative.";
    }

    if (!in_array($featured, ['Yes', 'No'], true) || !in_array($active, ['Yes', 'No'], true)) {
        $errors[] = "Featured and active values are invalid.";
    }

    $categoryStmt = $conn->prepare("SELECT id FROM tbl_category WHERE id = ? LIMIT 1");
    $categoryStmt->bind_param("i", $categoryId);
    $categoryStmt->execute();

    if (!$categoryStmt->get_result()->fetch_assoc()) {
        $errors[] = "Please select a valid category.";
    }

    $categoryStmt->close();

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxFileSize = 2 * 1024 * 1024;

    if (!$errors && $imageSource === 'online' && trim($_POST['image_url'] ?? '') !== '') {
        $imageUrl = trim($_POST['image_url']);

        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageName = $imageUrl;
        } else {
            $errors[] = "Image URL must be a valid URL.";
        }
    } elseif (!$errors && $imageSource === 'upload' && !empty($_FILES['image']['name'])) {
        $fileName = basename($_FILES['image']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $imageInfo = @getimagesize($_FILES['image']['tmp_name']);

        if (!in_array($fileExt, $allowedExtensions, true) || !$imageInfo) {
            $errors[] = "Image must be jpg, jpeg, png, gif, or webp.";
        } elseif ($_FILES['image']['size'] > $maxFileSize) {
            $errors[] = "Image file size must be 2MB or less.";
        } else {
            $uploadDir = __DIR__ . "/../uploads/products/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
            $newImage = "uploads/products/" . time() . "_" . bin2hex(random_bytes(6)) . "_" . $safeBaseName . "." . $fileExt;
            $target = __DIR__ . "/../" . $newImage;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $imageName = $newImage;
                $uploadedImagePath = $target;
            } else {
                $errors[] = "Unable to upload image.";
            }
        }
    }

    if (!$errors) {
        $priceValue = (float)$price;
        $originalPrice = $originalPriceInput === '' ? null : (string)(float)$originalPriceInput;

        $oldStock = (int)$product['stock_quantity'];
        $sql = "UPDATE tbl_product
                SET sku = ?, title = ?, description = ?, price = ?, original_price = ?, stock_quantity = ?, reorder_level = ?, image_name = ?, category_id = ?, featured = ?, active = ?
                WHERE product_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssdsiisissi",
            $sku,
            $title,
            $description,
            $priceValue,
            $originalPrice,
            $stockQuantity,
            $reorderLevel,
            $imageName,
            $categoryId,
            $featured,
            $active,
            $id
        );

        if ($stmt->execute()) {
            if ($stockQuantity !== $oldStock) {
                record_inventory_adjustment(
                    $conn,
                    $id,
                    'manual_edit',
                    $stockQuantity - $oldStock,
                    $stockQuantity,
                    'Stock changed from product edit form.',
                    null,
                    (int)($_SESSION['admin_id'] ?? 0),
                    null
                );
                notify_low_stock($conn, $id);
            }
            header("Location: manage-products.php");
            exit;
        }

        if ($uploadedImagePath !== null && file_exists($uploadedImagePath)) {
            unlink($uploadedImagePath);
        }
        error_log("Update product failed: " . $stmt->error);
        $errors[] = "Unable to update product right now.";
    }
}

$categories = $conn->query("SELECT id, title FROM tbl_category WHERE active = 'Yes' ORDER BY title ASC");

$currentTitle = $_POST['title'] ?? $product['title'];
$currentSku = $_POST['sku'] ?? $product['sku'];
$currentDescription = $_POST['description'] ?? $product['description'];
$currentPrice = $_POST['price'] ?? $product['price'];
$currentOriginalPrice = $_POST['original_price'] ?? $product['original_price'];
$currentStock = $_POST['stock_quantity'] ?? $product['stock_quantity'];
$currentReorderLevel = $_POST['reorder_level'] ?? $product['reorder_level'];
$currentCategory = (int)($_POST['category_id'] ?? $product['category_id']);
$currentFeatured = $_POST['featured'] ?? $product['featured'];
$currentActive = $_POST['active'] ?? $product['active'];
$currentImageSource = $_POST['image_source'] ?? 'keep';

$stock = (int)$product['stock_quantity'];
$stockLabel = 'In Stock';
$stockClass = 'success';

if ($stock <= 0) {
    $stockLabel = 'Out of Stock';
    $stockClass = 'danger';
} elseif ($stock <= 10) {
    $stockLabel = 'Low Stock';
    $stockClass = 'warning';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | SmartStock Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.product-edit-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .edit-product-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .edit-product-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
            padding: 28px;
            border-radius: 24px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #ffffff;
            box-shadow: 0 20px 52px rgba(37, 99, 235, 0.22);
        }

        .edit-product-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .edit-product-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .edit-product-subtitle {
            max-width: 680px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .edit-product-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .edit-product-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 24px;
            align-items: start;
        }

        .admin-clean-card {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .admin-clean-card-header {
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .admin-clean-card-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.15rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .admin-clean-card-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .admin-clean-card-body {
            padding: 24px;
        }

        .form-section-title {
            margin: 8px 0 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #0f172a;
            font-size: 0.86rem;
            font-weight: 850;
            letter-spacing: 0.06em;
            text-transform: uppercase;
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

        textarea.form-control {
            min-height: 120px;
        }

        .product-preview-card {
            position: sticky;
            top: 88px;
        }

        .product-preview-image {
            width: 100%;
            height: 260px;
            object-fit: cover;
            background: #eff6ff;
            border-bottom: 1px solid #e2e8f0;
        }

        .preview-body {
            padding: 20px;
        }

        .preview-product-name {
            margin: 0;
            color: #0f172a;
            font-size: 1.15rem;
            font-weight: 850;
            line-height: 1.35;
        }

        .preview-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .preview-price {
            margin-top: 18px;
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 850;
            letter-spacing: -0.04em;
        }

        .preview-old-price {
            color: #94a3b8;
            font-size: 0.92rem;
            font-weight: 700;
            text-decoration: line-through;
        }

        .image-choice-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .image-choice {
            position: relative;
            display: block;
            min-height: 94px;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #f8fafc;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .image-choice:hover {
            border-color: rgba(37, 99, 235, 0.35);
            background: #ffffff;
        }

        .image-choice input {
            position: absolute;
            top: 14px;
            right: 14px;
        }

        .image-choice-title {
            display: block;
            margin-bottom: 6px;
            color: #0f172a;
            font-weight: 850;
        }

        .image-choice-copy {
            display: block;
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .image-extra-panel {
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #f8fafc;
        }

        .admin-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            margin-top: 8px;
            border-top: 1px solid #e2e8f0;
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

        .status-select-group {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .help-note {
            margin-top: 7px;
            color: #64748b;
            font-size: 0.82rem;
        }

        @media (max-width: 991.98px) {
            .edit-product-header {
                flex-direction: column;
            }

            .edit-product-header-actions {
                width: 100%;
            }

            .edit-product-header-actions .btn {
                width: 100%;
            }

            .edit-product-layout {
                grid-template-columns: 1fr;
            }

            .product-preview-card {
                position: static;
                order: -1;
            }

            .image-choice-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .edit-product-shell {
                padding: 20px 14px 48px;
            }

            .edit-product-header {
                padding: 24px 18px;
                border-radius: 20px;
            }

            .admin-clean-card-body {
                padding: 18px;
            }

            .status-select-group {
                grid-template-columns: 1fr;
            }

            .admin-form-actions {
                flex-direction: column-reverse;
            }

            .admin-form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="product-edit-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="edit-product-shell">
        <section class="edit-product-header">
            <div>
                <span class="edit-product-eyebrow">Catalog Management</span>
                <h1 class="edit-product-title">Edit Product</h1>
                <p class="edit-product-subtitle">
                    Update product details, pricing, inventory status, category visibility, and image settings.
                </p>
            </div>

            <div class="edit-product-header-actions">
                <a class="btn btn-light rounded-pill px-4" href="manage-products.php">
                    Manage Products
                </a>
                <a class="btn btn-outline-light rounded-pill px-4" href="../index.php" target="_blank">
                    View Store
                </a>
            </div>
        </section>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm border-0 mb-4">
                <strong>Please fix the following issue(s):</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="edit-product-layout">
            <section class="admin-clean-card">
                <div class="admin-clean-card-header">
                    <h2>Product Information</h2>
                    <p>Keep product information clear and accurate for customers.</p>
                </div>

                <div class="admin-clean-card-body">
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <?php echo csrf_field(); ?>

                        <div class="col-12">
                            <div class="form-section-title">Basic Details</div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="title">Product Title</label>
                            <input
                                class="form-control"
                                id="title"
                                type="text"
                                name="title"
                                value="<?php echo e($currentTitle); ?>"
                                placeholder="Example: Safety Helmet"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="sku">SKU</label>
                            <input
                                class="form-control"
                                id="sku"
                                type="text"
                                name="sku"
                                value="<?php echo e($currentSku); ?>"
                                placeholder="Optional SKU"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea
                                class="form-control"
                                id="description"
                                name="description"
                                rows="4"
                                placeholder="Write a short product description for customers."
                            ><?php echo e($currentDescription); ?></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-section-title">Pricing and Inventory</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="price">Selling Price</label>
                            <input
                                class="form-control"
                                id="price"
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="price"
                                value="<?php echo e($currentPrice); ?>"
                                required
                            >
                            <div class="help-note">Required. Must be greater than zero.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="original_price">Original Price</label>
                            <input
                                class="form-control"
                                id="original_price"
                                type="number"
                                step="0.01"
                                min="0"
                                name="original_price"
                                value="<?php echo e($currentOriginalPrice); ?>"
                            >
                            <div class="help-note">Optional. Used for discount display.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="stock_quantity">Stock Quantity</label>
                            <input
                                class="form-control"
                                id="stock_quantity"
                                type="number"
                                min="0"
                                name="stock_quantity"
                                value="<?php echo e($currentStock); ?>"
                                required
                            >
                            <div class="help-note">Set to 0 if unavailable.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="reorder_level">Reorder Level</label>
                            <input
                                class="form-control"
                                id="reorder_level"
                                type="number"
                                min="0"
                                name="reorder_level"
                                value="<?php echo e($currentReorderLevel); ?>"
                                required
                            >
                            <div class="help-note">Low-stock alerts begin at this level.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-section-title">Category and Visibility</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="category_id">Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo (int)$cat['id']; ?>" <?php if ($currentCategory === (int)$cat['id']) echo 'selected'; ?>>
                                        <?php echo e($cat['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <div class="status-select-group">
                                <div>
                                    <label class="form-label" for="featured">Featured</label>
                                    <select class="form-select" id="featured" name="featured">
                                        <option value="Yes" <?php if ($currentFeatured === 'Yes') echo 'selected'; ?>>Yes</option>
                                        <option value="No" <?php if ($currentFeatured === 'No') echo 'selected'; ?>>No</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label" for="active">Active</label>
                                    <select class="form-select" id="active" name="active">
                                        <option value="Yes" <?php if ($currentActive === 'Yes') echo 'selected'; ?>>Yes</option>
                                        <option value="No" <?php if ($currentActive === 'No') echo 'selected'; ?>>No</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-section-title">Product Image</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Image Source</label>

                            <div class="image-choice-grid">
                                <label class="image-choice">
                                    <input class="form-check-input image-source" type="radio" name="image_source" value="keep" <?php if ($currentImageSource === 'keep') echo 'checked'; ?>>
                                    <span class="image-choice-title">Keep Current</span>
                                    <span class="image-choice-copy">Use the existing product image.</span>
                                </label>

                                <label class="image-choice">
                                    <input class="form-check-input image-source" type="radio" name="image_source" value="upload" <?php if ($currentImageSource === 'upload') echo 'checked'; ?>>
                                    <span class="image-choice-title">Upload File</span>
                                    <span class="image-choice-copy">Upload jpg, png, gif, or webp up to 2MB.</span>
                                </label>

                                <label class="image-choice">
                                    <input class="form-check-input image-source" type="radio" name="image_source" value="online" <?php if ($currentImageSource === 'online') echo 'checked'; ?>>
                                    <span class="image-choice-title">External URL</span>
                                    <span class="image-choice-copy">Use a direct web image URL.</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6 d-none" id="upload-section">
                            <div class="image-extra-panel">
                                <label class="form-label" for="image">Upload New Image</label>
                                <input class="form-control" id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="help-note">Allowed: jpg, jpeg, png, gif, webp. Max 2MB.</div>
                            </div>
                        </div>

                        <div class="col-md-6 d-none" id="online-section">
                            <div class="image-extra-panel">
                                <label class="form-label" for="image_url">Image URL</label>
                                <input
                                    class="form-control"
                                    id="image_url"
                                    type="url"
                                    name="image_url"
                                    placeholder="https://example.com/image.jpg"
                                    value="<?php echo e($_POST['image_url'] ?? ''); ?>"
                                >
                                <div class="help-note">Paste a valid direct image URL.</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="admin-form-actions">
                                <a class="btn btn-outline-secondary rounded-pill px-4" href="manage-products.php">
                                    Cancel
                                </a>

                                <button class="btn btn-smartstock rounded-pill px-4" type="submit">
                                    Update Product
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <aside class="admin-clean-card product-preview-card">
                <img
                    class="product-preview-image"
                    id="product-preview-image"
                    src="<?php echo e(product_image_src($product['image_name'], '../')); ?>"
                    alt="<?php echo e($product['title']); ?>"
                >

                <div class="preview-body">
                    <h2 class="preview-product-name">
                        <?php echo e($product['title']); ?>
                    </h2>

                    <div class="preview-meta">
                        <span class="badge text-bg-<?php echo e($stockClass); ?>">
                            <?php echo e($stockLabel); ?>
                        </span>

                        <span class="badge text-bg-<?php echo $product['active'] === 'Yes' ? 'success' : 'secondary'; ?>">
                            <?php echo $product['active'] === 'Yes' ? 'Active' : 'Inactive'; ?>
                        </span>

                        <span class="badge text-bg-<?php echo $product['featured'] === 'Yes' ? 'primary' : 'secondary'; ?>">
                            <?php echo $product['featured'] === 'Yes' ? 'Featured' : 'Not Featured'; ?>
                        </span>
                    </div>

                    <div class="preview-price">
                        <?php echo format_bdt($product['price']); ?>
                    </div>

                    <?php if (!empty($product['original_price']) && (float)$product['original_price'] > (float)$product['price']): ?>
                        <div class="preview-old-price">
                            <?php echo format_bdt($product['original_price']); ?>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <div class="small text-secondary">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span>Product ID</span>
                            <strong>#<?php echo (int)$product['product_id']; ?></strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span>SKU</span>
                            <strong><?php echo e($product['sku'] ?: 'N/A'); ?></strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span>Current Stock</span>
                            <strong><?php echo (int)$product['stock_quantity']; ?></strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span>Reorder Level</span>
                            <strong><?php echo (int)$product['reorder_level']; ?></strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3">
                            <span>Image Mode</span>
                            <strong><?php echo filter_var($product['image_name'], FILTER_VALIDATE_URL) ? 'URL' : 'Local'; ?></strong>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <script>
        function syncImageInputs() {
            const selected = document.querySelector('input[name="image_source"]:checked');
            const value = selected ? selected.value : 'keep';

            document.getElementById('upload-section').classList.toggle('d-none', value !== 'upload');
            document.getElementById('online-section').classList.toggle('d-none', value !== 'online');
        }

        const imageUrlInput = document.getElementById('image_url');
        const fileInput = document.getElementById('image');
        const previewImage = document.getElementById('product-preview-image');

        document.querySelectorAll('.image-source').forEach(input => {
            input.addEventListener('change', syncImageInputs);
        });

        if (imageUrlInput && previewImage) {
            imageUrlInput.addEventListener('input', () => {
                if (imageUrlInput.value.trim() !== '') {
                    previewImage.src = imageUrlInput.value.trim();
                }
            });
        }

        if (fileInput && previewImage) {
            fileInput.addEventListener('change', () => {
                const file = fileInput.files && fileInput.files[0];

                if (!file) {
                    return;
                }

                previewImage.src = URL.createObjectURL(file);
            });
        }

        syncImageInputs();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
