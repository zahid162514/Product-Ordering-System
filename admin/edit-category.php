
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'inventory']);

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Category not found.");
}

$stmt = $conn->prepare("SELECT id, title, image_name, featured, active, created_at FROM tbl_category WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    die("Category not found.");
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $title = trim($_POST['title'] ?? '');
    $featured = trim($_POST['featured'] ?? 'No');
    $active = trim($_POST['active'] ?? 'Yes');
    $imageSource = trim($_POST['image_source'] ?? 'keep');
    $imageName = $category['image_name'];

    if ($title === '') {
        $errors[] = "Category title is required.";
    }

    if (!in_array($featured, ['Yes', 'No'], true) || !in_array($active, ['Yes', 'No'], true)) {
        $errors[] = "Featured and active values are invalid.";
    }

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
            $uploadDir = __DIR__ . "/../assets/images/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
            $newImage = time() . "_" . bin2hex(random_bytes(6)) . "_" . $safeBaseName . "." . $fileExt;
            $target = $uploadDir . $newImage;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $imageName = $newImage;
            } else {
                $errors[] = "Unable to upload image.";
            }
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare(
            "UPDATE tbl_category 
             SET title = ?, image_name = ?, featured = ?, active = ? 
             WHERE id = ?"
        );

        $stmt->bind_param("ssssi", $title, $imageName, $featured, $active, $id);

        if ($stmt->execute()) {
            header("Location: manage-categories.php");
            exit;
        }

        error_log("Update category failed: " . $stmt->error);
        $errors[] = "Unable to update category right now.";
    }
}

$currentTitle = $_POST['title'] ?? $category['title'];
$currentFeatured = $_POST['featured'] ?? $category['featured'];
$currentActive = $_POST['active'] ?? $category['active'];
$currentImageSource = $_POST['image_source'] ?? 'keep';
$currentImageUrl = $_POST['image_url'] ?? (filter_var($category['image_name'], FILTER_VALIDATE_URL) ? $category['image_name'] : '');

