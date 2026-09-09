<?php
session_start();
require_once '../db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: /Project/login.php');
    exit;
}
$seller_id = (int) $_SESSION['user_id'];
$product_count = db_run('SELECT COUNT(*) AS total FROM products WHERE seller_id = ?', 'i', [$seller_id])->get_result()->fetch_assoc()['total'];
$summary = db_run("SELECT COUNT(DISTINCT o.order_id) AS order_count,
    COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.order_id END) AS pending_count,
    COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN oi.subtotal ELSE 0 END), 0) AS revenue
    FROM order_items oi JOIN products p ON p.product_id = oi.product_id
    JOIN orders o ON o.order_id = oi.order_id WHERE p.seller_id = ?", 'i', [$seller_id])->get_result()->fetch_assoc();
$page_title = 'Seller Dashboard';
include '../includes/header.php';
?>
<div class="page-heading dashboard-heading">
<p class="page-eyebrow">Seller workspace</p>
<h1>Seller Dashboard</h1>
<p class="muted page-intro">Welcome back, <?= h($_SESSION['username']) ?>.</p>
</div>
<div class="dashboard-stats">
    <div class="stat-card"><span class="stat-value"><?= (int) $product_count ?></span><span class="stat-label">Products Listed</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $summary['order_count'] ?></span><span class="stat-label">Orders Received</span></div>
    <div class="stat-card"><span class="stat-value">৳<?= number_format((float) $summary['revenue'], 2) ?></span><span class="stat-label">Order Revenue (excluding cancelled)</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int) $summary['pending_count'] ?></span><span class="stat-label">Pending Orders</span></div>
</div>
<div class="dashboard-links">
    <a class="dashboard-link" href="manage_products.php"><h3>Manage Products</h3><p>Add and update your listings.</p></a>
    <a class="dashboard-link" href="seller_orders.php"><h3>Incoming Orders</h3><p>Review and process orders containing your products.</p></a>
    <a class="dashboard-link" href="sales_summary.php"><h3>Sales Summary</h3><p>View units and revenue by period.</p></a>
</div>
<?php include '../includes/footer.php'; ?>
