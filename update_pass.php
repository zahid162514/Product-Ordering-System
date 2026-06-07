<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

if (($argc ?? 0) < 3) {
    fwrite(STDERR, "Usage: php update_pass.php <admin_username> <new_password>\n");
    exit(1);
}

require_once __DIR__ . '/includes/db.php';

$username = trim($argv[1]);
$newPassword = $argv[2];

if ($username === '' || strlen($newPassword) < 8) {
    fwrite(STDERR, "Username is required and password must be at least 8 characters.\n");
    exit(1);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE tbl_admin SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hash, $username);
$stmt->execute();

if ($stmt->affected_rows !== 1) {
    fwrite(STDERR, "No admin account was updated.\n");
    exit(1);
}

echo "Admin password updated.\n";
?>
