<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id']);
$message = '';

// Handle status toggle (activate/suspend) — an admin can never suspend themselves
if (isset($_GET['toggle_status'])) {
    $user_id = intval($_GET['toggle_status']);

    if ($user_id === $admin_id) {
        $message = "You can't suspend your own account.";
    } else {
        $current_stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
        $current_stmt->bind_param("i", $user_id);
        $current_stmt->execute();
        $current = $current_stmt->get_result()->fetch_assoc();

        if ($current) {
            $new_status = $current['status'] === 'active' ? 'suspended' : 'active';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            $stmt->execute();
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
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        $stmt->execute();
        $message = "Role updated.";
    }
}

$page_title = 'Manage Users';
include '../includes/header.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

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
?>

<h1>Manage Users</h1>
<?php if ($message): ?><p class="cart-message"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<form method="GET" class="period-filter">
    <input type="text" name="search" placeholder="Search username or email" value="<?php echo htmlspecialchars($search); ?>">
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
            <td><?php echo htmlspecialchars($u['username']); ?></td>
            <td><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
            <td>
                <?php if ($u['user_id'] == $admin_id): ?>
                    <?php echo ucfirst($u['role']); ?> (you)
                <?php else: ?>
                <form method="POST" class="status-update-form">
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
            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
            <td class="table-actions">
                <?php if ($u['user_id'] != $admin_id): ?>
                    <a href="manage_users.php?toggle_status=<?php echo $u['user_id']; ?>">
                        <?php echo $u['status'] === 'active' ? 'Suspend' : 'Reactivate'; ?>
                    </a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>
