<?php
// Call before committing a business transaction so its audit row commits with it.
function log_activity(mysqli $conn, string $action, ?string $target_type = null, ?int $target_id = null): void
{
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : null;
    $stmt = $conn->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issis', $user_id, $action, $target_type, $target_id, $ip);
    $stmt->execute();
}
