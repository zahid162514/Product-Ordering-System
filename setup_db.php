<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

/**
 * SmartStock – CLI Database Setup Script
 * Run this from command line: php setup_db.php
 */

$appConfig = require __DIR__ . '/includes/config.php';
$dbConfig = $appConfig['db'];
$dbName = $dbConfig['name'];
$quotedDbName = '`' . str_replace('`', '``', $dbName) . '`';

// Connect to MySQL (without selecting DB)
$conn = new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], '', $dbConfig['port']);

if ($conn->connect_error) {
    die("MySQL Connection Failed: " . $conn->connect_error . "\n");
}

echo "Connected to MySQL.\n";

// Create database if not exists
if (!$conn->query("CREATE DATABASE IF NOT EXISTS " . $quotedDbName)) {
    die("Failed to create database: " . $conn->error . "\n");
}

echo "Database '" . $dbName . "' ready.\n";

// Select database
$conn->select_db($dbName);

// Import schema
$schemaFile = __DIR__ . '/sql schema.txt';
if (!file_exists($schemaFile)) {
    die("Schema file not found: $schemaFile\n");
}

$schemaContent = file_get_contents($schemaFile);
$queries = array_filter(array_map('trim', preg_split('/;/', $schemaContent)));

echo "Importing schema...\n";
foreach ($queries as $query) {
    if (!empty($query) && !preg_match('/^--/', $query)) {  // Skip comments
        if (!$conn->query($query)) {
            echo "Schema Error: " . $conn->error . " (Query: " . substr($query, 0, 50) . "...)\n";
        }
    }
}

echo "Schema imported.\n";

// Import test data
$testFile = __DIR__ . '/test sql.txt';
if (!file_exists($testFile)) {
    die("Test data file not found: $testFile\n");
}

$testContent = file_get_contents($testFile);
$queries = array_filter(array_map('trim', preg_split('/;/', $testContent)));

echo "Importing test data...\n";
foreach ($queries as $query) {
    if (!empty($query) && !preg_match('/^--/', $query)) {  // Skip comments
        if (!$conn->query($query)) {
            echo "Test Data Error: " . $conn->error . " (Query: " . substr($query, 0, 50) . "...)\n";
        }
    }
}

echo "Test data imported.\n";
echo "Setup complete! Admin login: admin / admin123\n";

$conn->close();
?>
