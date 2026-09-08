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
				$order_stmt->bind_param("iid", $user_id, $address_id, $subtotal);
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
?>

<?php if ($order_placed): ?>
	<h1>Order Placed</h1>
	<p class="cart-message">Order #<?php echo $placed_order_id; ?> is pending admin approval. You will receive an update in My Orders.</p>
	<a class="btn-hero" href="orders.php">View My Orders</a>
<?php elseif (empty($cart_rows)): ?>
	<h1>Checkout</h1>
	<p class="empty-state">Your cart is empty. <a href="index.php">Continue shopping</a>.</p>
<?php else: ?>
	<h1>Checkout</h1>
	<?php if ($error): ?><p class="stock-warning-box"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

	<div class="checkout-layout">
		<section class="checkout-card">
			<h2>Shipping Details</h2>
			<div class="filter-group">
				<label>Phone Number</label>
				<input type="text" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
				<?php if (empty($user['phone'])): ?><p class="muted">Add your phone number in <a href="profile.php">Profile</a>.</p><?php endif; ?>
			</div>

			<form method="POST">
				<div class="filter-group">
					<label for="address_id">Shipping Address</label>
					<select name="address_id" id="address_id" required>
						<option value="">Select an address</option>
						<?php while ($address = $addresses->fetch_assoc()): ?>
							<option value="<?php echo $address['address_id']; ?>">
								<?php echo htmlspecialchars($address['line1'] . ', ' . $address['city'] . ', ' . $address['country']); ?>
							</option>
						<?php endwhile; ?>
					</select>
				</div>
				<p class="muted">Need another address? Add it from <a href="profile.php">Profile</a>.</p>
				<button type="submit" name="place_order" class="btn-hero" <?php echo empty($user['phone']) ? 'disabled' : ''; ?>>Place Order</button>
			</form>
		</section>

		<section class="checkout-card">
			<h2>Order Summary</h2>
			<?php foreach ($cart_rows as $item): ?>
				<p class="checkout-line">
					<span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['quantity']; ?></span>
					<strong>৳<?php echo number_format($item['line_total'], 2); ?></strong>
				</p>
			<?php endforeach; ?>
			<p class="checkout-total"><span>Total</span><strong>৳<?php echo number_format($subtotal, 2); ?></strong></p>
		</section>
	</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
