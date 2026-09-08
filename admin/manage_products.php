<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['seller', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$seller_id = intval($_SESSION['user_id']);
$is_admin = $_SESSION['role'] === 'admin';
$message = isset($_GET['updated']) ? 'Product updated.' : '';

function save_product_images($conn, $product_id, $image_urls, $uploaded_files)
{
    $image_index = 0;
    $upload_directory = dirname(__DIR__) . '/uploads/products';
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    // 1. Ensure directory exists with write permissions
    if (!is_dir($upload_directory)) {
        if (!mkdir($upload_directory, 0777, true)) {
            return 'Failed to create upload directory: ' . $upload_directory;
        }
    }

    if (!is_writable($upload_directory)) {
        return 'The upload folder is not writable: ' . $upload_directory;
    }

    // 2. Fallback for manual image URLs if no file is uploaded
    $has_upload = isset($uploaded_files['error']) && is_array($uploaded_files['error'])
        && in_array(UPLOAD_ERR_OK, $uploaded_files['error'], true);

    if (!$has_upload && !empty($image_urls)) {
        foreach ($image_urls as $image_url) {
            $image_url = trim($image_url);
            if ($image_url !== '') {
                $is_primary = ($image_index === 0) ? 1 : 0;
                $image_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, ?)");
                $image_stmt->bind_param("isi", $product_id, $image_url, $is_primary);
                $image_stmt->execute();
                $image_index++;
            }
        }
    }

    if (!isset($uploaded_files['error']) || !is_array($uploaded_files['error'])) {
        return '';
    }

    // 3. Process each uploaded file
    foreach ($uploaded_files['error'] as $file_index => $upload_error) {
        if ($upload_error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($upload_error !== UPLOAD_ERR_OK) {
            if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
                return 'Image is too large. Upload an image under 2MB, or increase upload_max_filesize in php.ini.';
            }
            return 'Image upload failed with error code ' . $upload_error . '.';
        }

        if ($uploaded_files['size'][$file_index] > 5 * 1024 * 1024) {
            return 'Each image must be 5 MB or smaller.';
        }

        $temporary_path = $uploaded_files['tmp_name'][$file_index];
        $image_info = @getimagesize($temporary_path);
        $mime_type = $image_info['mime'] ?? '';
        if (!$image_info || !isset($allowed_types[$mime_type])) {
            return 'Only JPG, PNG, GIF, and WebP image files are allowed.';
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime_type];
        $destination = $upload_directory . '/' . $filename;

        if (move_uploaded_file($temporary_path, $destination)) {
            $image_url = 'uploads/products/' . $filename;
            $is_primary = ($image_index === 0) ? 1 : 0;
            $image_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, ?)");
            $image_stmt->bind_param("isi", $product_id, $image_url, $is_primary);
            if (!$image_stmt->execute()) {
                return 'Image database save failed: ' . $image_stmt->error;
            }
            $image_index++;
        } else {
            return 'Could not move the uploaded file into: ' . $upload_directory;
        }
    }

    return '';
}

// A product must belong to a leaf category (one with no subcategories of its own)
$leaf_categories_sql = "SELECT c.category_id, c.category_name FROM categories c
                         WHERE NOT EXISTS (SELECT 1 FROM categories sub WHERE sub.parent_category_id = c.category_id)
                         ORDER BY c.category_name";
