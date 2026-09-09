<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// PHP discards POST/FILES when the entire multipart request exceeds this limit.
$post_limit_setting = trim(ini_get('post_max_size'));
$post_limit_bytes = (float) $post_limit_setting * match (strtolower(substr($post_limit_setting, -1))) {
    'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1
};
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $post_limit_bytes > 0 && (float) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $post_limit_bytes) {
    http_response_code(413);
    exit('The upload request is too large. Choose fewer images, each 5MB or smaller, and try again.');
}
require_once __DIR__ . '/../db.php';
$is_admin = defined('ADMIN_PRODUCT_OVERRIDE') && ADMIN_PRODUCT_OVERRIDE;
$override = $is_admin ? 1 : 0;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ($is_admin ? 'admin' : 'seller')) {
    header("Location: ../login.php");
    exit();
}

$seller_id = intval($_SESSION['user_id']);
$message = $_SESSION['product_notice'] ?? '';
unset($_SESSION['product_notice']);
$uploaded_paths = [];

// A product must belong to a leaf category (one with no subcategories of its own)
$leaf_categories_sql = "SELECT c.category_id, c.category_name FROM categories c
                         WHERE NOT EXISTS (SELECT 1 FROM categories sub WHERE sub.parent_category_id = c.category_id)
                         AND c.is_active = 1
                         ORDER BY c.category_name";

