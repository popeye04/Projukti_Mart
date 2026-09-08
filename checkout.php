<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("checkout.php"));
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Fetch cart items joined with live product data
$items_stmt = $conn->prepare(
    "SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price, p.stock_qty
     FROM cart_items ci
     JOIN cart c ON ci.cart_id = c.cart_id
     JOIN products p ON ci.product_id = p.product_id
     WHERE c.user_id = ?"
);
$items_stmt->bind_param("i", $user_id);
$items_stmt->execute();
$cart_result = $items_stmt->get_result();

$cart_rows = [];
$subtotal = 0;
while ($row = $cart_result->fetch_assoc()) {
    $row['line_total'] = $row['price'] * $row['quantity'];
    $subtotal += $row['line_total'];
    $cart_rows[] = $row;
}

if (empty($cart_rows)) {
    header("Location: cart.php");
    exit();
}

// Fetch saved addresses
$addr_stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addresses_result = $addr_stmt->get_result();
$addresses = [];
while ($a = $addresses_result->fetch_assoc()) {
    $addresses[] = $a;
}

$shipping_fee = ($subtotal >= 5000) ? 0 : 60;
$total = $subtotal + $shipping_fee;

$error = '';
$order_placed = false;
$placed_order_id = null;
$placed_total = 0;

// ---- Handle order placement ----
if (isset($_POST['place_order'])) {
    if (empty($addresses)) {
        $error = "Please add a shipping address before placing your order.";
    } else {
        $address_id = intval($_POST['address_id']);

        $own_check = $conn->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
        $own_check->bind_param("ii", $address_id, $user_id);
        $own_check->execute();

        if ($own_check->get_result()->num_rows === 0) {
            $error = "Invalid shipping address selected.";
        } else {
            $conn->begin_transaction();
            $stock_ok = true;
            $order_subtotal = 0;
            $locked_items = [];

            foreach ($cart_rows as $item) {
                // Lock the row so a simultaneous checkout by someone else can't
                // both pass the stock check for the same last unit
                $lock_stmt = $conn->prepare("SELECT stock_qty, price FROM products WHERE product_id = ? FOR UPDATE");
                $lock_stmt->bind_param("i", $item['product_id']);
                $lock_stmt->execute();
                $live = $lock_stmt->get_result()->fetch_assoc();

                if (!$live || $live['stock_qty'] < $item['quantity']) {
                    $stock_ok = false;
                    $error = "Sorry, \"{$item['name']}\" no longer has enough stock. Please update your cart.";
                    break;
                }

                $line_total = $live['price'] * $item['quantity'];
                $order_subtotal += $line_total;
                $locked_items[] = [
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $live['price'],
                    'subtotal'   => $line_total
                ];
            }

            if ($stock_ok) {
                $order_shipping = ($order_subtotal >= 5000) ? 0 : 60;
                $order_total = $order_subtotal + $order_shipping;

                $order_stmt = $conn->prepare(
                    "INSERT INTO orders (user_id, address_id, status, total_amount) VALUES (?, ?, 'pending', ?)"
                );
                $order_stmt->bind_param("iid", $user_id, $address_id, $order_total);
                $order_stmt->execute();
                $order_id = $order_stmt->insert_id;

                foreach ($locked_items as $li) {
                    $oi_stmt = $conn->prepare(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)"
                    );
                    $oi_stmt->bind_param(
                        "iiidd",
                        $order_id,
                        $li['product_id'],
                        $li['quantity'],
                        $li['unit_price'],
                        $li['subtotal']
                    );
                    $oi_stmt->execute();

                    $stock_stmt = $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?");
                    $stock_stmt->bind_param("ii", $li['quantity'], $li['product_id']);
                    $stock_stmt->execute();
                }

                // Clear the cart now that it's been converted into an order
                $conn->query("DELETE ci FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = $user_id");

                $conn->commit();
                $order_placed = true;
                $placed_order_id = $order_id;
                $placed_total = $order_total;
            } else {
                $conn->rollback();
            }
        }
    }
}

$page_title = 'Checkout';
include 'includes/header.php';

// Build the order-items table once, reused whether or not an address exists
ob_start();
?>
<h2>Order Items</h2>
<table class="cart-table">
    <thead>
        <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
        <?php foreach ($cart_rows as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo $row['quantity']; ?></td>
            <td>৳<?php echo number_format($row['price'], 2); ?></td>
            <td>৳<?php echo number_format($row['line_total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$order_items_html = ob_get_clean();
?>

<h1>Checkout</h1>

<?php if ($order_placed): ?>
    <div class="order-confirmation">
        <h2>Order Placed!</h2>
        <p>Thanks for your order — <strong>Order #<?php echo $placed_order_id; ?></strong> has been placed successfully.</p>
        <p>Total charged: <strong>৳<?php echo number_format($placed_total, 2); ?></strong></p>
        <a href="orders.php" class="btn-hero">View My Orders</a>
    </div>
<?php else: ?>

    <?php if ($error): ?><p class="stock-warning-box"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

    <div class="checkout-layout">
        <div class="checkout-main">
            <section class="checkout-section">
                <?php if (empty($addresses)): ?>
                    <h2>Shipping Address</h2>
                    <p class="empty-state">You don't have a saved address yet. <a href="profile.php">Add one here</a> before checking out.</p>
                    <?php echo $order_items_html; ?>
                <?php else: ?>
                    <form method="POST">
                        <h2>Shipping Address</h2>
                        <div class="address-options">
                            <?php foreach ($addresses as $addr): ?>
                            <label class="address-option">
                                <input type="radio" name="address_id" value="<?php echo $addr['address_id']; ?>" <?php echo $addr['is_default'] ? 'checked' : ''; ?>>
                                <span>
                                    <?php echo htmlspecialchars($addr['line1']); ?><?php echo $addr['line2'] ? ', ' . htmlspecialchars($addr['line2']) : ''; ?><br>
                                    <?php echo htmlspecialchars($addr['city']); ?><?php echo $addr['postal_code'] ? ', ' . htmlspecialchars($addr['postal_code']) : ''; ?>, <?php echo htmlspecialchars($addr['country']); ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <?php echo $order_items_html; ?>

                        <button type="submit" name="place_order" class="btn-hero">Place Order</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <aside class="checkout-summary">
            <h3>Order Summary</h3>
            <div class="summary-row"><span>Subtotal</span><span>৳<?php echo number_format($subtotal, 2); ?></span></div>
            <div class="summary-row"><span>Shipping</span><span><?php echo $shipping_fee === 0 ? 'Free' : '৳' . number_format($shipping_fee, 2); ?></span></div>
            <div class="summary-row total"><span>Total</span><span>৳<?php echo number_format($total, 2); ?></span></div>
        </aside>
    </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
