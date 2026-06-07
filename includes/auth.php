<?php
require_once __DIR__ . '/session.php';

function require_admin(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: admin-login.php');
        exit;
    }
}

function current_admin_role(): string
{
    return (string)($_SESSION['admin_role'] ?? 'manager');
}

function admin_has_role(array $allowedRoles): bool
{
    $role = current_admin_role();

    return in_array($role, $allowedRoles, true) || $role === 'super_admin';
}

function require_admin_role(array $allowedRoles): void
{
    require_admin();

    if (!admin_has_role($allowedRoles)) {
        http_response_code(403);
        exit('You do not have permission to access this admin area.');
    }
}

function require_customer(): void
{
    if (empty($_SESSION['customer_logged_in'])) {
        header('Location: customer-login.php');
        exit;
    }
}
?>