try {
    if (isset($_POST['add_product']) || isset($_POST['edit_product']) || isset($_POST['toggle_status'])) {
        $conn->begin_transaction();
        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (!isset($_POST['add_product'])) {
            $owned = db_run('SELECT status FROM products WHERE product_id = ? AND (? = 1 OR seller_id = ?) FOR UPDATE', 'iii', [$product_id, $override, $seller_id])->get_result()->fetch_assoc();
            if (!$owned) { throw new InvalidArgumentException('Product not found.'); }
        }
        if (isset($_POST['toggle_status'])) {
            $new_status = $owned['status'] === 'active' ? 'inactive' : 'active';
            db_run('UPDATE products SET status = ?, updated_at = NOW() WHERE product_id = ? AND (? = 1 OR seller_id = ?)', 'siii', [$new_status, $product_id, $override, $seller_id]);
        } else {
            $edit_status = input_text($_POST, 'status', $owned['status'] ?? 'active');
            if (!in_array($edit_status, ['active', 'inactive'], true)) { throw new InvalidArgumentException('Invalid status.'); }
            $name = input_text($_POST, 'name');
            $category_id = (int) ($_POST['category_id'] ?? 0);
            $brand = input_text($_POST, 'brand');
            $model = input_text($_POST, 'model');
            $description = input_text($_POST, 'description');
            $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
            $stock_qty = filter_var($_POST['stock_qty'] ?? '', FILTER_VALIDATE_INT);
            if ($name === '' || strlen($name) > 150 || strlen($brand) > 50 || strlen($model) > 50 || $price === false || !is_finite($price) || round($price, 2) <= 0 || $price > 99999999.99 || $stock_qty === false || $stock_qty < 0 || $stock_qty > 2147483647) {
                throw new InvalidArgumentException('Enter a name, positive price, and nonnegative whole-number stock within the allowed limits.');
            }
            $leaf = db_run('SELECT c.category_id FROM categories c LEFT JOIN categories parent ON parent.category_id = c.parent_category_id WHERE c.category_id = ? AND c.is_active = 1 AND (parent.category_id IS NULL OR parent.is_active = 1) AND NOT EXISTS (SELECT 1 FROM categories child WHERE child.parent_category_id = c.category_id) FOR UPDATE', 'i', [$category_id])->get_result()->fetch_assoc();
            if (!$leaf) { throw new InvalidArgumentException('Choose an active leaf category.'); }
            if (isset($_POST['add_product'])) {
                $product_owner_id = $seller_id;
                if ($is_admin) {
                    $product_owner_id = (int) ($_POST['seller_id'] ?? 0);
                    $owner = db_run("SELECT user_id FROM users WHERE user_id = ? AND role = 'seller' AND status = 'active' FOR UPDATE", 'i', [$product_owner_id])->get_result()->fetch_assoc();
                    if (!$owner) { throw new InvalidArgumentException('Choose an active seller for this product.'); }
                }
                $created = db_run("INSERT INTO products (category_id, seller_id, name, brand, model, description, price, stock_qty, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", 'iissssdis', [$category_id, $product_owner_id, $name, $brand, $model, $description, $price, $stock_qty, $edit_status]);
                $product_id = $created->insert_id;
            } else {
                db_run('UPDATE products SET category_id = ?, name = ?, brand = ?, model = ?, description = ?, price = ?, stock_qty = ?, updated_at = NOW() WHERE product_id = ? AND (? = 1 OR seller_id = ?)', 'issssdiiii', [$category_id, $name, $brand, $model, $description, $price, $stock_qty, $product_id, $override, $seller_id]);
                db_run('DELETE ps FROM product_specs ps JOIN products p ON p.product_id = ps.product_id WHERE ps.product_id = ? AND (? = 1 OR p.seller_id = ?)', 'iii', [$product_id, $override, $seller_id]);
            }
            $spec_keys = $_POST['spec_key'] ?? [];
            $spec_values = $_POST['spec_value'] ?? [];
            $image_urls = [];
            if (!is_array($spec_keys) || !is_array($spec_values) || count($spec_keys) > 30) {
                throw new InvalidArgumentException('Too many or invalid specifications.');
            }
            foreach ($spec_keys as $i => $unused) {
                $key = input_text($spec_keys, (string) $i);
                $value = input_text($spec_values, (string) $i);
                if (strlen($key) > 50 || strlen($value) > 100) { throw new InvalidArgumentException('Specification text is too long.'); }
                if (($key === '') !== ($value === '')) { throw new InvalidArgumentException('Each specification needs both a name and a value.'); }
                if ($key !== '' && $value !== '') {
                    db_run('INSERT INTO product_specs (product_id, spec_key, spec_value) SELECT product_id, ?, ? FROM products WHERE product_id = ? AND (? = 1 OR seller_id = ?)', 'ssiii', [$key, $value, $product_id, $override, $seller_id]);
                }
            }
            $uploads = $_FILES['product_images'] ?? null;
            $pending_uploads = [];
            if ($uploads !== null) {
                if (!is_array($uploads) || !is_array($uploads['error'] ?? null) || !is_array($uploads['name'] ?? null) || !is_array($uploads['tmp_name'] ?? null) || count($uploads['error']) > 3) {
                    throw new InvalidArgumentException('Choose up to 3 images.');
                }
                $allowed_types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
                $file_info = new finfo(FILEINFO_MIME_TYPE);
                foreach ($uploads['error'] as $i => $upload_error) {
                    if ($upload_error === UPLOAD_ERR_NO_FILE) { continue; }
                    if (in_array($upload_error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                        throw new InvalidArgumentException('Each image must be 5MB or smaller.');
                    }
                    if ($upload_error !== UPLOAD_ERR_OK) {
                        throw new InvalidArgumentException('Image upload failed. Please choose the files again and retry.');
                    }
                    $original_name = $uploads['name'][$i] ?? null;
                    $temporary_path = $uploads['tmp_name'][$i] ?? null;
                    if (!is_string($original_name) || !is_string($temporary_path) || !is_uploaded_file($temporary_path)) {
                        throw new InvalidArgumentException('Invalid image upload.');
                    }
                    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $file_size = filesize($temporary_path);
                    if ($file_size === false || $file_size > 5 * 1024 * 1024) {
                        throw new InvalidArgumentException('Each image must be 5MB or smaller.');
                    }
                    if (!isset($allowed_types[$extension]) || $file_info->file($temporary_path) !== $allowed_types[$extension] || @getimagesize($temporary_path) === false) {
                        throw new InvalidArgumentException('Only valid JPG, JPEG, PNG, and WebP images are allowed.');
                    }
                    $pending_uploads[] = ['temporary_path' => $temporary_path, 'extension' => $extension];
                }
            }
            if ($pending_uploads) {
                $upload_directory = dirname(__DIR__) . '/uploads';
                if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true) && !is_dir($upload_directory)) {
                    throw new InvalidArgumentException('Unable to create the uploads folder.');
                }
                if (!is_writable($upload_directory)) {
                    throw new InvalidArgumentException('The uploads folder is not writable.');
                }
                // New uploads replace the current product images; validate the whole batch first.
                $image_urls = [];
                if (!isset($_POST['add_product'])) {
                    db_run('DELETE pi FROM product_images pi JOIN products p ON p.product_id = pi.product_id WHERE pi.product_id = ? AND (? = 1 OR p.seller_id = ?)', 'iii', [$product_id, $override, $seller_id]);
                }
                foreach ($pending_uploads as $upload) {
                    do {
                        $filename = uniqid('', true) . '.' . $upload['extension'];
                        $destination = $upload_directory . '/' . $filename;
                    } while (file_exists($destination));
                    if (!move_uploaded_file($upload['temporary_path'], $destination)) {
                        throw new InvalidArgumentException('Unable to save an uploaded image. Please try again.');
                    }
                    $uploaded_paths[] = $destination;
                    $image_urls[] = 'uploads/' . $filename;
                }
            }
            $primary = 1;
            foreach ($image_urls as $i => $unused) {
                $url = input_text($image_urls, (string) $i);
                if ($url === '') { continue; }
                $local = preg_match('~^(?:/Project/)?(?:images|uploads)/[a-zA-Z0-9_./-]+$~', $url) && !str_contains($url, '..');
                if (strlen($url) > 255 || !$local) { throw new InvalidArgumentException('Use a valid local uploaded image path.'); }
                db_run('INSERT INTO product_images (product_id, image_url, is_primary) SELECT product_id, ?, ? FROM products WHERE product_id = ? AND (? = 1 OR seller_id = ?)', 'siiii', [$url, $primary, $product_id, $override, $seller_id]);
                $primary = 0;
            }
        }
        if ($is_admin && isset($_POST['edit_product'])) { db_run('UPDATE products SET status = ?, updated_at = NOW() WHERE product_id = ?', 'si', [$edit_status, $product_id]); }
        if (isset($_POST['add_product'])) { log_activity($conn, 'product_created', 'product', (int) $product_id); }
        elseif (($owned['status'] ?? '') !== 'inactive' && ((isset($_POST['toggle_status']) && $new_status === 'inactive') || ($is_admin && isset($_POST['edit_product']) && $edit_status === 'inactive'))) {
            log_activity($conn, 'product_deactivated', 'product', (int) $product_id);
        }
        $conn->commit();
        $_SESSION['product_notice'] = isset($_POST['add_product'])
            ? 'Product added successfully.'
            : (isset($_POST['edit_product']) ? 'Product updated successfully.' : 'Product status updated successfully.');
        header('Location: manage_products.php');
        exit;
    }
} catch (Throwable $exception) {
    $conn->rollback();
    // Only remove new files from this failed request; existing images stay intact.
    foreach ($uploaded_paths as $uploaded_path) {
        if (is_file($uploaded_path)) { unlink($uploaded_path); }
    }
    $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to save this product. Please try again.';
}

