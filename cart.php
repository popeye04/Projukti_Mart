<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("cart.php"));
    exit();
}

if ($_SESSION['role'] === 'admin') {
    header("Location: admin/dashboard.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Handle quantity updates
if (isset($_POST['update_cart']) && isset($_POST['quantity'])) {
    foreach ($_POST['quantity'] as $cart_item_id => $qty) {
        $cart_item_id = intval($cart_item_id);
        $qty = intval($qty);

        // Confirm this cart item actually belongs to this user before touching it
        $check_stmt = $conn->prepare(
            "SELECT ci.cart_item_id, p.stock_qty FROM cart_items ci
             JOIN cart c ON ci.cart_id = c.cart_id
             JOIN products p ON ci.product_id = p.product_id
             WHERE ci.cart_item_id = ? AND c.user_id = ?"
        );
        $check_stmt->bind_param("ii", $cart_item_id, $user_id);
        $check_stmt->execute();
        $item = $check_stmt->get_result()->fetch_assoc();

        if ($item) {
            $qty = max(1, min($qty, $item['stock_qty']));
            $update_stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
            $update_stmt->bind_param("ii", $qty, $cart_item_id);
            $update_stmt->execute();
        }
    }
}

// Handle item removal
if (isset($_GET['remove'])) {
    $cart_item_id = intval($_GET['remove']);
    $remove_stmt = $conn->prepare(
        "DELETE ci FROM cart_items ci
         JOIN cart c ON ci.cart_id = c.cart_id
         WHERE ci.cart_item_id = ? AND c.user_id = ?"
    );
    $remove_stmt->bind_param("ii", $cart_item_id, $user_id);
    $remove_stmt->execute();
    header("Location: cart.php");
    exit();
}

$page_title = 'My Cart';
include 'includes/header.php';

$items_stmt = $conn->prepare(
    "SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price, p.stock_qty
     FROM cart_items ci
     JOIN cart c ON ci.cart_id = c.cart_id
     JOIN products p ON ci.product_id = p.product_id
     WHERE c.user_id = ?
     ORDER BY ci.cart_item_id DESC"
);
$items_stmt->bind_param("i", $user_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

$subtotal = 0;
$cart_rows = [];
while ($row = $items->fetch_assoc()) {
    $row['line_total'] = $row['price'] * $row['quantity'];
    $subtotal += $row['line_total'];
    $cart_rows[] = $row;
}
?>

<div class="page-heading">
    <p class="page-eyebrow">Your next upgrade</p>
    <h1>My Cart</h1>
    <p class="page-intro">A few good choices. One great setup.</p>
</div>

<?php if (empty($cart_rows)): ?>
    <p class="empty-state">Your cart is empty. <a href="index.php">Continue shopping</a>.</p>
<?php else: ?>

<div class="cart-layout">
<div class="cart-content">
<form method="POST" class="cart-table-form">
    <table class="cart-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cart_rows as $row): ?>
            <tr>
                <td>
                    <a href="product.php?id=<?php echo $row['product_id']; ?>"><?php echo htmlspecialchars($row['name']); ?></a>
                    <?php if ($row['quantity'] > $row['stock_qty']): ?>
                        <p class="stock-warning">Only <?php echo $row['stock_qty']; ?> left in stock</p>
                    <?php endif; ?>
                </td>
                <td>৳<?php echo number_format($row['price'], 2); ?></td>
                <td>
                    <input
                        type="number"
                        name="quantity[<?php echo $row['cart_item_id']; ?>]"
                        value="<?php echo $row['quantity']; ?>"
                        min="1"
                        max="<?php echo $row['stock_qty']; ?>"
                    >
                </td>
                <td>৳<?php echo number_format($row['line_total'], 2); ?></td>
                <td><a class="remove-link" href="cart.php?remove=<?php echo $row['cart_item_id']; ?>">Remove</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="cart-actions">
        <button type="submit" name="update_cart" class="btn-filter">Update Cart</button>
    </div>
</form>
</div>

<div class="cart-summary">
    <p class="page-eyebrow">The details</p>
    <h2>Order summary</h2>
    <p>Subtotal <span>৳<?php echo number_format($subtotal, 2); ?></span></p>
    <a href="checkout.php" class="btn-hero">Proceed to Checkout</a>
    <p class="muted">Shipping calculated at checkout.</p>
</div>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
