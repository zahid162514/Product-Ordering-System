<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['super_admin']);

function admin_scalar_query(mysqli $conn, string $sql)
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return $row ? reset($row) : 0;
}

$summary = [
    'total' => (int) admin_scalar_query($conn, "SELECT COUNT(*) FROM tbl_admin"),
    'super_admin' => (int) admin_scalar_query($conn, "SELECT COUNT(*) FROM tbl_admin WHERE role = 'super_admin'"),
    'manager' => (int) admin_scalar_query($conn, "SELECT COUNT(*) FROM tbl_admin WHERE role = 'manager'"),
    'inventory' => (int) admin_scalar_query($conn, "SELECT COUNT(*) FROM tbl_admin WHERE role = 'inventory'"),
    'support' => (int) admin_scalar_query($conn, "SELECT COUNT(*) FROM tbl_admin WHERE role = 'support'"),
];

$summaryCards = [
    [
        'label' => 'Total Admins',
        'value' => $summary['total'],
        'note' => 'All back-office users',
        'tone' => 'primary',
    ],
    [
        'label' => 'Super Admins',
        'value' => $summary['super_admin'],
        'note' => 'Highest access level',
        'tone' => 'warning',
    ],
    [
        'label' => 'Workflow Roles',
        'value' => $summary['manager'] + $summary['inventory'] + $summary['support'],
        'note' => 'Manager, inventory, and support users',
        'tone' => 'success',
    ],
];

$adminResult = $conn->query("SELECT id, full_name, username, email, role, created_at FROM tbl_admin ORDER BY id DESC");
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);

