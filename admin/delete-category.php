<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin_role(['manager', 'inventory']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

require_valid_csrf();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$id = intval($_POST['id'] ?? 0);
if (!$id) { die("Category not found"); }

// Get category image to delete from server
$stmt = $conn->prepare("SELECT image_name FROM tbl_category WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();

// Delete from DB
$sql = "DELETE FROM tbl_category WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

// Delete image from server
if (!empty($category['image_name'])) {
    $imagePath = local_asset_absolute_path($category['image_name']);
    if ($imagePath !== null) {
        unlink($imagePath);
    }
}

header("Location: manage-categories.php");
exit;
?>
