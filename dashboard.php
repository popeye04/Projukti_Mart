<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_count = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'customer'")->fetch_assoc()['cnt'];
$seller_count = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'seller'")->fetch_assoc()['cnt'];
$product_count = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE status = 'active'")->fetch_assoc()['cnt'];
$order_count = $conn->query("SELECT COUNT(*) AS cnt FROM orders")->fetch_assoc()['cnt'];
$revenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS total FROM orders WHERE status != 'cancelled'")->fetch_assoc()['total'];
$flagged_count = $conn->query("SELECT COUNT(*) AS cnt FROM reviews WHERE is_flagged = 1")->fetch_assoc()['cnt'];

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>

<h1>Admin Dashboard</h1>
<p class="muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>.</p>

<div class="dashboard-stats">
    <div class="stat-card">
        <span class="stat-value"><?php echo $user_count; ?></span>
        <span class="stat-label">Customers</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?php echo $seller_count; ?></span>
        <span class="stat-label">Sellers</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?php echo $product_count; ?></span>
        <span class="stat-label">Active Products</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?php echo $order_count; ?></span>
        <span class="stat-label">Total Orders</span>
    </div>
    <div class="stat-card">
        <span class="stat-value">৳<?php echo number_format($revenue, 2); ?></span>
        <span class="stat-label">Platform Revenue</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?php echo $flagged_count; ?></span>
        <span class="stat-label">Flagged Reviews</span>
    </div>
</div>

<div class="dashboard-links">
    <a class="dashboard-link" href="manage_categories.php">
        <h3>Manage Categories</h3>
        <p>Create, edit, or remove categories and subcategories.</p>
    </a>
    <a class="dashboard-link" href="manage_users.php">
        <h3>Manage Users</h3>
        <p>Activate, suspend accounts, and assign roles.</p>
    </a>
    <a class="dashboard-link" href="manage_orders.php">
        <h3>Platform Orders</h3>
        <p>View all orders across the marketplace.</p>
    </a>
    <a class="dashboard-link" href="moderate_reviews.php">
        <h3>Moderate Reviews</h3>
        <p>Flag or hide inappropriate reviews.</p>
    </a>
</div>

<?php include '../includes/footer.php'; ?>
