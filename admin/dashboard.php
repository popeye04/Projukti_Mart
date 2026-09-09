<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_count = db_run("SELECT COUNT(*) AS cnt FROM users WHERE role = 'customer'")->get_result()->fetch_assoc()['cnt'];
$seller_count = db_run("SELECT COUNT(*) AS cnt FROM users WHERE role = 'seller'")->get_result()->fetch_assoc()['cnt'];
$product_count = db_run("SELECT COUNT(*) AS cnt FROM products WHERE status = 'active'")->get_result()->fetch_assoc()['cnt'];
$order_count = db_run("SELECT COUNT(*) AS cnt FROM orders")->get_result()->fetch_assoc()['cnt'];
$revenue = db_run("SELECT COALESCE(SUM(total_amount),0) AS total FROM orders WHERE status != 'cancelled'")->get_result()->fetch_assoc()['total'];
$flagged_count = db_run("SELECT COUNT(*) AS cnt FROM reviews WHERE is_flagged = 1")->get_result()->fetch_assoc()['cnt'];

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>

<div class="page-heading dashboard-heading">
<p class="page-eyebrow">Platform workspace</p>
<h1>Admin Dashboard</h1>
<p class="muted page-intro">Welcome back, <?php echo h($_SESSION['username']); ?>.</p>
</div>

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
    <a class="dashboard-link" href="activity.php"><h3>Activity Log</h3><p>Review sign-in failures, account changes, orders, and moderation events.</p></a>
    <a class="dashboard-link" href="manage_products.php"><h3>Manage All Products</h3><p>Edit or deactivate listings across all sellers.</p></a>
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
