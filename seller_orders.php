<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../login.php");
    exit();
}

$seller_id = intval($_SESSION['user_id']);
$message = '';

// Sellers move orders forward through the pipeline; cancellation stays a
// customer/admin action, and "pending" is set automatically at checkout.
$allowed_statuses = ['processing', 'shipped', 'delivered'];

if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];

    // Confirm this seller actually has at least one item in this order
    // before letting them touch its status (NFR-06)
    $own_check = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ? AND p.seller_id = ?"
    );
    $own_check->bind_param("ii", $order_id, $seller_id);
    $own_check->execute();
    $owns_item = $own_check->get_result()->fetch_assoc()['cnt'] > 0;

    if ($owns_item && in_array($new_status, $allowed_statuses)) {
        $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $update_stmt->bind_param("si", $new_status, $order_id);
        $update_stmt->execute();
        $message = "Order #$order_id status updated to " . ucfirst($new_status) . ".";
    } else {
        $message = "Unable to update that order.";
    }
}

$page_title = 'Incoming Orders';
include '../includes/header.php';

// Orders containing at least one of this seller's products
$orders_stmt = $conn->prepare(
    "SELECT DISTINCT o.order_id, o.status, o.order_date, u.username AS customer_name
     FROM orders o
     JOIN order_items oi ON o.order_id = oi.order_id
     JOIN products p ON oi.product_id = p.product_id
     JOIN users u ON o.user_id = u.user_id
     WHERE p.seller_id = ?
     ORDER BY o.order_date DESC"
);
$orders_stmt->bind_param("i", $seller_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();
?>

<h1>Incoming Orders</h1>
<?php if ($message): ?><p class="cart-message"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<?php if ($orders->num_rows === 0): ?>
    <p class="empty-state">No orders yet containing your products.</p>
<?php else: ?>

<?php while ($order = $orders->fetch_assoc()):
    // Only THIS seller's own line items within the order — never another
    // seller's products, even if they share the same order (NFR-06)
    $items_stmt = $conn->prepare(
        "SELECT oi.quantity, oi.unit_price, oi.subtotal, p.name
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ? AND p.seller_id = ?"
    );
    $items_stmt->bind_param("ii", $order['order_id'], $seller_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result();

    $seller_subtotal = 0;
    $item_rows = [];
    while ($item = $items->fetch_assoc()) {
        $seller_subtotal += $item['subtotal'];
        $item_rows[] = $item;
    }
?>
<div class="order-card">
    <div class="order-header">
        <div>
            <h3>Order #<?php echo $order['order_id']; ?></h3>
            <p class="muted">
                <?php echo htmlspecialchars($order['customer_name']); ?> ·
                <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?>
            </p>
        </div>
        <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
    </div>

    <table class="cart-table">
        <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($item_rows as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>৳<?php echo number_format($item['unit_price'], 2); ?></td>
                <td>৳<?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="order-footer">
        <span>Your items total: <strong>৳<?php echo number_format($seller_subtotal, 2); ?></strong></span>

        <?php if (in_array($order['status'], ['pending', 'processing', 'shipped'])): ?>
        <form method="POST" class="status-update-form">
            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
            <select name="status">
                <?php foreach ($allowed_statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                        <?php echo ucfirst($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status" class="btn-filter">Update</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
