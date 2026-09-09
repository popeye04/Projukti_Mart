<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';

try {
    if (isset($_POST['add_category']) || isset($_POST['edit_category']) || isset($_POST['toggle_active']) || isset($_POST['delete'])) {
        $conn->begin_transaction();
        db_run('SELECT category_id FROM categories ORDER BY category_id FOR UPDATE')->get_result()->fetch_all();
        $category_id = (int) ($_POST['category_id'] ?? 0);
        if (!isset($_POST['add_category']) && !db_run('SELECT category_id FROM categories WHERE category_id = ?', 'i', [$category_id])->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Category not found.');
        }
        if (isset($_POST['add_category']) || isset($_POST['edit_category'])) {
            $name = input_text($_POST, 'category_name');
            $description = input_text($_POST, 'description');
            $parent_id = input_text($_POST, 'parent_category_id') === '' ? null : (int) input_text($_POST, 'parent_category_id');
            if ($name === '' || strlen($name) > 50 || strlen($description) > 65535) { throw new InvalidArgumentException('Enter a category name of up to 50 bytes and a valid description.'); }
            if ($parent_id !== null) {
                if ($parent_id === $category_id || !db_run('SELECT category_id FROM categories WHERE category_id = ? AND parent_category_id IS NULL', 'i', [$parent_id])->get_result()->fetch_assoc()) {
                    throw new InvalidArgumentException('Choose a different top-level parent category.');
                }
                if (db_run('SELECT category_id FROM categories WHERE parent_category_id = ? LIMIT 1', 'i', [$category_id])->get_result()->fetch_assoc()) {
                    throw new InvalidArgumentException('A category with children must remain top-level.');
                }
                if (db_run('SELECT product_id FROM products WHERE category_id = ? LIMIT 1', 'i', [$parent_id])->get_result()->fetch_assoc()) {
                    throw new InvalidArgumentException('Move the parent category products to a leaf before adding children.');
                }
            }
            if (isset($_POST['add_category'])) {
                db_run('INSERT INTO categories (category_name, description, parent_category_id, is_active) VALUES (?, ?, ?, 1)', 'ssi', [$name, $description, $parent_id]);
            } else {
                db_run('UPDATE categories SET category_name = ?, description = ?, parent_category_id = ? WHERE category_id = ?', 'ssii', [$name, $description, $parent_id, $category_id]);
            }
        } elseif (isset($_POST['toggle_active'])) {
            db_run('UPDATE categories SET is_active = 1 - is_active WHERE category_id = ?', 'i', [$category_id]);
        } else {
            if (db_run('SELECT category_id FROM categories WHERE parent_category_id = ? LIMIT 1', 'i', [$category_id])->get_result()->fetch_assoc()) {
                throw new InvalidArgumentException('Move or remove the subcategories first.');
            }
            db_run('DELETE FROM categories WHERE category_id = ?', 'i', [$category_id]);
        }
        $conn->commit();
        header('Location: manage_categories.php'); exit;
    }
} catch (Throwable $exception) {
    $conn->rollback();
    $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Cannot save: the category name may already exist, or products still reference this category.';
}

$page_title = 'Manage Categories';
include '../includes/header.php';

// Only top-level categories can be a "parent" — keeps the hierarchy to 2 levels
$top_level = db_run("SELECT category_id, category_name FROM categories WHERE parent_category_id IS NULL ORDER BY category_name")->get_result();

$editing = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $editing = $edit_stmt->get_result()->fetch_assoc();
}

$categories = db_run(
    "SELECT c.*, p.category_name AS parent_name
     FROM categories c
     LEFT JOIN categories p ON c.parent_category_id = p.category_id
     ORDER BY COALESCE(p.category_name, c.category_name), c.parent_category_id IS NOT NULL, c.category_name"
)->get_result();
?>

<div class="page-heading dashboard-heading">
    <p class="page-eyebrow">Platform workspace / Catalog</p>
    <h1>Manage Categories</h1>
    <p class="page-intro">Give every great product a place to belong.</p>
</div>
<?php if ($message): ?><p class="cart-message"><?php echo h($message); ?></p><?php endif; ?>

<section class="seller-form-card">
    <h2><?php echo $editing ? 'Edit Category' : 'Add New Category'; ?></h2>
    <form method="POST"><?= csrf_field() ?>
        <?php if ($editing): ?>
            <input type="hidden" name="category_id" value="<?php echo $editing['category_id']; ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="filter-group">
                <label for="category_name">Name</label>
                <input type="text" id="category_name" name="category_name" value="<?php echo h($editing['category_name'] ?? ''); ?>" required>
            </div>
            <div class="filter-group">
                <label for="parent_category_id">Parent Category</label>
                <select id="parent_category_id" name="parent_category_id">
                    <option value="">None (top-level category)</option>
                    <?php
                    $top_level->data_seek(0);
                    while ($tl = $top_level->fetch_assoc()):
                        if ($editing && $editing['category_id'] == $tl['category_id']) continue;
                    ?>
                        <option value="<?php echo $tl['category_id']; ?>" <?php echo (isset($editing['parent_category_id']) && $editing['parent_category_id'] == $tl['category_id']) ? 'selected' : ''; ?>>
                            <?php echo h($tl['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="filter-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?php echo h($editing['description'] ?? ''); ?></textarea>
        </div>
        <button type="submit" name="<?php echo $editing ? 'edit_category' : 'add_category'; ?>" class="btn-filter">
            <?php echo $editing ? 'Update Category' : 'Add Category'; ?>
        </button>
        <?php if ($editing): ?><a href="manage_categories.php" class="btn-cancel-edit">Cancel Edit</a><?php endif; ?>
    </form>
</section>

<h2 class="section-heading">All Categories</h2>
<table class="cart-table">
    <thead><tr><th>Name</th><th>Parent</th><th>Status</th><th></th></tr></thead>
    <tbody>
        <?php while ($cat = $categories->fetch_assoc()): ?>
        <tr>
            <td><?php echo h($cat['category_name']); ?></td>
            <td><?php echo $cat['parent_name'] ? h($cat['parent_name']) : '—'; ?></td>
            <td><span class="status-badge status-<?php echo $cat['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
            <td class="table-actions">
                <a href="manage_categories.php?edit=<?php echo $cat['category_id']; ?>">Edit</a>
                <form method="POST" class="inline-form"><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $cat['category_id'] ?>"><button name="toggle_active" class="btn-filter"><?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
                <form method="POST" class="inline-form"><?= csrf_field() ?><input type="hidden" name="category_id" value="<?= (int) $cat['category_id'] ?>"><button name="delete" class="remove-link">Delete</button></form>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>
