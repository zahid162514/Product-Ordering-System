<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['super_admin']);

$error = "";

$formData = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'role' => 'manager',
];

$allowedRoles = ['super_admin', 'manager', 'inventory', 'support'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'manager');
    $plainPassword = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    $formData = [
        'full_name' => $fullName,
        'username' => $username,
        'email' => $email,
        'role' => $role,
    ];

    if ($fullName === '') {
        $error = "Full name is required.";
    } elseif ($username === '') {
        $error = "Username is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = "Please select a valid admin role.";
    } elseif (strlen($plainPassword) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($plainPassword !== $confirmPassword) {
        $error = "Password and confirm password do not match.";
    } else {
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO tbl_admin (full_name, username, email, password, role) 
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param("sssss", $fullName, $username, $email, $password, $role);

        if ($stmt->execute()) {
            header("Location: manage-admin.php");
            exit;
        }

        error_log("Add admin failed: " . $stmt->error);
        $error = "Unable to add admin. Username or email may already exist.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin | SmartStock</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        body.add-admin-page {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.07), transparent 28rem),
                #f8fafc;
            color: #0f172a;
        }

        .add-admin-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .add-admin-hero {
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

        .add-admin-eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .add-admin-title {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1;
            font-weight: 850;
            letter-spacing: -0.055em;
        }

        .add-admin-subtitle {
            max-width: 680px;
            margin: 12px 0 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .add-admin-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .add-admin-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 24px;
            align-items: start;
        }

        .admin-clean-card {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.07);
        }

        .admin-clean-card-header {
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .admin-clean-card-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 1.15rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .admin-clean-card-header p {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .admin-clean-card-body {
            padding: 24px;
        }

        .form-section-title {
            margin: 8px 0 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #0f172a;
            font-size: 0.86rem;
            font-weight: 850;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .form-label {
            color: #0f172a;
            font-size: 0.86rem;
            font-weight: 750;
        }

        .form-control {
            min-height: 44px;
            border-color: #d8e0ec;
            border-radius: 13px;
            font-size: 0.94rem;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.12);
        }

        .help-note {
            margin-top: 7px;
            color: #64748b;
            font-size: 0.82rem;
        }

        .admin-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            margin-top: 8px;
            border-top: 1px solid #e2e8f0;
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

        .access-info-card {
            position: sticky;
            top: 88px;
        }

        .access-icon {
            display: grid;
            place-items: center;
            width: 56px;
            height: 56px;
            margin-bottom: 18px;
            border-radius: 18px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 1.35rem;
            font-weight: 900;
        }

        .access-info-card h2 {
            margin: 0 0 10px;
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .access-info-card p {
            margin: 0;
            color: #64748b;
            line-height: 1.7;
            font-size: 0.92rem;
        }

        .access-rule-list {
            display: grid;
            gap: 12px;
            margin-top: 22px;
        }

        .access-rule {
            display: flex;
            gap: 10px;
            padding: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #f8fafc;
            color: #334155;
            font-size: 0.86rem;
            line-height: 1.45;
        }

        .access-rule strong {
            color: #0f172a;
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #2563eb;
            font-size: 0.82rem;
            font-weight: 750;
        }

        .password-wrapper .form-control {
            padding-right: 68px;
        }

        @media (max-width: 991.98px) {
            .add-admin-hero {
                flex-direction: column;
            }

            .add-admin-hero-actions {
                width: 100%;
            }

            .add-admin-hero-actions .btn {
                width: 100%;
            }

            .add-admin-layout {
                grid-template-columns: 1fr;
            }

            .access-info-card {
                position: static;
                order: -1;
            }
        }

        @media (max-width: 575.98px) {
            .add-admin-shell {
                padding: 20px 14px 48px;
            }

            .add-admin-hero {
                padding: 24px 18px;
                border-radius: 20px;
            }

            .admin-clean-card-body {
                padding: 18px;
            }

            .admin-form-actions {
                flex-direction: column-reverse;
            }

            .admin-form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="add-admin-page">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="add-admin-shell">
        <section class="add-admin-hero">
            <div>
                <span class="add-admin-eyebrow">Access Control</span>
                <h1 class="add-admin-title">Add Admin</h1>
                <p class="add-admin-subtitle">
                    Create a new administrator account for the SmartStock back office with secure login credentials.
                </p>
            </div>

            <div class="add-admin-hero-actions">
                <a class="btn btn-light rounded-pill px-4" href="manage-admin.php">
                    Manage Admins
                </a>

                <a class="btn btn-outline-light rounded-pill px-4" href="index.php">
                    Dashboard
                </a>
            </div>
        </section>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger shadow-sm border-0 mb-4">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <div class="add-admin-layout">
            <section class="admin-clean-card">
                <div class="admin-clean-card-header">
                    <h2>Administrator Details</h2>
                    <p>Enter account information carefully. Username and email must be unique.</p>
                </div>

                <div class="admin-clean-card-body">
                    <form method="post" class="row g-3">
                        <?php echo csrf_field(); ?>

                        <div class="col-12">
                            <div class="form-section-title">Profile Information</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="full_name">Full Name</label>
                            <input
                                class="form-control"
                                id="full_name"
                                type="text"
                                name="full_name"
                                value="<?php echo e($formData['full_name']); ?>"
                                placeholder="Example: Inventory Manager"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="username">Username</label>
                            <input
                                class="form-control"
                                id="username"
                                type="text"
                                name="username"
                                value="<?php echo e($formData['username']); ?>"
                                placeholder="Example: inventory"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="email">Email Address</label>
                            <input
                                class="form-control"
                                id="email"
                                type="email"
                                name="email"
                                value="<?php echo e($formData['email']); ?>"
                                placeholder="admin@example.com"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="role">Role</label>
                            <select class="form-select" id="role" name="role">
                                <?php foreach ($allowedRoles as $roleOption): ?>
                                    <option value="<?php echo e($roleOption); ?>" <?php if ($formData['role'] === $roleOption) echo 'selected'; ?>>
                                        <?php echo e(ucwords(str_replace('_', ' ', $roleOption))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-note">Super admins manage access. Other roles are scoped by workflow.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-section-title">Login Credentials</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>

                            <div class="password-wrapper">
                                <input
                                    class="form-control"
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                >

                                <button class="password-toggle" type="button" data-toggle-password="password">
                                    Show
                                </button>
                            </div>

                            <div class="help-note">Minimum 8 characters required.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="confirm_password">Confirm Password</label>

                            <div class="password-wrapper">
                                <input
                                    class="form-control"
                                    id="confirm_password"
                                    type="password"
                                    name="confirm_password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                >

                                <button class="password-toggle" type="button" data-toggle-password="confirm_password">
                                    Show
                                </button>
                            </div>

                            <div class="help-note">Must match the password field.</div>
                        </div>

                        <div class="col-12">
                            <div class="admin-form-actions">
                                <a class="btn btn-outline-secondary rounded-pill px-4" href="manage-admin.php">
                                    Cancel
                                </a>

                                <button class="btn btn-smartstock rounded-pill px-4" type="submit">
                                    Add Admin
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <aside class="admin-clean-card access-info-card">
                <div class="admin-clean-card-body">
                    <div class="access-icon">A</div>

                    <h2>Admin Access</h2>

                    <p>
                        New admin accounts can access the SmartStock back office. Add only trusted users who need product, order, inventory, or support management access.
                    </p>

                    <div class="access-rule-list">
                        <div class="access-rule">
                            <span>•</span>
                            <div>
                                <strong>Password safety:</strong>
                                Use at least 8 characters.
                            </div>
                        </div>

                        <div class="access-rule">
                            <span>•</span>
                            <div>
                                <strong>Unique login:</strong>
                                Username and email should not already exist.
                            </div>
                        </div>

                        <div class="access-rule">
                            <span>•</span>
                            <div>
                                <strong>Default role:</strong>
                                This form creates a standard admin account.
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-toggle-password');
                const input = document.getElementById(targetId);

                if (!input) {
                    return;
                }

                input.type = input.type === 'password' ? 'text' : 'password';
                button.textContent = input.type === 'password' ? 'Show' : 'Hide';
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
