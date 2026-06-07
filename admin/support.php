<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin_role(['manager', 'support']);

$allowedSupportStatuses = ['Open', 'In Progress', 'Waiting', 'Resolved'];
$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_support'])) {
    require_valid_csrf();

    $source = trim($_POST['source'] ?? '');
    $id = intval($_POST['message_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $assignedAdminId = intval($_POST['assigned_admin_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    $replyMessage = trim($_POST['reply_message'] ?? '');

    if (!in_array($source, ['contact', 'customer'], true) || $id <= 0 || !in_array($status, $allowedSupportStatuses, true)) {
        $errorMessage = "Invalid support update.";
    } else {
        $table = $source === 'contact' ? 'contact_messages' : 'customers_sms';
        $resolvedAtSql = $status === 'Resolved' ? "resolved_at = COALESCE(resolved_at, NOW())," : "resolved_at = NULL,";
        $repliedAtSql = $replyMessage !== '' ? "replied_at = COALESCE(replied_at, NOW())," : "";
        $assignedValue = $assignedAdminId > 0 ? $assignedAdminId : null;

        $stmt = $conn->prepare(
            "UPDATE $table
             SET status = ?, assigned_admin_id = ?, admin_notes = ?, reply_message = ?, $repliedAtSql $resolvedAtSql id = id
             WHERE id = ?"
        );
        $stmt->bind_param("sissi", $status, $assignedValue, $adminNotes, $replyMessage, $id);

        if ($stmt->execute()) {
            $successMessage = "Support message updated.";
        } else {
            $errorMessage = "Unable to update support message.";
        }
        $stmt->close();
    }
}

$statusFilter = trim($_GET['status'] ?? '');
$where = in_array($statusFilter, $allowedSupportStatuses, true) ? "WHERE status = ?" : "";
$countSql = "SELECT SUM(total) AS total FROM (
                SELECT COUNT(*) AS total FROM contact_messages $where
                UNION ALL
                SELECT COUNT(*) AS total FROM customers_sms $where
             ) counts";
$countStmt = $conn->prepare($countSql);
if ($where) {
    $countStmt->bind_param("ss", $statusFilter, $statusFilter);
}
$countStmt->execute();
$totalMessages = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$pagination = pagination_values($totalMessages, (int)($_GET['page'] ?? 1), 12);
$limit = $pagination['per_page'];
$offset = $pagination['offset'];

