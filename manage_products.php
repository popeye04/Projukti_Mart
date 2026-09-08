<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../login.php");
    exit();
}

$seller_id = intval($_SESSION['user_id']);
$message = '';

// A product must belong to a leaf category (one with no subcategories of its own)
$leaf_categories_sql = "SELECT c.category_id, c.category_name FROM categories c
                         WHERE NOT EXISTS (SELECT 1 FROM categories sub WHERE sub.parent_category_id = c.category_id)
                         AND c.is_active = 1
                         ORDER BY c.category_name";

// Handle Add Product
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock_qty = intval($_POST['stock_qty']);

    $insert_stmt = $conn->prepare(
        "INSERT INTO products (category_id, seller_id, name, brand, model, description, price, stock_qty, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
    );
    $insert_stmt->bind_param("iissssdi", $category_id, $seller_id, $name, $brand, $model, $description, $price, $stock_qty);
    $insert_stmt->execute();
    $new_product_id = $insert_stmt->insert_id;

    foreach ($_POST['spec_key'] as $i => $key) {
        $key = trim($key);
        $value = trim($_POST['spec_value'][$i]);
        if ($key !== '' && $value !== '') {
            $spec_stmt = $conn->prepare("INSERT INTO product_specs (product_id, spec_key, spec_value) VALUES (?, ?, ?)");
            $spec_stmt->bind_param("iss", $new_product_id, $key, $value);
            $spec_stmt->execute();
        }
    }

    foreach ($_POST['image_url'] as $i => $url) {
        $url = trim($url);
        if ($url !== '') {
            $is_primary = ($i === 0) ? 1 : 0;
            $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, ?)");
            $img_stmt->bind_param("isi", $new_product_id, $url, $is_primary);
            $img_stmt->execute();
        }
    }

    $message = "Product added.";
}

// Handle Edit Product
if (isset($_POST['edit_product'])) {
    $product_id = intval($_POST['product_id']);

    $own_check = $conn->prepare("SELECT product_id FROM products WHERE product_id = ? AND seller_id = ?");
    $own_check->bind_param("ii", $product_id, $seller_id);
    $own_check->execute();

    if ($own_check->get_result()->num_rows > 0) {
        $name = trim($_POST['name']);
        $category_id = intval($_POST['category_id']);
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $stock_qty = intval($_POST['stock_qty']);

        $update_stmt = $conn->prepare(
            "UPDATE products SET category_id=?, name=?, brand=?, model=?, description=?, price=?, stock_qty=? WHERE product_id=?"
        );
        $update_stmt->bind_param("issssdii", $category_id, $name, $brand, $model, $description, $price, $stock_qty, $product_id);
        $update_stmt->execute();

        // Simplest correct approach: replace specs/images entirely with the resubmitted set
        $conn->query("DELETE FROM product_specs WHERE product_id = $product_id");
        $conn->query("DELETE FROM product_images WHERE product_id = $product_id");

        foreach ($_POST['spec_key'] as $i => $key) {
            $key = trim($key);
            $value = trim($_POST['spec_value'][$i]);
            if ($key !== '' && $value !== '') {
                $spec_stmt = $conn->prepare("INSERT INTO product_specs (product_id, spec_key, spec_value) VALUES (?, ?, ?)");
                $spec_stmt->bind_param("iss", $product_id, $key, $value);
                $spec_stmt->execute();
            }
        }

        foreach ($_POST['image_url'] as $i => $url) {
            $url = trim($url);
            if ($url !== '') {
                $is_primary = ($i === 0) ? 1 : 0;
                $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, ?)");
                $img_stmt->bind_param("isi", $product_id, $url, $is_primary);
                $img_stmt->execute();
            }
        }

        $message = "Product updated.";
    }
}

// Handle Activate/Deactivate toggle
if (isset($_GET['toggle_status'])) {
    $product_id = intval($_GET['toggle_status']);

    $own_check = $conn->prepare("SELECT status FROM products WHERE product_id = ? AND seller_id = ?");
    $own_check->bind_param("ii", $product_id, $seller_id);
    $own_check->execute();
    $product_row = $own_check->get_result()->fetch_assoc();

    if ($product_row) {
        $new_status = $product_row['status'] === 'active' ? 'inactive' : 'active';
        $toggle_stmt = $conn->prepare("UPDATE products SET status = ? WHERE product_id = ?");
        $toggle_stmt->bind_param("si", $new_status, $product_id);
        $toggle_stmt->execute();
    }
    header("Location: manage_products.php");
    exit();
}

$page_title = 'Manage Products';
include '../includes/header.php';

