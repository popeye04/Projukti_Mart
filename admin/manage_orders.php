<?php
session_start();
require_once '../db.php';
require_once '../includes/order_actions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$all_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

if (isset($_POST['update_status'])) {
    try {
        change_order_status((int) ($_POST['order_id'] ?? 0), input_text($_POST, 'status'), 'admin');
        $message = 'Order updated.';
    } catch (Throwable $exception) {
        $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to update this order. Please try again.';
    }
}

$page_title = 'Platform Orders';
include '../includes/header.php';

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "SELECT o.order_id, o.status, o.total_amount, o.order_date, u.username AS customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE 1=1";
$params = [];
$types = '';

if (in_array($status_filter, $all_statuses)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
$sql .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Platform workspace / Orders</p>
    <h1>Platform Orders</h1>
    <p class="page-intro">Every order. One clear view.</p>
</div>
<?php if ($message): ?><p class="cart-message"><?php echo h($message); ?></p><?php endif; ?>

<form method="GET" class="period-filter">
    <label for="status">Status</label>
    <select name="status" id="status">
        <option value="">All</option>
        <?php foreach ($all_statuses as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-filter">Filter</button>
</form>

<?php if ($orders->num_rows === 0): ?>
    <p class="empty-state">No orders found.</p>
<?php else: ?>

<?php while ($order = $orders->fetch_assoc()):
    $items_stmt = $conn->prepare(
        "SELECT oi.quantity, oi.unit_price, oi.subtotal, p.name, u.username AS seller_name
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         JOIN users u ON p.seller_id = u.user_id
         WHERE oi.order_id = ?"
    );
    $items_stmt->bind_param("i", $order['order_id']);
    $items_stmt->execute();
    $items = $items_stmt->get_result();
?>
<div class="order-card">
    <div class="order-header">
        <div>
            <h3>Order #<?php echo $order['order_id']; ?></h3>
            <p class="muted"><?php echo h($order['customer_name']); ?> · <?php echo date('d M Y', strtotime($order['order_date'])); ?></p>
        </div>
        <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
    </div>

    <table class="cart-table">
        <thead><tr><th>Product</th><th>Seller</th><th>Qty</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
                <td><?php echo h($item['name']); ?></td>
                <td><?php echo h($item['seller_name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>৳<?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="order-footer">
        <?php render_order_history((int) $order['order_id']); ?>
        <span>Total: <strong>৳<?php echo number_format($order['total_amount'], 2); ?></strong></span>
        <form method="POST" class="status-update-form"><?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
            <select name="status">
                <?php foreach ($all_statuses as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status" class="btn-filter">Update</button>
        </form>
    </div>
</div>
<?php endwhile; ?>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
