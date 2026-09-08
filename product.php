<?php
session_start();
require 'db.php';

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare(
    "SELECT p.*, c.category_name, u.username AS seller_username, u.full_name AS seller_full_name
     FROM products p
     JOIN categories c ON p.category_id = c.category_id
     JOIN users u ON p.seller_id = u.user_id
     WHERE p.product_id = ? AND p.status = 'active'"
);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: index.php");
    exit();
}

$page_title = $product['name'];
$cart_message = '';

// Handle "Add to Cart" submission (must happen before any HTML output)
if (isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?redirect=" . urlencode("product.php?id=$product_id"));
        exit();
    }

    if ($_SESSION['role'] === 'admin') {
        $cart_message = "Admin accounts cannot add products to a cart.";
    } else {
        $quantity = max(1, intval($_POST['quantity']));

        if ($quantity > $product['stock_qty']) {
            $cart_message = "Sorry, only {$product['stock_qty']} left in stock.";
        } else {
            $user_id = intval($_SESSION['user_id']);

        // Every customer has at most one cart row — find it, or create it
        $cart_stmt = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = ?");
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_row = $cart_stmt->get_result()->fetch_assoc();

        if ($cart_row) {
            $cart_id = $cart_row['cart_id'];
        } else {
            $insert_cart = $conn->prepare("INSERT INTO cart (user_id) VALUES (?)");
            $insert_cart->bind_param("i", $user_id);
            $insert_cart->execute();
            $cart_id = $insert_cart->insert_id;
        }

        // If this product is already in the cart, bump the quantity instead of duplicating
        $existing_stmt = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $existing_stmt->bind_param("ii", $cart_id, $product_id);
        $existing_stmt->execute();
        $existing_item = $existing_stmt->get_result()->fetch_assoc();

        if ($existing_item) {
            $new_qty = min($existing_item['quantity'] + $quantity, $product['stock_qty']);
            $update_stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
            $update_stmt->bind_param("ii", $new_qty, $existing_item['cart_item_id']);
            $update_stmt->execute();
        } else {
            $insert_item = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
            $insert_item->bind_param("iii", $cart_id, $product_id, $quantity);
            $insert_item->execute();
        }

            $cart_message = "Added to cart!";
        }
    }
}

// Handle review submission (must happen before any HTML output)
$review_error = '';
if (isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?redirect=" . urlencode("product.php?id=$product_id"));
        exit();
    }

    $uid = intval($_SESSION['user_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    // Never trust the form alone — re-verify eligibility server-side
    $verify_stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM order_items oi
         JOIN orders o ON oi.order_id = o.order_id
         WHERE oi.product_id = ? AND o.user_id = ? AND o.status = 'delivered'"
    );
    $verify_stmt->bind_param("ii", $product_id, $uid);
    $verify_stmt->execute();
    $eligible = $verify_stmt->get_result()->fetch_assoc()['cnt'] > 0;

    $dup_stmt = $conn->prepare("SELECT review_id FROM reviews WHERE product_id = ? AND user_id = ?");
    $dup_stmt->bind_param("ii", $product_id, $uid);
    $dup_stmt->execute();
    $already_submitted = $dup_stmt->get_result()->num_rows > 0;

    if (!$eligible) {
        $review_error = "You can only review products from a delivered order.";
    } elseif ($already_submitted) {
        $review_error = "You've already reviewed this product.";
    } elseif ($rating < 1 || $rating > 5) {
        $review_error = "Please select a valid rating.";
    } else {
        $insert_review = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $insert_review->bind_param("iiis", $product_id, $uid, $rating, $comment);
        $insert_review->execute();

        header("Location: product.php?id=$product_id#reviews");
        exit();
    }
}

include 'includes/header.php';

// Log this view (feeds the future recommendation logic — FR-10)
$viewer_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
$log_stmt = $conn->prepare("INSERT INTO product_views (user_id, product_id) VALUES (?, ?)");
$log_stmt->bind_param("ii", $viewer_id, $product_id);
$log_stmt->execute();

$specs_stmt = $conn->prepare("SELECT spec_key, spec_value FROM product_specs WHERE product_id = ?");
$specs_stmt->bind_param("i", $product_id);
$specs_stmt->execute();
$specs = $specs_stmt->get_result();

$images_stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$images_stmt->bind_param("i", $product_id);
$images_stmt->execute();
$images = $images_stmt->get_result();

// Only show reviews that haven't been flagged/hidden by an admin
$reviews_stmt = $conn->prepare(
    "SELECT r.rating, r.comment, r.created_at, u.username FROM reviews r
     JOIN users u ON r.user_id = u.user_id
     WHERE r.product_id = ? AND r.is_flagged = 0
     ORDER BY r.created_at DESC"
);
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result();

$avg_stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE product_id = ? AND is_flagged = 0");
$avg_stmt->bind_param("i", $product_id);
$avg_stmt->execute();
$avg_data = $avg_stmt->get_result()->fetch_assoc();

