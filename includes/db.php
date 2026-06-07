<?php
$appConfig = require __DIR__ . '/config.php';
$dbConfig = $appConfig['db'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $dbConfig['host'],
        $dbConfig['user'],
        $dbConfig['pass'],
        $dbConfig['name'],
        $dbConfig['port']
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());

    if (($appConfig['app_env'] ?? 'production') === 'development') {
        die('Database Connection Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }

    http_response_code(500);
    die('Database connection error.');
}
?>
