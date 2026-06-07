<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

$errors = [];
$old = ['title' => '', 'featured' => 'No', 'active' => 'Yes', 'image_source' => 'upload', 'image_url' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $old = array_merge($old, $_POST);

    $title = trim($_POST['title'] ?? '');
    $featured = trim($_POST['featured'] ?? 'No');
    $active = trim($_POST['active'] ?? 'Yes');
    $imageSource = trim($_POST['image_source'] ?? 'upload');

    if ($title === '') {
        $errors[] = "Category title is required.";
    }
    if (!in_array($featured, ['Yes', 'No'], true) || !in_array($active, ['Yes', 'No'], true)) {
        $errors[] = "Featured and active values are invalid.";
    }

    $imageName = "";
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
            $imageName = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            $target = __DIR__ . "/../assets/images/" . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $errors[] = "Unable to upload image.";
                $imageName = "";
            }
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO tbl_category (title, image_name, featured, active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $imageName, $featured, $active);

        if ($stmt->execute()) {
            header("Location: manage-categories.php");
            exit;
        }

        error_log("Add category failed: " . $stmt->error);
        $errors[] = "Unable to add category right now.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category | SmartStock Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container admin-shell">
        <div class="admin-page-header mx-auto form-card">
            <div>
                <span class="admin-page-eyebrow">Collections</span>
                <h1 class="admin-page-title h3 mb-1">Add Category</h1>
                <p class="text-secondary mb-0">Create a new category for browsing, merchandising, and product organization.</p>
            </div>
        </div>

        <div class="card form-card admin-surface-card mx-auto">
            <div class="card-header bg-white"><h2 class="h5 admin-page-title mb-0">Category Details</h2></div>
            <div class="card-body">
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endforeach; ?>

                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <?php echo csrf_field(); ?>
                    <div class="col-12">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control" id="title" type="text" name="title" value="<?php echo e($old['title']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="featured">Featured</label>
                        <select class="form-select" id="featured" name="featured">
                            <option value="Yes" <?php if ($old['featured'] === 'Yes') echo 'selected'; ?>>Yes</option>
                            <option value="No" <?php if ($old['featured'] === 'No') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                    <div class="col-md-6">
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
                        <input class="form-control" id="image_url" type="url" name="image_url" value="<?php echo e($old['image_url']); ?>">
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-smartstock" type="submit">Add Category</button>
                        <a class="btn btn-outline-secondary" href="manage-categories.php">Cancel</a>
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
