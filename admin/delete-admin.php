<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin_role(['super_admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

require_valid_csrf();

require_once __DIR__ . '/../includes/db.php';
//include("includes/navbar.php");

$id = intval($_POST['id'] ?? 0);
if ($id) {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM tbl_admin");
    $adminCount = (int)($countResult->fetch_assoc()['total'] ?? 0);
    if ($adminCount <= 1 || $id === (int)($_SESSION['admin_id'] ?? 0)) {
        header("Location: manage-admin.php");
        exit;
    }

    $sql = "DELETE FROM tbl_admin WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: manage-admin.php");
exit;
?>
