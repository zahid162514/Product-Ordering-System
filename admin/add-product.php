<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

$errors = [];
$old = [
    'title' => '',
    'sku' => '',
    'description' => '',
    'price' => '',
    'original_price' => '',
    'stock_quantity' => '0',
    'reorder_level' => '10',
    'category_id' => '',
    'featured' => 'No',
    'active' => 'Yes',
    'image_source' => 'upload',
    'image_url' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $old = array_merge($old, $_POST);

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
    $imageSource = trim($_POST['image_source'] ?? 'upload');

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

    $imageName = "";
    $uploadedImagePath = null;
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
            $imageName = "uploads/products/" . time() . "_" . bin2hex(random_bytes(6)) . "_" . $safeBaseName . "." . $fileExt;
            $target = __DIR__ . "/../" . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $errors[] = "Unable to upload image.";
                $imageName = "";
            } else {
                $uploadedImagePath = $target;
            }
        }
    }

    if (!$errors) {
        $priceValue = (float)$price;
        $originalPrice = $originalPriceInput === '' ? null : (float)$originalPriceInput;

        $sql = "INSERT INTO tbl_product
                (sku, title, description, price, original_price, stock_quantity, reorder_level, image_name, category_id, featured, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssddiisiss", $sku, $title, $description, $priceValue, $originalPrice, $stockQuantity, $reorderLevel, $imageName, $categoryId, $featured, $active);

        if ($stmt->execute()) {
            header("Location: manage-products.php");
            exit;
        }

        if ($uploadedImagePath !== null && file_exists($uploadedImagePath)) {
            unlink($uploadedImagePath);
        }
        error_log("Add product failed: " . $stmt->error);
        $errors[] = "Unable to add product right now.";
    }
}

$categories = $conn->query("SELECT id, title FROM tbl_category WHERE active = 'Yes' ORDER BY title ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | SmartStock Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container admin-shell">
        <div class="admin-page-header mx-auto form-card">
            <div>
                <span class="admin-page-eyebrow">Catalog</span>
                <h1 class="admin-page-title h3 mb-1">Add New Product</h1>
                <p class="text-secondary mb-0">Create a product entry with pricing, stock, category, and image details.</p>
            </div>
        </div>

        <div class="card form-card admin-surface-card mx-auto">
            <div class="card-header bg-white">
                <h2 class="h5 admin-page-title mb-0">Product Details</h2>
            </div>
            <div class="card-body">
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endforeach; ?>

                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <?php echo csrf_field(); ?>
                    <div class="col-md-8">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control" id="title" type="text" name="title" value="<?php echo e($old['title']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sku">SKU</label>
                        <input class="form-control" id="sku" type="text" name="sku" value="<?php echo e($old['sku']); ?>" placeholder="Optional">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($old['description']); ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="price">Price</label>
                        <input class="form-control" id="price" type="number" step="0.01" min="0.01" name="price" value="<?php echo e($old['price']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="original_price">Original Price</label>
                        <input class="form-control" id="original_price" type="number" step="0.01" min="0" name="original_price" value="<?php echo e($old['original_price']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="stock_quantity">Stock Quantity</label>
                        <input class="form-control" id="stock_quantity" type="number" min="0" name="stock_quantity" value="<?php echo e($old['stock_quantity']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="reorder_level">Reorder Level</label>
                        <input class="form-control" id="reorder_level" type="number" min="0" name="reorder_level" value="<?php echo e($old['reorder_level'] ?? '10'); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select category</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo (int)$cat['id']; ?>" <?php if ((int)$old['category_id'] === (int)$cat['id']) echo 'selected'; ?>>
                                    <?php echo e($cat['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="featured">Featured</label>
                        <select class="form-select" id="featured" name="featured">
                            <option value="Yes" <?php if ($old['featured'] === 'Yes') echo 'selected'; ?>>Yes</option>
                            <option value="No" <?php if ($old['featured'] === 'No') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="active">Active</label>
                        <select class="form-select" id="active" name="active">
                            <option value="Yes" <?php if ($old['active'] === 'Yes') echo 'selected'; ?>>Yes</option>
                            <option value="No" <?php if ($old['active'] === 'No') echo 'selected'; ?>>No</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Image Source</label>
                        <div class="d-flex flex-wrap gap-3">
                            <label class="form-check">
                                <input class="form-check-input image-source" type="radio" name="image_source" value="upload" <?php if ($old['image_source'] !== 'online') echo 'checked'; ?>>
                                <span class="form-check-label">Upload File</span>
                            </label>
                            <label class="form-check">
                                <input class="form-check-input image-source" type="radio" name="image_source" value="online" <?php if ($old['image_source'] === 'online') echo 'checked'; ?>>
                                <span class="form-check-label">External URL</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-6" id="upload-section">
                        <label class="form-label" for="image">Upload Image</label>
                        <input class="form-control" id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
                    </div>
                    <div class="col-md-6" id="online-section">
                        <label class="form-label" for="image_url">Image URL</label>
                        <input class="form-control" id="image_url" type="url" name="image_url" value="<?php echo e($old['image_url']); ?>" placeholder="https://example.com/image.jpg">
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-smartstock" type="submit">Add Product</button>
                        <a class="btn btn-outline-secondary" href="manage-products.php">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function syncImageInputs() {
            const value = document.querySelector('input[name="image_source"]:checked').value;
            document.getElementById('upload-section').classList.toggle('d-none', value !== 'upload');
            document.getElementById('online-section').classList.toggle('d-none', value !== 'online');
        }
        document.querySelectorAll('.image-source').forEach(input => input.addEventListener('change', syncImageInputs));
        syncImageInputs();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
