<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';
$show_forgot = isset($_GET['forgot']) || isset($_POST['forgot_password']) || isset($_POST['verify_recovery']);
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php');

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!isset($_SESSION['login_attempts']) || !is_array($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $stored_attempt_state = $_SESSION['login_attempts'][$email] ?? null;
    if (is_array($stored_attempt_state)) {
        $attempt_state = [
            'failures' => intval($stored_attempt_state['failures'] ?? 0),
            'blocked_until' => intval($stored_attempt_state['blocked_until'] ?? 0)
        ];
    } else {
        // Convert cooldown values created by the previous login implementation.
        $attempt_state = [
            'failures' => 0,
            'blocked_until' => is_numeric($stored_attempt_state) ? intval($stored_attempt_state) : 0
        ];
    }
    $cooldown_until = $attempt_state['blocked_until'];
    $remaining_seconds = $cooldown_until - time();

    if ($remaining_seconds > 0) {
        $error = "Please wait {$remaining_seconds} seconds before trying again.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            $attempt_state['failures']++;

            if (!$user) {
                $error = "Username/email and password are invalid.";
            } else {
                $error = "Password is invalid.";
            }

            if ($attempt_state['failures'] >= 3) {
                $attempt_state['failures'] = 0;
                $attempt_state['blocked_until'] = time() + 10;
                $error .= " Three failed attempts. Please wait 10 seconds before trying again.";
            }

            $_SESSION['login_attempts'][$email] = $attempt_state;
        } elseif ($user['status'] === 'suspended') {
            $error = "This account has been suspended. Please contact support.";
        } else {
            unset($_SESSION['login_attempts'][$email]);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            header("Location: " . $redirect);
            exit();
        }
    }
}

if (isset($_POST['verify_recovery'])) {
    $recovery_email = trim($_POST['recovery_email']);
    $recovery_phone = trim($_POST['recovery_phone']);

    $recovery_stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ? AND phone = ? AND status = 'active'");
    $recovery_stmt->bind_param("ss", $recovery_email, $recovery_phone);
    $recovery_stmt->execute();
    $recovery_user = $recovery_stmt->get_result()->fetch_assoc();

    if ($recovery_user) {
        $_SESSION['recovery_user_id'] = $recovery_user['user_id'];
        $_SESSION['recovery_username'] = $recovery_user['username'];
        $success = "Hello " . $recovery_user['username'] . ". Please reset your password below.";
    } else {
        $error = "The email and phone number do not match an active account.";
    }
    $show_forgot = true;
}

if (isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!isset($_SESSION['recovery_user_id'])) {
        $error = "Please verify your email and phone number first.";
        $show_forgot = true;
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
        $show_forgot = true;
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
        $show_forgot = true;
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $reset_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $reset_stmt->bind_param("si", $hashed_password, $_SESSION['recovery_user_id']);
        $reset_stmt->execute();

        unset($_SESSION['recovery_user_id'], $_SESSION['recovery_username']);
        $success = "Password reset successfully. You can now log in.";
        $show_forgot = false;
    }
}

$page_title = 'Login';
include 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1><?php echo $show_forgot ? 'Reset Password' : 'Login'; ?></h1>
        <?php if ($error): ?><p class="stock-warning-box"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <?php if ($success): ?><p class="cart-message"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>

        <?php if (!$show_forgot): ?>
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
        <p class="auth-switch"><a href="login.php?forgot=1">Forgot password?</a></p>

        <p class="auth-switch">Don't have an account? <a href="register.php">Register here</a></p>
        <?php else: ?>
            <?php if (!isset($_SESSION['recovery_user_id'])): ?>
            <form method="POST">
                <div class="filter-group">
                    <label for="recovery_email">Registered Email</label>
                    <input type="email" id="recovery_email" name="recovery_email" required>
                </div>
                <div class="filter-group">
                    <label for="recovery_phone">Registered Phone Number</label>
                    <input type="text" id="recovery_phone" name="recovery_phone" required>
                </div>
                <button type="submit" name="verify_recovery" class="btn-hero auth-submit">Verify Account</button>
            </form>
            <?php else: ?>
            <form method="POST">
                <div class="filter-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="filter-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password" class="btn-hero auth-submit">Reset Password</button>
            </form>
            <?php endif; ?>
            <p class="auth-switch"><a href="login.php">Back to login</a></p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
