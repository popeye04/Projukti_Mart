<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle flag/unflag toggle
if (isset($_POST['toggle_flag'])) {
    $review_id = intval($_POST['toggle_flag']);
    $current_stmt = $conn->prepare("SELECT is_flagged FROM reviews WHERE review_id = ?");
    $current_stmt->bind_param("i", $review_id);
    $current_stmt->execute();
    $current = $current_stmt->get_result()->fetch_assoc();

    if ($current) {
        $new_flag = $current['is_flagged'] ? 0 : 1;
        $stmt = $conn->prepare("UPDATE reviews SET is_flagged = ? WHERE review_id = ?");
        $stmt->bind_param("ii", $new_flag, $review_id);
        $stmt->execute();
        if ($new_flag) { log_activity($conn, 'review_flagged', 'review', $review_id); }
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
$reviews = db_run($sql)->get_result();
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Platform workspace / Community</p>
    <h1>Moderate Reviews</h1>
    <p class="page-intro">Keep product feedback useful and trustworthy.</p>
</div>

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
            ★ <?php echo $r['rating']; ?>/5 — <strong><?php echo h($r['username']); ?></strong>
            on <a href="../product.php?id=<?php echo $r['product_id']; ?>"><?php echo h($r['product_name']); ?></a>
            <?php if ($r['is_flagged']): ?><span class="status-badge status-cancelled">Flagged</span><?php endif; ?>
        </p>
        <p class="review-comment"><?php echo h($r['comment']); ?></p>
        <p class="review-date"><?php echo date('d M Y', strtotime($r['created_at'])); ?></p>
        <form method="POST" class="inline-form"><?= csrf_field() ?><button class="btn-filter" name="toggle_flag" value="<?= (int) $r['review_id'] ?>">
            <?php echo $r['is_flagged'] ? 'Unflag' : 'Flag as Inappropriate'; ?>
        </button></form>
    </div>
    <?php endwhile; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
