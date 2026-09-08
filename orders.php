<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("orders.php"));
    exit();
}

if ($_SESSION['role'] !== 'customer') {
    $destination = $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'seller/manage_products.php';
    header("Location: $destination");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = '';

$notifications_stmt = $conn->prepare(
    "SELECT message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10"
);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result();
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = " . $user_id);

// Handle order cancellation (only allowed while status = 'pending')
if (isset($_POST['cancel_order'])) {
    $order_id = intval($_POST['order_id']);

    $check_stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $order_id, $user_id);
    $check_stmt->execute();
    $order_check = $check_stmt->get_result()->fetch_assoc();

    if ($order_check && $order_check['status'] === 'pending') {
        $cancel_stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
        $cancel_stmt->bind_param("i", $order_id);
        $cancel_stmt->execute();

        $message = "Order #$order_id has been cancelled.";
    } else {
        $message = "This order can no longer be cancelled.";
    }
}

$page_title = 'My Orders';
include 'includes/header.php';

$orders_stmt = $conn->prepare(
    "SELECT o.order_id, o.status, o.total_amount, o.order_date,
            a.line1, a.line2, a.city, a.postal_code, a.country
     FROM orders o
     JOIN addresses a ON o.address_id = a.address_id
     WHERE o.user_id = ?
     ORDER BY o.order_date DESC"
);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();
?>

<h1>My Orders</h1>

<?php if ($message): ?><p class="cart-message"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<?php if ($notifications->num_rows > 0): ?>
<section class="order-notifications">
    <h2>Order Updates</h2>
    <?php while ($notification = $notifications->fetch_assoc()): ?>
        <p class="cart-message">
            <?php echo htmlspecialchars($notification['message']); ?>
            <span class="muted"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
        </p>
    <?php endwhile; ?>
</section>
<?php endif; ?>

<?php if ($orders->num_rows === 0): ?>
    <p class="empty-state">You haven't placed any orders yet. <a href="index.php">Start shopping</a>.</p>
<?php else: ?>

<?php while ($order = $orders->fetch_assoc()):
    $oi_stmt = $conn->prepare(
        "SELECT oi.quantity, oi.unit_price, oi.subtotal, p.name, p.product_id
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ?"
    );
    $oi_stmt->bind_param("i", $order['order_id']);
    $oi_stmt->execute();
    $order_items = $oi_stmt->get_result();
?>
<div class="order-card">
    <div class="order-header">
        <div>
            <h3>Order #<?php echo $order['order_id']; ?></h3>
            <p class="muted"><?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?></p>
        </div>
        <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
    </div>

    <table class="cart-table">
        <thead>
            <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
            <?php while ($item = $order_items->fetch_assoc()): ?>
            <tr>
                <td><a href="product.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>৳<?php echo number_format($item['unit_price'], 2); ?></td>
                <td>৳<?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="order-footer">
        <p class="order-address">
            Shipping to: <?php echo htmlspecialchars($order['line1']); ?><?php echo $order['line2'] ? ', ' . htmlspecialchars($order['line2']) : ''; ?>,
            <?php echo htmlspecialchars($order['city']); ?>, <?php echo htmlspecialchars($order['country']); ?>
        </p>
        <div class="order-total-row">
            <span>Total: <strong>৳<?php echo number_format($order['total_amount'], 2); ?></strong></span>
            <?php if ($order['status'] === 'pending'): ?>
                <form method="POST" class="cancel-form">
                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                    <button type="submit" name="cancel_order">Cancel Order</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endwhile; ?>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
