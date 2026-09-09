<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../login.php");
    exit();
}

$seller_id = intval($_SESSION['user_id']);
$period = isset($_GET['period']) ? $_GET['period'] : '30days';

switch ($period) {
    case '7days':
        $date_condition = "o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $date_condition = "o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'year':
        $date_condition = "o.order_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    default:
        $date_condition = "1=1";
        $period = 'all';
}

$page_title = 'Sales Summary';
include '../includes/header.php';

$summary_sql = "SELECT COALESCE(SUM(oi.quantity), 0) AS units_sold,
                        COALESCE(SUM(oi.subtotal), 0) AS revenue,
                        COUNT(DISTINCT o.order_id) AS order_count
                 FROM order_items oi
                 JOIN products p ON oi.product_id = p.product_id
                 JOIN orders o ON oi.order_id = o.order_id
                 WHERE p.seller_id = ? AND o.status != 'cancelled' AND $date_condition";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("i", $seller_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

$breakdown_sql = "SELECT p.name, SUM(oi.quantity) AS units_sold, SUM(oi.subtotal) AS revenue
                   FROM order_items oi
                   JOIN products p ON oi.product_id = p.product_id
                   JOIN orders o ON oi.order_id = o.order_id
                   WHERE p.seller_id = ? AND o.status != 'cancelled' AND $date_condition
                   GROUP BY p.product_id, p.name
                   ORDER BY revenue DESC";
$breakdown_stmt = $conn->prepare($breakdown_sql);
$breakdown_stmt->bind_param("i", $seller_id);
$breakdown_stmt->execute();
$breakdown = $breakdown_stmt->get_result();
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Seller workspace / Performance</p>
    <h1>Sales Summary</h1>
    <p class="page-intro">Your store's performance, at a glance.</p>
</div>

<form method="GET" class="period-filter">
    <label for="period">Period</label>
    <select name="period" id="period">
        <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
        <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last 12 Months</option>
        <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
    </select>
    <button type="submit" class="btn-filter">Apply</button>
</form>

<div class="dashboard-stats">
    <div class="stat-card">
        <span class="stat-value"><?php echo $summary['units_sold']; ?></span>
        <span class="stat-label">Units Sold</span>
    </div>
    <div class="stat-card">
        <span class="stat-value">৳<?php echo number_format($summary['revenue'], 2); ?></span>
        <span class="stat-label">Revenue</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?php echo $summary['order_count']; ?></span>
        <span class="stat-label">Orders</span>
    </div>
</div>

<h2 class="section-heading">By Product</h2>
<?php if ($breakdown->num_rows === 0): ?>
    <p class="empty-state">No sales in this period yet.</p>
<?php else: ?>
<table class="cart-table">
    <thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
    <tbody>
        <?php while ($row = $breakdown->fetch_assoc()): ?>
        <tr>
            <td><?php echo h($row['name']); ?></td>
            <td><?php echo $row['units_sold']; ?></td>
            <td>৳<?php echo number_format($row['revenue'], 2); ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
