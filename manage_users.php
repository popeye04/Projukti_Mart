<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id']);
$message = '';

try {
    if (isset($_POST['toggle_status']) || isset($_POST['change_role'])) {
        $conn->begin_transaction();
        $user_id = (int) ($_POST['user_id'] ?? 0);

        if ($user_id === $admin_id) {
            throw new InvalidArgumentException(
                isset($_POST['toggle_status']) ? "You can't suspend your own account." : "You can't change your own role."
            );
        }

        $current = db_run('SELECT status FROM users WHERE user_id = ?', 'i', [$user_id])->get_result()->fetch_assoc();
        if (!$current) {
            throw new InvalidArgumentException('User not found.');
        }

        if (isset($_POST['toggle_status'])) {
            $new_status = $current['status'] === 'active' ? 'suspended' : 'active';
            db_run('UPDATE users SET status = ? WHERE user_id = ?', 'si', [$new_status, $user_id]);
        } else {
            $new_role = input_text($_POST, 'role');
            if (!in_array($new_role, ['customer', 'seller', 'admin'], true)) {
                throw new InvalidArgumentException('Choose a valid role.');
            }
            db_run('UPDATE users SET role = ? WHERE user_id = ?', 'si', [$new_role, $user_id]);
        }

        $conn->commit();
        header('Location: manage_users.php');
        exit;
    }
} catch (Throwable $exception) {
    $conn->rollback();
    $message = $exception instanceof InvalidArgumentException
        ? $exception->getMessage()
        : 'Could not update the user — please try again.';
}

$page_title = 'Manage Users';
include '../includes/header.php';

$search = input_text($_GET, 'search');
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT user_id, username, email, role, status, created_at FROM users WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if (in_array($role_filter, ['customer', 'seller', 'admin'], true)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}
$sql .= " ORDER BY user_id DESC";

$users = db_run($sql, $types, $params)->get_result();
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Platform workspace / Accounts</p>
    <h1>Manage Users</h1>
    <p class="page-intro">Keep an eye on who's on the platform.</p>
</div>
<?php if ($message): ?><p class="cart-message"><?php echo h($message); ?></p><?php endif; ?>

<form method="GET" class="period-filter">
    <input type="text" name="search" placeholder="Search username or email" value="<?php echo h($search); ?>">
    <select name="role">
        <option value="">All Roles</option>
        <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
        <option value="seller" <?php echo $role_filter === 'seller' ? 'selected' : ''; ?>>Seller</option>
        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
    </select>
    <button type="submit" class="btn-filter">Filter</button>
</form>

<table class="cart-table">
    <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
            <td><?php echo h($u['username']); ?></td>
            <td><?php echo h($u['email'] ?? '—'); ?></td>
            <td>
                <?php if ($u['user_id'] == $admin_id): ?>
                    <?php echo ucfirst($u['role']); ?> (you)
                <?php else: ?>
                <form method="POST" class="status-update-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                    <select name="role">
                        <option value="customer" <?php echo $u['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="seller" <?php echo $u['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                        <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                    <button type="submit" name="change_role" class="btn-filter">Save</button>
                </form>
                <?php endif; ?>
            </td>
            <td>
                <span class="status-badge status-<?php echo $u['status'] === 'active' ? 'active' : 'cancelled'; ?>">
                    <?php echo ucfirst($u['status']); ?>
                </span>
            </td>
            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
            <td class="table-actions">
                <?php if ($u['user_id'] != $admin_id): ?>
                <form method="POST" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                    <button type="submit" name="toggle_status" class="remove-link">
                        <?php echo $u['status'] === 'active' ? 'Suspend' : 'Reactivate'; ?>
                    </button>
                </form>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>
