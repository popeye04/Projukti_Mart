<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle flag/unflag toggle
if (isset($_GET['toggle_flag'])) {
    $review_id = intval($_GET['toggle_flag']);
    $current_stmt = $conn->prepare("SELECT is_flagged FROM reviews WHERE review_id = ?");
    $current_stmt->bind_param("i", $review_id);
    $current_stmt->execute();
    $current = $current_stmt->get_result()->fetch_assoc();

    if ($current) {
        $new_flag = $current['is_flagged'] ? 0 : 1;
        $stmt = $conn->prepare("UPDATE reviews SET is_flagged = ? WHERE review_id = ?");
        $stmt->bind_param("ii", $new_flag, $review_id);
        $stmt->execute();
    }
    header("Location: moderate_reviews.php");
    exit();
}

$page_title = 'Moderate Reviews';
include '../includes/header.php';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT r.review_id, r.product_id, r.rating, r.comment, r.is_flagged, r.created_at,
               p.name AS product_name, u.username
        FROM reviews r
        JOIN products p ON r.product_id = p.product_id
        JOIN users u ON r.user_id = u.user_id";
if ($filter === 'flagged') {
    $sql .= " WHERE r.is_flagged = 1";
}
$sql .= " ORDER BY r.created_at DESC";
$reviews = $conn->query($sql);
?>

<h1>Moderate Reviews</h1>

<form method="GET" class="period-filter">
    <label for="filter">Show</label>
    <select name="filter" id="filter">
        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
        <option value="flagged" <?php echo $filter === 'flagged' ? 'selected' : ''; ?>>Flagged Only</option>
    </select>
    <button type="submit" class="btn-filter">Apply</button>
</form>

<?php if ($reviews->num_rows === 0): ?>
    <p class="empty-state">No reviews found.</p>
<?php else: ?>
    <?php while ($r = $reviews->fetch_assoc()): ?>
    <div class="admin-review-card">
        <p class="review-rating">
            ★ <?php echo $r['rating']; ?>/5 — <strong><?php echo htmlspecialchars($r['username']); ?></strong>
            on <a href="../product.php?id=<?php echo $r['product_id']; ?>"><?php echo htmlspecialchars($r['product_name']); ?></a>
            <?php if ($r['is_flagged']): ?><span class="status-badge status-cancelled">Flagged</span><?php endif; ?>
        </p>
        <p class="review-comment"><?php echo htmlspecialchars($r['comment']); ?></p>
        <p class="review-date"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></p>
        <a class="table-actions-link" href="moderate_reviews.php?toggle_flag=<?php echo $r['review_id']; ?>">
            <?php echo $r['is_flagged'] ? 'Unflag' : 'Flag as Inappropriate'; ?>
        </a>
    </div>
    <?php endwhile; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
