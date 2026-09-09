<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id']);
$message = '';

if (isset($_POST['approve_seller'])) {
    $approved = db_run("UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'seller' AND status = 'pending_approval'", 'i', [(int) $_POST['approve_seller']]);
    $message = $approved->affected_rows ? 'Seller approved.' : 'No pending seller found.';
}

// Handle status toggle (activate/suspend) — an admin can never suspend themselves
if (isset($_POST['toggle_status'])) {
    $user_id = intval($_POST['toggle_status']);

    if ($user_id === $admin_id) {
        $message = "You can't suspend your own account.";
    } else {
        $current_stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
        $current_stmt->bind_param("i", $user_id);
        $current_stmt->execute();
        $current = $current_stmt->get_result()->fetch_assoc();

        if ($current && $current['status'] !== 'pending_approval') {
            $new_status = $current['status'] === 'active' ? 'suspended' : 'active';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            $stmt->execute();
            if ($new_status === 'suspended') { log_activity($conn, 'user_suspended', 'user', $user_id); }
            $message = $new_status === 'suspended' ? "Account suspended." : "Account reactivated.";
        }
    }
}

// Handle role change — same self-protection rule applies
if (isset($_POST['change_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['role'];

    if ($user_id === $admin_id) {
        $message = "You can't change your own role.";
    } elseif (in_array($new_role, ['customer', 'seller', 'admin'])) {
        $target = db_run('SELECT email FROM users WHERE user_id = ?', 'i', [$user_id])->get_result()->fetch_assoc();
        $admin_count = (int) db_run("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'")->get_result()->fetch_assoc()['total'];
        if ($new_role === 'admin' && (!$target || strtolower($target['email']) !== 'admin@projuktimart.com' || $admin_count > 0)) {
            $message = 'Only the single admin@projuktimart.com account may have the admin role.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            $stmt->execute();
            $message = "Role updated.";
        }
    }
}

$page_title = 'Manage Users';
include '../includes/header.php';

$search = input_text($_GET, 'search');
$role_filter = input_text($_GET, 'role');

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
if (in_array($role_filter, ['customer', 'seller', 'admin'])) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= 's';
}
$sql .= " ORDER BY user_id DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
$pending_sellers = db_run("SELECT user_id, username, full_name, email, created_at FROM users WHERE role = 'seller' AND status = 'pending_approval' ORDER BY created_at, user_id")->get_result();
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Platform workspace / People</p>
    <h1>Manage Users</h1>
    <p class="page-intro">Manage accounts, seller approvals and access.</p>
</div>
<?php if ($message): ?><p class="cart-message"><?php echo h($message); ?></p><?php endif; ?>

<section class="seller-form-card">
    <h2>Pending Seller Approvals</h2>
    <?php if (!$pending_sellers->num_rows): ?><p>No sellers awaiting approval.</p><?php else: ?>
    <table class="cart-table"><thead><tr><th>Seller</th><th>Email</th><th>Registered</th><th>Action</th></tr></thead><tbody>
    <?php while ($pending = $pending_sellers->fetch_assoc()): ?>
    <tr><td><?= h($pending['full_name'] ?: $pending['username']) ?></td><td><?= h($pending['email']) ?></td><td><?= h($pending['created_at']) ?></td>
        <td><form method="POST"><?= csrf_field() ?><button class="btn-filter" name="approve_seller" value="<?= (int) $pending['user_id'] ?>">Approve</button></form></td></tr>
    <?php endwhile; ?></tbody></table><?php endif; ?>
</section>

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
                <form method="POST" class="status-update-form"><?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
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
            <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
            <td class="table-actions">
                <?php if ($u['status'] === 'pending_approval'): ?>
                    Awaiting approval
                <?php elseif ($u['user_id'] != $admin_id): ?>
                    <form method="POST" class="inline-form"><?= csrf_field() ?><button name="toggle_status" class="btn-filter" value="<?= (int) $u['user_id'] ?>">
                        <?php echo $u['status'] === 'active' ? 'Suspend' : 'Reactivate'; ?>
                    </button></form>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>
