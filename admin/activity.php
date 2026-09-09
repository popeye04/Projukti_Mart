<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$action = input_text($_GET, 'action');
$params = [];
$types = '';
$where = '';
if ($action !== '') {
    $where = ' WHERE log.action = ?';
    $params[] = $action;
    $types = 's';
}

$actions = db_run('SELECT DISTINCT action FROM activity_log ORDER BY action')->get_result();
$stmt = $conn->prepare("SELECT log.action, log.target_type, log.target_id, log.ip_address, log.logged_at, user.username
    FROM activity_log log LEFT JOIN users user ON user.user_id = log.user_id{$where}
    ORDER BY log.logged_at DESC, log.log_id DESC LIMIT 200");
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$logs = $stmt->get_result();

$page_title = 'Activity Log';
include '../includes/header.php';
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Platform workspace / Audit</p>
    <h1>Activity Log</h1>
    <p class="page-intro">Recent account and marketplace activity.</p>
</div>

<form method="GET" class="period-filter">
    <label for="action">Action</label>
    <select id="action" name="action">
        <option value="">All actions</option>
        <?php while ($row = $actions->fetch_assoc()): ?>
            <option value="<?= h($row['action']) ?>" <?= $action === $row['action'] ? 'selected' : '' ?>><?= h(ucwords(str_replace('_', ' ', $row['action']))) ?></option>
        <?php endwhile; ?>
    </select>
    <button type="submit" class="btn-filter">Filter</button>
</form>

<?php if ($logs->num_rows === 0): ?>
    <p class="empty-state">No activity has been recorded yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="cart-table">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Target</th><th>IP address</th></tr></thead>
            <tbody>
                <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= h(date('d M Y, H:i', strtotime($log['logged_at']))) ?></td>
                        <td><?= h($log['username'] ?? 'System / deleted user') ?></td>
                        <td><?= h(ucwords(str_replace('_', ' ', $log['action']))) ?></td>
                        <td><?= h(trim(($log['target_type'] ?? '') . ($log['target_id'] ? ' #' . $log['target_id'] : ''))) ?: '—' ?></td>
                        <td><?= h($log['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