$imageMode = filter_var($category['image_name'], FILTER_VALIDATE_URL) ? 'External URL' : 'Local Image';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category | SmartStock Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.category-edit-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .category-edit-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .category-edit-header {
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

        .category-edit-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .category-edit-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .category-edit-subtitle {
            max-width: 680px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .category-edit-header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .category-edit-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
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

        .status-select-group {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .image-choice-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .image-choice {
            position: relative;
            display: block;
            min-height: 96px;
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
            padding-right: 18px;
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

        .help-note {
            margin-top: 7px;
            color: #64748b;
            font-size: 0.82rem;
        }

        .category-preview-card {
            position: sticky;
            top: 88px;
        }

        .category-preview-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background: #eff6ff;
            border-bottom: 1px solid #e2e8f0;
        }

        .preview-body {
            padding: 20px;
        }

        .preview-category-name {
            margin: 0;
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 850;
            line-height: 1.35;
        }

        .preview-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .preview-info-list {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
        }

        .preview-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 10px;
            color: #64748b;
            font-size: 0.88rem;
        }

        .preview-info-row strong {
            color: #0f172a;
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

        @media (max-width: 991.98px) {
            .category-edit-header {
                flex-direction: column;
            }

            .category-edit-header-actions {
                width: 100%;
            }

            .category-edit-header-actions .btn {
                width: 100%;
            }

            .category-edit-layout {
                grid-template-columns: 1fr;
            }

            .category-preview-card {
                position: static;
                order: -1;
            }

            .image-choice-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .category-edit-shell {
                padding: 20px 14px 48px;
            }

            .category-edit-header {
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
<body class="category-edit-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="category-edit-shell">
        <section class="category-edit-header">
            <div>
                <span class="category-edit-eyebrow">Collection Management</span>
                <h1 class="category-edit-title">Edit Category</h1>
                <p class="category-edit-subtitle">
                    Update category title, image, featured status, and customer-facing visibility.
                </p>
            </div>

            <div class="category-edit-header-actions">
                <a class="btn btn-light rounded-pill px-4" href="manage-categories.php">
                    Manage Categories
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

        <div class="category-edit-layout">
            <section class="admin-clean-card">
                <div class="admin-clean-card-header">
                    <h2>Category Details</h2>
                    <p>Keep category information clear so customers can browse products easily.</p>
                </div>

                <div class="admin-clean-card-body">
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <?php echo csrf_field(); ?>

                        <div class="col-12">
                            <div class="form-section-title">Basic Information</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="title">Category Title</label>
                            <input
                                class="form-control"
                                id="title"
                                type="text"
                                name="title"
                                value="<?php echo e($currentTitle); ?>"
                                placeholder="Example: Safety Products"
                                required
                            >
                        </div>

                        <div class="col-12">
                            <div class="form-section-title">Visibility Settings</div>
                        </div>

                        <div class="col-12">
                            <div class="status-select-group">
                                <div>
                                    <label class="form-label" for="featured">Featured</label>
                                    <select class="form-select" id="featured" name="featured">
                                        <option value="Yes" <?php if ($currentFeatured === 'Yes') echo 'selected'; ?>>Yes</option>
                                        <option value="No" <?php if ($currentFeatured === 'No') echo 'selected'; ?>>No</option>
                                    </select>
                                    <div class="help-note">Featured categories may appear first in the storefront.</div>
                                </div>

                                <div>
                                    <label class="form-label" for="active">Active</label>
                                    <select class="form-select" id="active" name="active">
                                        <option value="Yes" <?php if ($currentActive === 'Yes') echo 'selected'; ?>>Yes</option>
                                        <option value="No" <?php if ($currentActive === 'No') echo 'selected'; ?>>No</option>
                                    </select>
                                    <div class="help-note">Inactive categories are hidden from customer browsing.</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-section-title">Category Image</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Image Source</label>

                            <div class="image-choice-grid">
                                <label class="image-choice">
                                    <input
                                        class="form-check-input image-source"
                                        type="radio"
                                        name="image_source"
                                        value="keep"
                                        <?php if ($currentImageSource === 'keep') echo 'checked'; ?>
                                    >
                                    <span class="image-choice-title">Keep Current</span>
                                    <span class="image-choice-copy">Use the existing category image.</span>
                                </label>

                                <label class="image-choice">
                                    <input
                                        class="form-check-input image-source"
                                        type="radio"
                                        name="image_source"
                                        value="upload"
                                        <?php if ($currentImageSource === 'upload') echo 'checked'; ?>
                                    >
                                    <span class="image-choice-title">Upload File</span>
                                    <span class="image-choice-copy">Upload jpg, png, gif, or webp up to 2MB.</span>
                                </label>

                                <label class="image-choice">
                                    <input
                                        class="form-check-input image-source"
                                        type="radio"
                                        name="image_source"
                                        value="online"
                                        <?php if ($currentImageSource === 'online') echo 'checked'; ?>
                                    >
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
                                    value="<?php echo e($currentImageUrl); ?>"
                                >
                                <div class="help-note">Paste a valid direct image URL.</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="admin-form-actions">
                                <a class="btn btn-outline-secondary rounded-pill px-4" href="manage-categories.php">
                                    Cancel
                                </a>

                                <button class="btn btn-smartstock rounded-pill px-4" type="submit">
                                    Update Category
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <aside class="admin-clean-card category-preview-card">
                <img
                    class="category-preview-image"
                    id="category-preview-image"
                    src="<?php echo e(product_image_src($category['image_name'], '../')); ?>"
                    alt="<?php echo e($category['title']); ?>"
                >

                <div class="preview-body">
                    <h2 class="preview-category-name">
                        <?php echo e($category['title']); ?>
                    </h2>

                    <div class="preview-meta">
                        <span class="badge text-bg-<?php echo $category['active'] === 'Yes' ? 'success' : 'secondary'; ?>">
                            <?php echo $category['active'] === 'Yes' ? 'Active' : 'Inactive'; ?>
                        </span>

                        <span class="badge text-bg-<?php echo $category['featured'] === 'Yes' ? 'primary' : 'secondary'; ?>">
                            <?php echo $category['featured'] === 'Yes' ? 'Featured' : 'Not Featured'; ?>
                        </span>
                    </div>

                    <div class="preview-info-list">
                        <div class="preview-info-row">
                            <span>Category ID</span>
                            <strong>#<?php echo (int)$category['id']; ?></strong>
                        </div>

                        <div class="preview-info-row">
                            <span>Image Mode</span>
                            <strong><?php echo e($imageMode); ?></strong>
                        </div>

                        <div class="preview-info-row">
                            <span>Customer Visibility</span>
                            <strong><?php echo $category['active'] === 'Yes' ? 'Visible' : 'Hidden'; ?></strong>
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
        const previewImage = document.getElementById('category-preview-image');

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