function admin_role_badge(?string $role): string
{
    $role = trim((string)$role);

    if ($role === 'super_admin') {
        return '<span class="badge rounded-pill text-bg-primary">Super Admin</span>';
    }

    if ($role === 'manager') {
        return '<span class="badge rounded-pill text-bg-success">Manager</span>';
    }

    if ($role === 'inventory') {
        return '<span class="badge rounded-pill text-bg-warning">Inventory</span>';
    }

    if ($role === 'support') {
        return '<span class="badge rounded-pill text-bg-info">Support</span>';
    }

    return '<span class="badge rounded-pill text-bg-secondary">' . e($role ?: 'Unknown') . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrators | SmartStock</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.manage-admin-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .admins-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .admins-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
            padding: 30px;
            border-radius: 26px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #ffffff;
            box-shadow: 0 20px 52px rgba(37, 99, 235, 0.22);
        }

        .admins-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .admins-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .admins-subtitle {
            max-width: 700px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .admins-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .admins-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .admins-summary-card {
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
        }

        .summary-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .summary-label {
            margin: 0;
            color: #64748b;
            font-size: 0.84rem;
            font-weight: 750;
        }

        .summary-dot {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 14px;
            font-weight: 900;
        }

        .tone-primary .summary-dot {
            background: #eff6ff;
            color: #2563eb;
        }

        .tone-warning .summary-dot {
            background: #fef3c7;
            color: #d97706;
        }

        .tone-success .summary-dot {
            background: #dcfce7;
            color: #16a34a;
        }

        .summary-value {
            margin: 0;
            color: #0f172a;
            font-size: 2rem;
            font-weight: 850;
            letter-spacing: -0.045em;
        }

        .summary-note {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 0.84rem;
        }

        .admins-panel {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .admins-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .admins-panel-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .admins-panel-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .admins-table {
            margin: 0;
        }

        .admins-table thead th {
            padding: 14px 16px;
            background: #f8fafc;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.74rem;
            font-weight: 850;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .admins-table tbody td {
            padding: 16px;
            border-color: #eef2f7;
            vertical-align: middle;
        }

        .admins-table tbody tr:hover {
            background: #f8fafc;
        }

        .admin-avatar {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            border-radius: 16px;
            background: #eff6ff;
            color: #2563eb;
            font-weight: 900;
            text-transform: uppercase;
        }

        .admin-user-cell {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 240px;
        }

        .admin-full-name {
            color: #0f172a;
            font-weight: 850;
            line-height: 1.35;
        }

        .admin-id {
            margin-top: 4px;
            color: #64748b;
            font-size: 0.82rem;
        }

        .username-pill {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #334155;
            font-size: 0.84rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .email-text {
            color: #334155;
            font-weight: 650;
            word-break: break-word;
        }

        .date-text {
            color: #64748b;
            font-size: 0.86rem;
            font-weight: 650;
            white-space: nowrap;
        }

        .action-group {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            white-space: nowrap;
        }

        .action-group form {
            margin: 0;
        }

        .current-account-pill {
            display: inline-flex;
            padding: 7px 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .admins-empty-state {
            padding: 52px 20px;
            text-align: center;
        }

        .admins-empty-state h3 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 1.12rem;
            font-weight: 850;
        }

        .admins-empty-state p {
            margin: 0 0 18px;
            color: #64748b;
        }

        .btn-smartstock {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #ffffff;
            font-weight: 750;
        }

        .btn-smartstock:hover {
            border-color: #1e3a8a;
            background: #1e3a8a;
            color: #ffffff;
        }

        @media (max-width: 1199.98px) {
            .admins-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .admins-shell {
                padding: 20px 14px 48px;
            }

            .admins-hero {
                flex-direction: column;
                padding: 24px 18px;
                border-radius: 20px;
            }

            .admins-hero-actions {
                width: 100%;
            }

            .admins-hero-actions .btn {
                width: 100%;
            }

            .admins-summary-grid {
                grid-template-columns: 1fr;
            }

            .admins-panel-header {
                flex-direction: column;
                padding: 20px;
            }

            .admins-panel-header .btn {
                width: 100%;
            }

            .action-group {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }

            .action-group .btn,
            .action-group form,
            .action-group button {
                width: 100%;
            }
        }
    </style>
</head>

<body class="manage-admin-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="admins-shell">
        <section class="admins-hero">
            <div>
                <span class="admins-eyebrow">Access Control</span>
                <h1 class="admins-title">Administrators</h1>
                <p class="admins-subtitle">
                    Manage back-office administrator accounts, review access roles, and maintain secure operational control.
                </p>
            </div>

            <div class="admins-hero-actions">
                <a class="btn btn-light rounded-pill px-4" href="add-admin.php">
                    Add Admin
                </a>

                <a class="btn btn-outline-light rounded-pill px-4" href="index.php">
                    Dashboard
                </a>
            </div>
        </section>

        <section class="admins-summary-grid">
            <?php foreach ($summaryCards as $card): ?>
                <article class="admins-summary-card tone-<?php echo e($card['tone']); ?>">
                    <div class="summary-top">
                        <p class="summary-label"><?php echo e($card['label']); ?></p>
                        <div class="summary-dot">●</div>
                    </div>

                    <h2 class="summary-value">
                        <?php echo (int)$card['value']; ?>
                    </h2>

                    <p class="summary-note">
                        <?php echo e($card['note']); ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="admins-panel">
            <div class="admins-panel-header">
                <div>
                    <h2>Administrator Accounts</h2>
                    <p>
                        <?php echo $adminResult ? (int)$adminResult->num_rows : 0; ?> admin account(s) found.
                    </p>
                </div>

                <a class="btn btn-smartstock rounded-pill px-4" href="add-admin.php">
                    Add Admin
                </a>
            </div>

            <div class="table-responsive">
                <table class="table admins-table align-middle">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($adminResult && $adminResult->num_rows > 0): ?>
                            <?php while ($row = $adminResult->fetch_assoc()): ?>
                                <?php
                                    $adminId = (int)$row['id'];
                                    $fullName = trim((string)($row['full_name'] ?? 'Admin User'));
                                    $initial = mb_substr($fullName !== '' ? $fullName : ($row['username'] ?? 'A'), 0, 1);
                                    $isCurrentAdmin = $currentAdminId > 0 && $currentAdminId === $adminId;
                                ?>

                                <tr>
                                    <td>
                                        <div class="admin-user-cell">
                                            <div class="admin-avatar">
                                                <?php echo e($initial); ?>
                                            </div>

                                            <div>
                                                <div class="admin-full-name">
                                                    <?php echo e($fullName); ?>
                                                </div>

                                                <div class="admin-id">
                                                    Admin ID: #<?php echo $adminId; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="username-pill">
                                            <?php echo e($row['username']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="email-text">
                                            <?php echo e($row['email'] ?? 'N/A'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo admin_role_badge($row['role'] ?? 'admin'); ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($row['created_at'])): ?>
                                            <span class="date-text">
                                                <?php echo e(date('M d, Y', strtotime($row['created_at']))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end">
                                        <div class="action-group">
                                            <a
                                                class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                                href="update-admin.php?id=<?php echo $adminId; ?>"
                                            >
                                                Edit
                                            </a>

                                            <?php if ($isCurrentAdmin): ?>
                                                <span class="current-account-pill">
                                                    Current Account
                                                </span>
                                            <?php else: ?>
                                                <form method="post" action="delete-admin.php" onsubmit="return confirm('Delete this admin? This action cannot be undone.')">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="id" value="<?php echo $adminId; ?>">

                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="admins-empty-state">
                                        <h3>No administrator accounts found</h3>
                                        <p>Create an administrator account to manage SmartStock access.</p>

                                        <a class="btn btn-smartstock rounded-pill px-4" href="add-admin.php">
                                            Add Admin
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
