<?php
session_start();
require_once 'db.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $page_title = 'Logout';
    include 'includes/header.php';
    echo '<h1>Logout</h1><form method="POST">' . csrf_field() . '<button class="btn-filter">Confirm Logout</button></form>';
    include 'includes/footer.php';
    exit;
}
if (isset($_SESSION['user_id'])) { log_activity($conn, 'logout', 'user', (int) $_SESSION['user_id']); }
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