$leaf_categories = $conn->query($leaf_categories_sql);

$editing = null;
$editing_specs = [];
$editing_images = [];
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ? AND seller_id = ?");
    $edit_stmt->bind_param("ii", $edit_id, $seller_id);
    $edit_stmt->execute();
    $editing = $edit_stmt->get_result()->fetch_assoc();

    if ($editing) {
        $spec_stmt = $conn->prepare("SELECT spec_key, spec_value FROM product_specs WHERE product_id = ?");
        $spec_stmt->bind_param("i", $edit_id);
        $spec_stmt->execute();
        $editing_specs = $spec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $img_stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
        $img_stmt->bind_param("i", $edit_id);
        $img_stmt->execute();
        $editing_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$products_stmt = $conn->prepare(
    "SELECT p.*, c.category_name FROM products p
     JOIN categories c ON p.category_id = c.category_id
     WHERE p.seller_id = ?
     ORDER BY p.created_at DESC"
);
$products_stmt->bind_param("i", $seller_id);
$products_stmt->execute();
$products = $products_stmt->get_result();
?>

<h1>Manage Products</h1>
<?php if ($message): ?><p class="cart-message"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<section class="seller-form-card">
    <h2><?php echo $editing ? 'Edit Product' : 'Add New Product'; ?></h2>
    <form method="POST">
        <?php if ($editing): ?>
            <input type="hidden" name="product_id" value="<?php echo $editing['product_id']; ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="filter-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" required>
            </div>
            <div class="filter-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <?php
                    $leaf_categories->data_seek(0);
                    while ($cat = $leaf_categories->fetch_assoc()):
                    ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($editing['category_id']) && $editing['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($editing['brand'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="model">Model</label>
                <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($editing['model'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="price">Price (৳)</label>
                <input type="number" step="0.01" id="price" name="price" value="<?php echo htmlspecialchars($editing['price'] ?? ''); ?>" required>
            </div>
            <div class="filter-group">
                <label for="stock_qty">Stock Quantity</label>
                <input type="number" id="stock_qty" name="stock_qty" value="<?php echo htmlspecialchars($editing['stock_qty'] ?? '0'); ?>" required>
            </div>
        </div>

        <div class="filter-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($editing['description'] ?? ''); ?></textarea>
        </div>

        <fieldset class="spec-fieldset">
            <legend>Specifications</legend>
            <?php for ($i = 0; $i < 5; $i++):
                $spec_key = $editing_specs[$i]['spec_key'] ?? '';
                $spec_value = $editing_specs[$i]['spec_value'] ?? '';
            ?>
            <div class="spec-row">
                <input type="text" name="spec_key[]" placeholder="e.g. RAM" value="<?php echo htmlspecialchars($spec_key); ?>">
                <input type="text" name="spec_value[]" placeholder="e.g. 8GB" value="<?php echo htmlspecialchars($spec_value); ?>">
            </div>
            <?php endfor; ?>
        </fieldset>

        <fieldset class="spec-fieldset">
            <legend>Image URLs</legend>
            <?php for ($i = 0; $i < 3; $i++):
                $img_url = $editing_images[$i]['image_url'] ?? '';
            ?>
            <div class="filter-group">
                <input type="text" name="image_url[]" placeholder="https://..." value="<?php echo htmlspecialchars($img_url); ?>">
            </div>
            <?php endfor; ?>
            <p class="muted">Tip: use a placeholder image service like placehold.co while testing.</p>
        </fieldset>

        <button type="submit" name="<?php echo $editing ? 'edit_product' : 'add_product'; ?>" class="btn-filter">
            <?php echo $editing ? 'Update Product' : 'Add Product'; ?>
        </button>
        <?php if ($editing): ?><a href="manage_products.php" class="btn-cancel-edit">Cancel Edit</a><?php endif; ?>
    </form>
</section>

<section class="seller-product-list">
    <h2>Your Products</h2>
    <?php if ($products->num_rows === 0): ?>
        <p class="empty-state">You haven't listed any products yet.</p>
    <?php else: ?>
    <table class="cart-table">
        <thead>
            <tr><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php while ($p = $products->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['name']); ?></td>
                <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                <td>৳<?php echo number_format($p['price'], 2); ?></td>
                <td><?php echo $p['stock_qty']; ?></td>
                <td><span class="status-badge status-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                <td class="table-actions">
                    <a href="manage_products.php?edit=<?php echo $p['product_id']; ?>">Edit</a>
                    <a href="manage_products.php?toggle_status=<?php echo $p['product_id']; ?>">
                        <?php echo $p['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php include '../includes/footer.php'; ?>
