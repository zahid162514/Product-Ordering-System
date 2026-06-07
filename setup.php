<?php
$allowSetup = getenv('APP_ENV') === 'development' || getenv('ALLOW_SETUP') === '1';
if (!$allowSetup) {
    http_response_code(404);
    exit('Not found');
}

/**
 * SmartStock - Setup and Verification
 *
 * This script helps you verify the environment and database setup.
 * Run it from your browser only in development.
 */

$appConfig = require __DIR__ . '/includes/config.php';
$dbConfig = $appConfig['db'];
$config = [
    'host' => $dbConfig['host'],
    'user' => $dbConfig['user'],
    'pass' => $dbConfig['pass'],
    'dbname' => $dbConfig['name'],
    'port' => $dbConfig['port'],
];

$quotedDbName = '`' . str_replace('`', '``', $config['dbname']) . '`';

echo "<!DOCTYPE html>";
echo "<html lang='en'><head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Setup | SmartStock</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 40px; color: #2b2b2b; background: #f8fafc; }";
echo ".container { max-width: 840px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 14px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }";
echo ".success { color: #166534; background: #ecfdf5; padding: 10px 12px; margin: 10px 0; border-left: 4px solid #16a34a; }";
echo ".error { color: #991b1b; background: #fef2f2; padding: 10px 12px; margin: 10px 0; border-left: 4px solid #dc2626; }";
echo ".info { color: #1e40af; background: #eff6ff; padding: 10px 12px; margin: 10px 0; border-left: 4px solid #2563eb; }";
echo ".warning { color: #92400e; background: #fffbeb; padding: 10px 12px; margin: 10px 0; border-left: 4px solid #f59e0b; }";
echo "h1 { color: #0f172a; margin-top: 0; }";
echo "h2 { border-bottom: 2px solid #dbeafe; padding-bottom: 8px; color: #0f172a; }";
echo "h3 { color: #111827; }";
echo "code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: Consolas, 'Courier New', monospace; }";
echo "ol, ul { line-height: 1.6; }";
echo "</style>";
echo "</head><body>";
echo "<div class='container'>";
echo "<h1>SmartStock - Setup & Verification</h1>";

echo "<h2>1. System Requirements Check</h2>";
$phpVersion = phpversion();
echo "PHP Version: " . htmlspecialchars($phpVersion) . " ";
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "<span class='success'>OK</span>";
} else {
    echo "<span class='error'>FAILED - Minimum PHP 7.4 required</span>";
}
echo "<br>";

$mysqliLoaded = extension_loaded('mysqli');
echo "MySQLi Extension: <span class='" . ($mysqliLoaded ? 'success' : 'error') . "'>";
echo $mysqliLoaded ? 'OK' : 'NOT LOADED';
echo "</span><br>";

echo "<h2>2. Database Connection</h2>";
$conn = new mysqli($config['host'], $config['user'], $config['pass'], '', $config['port']);

if ($conn->connect_error) {
    echo "<div class='error'>Connection failed: " . htmlspecialchars($conn->connect_error) . "</div>";
    echo "<div class='info'>Check your database environment variables or <code>includes/config.php</code>.</div>";
} else {
    echo "<div class='success'>Connected to MySQL</div>";

    echo "<h2>3. Database Setup</h2>";
    $dbExists = $conn->select_db($config['dbname']);

    if (!$dbExists) {
        echo "<div class='warning'>Database '" . htmlspecialchars($config['dbname']) . "' not found. Creating it now.</div>";
        if ($conn->query("CREATE DATABASE IF NOT EXISTS " . $quotedDbName)) {
            echo "<div class='success'>Database created</div>";
            $conn->select_db($config['dbname']);
        } else {
            echo "<div class='error'>Failed to create database: " . htmlspecialchars($conn->error) . "</div>";
        }
    } else {
        echo "<div class='success'>Database exists</div>";
    }

    if ($conn->select_db($config['dbname'])) {
        $dbNameForSql = $conn->real_escape_string($config['dbname']);
        $tablesCheck = $conn->query("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '" . $dbNameForSql . "'");
        $tablesCount = (int)($tablesCheck ? $tablesCheck->fetch_assoc()['count'] : 0);

        echo "<h2>4. Database Tables</h2>";

        if ($tablesCount === 0) {
            echo "<div class='info'>No tables found. Import the SQL schema to continue.</div>";
            echo "<h3>To import the schema:</h3>";
            echo "<ol>";
            echo "<li>Open phpMyAdmin</li>";
            echo "<li>Select database: " . htmlspecialchars($config['dbname']) . "</li>";
            echo "<li>Go to the Import tab</li>";
            echo "<li>Upload <code>sql schema.sql</code></li>";
            echo "<li>Click Go</li>";
            echo "</ol>";
        } else {
            echo "<div class='success'>Found " . $tablesCount . " tables</div>";
            $tablesResult = $conn->query("SHOW TABLES");
            echo "<h3>Tables</h3>";
            echo "<ul>";
            while ($table = $tablesResult->fetch_row()) {
                echo "<li>" . htmlspecialchars($table[0]) . "</li>";
            }
            echo "</ul>";

            $adminCheck = $conn->query("SELECT COUNT(*) AS count FROM tbl_admin");
            if ($adminCheck) {
                $adminCount = (int)$adminCheck->fetch_assoc()['count'];
                echo "<div class='info'>Admin users found: " . $adminCount . "</div>";
                if ($adminCount === 0) {
                    echo "<div class='warning'>No admin user found. Create your first admin account before going live.</div>";
                }
            }
        }
    }
}

echo "<h2>5. File Permissions</h2>";
$uploadsPath = __DIR__ . '/uploads/products';
if (!is_dir($uploadsPath)) {
    mkdir($uploadsPath, 0755, true);
}

if (is_writable($uploadsPath)) {
    echo "<div class='success'>uploads/products is writable</div>";
} else {
    echo "<div class='warning'>uploads/products may not be writable. Product image uploads may fail.</div>";
}

echo "<h2>6. Next Steps</h2>";
echo "<ol>";
echo "<li>Import SQL schema from <code>sql schema.sql</code> using phpMyAdmin</li>";
echo "<li>Update database credentials through environment variables or <code>includes/config.php</code> if needed</li>";
echo "<li>Visit <a href='index.php'>index.php</a></li>";
echo "<li>Demo data is included; see <code>README.md</code> for quick-start logins</li>";
echo "</ol>";

echo "<h2>Troubleshooting</h2>";
echo "<div class='info'>";
echo "<strong>If you see connection errors:</strong><br>";
echo "Check the database values in your environment or in <code>includes/config.php</code>.";
echo "</div>";

$conn->close();
echo "</div>";
echo "</body></html>";
