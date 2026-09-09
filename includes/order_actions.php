<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../db.php';

function change_order_status(int $order_id, string $new_status, string $actor, string $notes = ''): void
{
    global $conn;
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if (!$user_id || !in_array($new_status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'], true)) { throw new InvalidArgumentException('Invalid order status.'); }
    if (!in_array($actor, ['admin', 'seller', 'customer'], true) || ($_SESSION['role'] ?? '') !== $actor) { throw new InvalidArgumentException('Access denied.'); }
    $notes = trim($notes);
    if (mb_strlen($notes) > 2000) { throw new InvalidArgumentException('Tracking notes must be 2,000 characters or fewer.'); }
    try {
        $conn->begin_transaction();
        $scope = $actor === 'customer' ? ' AND o.user_id = ?' : ($actor === 'seller' ? ' AND EXISTS (SELECT 1 FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = o.order_id AND p.seller_id = ?)' : '');
        $params = [$order_id]; $types = 'i';
        if ($scope !== '') { $params[] = $user_id; $types .= 'i'; }
        $order = db_run('SELECT o.status FROM orders o WHERE o.order_id = ?' . $scope . ' FOR UPDATE', $types, $params)->get_result()->fetch_assoc();
        if (!$order) { throw new InvalidArgumentException('Order not found.'); }
        $old_status = $order['status'];
        if ($new_status === $old_status) {
            if ($notes !== '') { throw new InvalidArgumentException('Choose a new status to save tracking notes.'); }
            $conn->commit(); return;
        }
        if ($old_status === 'delivered') { throw new InvalidArgumentException('Delivered orders are final and cannot be changed.'); }
        if ($new_status === 'cancelled' && !in_array($old_status, ['pending', 'processing'], true)) { throw new InvalidArgumentException('Only pending or processing (confirmed) orders can be cancelled.'); }
        if ($actor === 'customer' && ($new_status !== 'cancelled' || !in_array($old_status, ['pending', 'processing'], true))) { throw new InvalidArgumentException('Only pending or processing orders can be cancelled.'); }
        if ($actor === 'seller') {
            $steps = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];
            if (!isset($steps[$old_status], $steps[$new_status]) || $steps[$new_status] <= $steps[$old_status] || $new_status === 'pending') { throw new InvalidArgumentException('Orders can only move forward; cancelled orders cannot be reopened by a seller.'); }
        }
        // Inventory is deducted only when delivery is confirmed. A cancelled
        // order keeps its units available because it has not consumed stock.
        if ($new_status === 'delivered') {
            $items = db_run('SELECT product_id, SUM(quantity) AS quantity FROM order_items WHERE order_id = ? GROUP BY product_id ORDER BY product_id', 'i', [$order_id])->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($items as $item) {
                $product = db_run('SELECT stock_qty, status FROM products WHERE product_id = ? FOR UPDATE', 'i', [$item['product_id']])->get_result()->fetch_assoc();
                if (!$product) { throw new InvalidArgumentException('An order product is unavailable.'); }
                if ($product['stock_qty'] < $item['quantity']) { throw new InvalidArgumentException('Cannot mark as delivered: there is insufficient stock.'); }
                db_run('UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?', 'iii', [$item['quantity'], $item['product_id'], $item['quantity']]);
            }
        }
        db_run('UPDATE orders o SET o.status = ?, o.updated_at = NOW() WHERE o.order_id = ?' . $scope, 's' . $types, array_merge([$new_status], $params));
        db_run('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)', 'issis', [$order_id, $old_status, $new_status, $user_id, $notes !== '' ? $notes : null]);
        if ($new_status === 'cancelled') { log_activity($conn, 'order_cancelled', 'order', $order_id); }
        $conn->commit();
    } catch (Throwable $exception) { $conn->rollback(); throw $exception; }
}

function render_order_history(int $order_id): void
{
    $role = $_SESSION['role'] ?? '';
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if (!$user_id || !in_array($role, ['customer', 'seller', 'admin'], true)) { return; }
    $scope = $role === 'admin' ? '' : ($role === 'customer' ? ' AND o.user_id = ?' : ' AND EXISTS (SELECT 1 FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = o.order_id AND p.seller_id = ?)');
    $params = [$order_id]; $types = 'i';
    if ($scope !== '') { $params[] = $user_id; $types .= 'i'; }
    $rows = db_run('SELECT history.*, u.username FROM order_status_history history JOIN orders o ON o.order_id = history.order_id LEFT JOIN users u ON u.user_id = history.changed_by WHERE history.order_id = ?' . $scope . ' ORDER BY history.changed_at, history.history_id', $types, $params)->get_result();
    echo '<details class="order-history"><summary>Status History</summary>';
    if (!$rows->num_rows) { echo '<p class="muted">No recorded changes. This order may predate status tracking.</p>'; }
    else {
        echo '<ol class="status-timeline">';
        while ($row = $rows->fetch_assoc()) {
            echo '<li><strong>' . h($row['old_status'] ?? 'Created') . ' → ' . h($row['new_status']) . '</strong> <time>' . h($row['changed_at']) . '</time> — ' . h($row['username'] ?? 'System / removed account');
            if ($row['notes']) { echo '<p>' . nl2br(h($row['notes'])) . '</p>'; }
            echo '</li>';
        }
        echo '</ol>';
    }
    echo '</details>';
}