$page_title = 'Manage Products';
include __DIR__ . '/header.php';

$leaf_categories = db_run($leaf_categories_sql)->get_result();
$available_sellers = $is_admin
    ? db_run("SELECT user_id, username, full_name FROM users WHERE role = 'seller' AND status = 'active' ORDER BY username")->get_result()
    : null;

$editing = null;
$editing_specs = [];
$editing_images = [];
if (isset($_GET['edit']) || isset($_POST['edit_product'])) {
    $edit_id = isset($_POST['edit_product']) ? (int) ($_POST['product_id'] ?? 0) : intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ? AND (? = 1 OR seller_id = ?)");
    $edit_stmt->bind_param("iii", $edit_id, $override, $seller_id);
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

$is_editing = $editing !== null;
// Preserve authorized submitted fields on validation errors, including all specs.
if ($message !== '' && (isset($_POST['add_product']) || ($is_editing && isset($_POST['edit_product'])))) {
    foreach (['name', 'category_id', 'brand', 'model', 'description', 'price', 'stock_qty', 'status'] as $field) {
        $editing[$field] = input_text($_POST, $field, (string) ($editing[$field] ?? ''));
    }
    $editing_specs = [];
    $submitted_keys = is_array($_POST['spec_key'] ?? null) ? $_POST['spec_key'] : [];
    $submitted_values = is_array($_POST['spec_value'] ?? null) ? $_POST['spec_value'] : [];
    foreach (array_slice($submitted_keys, 0, 30, true) as $i => $unused) {
        $editing_specs[] = ['spec_key' => input_text($submitted_keys, (string) $i), 'spec_value' => input_text($submitted_values, (string) $i)];
    }
}

$products_stmt = $conn->prepare(
    "SELECT p.*, c.category_name, u.username AS seller_name FROM products p
     JOIN categories c ON p.category_id = c.category_id
     JOIN users u ON u.user_id = p.seller_id
     WHERE (? = 1 OR p.seller_id = ?)
     ORDER BY p.created_at DESC"
);
$products_stmt->bind_param("ii", $override, $seller_id);
$products_stmt->execute();
$products = $products_stmt->get_result();
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Catalog workspace</p>
    <h1>Manage Products</h1>
    <p class="page-intro">Great listings start with the details.</p>
</div>
<?php if ($message): ?><p class="cart-message"><?php echo h($message); ?></p><?php endif; ?>

<section class="seller-form-card">
    <h2><?php echo $is_editing ? 'Edit Product' : 'Add New Product'; ?></h2>
    <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?>
        <?php if ($is_editing): ?>
            <input type="hidden" name="product_id" value="<?php echo $editing['product_id']; ?>">
        <?php endif; ?>

        <?php if ($is_admin): ?><div class="filter-group"><label for="status">Status</label><select id="status" name="status"><option value="active" <?= ($editing['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div><?php endif; ?>
        <div class="form-grid">
            <?php if ($is_admin && !$is_editing): ?>
            <div class="filter-group">
                <label for="seller_id">Seller</label>
                <select id="seller_id" name="seller_id" required>
                    <option value="">Choose a seller</option>
                    <?php while ($seller = $available_sellers->fetch_assoc()): ?>
                        <option value="<?= (int) $seller['user_id'] ?>" <?= (int) input_text($_POST, 'seller_id') === (int) $seller['user_id'] ? 'selected' : '' ?>><?= h($seller['username']) ?><?= $seller['full_name'] ? ' — ' . h($seller['full_name']) : '' ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" value="<?php echo h($editing['name'] ?? ''); ?>" required>
            </div>
            <div class="filter-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <?php
                    $leaf_categories->data_seek(0);
                    while ($cat = $leaf_categories->fetch_assoc()):
                    ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($editing['category_id']) && $editing['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo h($cat['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" value="<?php echo h($editing['brand'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="model">Model</label>
                <input type="text" id="model" name="model" value="<?php echo h($editing['model'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="price">Price (৳)</label>
                <input type="number" step="0.01" id="price" name="price" value="<?php echo h($editing['price'] ?? ''); ?>" required>
            </div>
            <div class="filter-group">
                <label for="stock_qty">Stock Quantity</label>
                <input type="number" id="stock_qty" name="stock_qty" value="<?php echo h($editing['stock_qty'] ?? '0'); ?>" min="0" required>
            </div>
        </div>

        <div class="filter-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?php echo h($editing['description'] ?? ''); ?></textarea>
        </div>

        <fieldset class="spec-fieldset">
            <legend>Specifications</legend>
            <div id="specRows">
            <?php for ($i = 0; $i < max(1, count($editing_specs)); $i++):
                $spec_key = $editing_specs[$i]['spec_key'] ?? '';
                $spec_value = $editing_specs[$i]['spec_value'] ?? '';
            ?>
            <div class="spec-row">
                <input type="text" name="spec_key[]" aria-label="Specification name" maxlength="50" placeholder="e.g. RAM" value="<?php echo h($spec_key); ?>">
                <input type="text" name="spec_value[]" aria-label="Specification value" maxlength="100" placeholder="e.g. 8GB" value="<?php echo h($spec_value); ?>">
                <button type="button" class="remove-spec remove-link">Remove</button>
            </div>
            <?php endfor; ?>
            </div>
            <button type="button" id="addSpec" class="btn-filter">Add Spec</button>
            <p id="specStatus" class="muted" role="status">Up to 30 specifications.</p>
        </fieldset>

        <fieldset class="spec-fieldset">
            <legend>Product Images</legend>
            <div class="filter-group">
                <label for="product_image_1">Upload images</label>
                <input type="file" id="product_image_1" name="product_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <input type="file" id="product_image_2" name="product_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-label="Product image 2">
                <input type="file" id="product_image_3" name="product_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-label="Product image 3">
                <p class="muted">Upload up to 3 images, one per field. JPG, JPEG, PNG, or WebP only; 5MB maximum per image. Files are saved in the uploads folder. The first image is primary; new uploads replace the current image list.</p>
            </div>
        </fieldset>

        <button type="submit" name="<?php echo $is_editing ? 'edit_product' : 'add_product'; ?>" class="btn-filter">
            <?php echo $is_editing ? 'Update Product' : 'Add Product'; ?>
        </button>
        <?php if ($is_editing): ?><a href="manage_products.php" class="btn-cancel-edit">Cancel Edit</a><?php endif; ?>
    </form>
</section>
<section class="seller-product-list">
    <h2><?= $is_admin ? 'All Seller Products' : 'Your Products' ?></h2>
    <?php if ($products->num_rows === 0): ?>
        <p class="empty-state">You haven't listed any products yet.</p>
    <?php else: ?>
    <table class="cart-table">
        <thead>
            <tr><th>Name</th><?php if ($is_admin): ?><th>Seller</th><?php endif; ?><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php while ($p = $products->fetch_assoc()): ?>
            <tr>
                <td><?php echo h($p['name']); ?></td><?php if ($is_admin): ?><td><?= h($p['seller_name']) ?></td><?php endif; ?>
                <td><?php echo h($p['category_name']); ?></td>
                <td>৳<?php echo number_format($p['price'], 2); ?></td>
                <td><?php echo $p['stock_qty']; ?></td>
                <td><span class="status-badge status-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                <td class="table-actions">
                    <a href="manage_products.php?edit=<?php echo $p['product_id']; ?>">Edit</a>
                    <form method="POST" class="inline-form"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>"><button class="btn-filter" name="toggle_status">
                        <?php echo $p['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                    </button></form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<script src="/Project/assets/js/product_management.js" defer></script>
<?php include __DIR__ . '/footer.php'; ?>