// Handle Add Product
if (isset($_POST['form_action']) && $_POST['form_action'] === 'add_product') {
    $product_seller_id = $seller_id;
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
    $insert_stmt->bind_param("iissssdi", $category_id, $product_seller_id, $name, $brand, $model, $description, $price, $stock_qty);
    if (!$insert_stmt->execute()) {
        $message = "Product could not be added: " . $insert_stmt->error;
    }
    $new_product_id = $insert_stmt->insert_id;

    
foreach ($_POST['spec_key'] ?? [] as $i => $key) {
        $key = trim($key);
        $value = trim($_POST['spec_value'][$i]);
        if ($key !== '' && $value !== '') {
            $spec_stmt = $conn->prepare("INSERT INTO product_specs (product_id, spec_key, spec_value) VALUES (?, ?, ?)");
            $spec_stmt->bind_param("iss", $new_product_id, $key, $value);
            $spec_stmt->execute();
        }
    }

    $image_error = save_product_images($conn, $new_product_id, [], $_FILES['image_file'] ?? []);
    if ($image_error !== '') {
        $message = $image_error;
    }

    if ($insert_stmt->errno === 0 && $message === '') {
        header("Location: manage_products.php?updated=1");
        exit();
    }
}

// Handle Edit Product
if (isset($_POST['form_action']) && $_POST['form_action'] === 'edit_product') {
    $product_id = intval($_POST['product_id']);

    $own_check = $is_admin
        ? $conn->prepare("SELECT product_id FROM products WHERE product_id = ?")
        : $conn->prepare("SELECT product_id FROM products WHERE product_id = ? AND seller_id = ?");
    if (!$is_admin) {
        $own_check->bind_param("ii", $product_id, $seller_id);
    } else {
        $own_check->bind_param("i", $product_id);
    }
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
        if (!$update_stmt) {
            $message = "Product update could not start: " . $conn->error;
        } else {
            $update_stmt->bind_param("issssdii", $category_id, $name, $brand, $model, $description, $price, $stock_qty, $product_id);
            if (!$update_stmt->execute()) {
                $message = "Product could not be updated: " . $update_stmt->error;
            }
        }

        if ($message === '') {
            $has_new_images = isset($_FILES['image_file']['error'])
                && is_array($_FILES['image_file']['error'])
                && in_array(UPLOAD_ERR_OK, $_FILES['image_file']['error'], true);

            $conn->query("DELETE FROM product_specs WHERE product_id = $product_id");
            if ($has_new_images) {
                $conn->query("DELETE FROM product_images WHERE product_id = $product_id");
            }

            foreach ($_POST['spec_key'] ?? [] as $i => $key) {
                $key = trim($key);
                $value = trim($_POST['spec_value'][$i] ?? '');

                if ($key !== '' && $value !== '') {
                    $spec_stmt = $conn->prepare(
                        "INSERT INTO product_specs (product_id, spec_key, spec_value) VALUES (?, ?, ?)"
                    );
                    $spec_stmt->bind_param("iss", $product_id, $key, $value);
                    $spec_stmt->execute();
                }
            }

            $image_error = save_product_images(
                $conn,
                $product_id,
                [],
                $_FILES['image_file'] ?? []
            );
            if ($image_error !== '') {
                $message = $image_error;
            }

            if ($update_stmt->errno === 0 && $message === '') {
                header("Location: manage_products.php?edit=$product_id&updated=1");
                exit();
            }
        }
    } else {
        $message = "You are not allowed to edit that product.";
    }
}

