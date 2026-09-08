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

<?php if (isset($_SESSION['store_error'])): ?>
<p class="stock-warning-box"><?php echo htmlspecialchars($_SESSION['store_error']); ?></p>
<?php unset($_SESSION['store_error']); endif; ?>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow"><span class="status-dot" aria-hidden="true"></span> A little ahead of the everyday</span>
        <h1>Make room for<br>what’s <span>next.</span></h1>
        <p>From your next big idea to your everyday essentials. Discover mobiles, PCs, laptops, and the gear that makes it all happen.</p>
        <div class="hero-actions">
            <a href="#categories" class="btn-hero">Find your next upgrade <span aria-hidden="true">↗</span></a>
            <a href="#categories" class="hero-secondary">Explore the collection <span aria-hidden="true">→</span></a>
        </div>
        <div class="hero-meta"><span>Built for your world.</span><span>Priced in ৳. Made for Bangladesh.</span></div>
    </div>
    <div class="hero-visual" aria-hidden="true">
        <div class="visual-topline"><span>THE NEXT CHAPTER</span><span>PM / 01</span></div>
        <div class="device-scene">
            <div class="hero-orbit"></div>
            <div class="device-glow"></div>
            <div class="device-laptop">
                <div class="device-screen">
                    <div class="device-display"><span class="display-grid"></span><span class="display-orbit"></span><span class="display-mark display-word">P<span>m.</span></span><span class="display-caption">IDEAS. UNLIMITED.</span></div>
                </div>
                <div class="device-base"></div>
            </div>
        </div>
        <div class="visual-caption"><span>Less ordinary.<br><strong>More possibility.</strong></span><span class="visual-cross">+</span></div>
    </div>
</section>

<section id="categories" class="category-tiles">
    <div class="section-heading">
        <div><span class="eyebrow">Find your focus</span><h2>Good tech. Your way.</h2></div>
        <span class="section-index">01 / THE COLLECTION</span>
    </div>
    <div class="tile-grid">
        <?php if ($categories->num_rows === 0): ?>
            <p class="empty-state">Categories haven't been added yet.</p>
        <?php else: while ($cat = $categories->fetch_assoc()): ?>
            <a class="category-tile" href="category.php?category_id=<?php echo $cat['category_id']; ?>">
                <span class="tile-icon"><?php echo strtoupper(substr($cat['category_name'], 0, 1)); ?></span>
                <span class="tile-name"><?php echo htmlspecialchars($cat['category_name']); ?></span>
                <span class="tile-caption">Explore the collection</span>
                <span class="tile-arrow" aria-hidden="true">↗</span>
            </a>
        <?php endwhile; endif; ?>
    </div>
</section>

<section class="recommendations">
    <div class="section-heading"><div><span class="eyebrow">Worth a closer look</span><h2><?php echo htmlspecialchars($product_heading); ?></h2></div><span class="section-index">02 / DISCOVER MORE</span></div>

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
                <?php if (!empty($p['brand'])): ?>
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
