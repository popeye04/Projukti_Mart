<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php');

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        $error = "Invalid username or password.";
    } elseif ($user['status'] === 'suspended') {
        $error = "This account has been suspended. Please contact support.";
    } else {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time(); // used by the session-timeout check in header.php

        header("Location: " . $redirect);
        exit();
    }
}

$page_title = 'Login';
include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1>Login</h1>
        <?php if ($error): ?><p class="stock-warning-box"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            <div class="filter-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="filter-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn-hero auth-submit">Login</button>
        </form>

        <p class="auth-switch">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