// Handle Activate/Deactivate toggle
if (isset($_GET['toggle_status'])) {
    $product_id = intval($_GET['toggle_status']);

    $own_check = $is_admin
        ? $conn->prepare("SELECT status FROM products WHERE product_id = ?")
        : $conn->prepare("SELECT status FROM products WHERE product_id = ? AND seller_id = ?");
    if (!$is_admin) {
        $own_check->bind_param("ii", $product_id, $seller_id);
    } else {
        $own_check->bind_param("i", $product_id);
    }
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
    $edit_stmt = $is_admin
        ? $conn->prepare("SELECT * FROM products WHERE product_id = ?")
        : $conn->prepare("SELECT * FROM products WHERE product_id = ? AND seller_id = ?");
    if (!$is_admin) {
        $edit_stmt->bind_param("ii", $edit_id, $seller_id);
    } else {
        $edit_stmt->bind_param("i", $edit_id);
    }
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
     " . ($is_admin ? "WHERE 1=1" : "WHERE p.seller_id = ?") . "
     ORDER BY p.created_at DESC"
);
if (!$is_admin) {
    $products_stmt->bind_param("i", $seller_id);
}
$products_stmt->execute();
$products = $products_stmt->get_result();
$sellers = $is_admin ? $conn->query("SELECT user_id, username, full_name FROM users WHERE role = 'seller' AND status = 'active' ORDER BY username") : null;
$seller_sales = null;
if (!$is_admin) {
    $seller_sales_stmt = $conn->prepare(
        "SELECT COALESCE(SUM(oi.quantity), 0) AS units_sold,
                COALESCE(SUM(oi.subtotal), 0) AS revenue,
                COUNT(DISTINCT o.order_id) AS order_count
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         JOIN orders o ON oi.order_id = o.order_id
         WHERE p.seller_id = ? AND o.status = 'delivered'"
    );
    $seller_sales_stmt->bind_param("i", $seller_id);
    $seller_sales_stmt->execute();
    $seller_sales = $seller_sales_stmt->get_result()->fetch_assoc();
}
?>

<main class="site-main">
    <div class="section-heading">
        <h1>Manage Products</h1>
    </div>

    <?php if (!$is_admin): ?>
    <div class="dashboard-stats">
        <div class="stat-card">
            <span class="stat-value"><?php echo $products->num_rows; ?></span>
            <span class="stat-label">Your Products</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo $seller_sales['units_sold']; ?></span>
            <span class="stat-label">Delivered Units</span>
        </div>
        <div class="stat-card">
            <span class="stat-value">৳<?php echo number_format($seller_sales['revenue'], 2); ?></span>
            <span class="stat-label">Your Revenue</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?php echo $seller_sales['order_count']; ?></span>
            <span class="stat-label">Delivered Orders</span>
        </div>
    </div>

    <div class="dashboard-links dashboard-primary-links">
        <a class="dashboard-link" href="seller_orders.php">
            <h3>Seller Orders</h3>
            <p>View orders containing your products.</p>
        </a>
        <a class="dashboard-link" href="sales_summary.php">
            <h3>Sales Summary</h3>
            <p>Review your delivered sales.</p>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($message): ?>
    <p class="cart-message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <!-- Add/Edit Product Form -->
    <section class="seller-form-card">
        <h2><?php echo $editing ? 'Edit Product' : 'Add New Product'; ?></h2>
        
        <form method="POST" enctype="multipart/form-data">
            <?php if ($editing): ?>
                <input type="hidden" name="product_id" value="<?php echo $editing['product_id']; ?>">
                <input type="hidden" name="form_action" value="edit_product">
            <?php else: ?>
                <input type="hidden" name="form_action" value="add_product">
            <?php endif; ?>

            <!-- Basic Product Info -->
            <div class="form-grid">
                <div class="filter-group">
                    <label for="name">Product Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" required>
                </div>

                <div class="filter-group">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
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
                    <label for="price">Price (৳) <span class="required">*</span></label>
                    <input type="number" step="0.01" id="price" name="price" value="<?php echo htmlspecialchars($editing['price'] ?? ''); ?>" required>
                </div>

                <div class="filter-group">
                    <label for="stock_qty">Stock Quantity <span class="required">*</span></label>
                    <input type="number" id="stock_qty" name="stock_qty" value="<?php echo htmlspecialchars($editing['stock_qty'] ?? '0'); ?>" required>
                </div>
            </div>

            <!-- Description -->
            <div class="filter-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($editing['description'] ?? ''); ?></textarea>
            </div>

            <!-- Additional Specs -->
            <fieldset class="spec-fieldset">
                <legend>Additional Descriptions</legend>
                <div id="description-rows">
                    <?php $description_count = max(1, count($editing_specs)); for ($i = 0; $i < $description_count; $i++):
                        $spec_key = $editing_specs[$i]['spec_key'] ?? '';
                        $spec_value = $editing_specs[$i]['spec_value'] ?? '';
                    ?>
                    <div class="spec-row description-row">
                        <input type="text" name="spec_key[]" placeholder="Description title" value="<?php echo htmlspecialchars($spec_key); ?>">
                        <textarea name="spec_value[]" rows="2" placeholder="Write the description here..."><?php echo htmlspecialchars($spec_value); ?></textarea>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="button" class="btn-filter add-description" id="add-description">+ Add More Description</button>
            </fieldset>

            <!-- Product Images -->
            <fieldset class="spec-fieldset">
                <legend>Product Images</legend>
                <?php for ($i = 0; $i < 3; $i++):
                    $img_url = $editing_images[$i]['image_url'] ?? '';
                ?>
                <div class="filter-group">
                    <label for="image_file_<?php echo $i; ?>">Image <?php echo $i + 1; ?></label>
                    <input type="file" id="image_file_<?php echo $i; ?>" name="image_file[]" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if ($img_url): ?>
                        <span class="muted">Current: <?php echo htmlspecialchars(basename($img_url)); ?></span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
                <p class="muted">Each image must be 5 MB or smaller. Supported formats: JPG, PNG, GIF, WebP</p>
            </fieldset>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-filter">
                    <?php echo $editing ? 'Update Product' : 'Add Product'; ?>
                </button>
                <?php if ($editing): ?>
                    <a href="manage_products.php" class="btn-cancel-edit">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Products List -->
    <section class="seller-product-list">
        <h2 class="section-heading">Your Products</h2>
        
        <?php if ($products->num_rows === 0): ?>
            <p class="empty-state">You haven't listed any products yet.</p>
        <?php else: ?>
        
        <div class="table-responsive">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th class="table-actions-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $products->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span class="product-name"><?php echo htmlspecialchars($p['name']); ?></span>
                        </td>
                        <td>
                            <span class="muted"><?php echo htmlspecialchars($p['category_name']); ?></span>
                        </td>
                        <td>
                            <span class="price">৳<?php echo number_format($p['price'], 2); ?></span>
                        </td>
                        <td>
                            <span class="stock-qty"><?php echo $p['stock_qty']; ?></span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $p['status']; ?>">
                                <?php echo ucfirst($p['status']); ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <a href="manage_products.php?edit=<?php echo $p['product_id']; ?>" class="action-link">Edit</a>
                            <a href="manage_products.php?toggle_status=<?php echo $p['product_id']; ?>" class="action-link">
                                <?php echo $p['status'] === 'active'
                                    ? ($is_admin ? 'Remove' : 'Deactivate')
                                    : 'Activate'; ?>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </section>
