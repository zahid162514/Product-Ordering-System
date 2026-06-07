<?php
require_once __DIR__ . '/includes/session.php';
app_destroy_session();
header("Location: index.php");
exit;
?>
