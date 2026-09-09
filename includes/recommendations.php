<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/search.php';

function recommendations(?int $user_id = null, ?int $product_id = null): array
{
    $select = "SELECT p.product_id, p.name, p.price, p.brand,
        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS image_url
        FROM products p JOIN categories c ON c.category_id = p.category_id
        LEFT JOIN categories parent ON parent.category_id = c.parent_category_id ";
    $active = "p.status = 'active' AND c.is_active = 1 AND (parent.category_id IS NULL OR parent.is_active = 1)";
    try {
        if ($product_id !== null) {
            $rows = db_run($select . "JOIN (SELECT product_id, COUNT(*) AS views FROM product_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY product_id) history ON history.product_id = p.product_id
                WHERE $active AND p.product_id <> ? AND p.category_id = (SELECT category_id FROM products WHERE product_id = ?)
                ORDER BY history.views DESC, p.product_id DESC LIMIT 6", 'ii', [$product_id, $product_id])->get_result()->fetch_all(MYSQLI_ASSOC);
            return ['title' => 'Customers Also Viewed', 'products' => $rows];
        }
        if ($user_id !== null) {
            $rows = db_run($select . "JOIN (SELECT product_id, MAX(viewed_at) AS last_view FROM product_views WHERE user_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY product_id) history ON history.product_id = p.product_id
                WHERE $active ORDER BY history.last_view DESC, p.product_id DESC LIMIT 6", 'i', [$user_id])->get_result()->fetch_all(MYSQLI_ASSOC);
            if ($rows) { return ['title' => 'Recommended for You', 'products' => $rows]; }
        }
        $rows = db_run($select . "JOIN (SELECT product_id, COUNT(*) AS views FROM product_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY product_id) history ON history.product_id = p.product_id
            WHERE $active ORDER BY history.views DESC, p.product_id DESC LIMIT 6")->get_result()->fetch_all(MYSQLI_ASSOC);
        return ['title' => 'Trending This Week', 'products' => $rows];
    } catch (Throwable $exception) {
        error_log('Recommendations: ' . $exception->getMessage());
        return ['title' => '', 'products' => []];
    }
}
