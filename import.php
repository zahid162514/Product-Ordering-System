<?php
$allowImport = getenv('APP_ENV') === 'development' && getenv('ALLOW_SQL_IMPORT') === '1';
if (!$allowImport) {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/includes/csrf.php';

/**
 * SmartStock - SQL Import Helper
 *
 * Use this only in development when phpMyAdmin is not convenient.
 */

function run_sql_batch(mysqli $conn, string $content): array
{
    $queries = array_filter(array_map('trim', preg_split('/;/', $content)));
    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    foreach ($queries as $query) {
        if ($query === '') {
            continue;
        }

        if ($conn->query($query)) {
            $successCount++;
        } else {
            $errorCount++;
            $errors[] = $conn->error;
        }
    }

    return [$successCount, $errorCount, $errors];
}

$appConfig = require __DIR__ . '/includes/config.php';
$dbConfig = $appConfig['db'];
$dbName = $dbConfig['name'];
$quotedDbName = '`' . str_replace('`', '``', $dbName) . '`';

$message = '';
$messageType = '';

$conn = new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], '', $dbConfig['port']);
if ($conn->connect_error) {
    $message = 'Cannot connect to MySQL: ' . $conn->connect_error . '. Check your database environment variables or includes/config.php.';
    $messageType = 'error';
} else {
    if (!$conn->select_db($dbName)) {
        if ($conn->query("CREATE DATABASE " . $quotedDbName)) {
            $conn->select_db($dbName);
            $message = 'Created database: ' . $dbName;
            $messageType = 'success';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sqlfile'])) {
        require_valid_csrf();

        $file = $_FILES['sqlfile'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $content = (string)file_get_contents($file['tmp_name']);
            [$successCount, $errorCount, $errors] = run_sql_batch($conn, $content);

            if ($errorCount === 0) {
                $message = 'Successfully imported ' . $successCount . ' SQL statements.';
                $messageType = 'success';
            } else {
                $message = 'Imported ' . $successCount . ' statements with ' . $errorCount . ' errors.';
                $messageType = 'warning';
            }
        } else {
            $message = 'Error uploading file.';
            $messageType = 'error';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_sql']) && !empty($_POST['sqlcontent'])) {
        require_valid_csrf();

        [$successCount, $errorCount, $errors] = run_sql_batch($conn, (string)$_POST['sqlcontent']);
        if ($errorCount === 0) {
            $message = 'Executed ' . $successCount . ' SQL statements successfully.';
            $messageType = 'success';
        } else {
            $message = 'Executed ' . $successCount . ' statements with ' . $errorCount . ' errors.';
            $messageType = 'warning';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Import | SmartStock</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fb; padding: 20px; color: #1f2937; }
        .container { max-width: 860px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 14px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        h1 { color: #111827; margin-bottom: 10px; }
        h2 { color: #111827; margin-top: 30px; margin-bottom: 15px; border-bottom: 2px solid #dbeafe; padding-bottom: 8px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid; }
        .success { background: #ecfdf5; color: #166534; border-color: #16a34a; }
        .error { background: #fef2f2; color: #991b1b; border-color: #dc2626; }
        .warning { background: #fffbeb; color: #92400e; border-color: #f59e0b; }
        .info { background: #eff6ff; color: #1e40af; border-color: #2563eb; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #111827; }
        input[type="file"], textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 10px; font-family: monospace; font-size: 14px; }
        textarea { min-height: 300px; resize: vertical; }
        button { background: #2563eb; color: #fff; padding: 12px 22px; border: none; border-radius: 999px; cursor: pointer; font-size: 15px; font-weight: 700; }
        button:hover { background: #1d4ed8; }
        .instructions, .status { background: #f8fafc; padding: 16px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .instructions ol { margin-left: 20px; }
        .instructions li { margin-bottom: 10px; }
        code { background: #eef2ff; padding: 2px 6px; border-radius: 4px; font-family: Consolas, 'Courier New', monospace; }
        .status-item { margin: 10px 0; }
        .status-ok { color: #16a34a; }
        .status-error { color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SmartStock - SQL Import Helper</h1>
        <div style="text-align: center; font-size: 12px; color: #6b7280; margin-bottom: 20px;">
            by Laobaan Bangladesh LTD.
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="status">
            <h2>Database Status</h2>
            <?php if ($conn->connect_error): ?>
                <div class="status-item status-error">MySQL error: <?php echo htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
                <div class="status-item status-ok">Connected to MySQL</div>
                <div class="status-item">Database: <code><?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?></code></div>
                <?php
                if ($conn->select_db($dbName)) {
                    $dbNameForSql = $conn->real_escape_string($dbName);
                    $result = $conn->query("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '" . $dbNameForSql . "'");
                    $tableCount = $result ? (int)$result->fetch_assoc()['count'] : 0;
                    echo "<div class='status-item'>";
                    echo $tableCount > 0 ? 'Tables found: ' . $tableCount : 'No tables found yet.';
                    echo "</div>";
                }
                ?>
            <?php endif; ?>
        </div>

        <h2>Method 1: Upload SQL File</h2>
        <div class="instructions">
            <ol>
                <li>Select the SQL file, such as <code>sql schema.sql</code>.</li>
                <li>Click Import File.</li>
                <li>Wait for the import to complete.</li>
            </ol>
        </div>

        <?php if (!$conn->connect_error): ?>
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="sqlfile">Select SQL File</label>
                <input type="file" id="sqlfile" name="sqlfile" accept=".sql,.txt" required>
            </div>
            <button type="submit">Import File</button>
        </form>
        <?php endif; ?>

        <h2>Method 2: Paste SQL Content</h2>
        <div class="instructions">
            <ol>
                <li>Open the SQL file in a text editor.</li>
                <li>Copy all of the content.</li>
                <li>Paste it into the textarea below.</li>
                <li>Click Execute SQL.</li>
            </ol>
        </div>

        <?php if (!$conn->connect_error): ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="sqlcontent">SQL Content</label>
                <textarea id="sqlcontent" name="sqlcontent" placeholder="Paste your SQL code here..."></textarea>
            </div>
            <button type="submit" name="execute_sql">Execute SQL</button>
        </form>
        <?php endif; ?>

        <h2>Method 3: phpMyAdmin</h2>
        <div class="instructions">
            <ol>
                <li>Open phpMyAdmin in your browser (<code>http://localhost/phpmyadmin</code>).</li>
                <li>Create or select the database: <code><?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?></code>.</li>
                <li>Click the Import tab.</li>
                <li>Choose <code>sql schema.sql</code>.</li>
                <li>Click Go.</li>
            </ol>
        </div>

        <h2>Next Steps</h2>
        <div class="status">
            <div class="status-item">1. Import <code>sql schema.sql</code> to create tables.</div>
            <div class="status-item">2. Go to <a href="index.php">index.php</a> to access the application.</div>
            <div class="status-item">3. Demo data is included; see <code>README.md</code> for quick-start logins.</div>
            <div class="status-item">4. Change any default or temporary credentials before going live.</div>
        </div>

        <h2>Configuration</h2>
        <div class="instructions">
            <p>Database credentials are read from the config layer:</p>
            <code>
DB_HOST = <?php echo htmlspecialchars($dbConfig['host'], ENT_QUOTES, 'UTF-8'); ?><br>
DB_USER = <?php echo htmlspecialchars($dbConfig['user'], ENT_QUOTES, 'UTF-8'); ?><br>
DB_NAME = <?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?><br>
            </code>
            <p style="margin-top: 10px;">If these values are incorrect, update your environment variables or <code>includes/config.php</code>.</p>
        </div>
    </div>
</body>
</html>
