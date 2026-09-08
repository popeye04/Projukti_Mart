<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php');

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $role = $_POST['role'] === 'seller' ? 'seller' : 'customer'; // admin accounts are never self-registered

    if ($username === '' || $email === '' || $password === '') {
        $error = "Username, email, and password are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "That username is already taken.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $insert_stmt = $conn->prepare(
                "INSERT INTO users (username, password, role, full_name, email, phone) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert_stmt->bind_param("ssssss", $username, $hashed, $role, $full_name, $email, $phone);

            if ($insert_stmt->execute()) {
                $_SESSION['user_id'] = $insert_stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['last_activity'] = time();

                header("Location: " . $redirect);
                exit();
            } else {
                $error = "That email is already registered to another account.";
            }
        }
    }
}

$page_title = 'Register';
include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1>Create an Account</h1>
        <?php if ($error): ?><p class="stock-warning-box"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

            <div class="filter-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="filter-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>
            <div class="filter-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            <div class="filter-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            <div class="filter-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="filter-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="filter-group">
                <label>I want to</label>
                <div class="role-options">
                    <label class="role-option">
                        <input type="radio" name="role" value="customer" <?php echo (!isset($_POST['role']) || $_POST['role'] === 'customer') ? 'checked' : ''; ?>>
                        <span>Shop as a Customer</span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="seller" <?php echo (isset($_POST['role']) && $_POST['role'] === 'seller') ? 'checked' : ''; ?>>
                        <span>Sell as a Seller</span>
                    </label>
                </div>
            </div>

            <button type="submit" name="register" class="btn-hero auth-submit">Create Account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
