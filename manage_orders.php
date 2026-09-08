<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$all_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// Admin can set any status (full override, unlike sellers)
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];

    if (in_array($new_status, $all_statuses)) {
        $current_stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ?");
        $current_stmt->bind_param("i", $order_id);
        $current_stmt->execute();
        $current_order = $current_stmt->get_result()->fetch_assoc();

        if ($current_order) {
            // Restock automatically if this transition is INTO cancelled
            if ($new_status === 'cancelled' && $current_order['status'] !== 'cancelled') {
                $conn->begin_transaction();
                $items_stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $items_stmt->bind_param("i", $order_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                while ($item = $items_result->fetch_assoc()) {
                    $restock_stmt = $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE product_id = ?");
                    $restock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                    $restock_stmt->execute();
                }
                $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                $update_stmt->bind_param("si", $new_status, $order_id);
                $update_stmt->execute();
                $conn->commit();
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
                $stmt->execute();
            }
            $message = "Order #$order_id updated to " . ucfirst($new_status) . ".";
        }
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

<h1>Platform Orders</h1>
<?php if ($message): ?><p class="cart-message"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

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
            <p class="muted"><?php echo htmlspecialchars($order['customer_name']); ?> · <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></p>
        </div>
        <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
    </div>

    <table class="cart-table">
        <thead><tr><th>Product</th><th>Seller</th><th>Qty</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars($item['seller_name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>৳<?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="order-footer">
        <span>Total: <strong>৳<?php echo number_format($order['total_amount'], 2); ?></strong></span>
        <form method="POST" class="status-update-form">
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