</main>

<?php include '../includes/footer.php'; ?>

<style>
    .form-actions {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-top: 20px;
    }

    .product-name {
        font-weight: 600;
        color: var(--color-text);
    }

    .price {
        font-family: var(--font-display);
        font-weight: 700;
        color: var(--color-accent-dark);
    }

    .stock-qty {
        color: var(--color-text);
        font-weight: 500;
    }

    .action-link {
        color: var(--color-accent-dark);
        font-size: 13px;
        font-weight: 500;
        transition: color 160ms ease;
    }

    .action-link:hover {
        color: var(--color-accent);
    }

    .table-responsive {
        overflow-x: auto;
    }

    .table-actions-header {
        text-align: right;
    }

    .required {
        color: #DC2626;
    }

    .add-description {
        margin-top: 10px;
    }
</style>

<script>
    document.getElementById('add-description')?.addEventListener('click', function () {
        const rows = document.getElementById('description-rows');
        const row = document.createElement('div');
        row.className = 'spec-row description-row';
        row.innerHTML = '<input type="text" name="spec_key[]" placeholder="Description title" style="width: 100%;">' +
            '<textarea name="spec_value[]" rows="2" placeholder="Write the description here..." style="width: 100%;"></textarea>';
        rows.appendChild(row);
    });
</script>
