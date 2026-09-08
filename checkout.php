<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php?redirect=" . urlencode("checkout.php"));
	exit();
}

if ($_SESSION['role'] === 'admin') {
	header("Location: admin/dashboard.php");
	exit();
}

$user_id = intval($_SESSION['user_id']);
$error = '';
$order_placed = false;
$placed_order_id = null;

$user_stmt = $conn->prepare("SELECT full_name, phone FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

$cart_stmt = $conn->prepare(
	"SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price, p.stock_qty
	 FROM cart_items ci
	 JOIN cart c ON ci.cart_id = c.cart_id
	 JOIN products p ON ci.product_id = p.product_id
	 WHERE c.user_id = ? AND p.status = 'active'"
);
$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();
$cart_rows = [];
$subtotal = 0;
while ($row = $cart_result->fetch_assoc()) {
	$row['line_total'] = $row['price'] * $row['quantity'];
	$subtotal += $row['line_total'];
	$cart_rows[] = $row;
}

$addresses_stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
$addresses_stmt->bind_param("i", $user_id);
$addresses_stmt->execute();
$addresses = $addresses_stmt->get_result();

$shipping_fee = ($subtotal >= 5000) ? 0 : 60;
$total = $subtotal + $shipping_fee;

if (isset($_POST['place_order'])) {
	$address_id = intval($_POST['address_id'] ?? 0);

	if (empty($cart_rows)) {
		$error = "Your cart is empty.";
	} elseif (trim($user['phone'] ?? '') === '') {
		$error = "Please add your phone number in your profile before placing an order.";
	} elseif ($address_id <= 0) {
		$error = "Please select a shipping address.";
	} else {
		$address_check = $conn->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
		$address_check->bind_param("ii", $address_id, $user_id);
		$address_check->execute();

		if ($address_check->get_result()->num_rows === 0) {
			$error = "Please select a valid shipping address.";
		} else {
			$conn->begin_transaction();
			try {
				// Stock is checked here but reserved/decreased only after admin approval.
				foreach ($cart_rows as $item) {
					if ($item['quantity'] > $item['stock_qty']) {
						throw new Exception("Only {$item['stock_qty']} of {$item['name']} is currently available.");
					}
				}

				$order_stmt = $conn->prepare(
					"INSERT INTO orders (user_id, address_id, status, total_amount) VALUES (?, ?, 'pending', ?)"
				);
				$order_stmt->bind_param("iid", $user_id, $address_id, $total);
				if (!$order_stmt->execute()) {
					throw new Exception("Could not create the order.");
				}
				$placed_order_id = $order_stmt->insert_id;

				$item_stmt = $conn->prepare(
					"INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)"
				);
				foreach ($cart_rows as $item) {
					$item_subtotal = $item['price'] * $item['quantity'];
					$item_stmt->bind_param("iiidd", $placed_order_id, $item['product_id'], $item['quantity'], $item['price'], $item_subtotal);
					$item_stmt->execute();
				}

				$clear_stmt = $conn->prepare(
					"DELETE ci FROM cart_items ci JOIN cart c ON ci.cart_id = c.cart_id WHERE c.user_id = ?"
				);
				$clear_stmt->bind_param("i", $user_id);
				$clear_stmt->execute();
				$conn->commit();
				$order_placed = true;
			} catch (Throwable $exception) {
				$conn->rollback();
				$error = $exception->getMessage();
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

<div class="page-heading">
	<p class="page-eyebrow">Make it yours</p>
	<h1>Checkout</h1>
	<p class="page-intro">Good technology. Delivered to your door.</p>
</div>

<?php if ($order_placed): ?>
	<div class="order-confirmation">
		<h2>Order Placed!</h2>
		<p>Thanks for your order — <strong>Order #<?php echo $placed_order_id; ?></strong> has been placed successfully.</p>
		<p>Order total: <strong>৳<?php echo number_format($total, 2); ?></strong></p>
		<a href="orders.php" class="btn-hero">View My Orders</a>
	</div>
<?php elseif (empty($cart_rows)): ?>
	<p class="empty-state">Your cart is empty. <a href="index.php">Continue shopping</a>.</p>
<?php else: ?>

	<ol class="checkout-steps" aria-label="Checkout progress">
		<li><span>01</span> Cart</li>
		<li aria-current="step"><span>02</span> Address &amp; review</li>
		<li><span>03</span> Confirmation</li>
	</ol>

	<?php if ($error): ?><p class="stock-warning-box"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

	<div class="checkout-layout">
		<div class="checkout-main">
			<section class="checkout-section">
				<form method="POST">
					<h2>Shipping Details</h2>
					<div class="filter-group">
						<label>Phone Number</label>
						<input type="text" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
						<?php if (empty($user['phone'])): ?><p class="muted">Add your phone number in <a href="profile.php">Profile</a>.</p><?php endif; ?>
					</div>

					<h2>Shipping Address</h2>
					<div class="address-options">
						<?php if ($addresses->num_rows > 0): ?>
							<?php while ($address = $addresses->fetch_assoc()): ?>
								<label class="address-option"><input type="radio" name="address_id" value="<?php echo (int) $address['address_id']; ?>" required>
								<span><?php echo htmlspecialchars($address['line1']); ?><?php echo $address['line2'] ? ', ' . htmlspecialchars($address['line2']) : ''; ?><br><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['country']); ?></span></label>
							<?php endwhile; ?>
						<?php else: ?>
							<p class="muted">No saved addresses. Please add an address in <a href="profile.php">Profile</a>.</p>
						<?php endif; ?>
					</div>
					<p class="muted">Need another address? Add it from <a href="profile.php">Profile</a>.</p>

					<?= $order_items_html ?>

					<button type="submit" name="place_order" class="btn-hero" <?php echo (empty($user['phone']) || empty($cart_rows)) ? 'disabled' : ''; ?>>Place Order</button>
				</form>
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
