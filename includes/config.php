<?php
require_once __DIR__ . '/env.php';
smartstock_load_env_file(dirname(__DIR__) . '/.env');

if (!function_exists('smartstock_env')) {
    function smartstock_env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }
}

return [
    'app_env' => smartstock_env('APP_ENV', 'production'),
    'app' => [
        'name' => smartstock_env('APP_NAME', 'SmartStock'),
        'base_url' => rtrim(smartstock_env('APP_BASE_URL', 'http://localhost/PRODUCTS_ORDERING'), '/'),
    ],
    'db' => [
        'host' => smartstock_env('DB_HOST', '127.0.0.1'),
        'user' => smartstock_env('DB_USER', 'root'),
        'pass' => smartstock_env('DB_PASS', ''),
        'name' => smartstock_env('DB_NAME', 'products_ordering_db'),
        'port' => (int)smartstock_env('DB_PORT', 3306),
    ],
    'mail' => [
        'transport' => smartstock_env('MAIL_TRANSPORT', smartstock_env('MAIL_HOST', '') !== '' ? 'smtp' : 'mail'),
        'host' => smartstock_env('MAIL_HOST', ''),
        'port' => (int)smartstock_env('MAIL_PORT', 587),
        'username' => smartstock_env('MAIL_USERNAME', ''),
        'password' => smartstock_env('MAIL_PASSWORD', ''),
        'encryption' => strtolower(smartstock_env('MAIL_ENCRYPTION', 'tls')),
        'from_email' => smartstock_env('MAIL_FROM_EMAIL', 'mdnazmul723048@gmail.com'),
        'from_name' => smartstock_env('MAIL_FROM_NAME', 'SmartStock'),
        'timeout' => (int)smartstock_env('MAIL_TIMEOUT', 15),
    ],
    'sslcommerz' => [
        'enabled' => smartstock_env('SSLCOMMERZ_ENABLED', '0') === '1',
        'sandbox' => smartstock_env('SSLCOMMERZ_SANDBOX', '1') === '1',
        'store_id' => smartstock_env('SSLCOMMERZ_STORE_ID', ''),
        'store_password' => smartstock_env('SSLCOMMERZ_STORE_PASSWORD', ''),
        'currency' => smartstock_env('SSLCOMMERZ_CURRENCY', 'BDT'),
        'session_api' => smartstock_env('SSLCOMMERZ_SESSION_API', 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'),
        'validation_api' => smartstock_env('SSLCOMMERZ_VALIDATION_API', 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'),
    ],
];
?>
