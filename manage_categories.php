<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';

// Handle Add Category
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $parent_category_id = $_POST['parent_category_id'] !== '' ? intval($_POST['parent_category_id']) : null;

    $stmt = $conn->prepare("INSERT INTO categories (category_name, description, parent_category_id, is_active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("ssi", $category_name, $description, $parent_category_id);
    $message = $stmt->execute() ? "Category added successfully." : "That category name already exists.";
}

// Handle Edit Category
if (isset($_POST['edit_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $parent_category_id = $_POST['parent_category_id'] !== '' ? intval($_POST['parent_category_id']) : null;

    if ($parent_category_id === $category_id) {
        $message = "A category cannot be its own parent.";
    } else {
        $stmt = $conn->prepare("UPDATE categories SET category_name=?, description=?, parent_category_id=? WHERE category_id=?");
        $stmt->bind_param("ssii", $category_name, $description, $parent_category_id, $category_id);
        $message = $stmt->execute() ? "Category updated successfully." : "That category name already exists.";
    }
}

// Handle Activate/Deactivate toggle
if (isset($_GET['toggle_active'])) {
    $category_id = intval($_GET['toggle_active']);
    $current_stmt = $conn->prepare("SELECT is_active FROM categories WHERE category_id = ?");
    $current_stmt->bind_param("i", $category_id);
    $current_stmt->execute();
    $current = $current_stmt->get_result()->fetch_assoc();

    if ($current) {
        $new_status = $current['is_active'] ? 0 : 1;
        $stmt = $conn->prepare("UPDATE categories SET is_active = ? WHERE category_id = ?");
        $stmt->bind_param("ii", $new_status, $category_id);
        $stmt->execute();
    }
    header("Location: manage_categories.php");
    exit();
}

// Handle Delete (only succeeds if no products still reference it, thanks to ON DELETE RESTRICT)
if (isset($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    if (!$stmt->execute()) {
        $message = "Can't delete this category — it still has products assigned to it. Deactivate it instead.";
    } else {
        header("Location: manage_categories.php");
        exit();
    }
}

$page_title = 'Manage Categories';
include '../includes/header.php';

// Only top-level categories can be a "parent" — keeps the hierarchy to 2 levels
$top_level = $conn->query("SELECT category_id, category_name FROM categories WHERE parent_category_id IS NULL ORDER BY category_name");

$editing = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $editing = $edit_stmt->get_result()->fetch_assoc();
}

$categories = $conn->query(
    "SELECT c.*, p.category_name AS parent_name
     FROM categories c
     LEFT JOIN categories p ON c.parent_category_id = p.category_id
     ORDER BY COALESCE(p.category_name, c.category_name), c.parent_category_id IS NOT NULL, c.category_name"
);
?>

<main class="site-main">
    <div class="section-heading">
        <h1>Manage Categories</h1>
    </div>

    <?php if ($message): ?>
    <p class="cart-message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <!-- Add/Edit Category Form -->
    <section class="seller-form-card">
        <h2><?php echo $editing ? 'Edit Category' : 'Add New Category'; ?></h2>
        
        <form method="POST">
            <?php if ($editing): ?>
                <input type="hidden" name="category_id" value="<?php echo $editing['category_id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="filter-group">
                    <label for="category_name">Category Name <span class="required">*</span></label>
                    <input type="text" id="category_name" name="category_name" value="<?php echo htmlspecialchars($editing['category_name'] ?? ''); ?>" required>
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
                                <?php echo htmlspecialchars($tl['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="filter-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editing['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" name="<?php echo $editing ? 'edit_category' : 'add_category'; ?>" class="btn-filter">
                    <?php echo $editing ? 'Update Category' : 'Add Category'; ?>
                </button>
                <?php if ($editing): ?>
                    <a href="manage_categories.php" class="btn-cancel-edit">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Categories List -->
    <section class="seller-product-list">
        <h2 class="section-heading">All Categories</h2>
        
        <?php if ($categories->num_rows === 0): ?>
            <p class="empty-state">No categories found.</p>
        <?php else: ?>
        
        <div class="table-responsive">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Parent Category</th>
                        <th>Status</th>
                        <th class="table-actions-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span class="category-name"><?php echo htmlspecialchars($cat['category_name']); ?></span>
                        </td>
                        <td>
                            <span class="muted"><?php echo $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '—'; ?></span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $cat['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <a href="manage_categories.php?edit=<?php echo $cat['category_id']; ?>" class="action-link">Edit</a>
                            <a href="manage_categories.php?toggle_active=<?php echo $cat['category_id']; ?>" class="action-link">
                                <?php echo $cat['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="manage_categories.php?delete=<?php echo $cat['category_id']; ?>" class="action-link remove-link">Delete</a>
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

    .category-name {
        font-weight: 600;
        color: var(--color-text);
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

    .action-link.remove-link {
        color: #DC2626;
    }

    .action-link.remove-link:hover {
        color: #991B1B;
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
</style>