// Determine whether the logged-in user is eligible to leave a review
$has_delivered_order = false;
$already_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);

    $purchase_check = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM order_items oi
         JOIN orders o ON oi.order_id = o.order_id
         WHERE oi.product_id = ? AND o.user_id = ? AND o.status = 'delivered'"
    );
    $purchase_check->bind_param("ii", $product_id, $uid);
    $purchase_check->execute();
    $has_delivered_order = $purchase_check->get_result()->fetch_assoc()['cnt'] > 0;

    $reviewed_check = $conn->prepare("SELECT review_id FROM reviews WHERE product_id = ? AND user_id = ?");
    $reviewed_check->bind_param("ii", $product_id, $uid);
    $reviewed_check->execute();
    $already_reviewed = $reviewed_check->get_result()->num_rows > 0;
}
?>

<nav class="breadcrumb">
    <a href="index.php">Home</a> /
    <a href="category.php?category_id=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> /
    <span><?php echo htmlspecialchars($product['name']); ?></span>
</nav>

<div class="product-detail">
    <div class="product-gallery">
        <?php if ($images->num_rows === 0): ?>
            <?php $image_url = get_product_image_url('', $product['name']); ?>
            <img src="<?php echo htmlspecialchars($image_url ?: 'https://placehold.co/500x500?text=' . urlencode($product['name'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
        <?php else: while ($img = $images->fetch_assoc()): ?>
            <?php
            $image_url = get_product_image_url($img['image_url'], $product['name']);
            ?>
            <img src="<?php echo htmlspecialchars($image_url ?: 'https://placehold.co/500x500?text=' . urlencode($product['name'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
        <?php endwhile; endif; ?>
    </div>

    <div class="product-info">
        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
        <?php if ($product['brand']): ?>
            <p class="muted"><?php echo htmlspecialchars($product['brand']); ?> <?php echo htmlspecialchars($product['model']); ?></p>
        <?php endif; ?>
        <p class="seller-line">Sold by <strong><?php echo htmlspecialchars($product['seller_full_name'] ?: $product['seller_username']); ?></strong></p>

        <?php if ($avg_data['total_reviews'] > 0): ?>
            <p class="rating-summary">★ <?php echo number_format($avg_data['avg_rating'], 1); ?> (<?php echo $avg_data['total_reviews']; ?> reviews)</p>
        <?php else: ?>
            <p class="rating-summary muted">No reviews yet</p>
        <?php endif; ?>

        <p class="price">৳<?php echo number_format($product['price'], 2); ?></p>

       <?php if ($product['stock_qty'] > 0): ?>
            <p class="stock-badge in">In Stock (<?php echo $product['stock_qty']; ?> available)</p>
        <?php else: ?>
            <p class="stock-badge out">Out of Stock</p>
        <?php endif; ?>

        <?php if ($cart_message): ?><p class="cart-message"><?php echo htmlspecialchars($cart_message); ?></p><?php endif; ?>

        <?php if ($product['stock_qty'] > 0): ?>
        <form method="POST" class="add-to-cart-form">
            <label for="quantity">Qty</label>
            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock_qty']; ?>">
            <button type="submit" name="add_to_cart" class="btn-add">Add to Cart</button>
        </form>
        <?php endif; ?>

        <div class="product-description">
            <h3>Description</h3>
            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
        </div>

        <?php if ($specs->num_rows > 0): ?>
        <div class="product-specs">
            <h3>Specifications</h3>
            <table>
                <?php while ($spec = $specs->fetch_assoc()): ?>
                <tr>
                    <th><?php echo htmlspecialchars($spec['spec_key']); ?></th>
                    <td><?php echo htmlspecialchars($spec['spec_value']); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<section class="product-reviews" id="reviews">
    <h2>Customer Reviews</h2>
    <?php if ($reviews->num_rows === 0): ?>
        <p class="empty-state">No reviews yet for this product.</p>
    <?php else: while ($r = $reviews->fetch_assoc()): ?>
        <div class="review-card">
            <p class="review-rating">★ <?php echo $r['rating']; ?>/5 — <strong><?php echo htmlspecialchars($r['username']); ?></strong></p>
            <p class="review-comment"><?php echo htmlspecialchars($r['comment']); ?></p>
            <p class="review-date"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></p>
        </div>
    <?php endwhile; endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <p class="empty-state">
            <a href="login.php?redirect=<?php echo urlencode("product.php?id=$product_id"); ?>">Log in</a>
            to leave a review after your order is delivered.
        </p>
    <?php elseif ($already_reviewed): ?>
        <p class="empty-state">You've already reviewed this product. Thanks for your feedback!</p>
    <?php elseif (!$has_delivered_order): ?>
        <p class="empty-state">You can review this product once your order has been delivered.</p>
    <?php else: ?>
        <form method="POST" class="review-form">
            <h3>Write a Review</h3>
            <?php if ($review_error): ?><p class="stock-warning-box"><?php echo htmlspecialchars($review_error); ?></p><?php endif; ?>
            <div class="filter-group">
                <label for="rating">Rating</label>
                <select name="rating" id="rating" required>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Average</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Terrible</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="comment">Your Review</label>
                <textarea name="comment" id="comment" rows="4" required></textarea>
            </div>
            <button type="submit" name="submit_review" class="btn-filter">Submit Review</button>
        </form>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