$sql = "SELECT source, id, name, email, subject, message, status, assigned_admin_id,
               admin_notes, reply_message, replied_at, resolved_at, created_at
        FROM (
            SELECT 'contact' AS source, id, name, email, NULL AS subject, message, status, assigned_admin_id,
                   admin_notes, reply_message, replied_at, resolved_at, submitted_at AS created_at
            FROM contact_messages
            " . ($where ? "WHERE status = ?" : "") . "
            UNION ALL
            SELECT 'customer' AS source, id, name, email, subject, message, status, assigned_admin_id,
                   admin_notes, reply_message, replied_at, resolved_at, created_at
            FROM customers_sms
            " . ($where ? "WHERE status = ?" : "") . "
        ) inbox
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($where) {
    $stmt->bind_param("ssii", $statusFilter, $statusFilter, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$messages = $stmt->get_result();

$admins = $conn->query("SELECT id, username FROM tbl_admin ORDER BY username ASC");
$adminOptions = [];
while ($admin = $admins->fetch_assoc()) {
    $adminOptions[] = $admin;
}

function support_date(?string $date): string
{
    return $date ? date('M d, Y h:i A', strtotime($date)) : 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Messages | SmartStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container-fluid admin-shell">
        <div class="admin-page-header">
            <div>
                <span class="admin-page-eyebrow">Support Inbox</span>
                <h1 class="admin-page-title h3 mb-1">Support Workflow</h1>
                <p class="text-secondary mb-0">Assign, track, reply to, and resolve contact and customer messages.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="export.php?type=support">Export CSV</a>
                <a class="btn btn-smartstock" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>

        <form method="get" class="card admin-surface-card mb-4">
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($allowedSupportStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php if ($statusFilter === $status) echo 'selected'; ?>>
                                <?php echo e($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-smartstock" type="submit">Filter</button>
                </div>
            </div>
        </form>

        <div class="card admin-surface-card">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0"><?php echo (int)$totalMessages; ?> message(s)</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Notes / Reply</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($messages && $messages->num_rows > 0): ?>
                            <?php while ($msg = $messages->fetch_assoc()): ?>
                                <tr>
                                    <td style="min-width: 280px;">
                                        <div class="fw-semibold"><?php echo e($msg['name'] ?: 'Unknown'); ?></div>
                                        <a href="mailto:<?php echo e($msg['email']); ?>"><?php echo e($msg['email'] ?: 'No email'); ?></a>
                                        <?php if (!empty($msg['subject'])): ?>
                                            <div class="small text-secondary"><?php echo e($msg['subject']); ?></div>
                                        <?php endif; ?>
                                        <div class="small text-secondary"><?php echo e($msg['source']); ?> · <?php echo e(support_date($msg['created_at'])); ?></div>
                                        <p class="mt-2 mb-0"><?php echo nl2br(e($msg['message'])); ?></p>
                                    </td>
                                    <td><?php echo e($msg['status']); ?></td>
                                    <td>
                                        <?php
                                            $assignedName = 'Unassigned';
                                            foreach ($adminOptions as $admin) {
                                                if ((int)$admin['id'] === (int)$msg['assigned_admin_id']) {
                                                    $assignedName = $admin['username'];
                                                    break;
                                                }
                                            }
                                            echo e($assignedName);
                                        ?>
                                    </td>
                                    <td style="min-width: 260px;">
                                        <div class="small"><strong>Notes:</strong> <?php echo e($msg['admin_notes'] ?: 'None'); ?></div>
                                        <div class="small mt-1"><strong>Reply:</strong> <?php echo e($msg['reply_message'] ?: 'None'); ?></div>
                                    </td>
                                    <td class="text-end" style="min-width: 360px;">
                                        <form method="post" class="row g-2 text-start">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="source" value="<?php echo e($msg['source']); ?>">
                                            <input type="hidden" name="message_id" value="<?php echo (int)$msg['id']; ?>">
                                            <div class="col-md-6">
                                                <select class="form-select form-select-sm" name="status">
                                                    <?php foreach ($allowedSupportStatuses as $status): ?>
                                                        <option value="<?php echo e($status); ?>" <?php if ($msg['status'] === $status) echo 'selected'; ?>>
                                                            <?php echo e($status); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select form-select-sm" name="assigned_admin_id">
                                                    <option value="0">Unassigned</option>
                                                    <?php foreach ($adminOptions as $admin): ?>
                                                        <option value="<?php echo (int)$admin['id']; ?>" <?php if ((int)$msg['assigned_admin_id'] === (int)$admin['id']) echo 'selected'; ?>>
                                                            <?php echo e($admin['username']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <input class="form-control form-control-sm" name="admin_notes" value="<?php echo e($msg['admin_notes']); ?>" placeholder="Internal notes">
                                            </div>
                                            <div class="col-12">
                                                <input class="form-control form-control-sm" name="reply_message" value="<?php echo e($msg['reply_message']); ?>" placeholder="Reply summary">
                                            </div>
                                            <div class="col-12 d-grid">
                                                <button class="btn btn-sm btn-outline-primary" type="submit" name="update_support">Save Workflow</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-secondary py-5">No support messages found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
                $paginationQuery = $_GET;
                unset($paginationQuery['page']);
                echo render_pagination('support.php', $pagination['page'], $pagination['total_pages'], $paginationQuery);
            ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
