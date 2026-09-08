<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("profile.php"));
    exit();
}

if ($_SESSION['role'] !== 'customer') {
    $destination = $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'seller/manage_products.php';
    header("Location: $destination");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = '';

// Update personal info
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email_local = trim($_POST['email_local'] ?? '');
    $email = $email_local . '@gmail.com';
    $phone = trim($_POST['phone']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $email)) {
        $message = "Seller and customer accounts must use a valid @gmail.com email address.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
        $stmt->execute();

        $message = "Profile updated.";
    }
}

// Add new address
if (isset($_POST['add_address'])) {
    $line1 = trim($_POST['line1']);
    $line2 = trim($_POST['line2']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $country = trim($_POST['country']);

    $stmt = $conn->prepare(
        "INSERT INTO addresses (user_id, line1, line2, city, postal_code, country, is_default)
         VALUES (?, ?, ?, ?, ?, ?, 0)"
    );
    $stmt->bind_param("isssss", $user_id, $line1, $line2, $city, $postal_code, $country);
    $stmt->execute();

    // Make it the default automatically if it's the user's first address
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM addresses WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['total'];
    if ($count == 1) {
        $new_id = $stmt->insert_id;
        $conn->query("UPDATE addresses SET is_default = 1 WHERE address_id = $new_id");
    }

    $message = "Address added.";
}

// Edit existing address
if (isset($_POST['edit_address'])) {
    $address_id = intval($_POST['address_id']);
    $line1 = trim($_POST['line1']);
    $line2 = trim($_POST['line2']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $country = trim($_POST['country']);

    $stmt = $conn->prepare(
        "UPDATE addresses SET line1 = ?, line2 = ?, city = ?, postal_code = ?, country = ?
         WHERE address_id = ? AND user_id = ?"
    );
    $stmt->bind_param("sssssii", $line1, $line2, $city, $postal_code, $country, $address_id, $user_id);
    $stmt->execute();

    $message = "Address updated.";
}

// Delete an address
if (isset($_GET['remove_address'])) {
    $address_id = intval($_GET['remove_address']);
    $stmt = $conn->prepare("DELETE FROM addresses WHERE address_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);
    $stmt->execute();
    header("Location: profile.php");
    exit();
}

// Set an address as default
if (isset($_GET['set_default'])) {
    $address_id = intval($_GET['set_default']);

    $check_stmt = $conn->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $address_id, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $conn->query("UPDATE addresses SET is_default = 0 WHERE user_id = $user_id");
        $conn->query("UPDATE addresses SET is_default = 1 WHERE address_id = $address_id");
    }
    header("Location: profile.php");
    exit();
}

$page_title = 'My Profile';
include 'includes/header.php';

$user_stmt = $conn->prepare("SELECT username, full_name, email, phone FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

$addr_stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addresses = $addr_stmt->get_result();

// If an edit link was clicked, load that address into the form
$editing_address = null;
if (isset($_GET['edit_address'])) {
    $edit_id = intval($_GET['edit_address']);
    $edit_stmt = $conn->prepare("SELECT * FROM addresses WHERE address_id = ? AND user_id = ?");
    $edit_stmt->bind_param("ii", $edit_id, $user_id);
    $edit_stmt->execute();
    $editing_address = $edit_stmt->get_result()->fetch_assoc();
}
?>

<h1>My Profile</h1>

<?php if ($message): ?><p class="cart-message"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<div class="profile-layout">
    <section class="profile-card">
        <h2>Personal Information</h2>
        <form method="POST">
            <div class="filter-group">
                <label>Username</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
            </div>
            <div class="filter-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="email_local">Gmail Address</label>
                <div class="email-input">
                    <input type="text" id="email_local" name="email_local" pattern="[A-Za-z0-9._%+-]+" title="Enter the part before @gmail.com" value="<?php echo htmlspecialchars(preg_replace('/@gmail\.com$/i', '', $user['email'] ?? '')); ?>" required>
                    <span>@gmail.com</span>
                </div>
            </div>
            <div class="filter-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            <button type="submit" name="update_profile" class="btn-filter">Save Changes</button>
        </form>
    </section>

    <section class="profile-card">
        <h2><?php echo $editing_address ? 'Edit Address' : 'Add New Address'; ?></h2>
        <form method="POST">
            <?php if ($editing_address): ?>
                <input type="hidden" name="address_id" value="<?php echo $editing_address['address_id']; ?>">
            <?php endif; ?>
            <div class="filter-group">
                <label for="line1">Address Line 1</label>
                <input type="text" id="line1" name="line1" value="<?php echo htmlspecialchars($editing_address['line1'] ?? ''); ?>" required>
            </div>
            <div class="filter-group">
                <label for="line2">Address Line 2</label>
                <input type="text" id="line2" name="line2" value="<?php echo htmlspecialchars($editing_address['line2'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($editing_address['city'] ?? ''); ?>" required>
            </div>
            <div class="filter-group">
                <label for="postal_code">Postal Code</label>
                <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($editing_address['postal_code'] ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="country">Country</label>
                <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($editing_address['country'] ?? 'Bangladesh'); ?>" required>
            </div>
            <button type="submit" name="<?php echo $editing_address ? 'edit_address' : 'add_address'; ?>" class="btn-filter">
                <?php echo $editing_address ? 'Update Address' : 'Add Address'; ?>
            </button>
        </form>
    </section>
</div>

<section class="address-list">
    <h2>Saved Addresses</h2>
    <?php if ($addresses->num_rows === 0): ?>
        <p class="empty-state">No saved addresses yet — add one above.</p>
    <?php else: ?>
        <?php while ($addr = $addresses->fetch_assoc()): ?>
        <div class="address-card">
            <p>
                <?php echo htmlspecialchars($addr['line1']); ?><?php echo $addr['line2'] ? ', ' . htmlspecialchars($addr['line2']) : ''; ?><br>
                <?php echo htmlspecialchars($addr['city']); ?><?php echo $addr['postal_code'] ? ', ' . htmlspecialchars($addr['postal_code']) : ''; ?><br>
                <?php echo htmlspecialchars($addr['country']); ?>
            </p>
            <?php if ($addr['is_default']): ?>
                <span class="default-badge">Default</span>
            <?php endif; ?>
            <div class="address-actions">
                <a href="profile.php?edit_address=<?php echo $addr['address_id']; ?>">Edit</a>
                <?php if (!$addr['is_default']): ?>
                    <a href="profile.php?set_default=<?php echo $addr['address_id']; ?>">Set as Default</a>
                <?php endif; ?>
                <a class="remove-link" href="profile.php?remove_address=<?php echo $addr['address_id']; ?>">Delete</a>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
