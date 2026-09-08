<?php
session_start();
require 'db.php';

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

$cat_stmt = $conn->prepare("SELECT category_id, category_name, is_active FROM categories WHERE category_id = ?");
$cat_stmt->bind_param("i", $category_id);
$cat_stmt->execute();
$category = $cat_stmt->get_result()->fetch_assoc();

if (!$category || !$category['is_active']) {
    header("Location: index.php");
    exit();
}

$page_title = $category['category_name'];
include 'includes/header.php';
$is_seller = isset($_SESSION['role']) && $_SESSION['role'] === 'seller';
$seller_id = $is_seller ? intval($_SESSION['user_id']) : 0;

// Subcategories under this one, if any
$sub_stmt = $conn->prepare("SELECT category_id, category_name FROM categories WHERE parent_category_id = ? AND is_active = 1");
$sub_stmt->bind_param("i", $category_id);
$sub_stmt->execute();
$subcategories = $sub_stmt->get_result();

// Products shown here come from this category AND any of its subcategories
$category_ids = [$category_id];
if ($subcategories->num_rows > 0) {
    $subcategories->data_seek(0);
    while ($sub = $subcategories->fetch_assoc()) {
        $category_ids[] = $sub['category_id'];
    }
    $subcategories->data_seek(0);
}

// Read filters from the querystring
$min_price = (isset($_GET['min_price']) && $_GET['min_price'] !== '') ? floatval($_GET['min_price']) : null;
$max_price = (isset($_GET['max_price']) && $_GET['max_price'] !== '') ? floatval($_GET['max_price']) : null;
$brand     = (isset($_GET['brand']) && $_GET['brand'] !== '') ? $_GET['brand'] : null;
$sort      = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$placeholders = implode(',', array_fill(0, count($category_ids), '?'));
$id_types = str_repeat('i', count($category_ids));

// Distinct brands available in this category (for the filter dropdown)
$brand_sql = "SELECT DISTINCT brand FROM products
              WHERE category_id IN ($placeholders) AND status = 'active' AND brand IS NOT NULL";
$brand_params = $category_ids;
$brand_types = $id_types;
if ($is_seller) {
    $brand_sql .= " AND seller_id = ?";
    $brand_params[] = $seller_id;
    $brand_types .= 'i';
}
$brand_sql .= " ORDER BY brand ASC";
$brand_stmt = $conn->prepare($brand_sql);
$brand_stmt->bind_param($brand_types, ...$brand_params);
$brand_stmt->execute();
$brands = $brand_stmt->get_result();

// Build the main product query
$sql = "SELECT product_id, name, brand, price, stock_qty,
           (SELECT image_url FROM product_images WHERE product_id = products.product_id ORDER BY is_primary DESC, image_id ASC LIMIT 1) AS image_url
    FROM products
        WHERE category_id IN ($placeholders) AND status = 'active'";
$params = $category_ids;
$param_types = $id_types;
if ($is_seller) {
    $sql .= " AND seller_id = ?";
    $params[] = $seller_id;
    $param_types .= 'i';
}

if ($min_price !== null) {
    $sql .= " AND price >= ?";
    $params[] = $min_price;
    $param_types .= 'd';
}
if ($max_price !== null) {
    $sql .= " AND price <= ?";
    $params[] = $max_price;
    $param_types .= 'd';
}
if ($brand !== null) {
    $sql .= " AND brand = ?";
    $params[] = $brand;
    $param_types .= 's';
}

switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    default:
        $sql .= " ORDER BY created_at DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$products = $stmt->get_result();
?>

<nav class="breadcrumb">
    <a href="index.php">Home</a> / <span><?php echo htmlspecialchars($category['category_name']); ?></span>
</nav>

<div class="category-layout">
    <aside class="filters">
        <?php if ($subcategories->num_rows > 0): ?>
        <div class="filter-group">
            <label>Subcategory</label>
            <ul class="subcat-list">
                <?php while ($sub = $subcategories->fetch_assoc()): ?>
                    <li><a href="category.php?category_id=<?php echo $sub['category_id']; ?>"><?php echo htmlspecialchars($sub['category_name']); ?></a></li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php endif; ?>

        <h3>Filters</h3>
        <form method="GET" action="category.php">
            <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">

            <div class="filter-group">
                <label>Price Range (৳)</label>
                <div class="price-inputs">
                    <input type="number" name="min_price" placeholder="Min" value="<?php echo htmlspecialchars($min_price ?? ''); ?>">
                    <input type="number" name="max_price" placeholder="Max" value="<?php echo htmlspecialchars($max_price ?? ''); ?>">
                </div>
            </div>

            <?php if ($brands->num_rows > 0): ?>
            <div class="filter-group">
                <label for="brand">Brand</label>
                <select name="brand" id="brand">
                    <option value="">All Brands</option>
                    <?php while ($b = $brands->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($b['brand']); ?>" <?php echo ($brand === $b['brand']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['brand']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <label for="sort">Sort by</label>
                <select name="sort" id="sort">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
            </div>

            <button type="submit" class="btn-filter">Apply Filters</button>
        </form>
    </aside>

    <section class="results">
        <div class="results-header">
            <h1><?php echo htmlspecialchars($category['category_name']); ?></h1>
        </div>

        <?php if ($products->num_rows === 0): ?>
            <p class="empty-state">No products found. Try adjusting your filters.</p>
        <?php else: ?>
        <div class="product-grid">
            <?php while ($p = $products->fetch_assoc()): ?>
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
                    <?php if ($p['stock_qty'] <= 0): ?>
                        <p class="stock-badge out">Out of Stock</p>
                    <?php endif; ?>
                    <a class="btn-add" href="product.php?id=<?php echo $p['product_id']; ?>">View Details</a>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
