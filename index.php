<?php
session_start();
require 'db.php';
$page_title = 'Home';
include 'includes/header.php';

// Top-level categories for the shop-by-category tiles
$categories = $conn->query(
    "SELECT category_id, category_name FROM categories WHERE parent_category_id IS NULL AND is_active = 1 ORDER BY category_name ASC"
);

$is_seller = isset($_SESSION['role']) && $_SESSION['role'] === 'seller';
$product_heading = $is_seller ? 'Your Products' : 'Trending Picks';

if ($is_seller) {
    $seller_id = intval($_SESSION['user_id']);
    $trending_stmt = $conn->prepare(
        "SELECT product_id, name, brand, price,
                (SELECT image_url FROM product_images WHERE product_id = products.product_id ORDER BY is_primary DESC, image_id ASC LIMIT 1) AS image_url
         FROM products
         WHERE status = 'active' AND seller_id = ?
         ORDER BY created_at DESC"
    );
    $trending_stmt->bind_param("i", $seller_id);
    $trending_stmt->execute();
    $trending = $trending_stmt->get_result();
} else {
    $trending = $conn->query(
        "SELECT product_id, name, brand, price,
                (SELECT image_url FROM product_images WHERE product_id = products.product_id ORDER BY is_primary DESC, image_id ASC LIMIT 1) AS image_url
         FROM products WHERE status = 'active' ORDER BY created_at DESC LIMIT 8"
    );
}
?>

<section class="hero">
    <div class="hero-text">
        <h1>Tech that keeps up with you.</h1>
        <p>Mobiles, PCs, and laptops — picked, priced, and ready to ship from Projukti Mart.</p>
        <a href="#categories" class="btn-hero">Start Browsing</a>
    </div>
</section>

<section id="categories" class="category-tiles">
    <h2>Shop by Category</h2>
    <div class="tile-grid">
        <?php if ($categories->num_rows === 0): ?>
            <p class="empty-state">Categories haven't been added yet.</p>
        <?php else: while ($cat = $categories->fetch_assoc()): ?>
            <a class="category-tile" href="category.php?category_id=<?php echo $cat['category_id']; ?>">
                <span class="tile-icon"><?php echo strtoupper(substr($cat['category_name'], 0, 1)); ?></span>
                <span class="tile-name"><?php echo htmlspecialchars($cat['category_name']); ?></span>
            </a>
        <?php endwhile; endif; ?>
    </div>
</section>

<section class="trending">
    <h2><?php echo $product_heading; ?></h2>
    <?php if ($trending->num_rows === 0): ?>
        <p class="empty-state"><?php echo $is_seller ? 'You have not added any active products yet.' : 'No products yet — the catalog is just getting started. Check back soon.'; ?></p>
    <?php else: ?>
    <div class="product-grid">
        <?php while ($p = $trending->fetch_assoc()): ?>
            <div class="product-card">
                <?php
                $image_url = get_product_image_url($p['image_url'], $p['name']);
                ?>
                <img
                    src="<?php echo htmlspecialchars($image_url ?: 'https://placehold.co/300x300?text=' . urlencode($p['name'])); ?>"
                    alt="<?php echo htmlspecialchars($p['name']); ?>"
                >
                <h3><?php echo htmlspecialchars($p['name']); ?></h3>
                <?php if ($p['brand']): ?>
                    <p class="muted"><?php echo htmlspecialchars($p['brand']); ?></p>
                <?php endif; ?>
                <p class="price">৳<?php echo number_format($p['price'], 2); ?></p>
                <a class="btn-add" href="product.php?id=<?php echo $p['product_id']; ?>">View Details</a>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
