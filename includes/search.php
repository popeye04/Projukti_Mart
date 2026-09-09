<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../db.php';

function search_filters(array $source): array
{
    $filters = ['cat' => max(0, (int) input_text($source, 'cat')), 'brand' => substr(input_text($source, 'brand'), 0, 50),
        'spec_key' => substr(input_text($source, 'spec_key'), 0, 50), 'spec_value' => substr(input_text($source, 'spec_value'), 0, 100),
        'sort' => input_text($source, 'sort', 'newest')];
    foreach (['min_price', 'max_price'] as $key) {
        $value = input_text($source, $key);
        $filters[$key] = $value !== '' && is_numeric($value) && is_finite((float) $value) ? max(0, min(99999999.99, (float) $value)) : null;
    }
    if ($filters['min_price'] !== null && $filters['max_price'] !== null && $filters['min_price'] > $filters['max_price']) {
        [$filters['min_price'], $filters['max_price']] = [$filters['max_price'], $filters['min_price']];
    }
    if (!in_array($filters['sort'], ['price_asc', 'price_desc', 'newest', 'relevance'], true)) { $filters['sort'] = 'newest'; }
    return $filters;
}

function search_tokens(string $query): array
{
    preg_match_all('/[\p{L}\p{N}]+/u', $query, $matches);
    return array_slice(array_values(array_unique($matches[0] ?? [])), 0, 20);
}

function search_budget(float $deadline): void
{
    $remaining = $deadline - microtime(true);
    if ($remaining <= 0.05) { throw new RuntimeException('Search deadline exceeded'); }
    // MariaDB on XAMPP interrupts long SELECTs; wall-clock checks also cover parsing.
    db_run('SET SESSION max_statement_time = ?', 'd', [min(1.5, $remaining)]);
}

function search_products(string $query, array $filters, int $limit = 60, ?float $deadline = null, array $intent_keys = [], ?int $seller_id = null): array
{
    $tokens = search_tokens($query);
    $fulltext = implode(' ', array_map(static fn($word) => '+' . $word . '*', $tokens));
    $use_fulltext = count($tokens) > 0 && min(array_map('strlen', $tokens)) >= 3;
    foreach ($use_fulltext ? ['fulltext', 'like'] : ['like'] as $mode) {
        if ($deadline !== null) { search_budget($deadline); }
        $where = ["p.status = 'active'", 'c.is_active = 1', '(parent.category_id IS NULL OR parent.is_active = 1)'];
        $params = [];
        $types = '';
        $score = '1.0';
        if ($mode === 'fulltext') {
            $score = 'MATCH(p.name, p.description) AGAINST (? IN BOOLEAN MODE)';
            $params[] = $fulltext; $types .= 's';
        }
        if (!empty($filters['cat'])) {
            $where[] = '(c.category_id = ? OR c.parent_category_id = ?)';
            array_push($params, $filters['cat'], $filters['cat']); $types .= 'ii';
        }
        if ($seller_id !== null) {
            $where[] = 'p.seller_id = ?';
            $params[] = $seller_id; $types .= 'i';
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if (($filters[$key] ?? null) !== null) { $where[] = "p.price $operator ?"; $params[] = $filters[$key]; $types .= 'd'; }
        }
        if (!empty($filters['brand'])) { $where[] = 'p.brand = ?'; $params[] = $filters['brand']; $types .= 's'; }
        if (!empty($filters['spec_key'])) {
            $condition = 'EXISTS (SELECT 1 FROM product_specs ps WHERE ps.product_id = p.product_id AND ps.spec_key = ?';
            $params[] = $filters['spec_key']; $types .= 's';
            if (!empty($filters['spec_value'])) { $condition .= ' AND ps.spec_value LIKE ?'; $params[] = '%' . $filters['spec_value'] . '%'; $types .= 's'; }
            $where[] = $condition . ')';
        } elseif (!empty($filters['spec_value'])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_specs ps WHERE ps.product_id = p.product_id AND ps.spec_value LIKE ?)';
            $params[] = '%' . $filters['spec_value'] . '%'; $types .= 's';
        }
        foreach ($intent_keys as $keys) {
            $parts = [];
            foreach ($keys as $key) { $parts[] = 'LOWER(ps.spec_key) LIKE ?'; $params[] = '%' . $key . '%'; $types .= 's'; }
            $where[] = 'EXISTS (SELECT 1 FROM product_specs ps WHERE ps.product_id = p.product_id AND (' . implode(' OR ', $parts) . '))';
        }
        if ($mode === 'fulltext') {
            $where[] = 'MATCH(p.name, p.description) AGAINST (? IN BOOLEAN MODE)';
            $params[] = $fulltext; $types .= 's';
        } else {
            foreach ($tokens as $word) {
                $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ? OR EXISTS (SELECT 1 FROM product_specs ps WHERE ps.product_id = p.product_id AND (ps.spec_key LIKE ? OR ps.spec_value LIKE ?)))';
                // The fallback mirrors the FULLTEXT `word*` behavior: a token
                // such as "lap" finds values that start with "lap".
                for ($i = 0; $i < 5; $i++) { $params[] = $word . '%'; $types .= 's'; }
            }
        }
        $sorts = ['price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'newest' => 'p.created_at DESC', 'relevance' => 'relevance_score DESC'];
        $order = $sorts[$filters['sort'] ?? 'relevance'] ?? $sorts['relevance'];
        $sql = "SELECT p.product_id, p.name, p.price, p.brand, p.stock_qty, c.category_name AS category,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS image_url,
            $score AS relevance_score
            FROM products p JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN categories parent ON parent.category_id = c.parent_category_id
            WHERE " . implode(' AND ', $where) . " ORDER BY $order, p.product_id DESC LIMIT ?";
        $params[] = max(1, min(200, $limit)); $types .= 'i';
        try {
            $rows = db_run($sql, $types, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (mysqli_sql_exception $exception) {
            if ($mode === 'fulltext' && in_array($exception->getCode(), [1191, 1064], true)) { continue; }
            throw $exception;
        }
        foreach ($rows as &$row) {
            $row['product_id'] = (int) $row['product_id'];
            $row['price'] = (float) $row['price'];
            $row['relevance_score'] = (float) $row['relevance_score'];
            $row['image_url'] = product_image($row['image_url']);
        }
        unset($row);
        if ($rows || $mode === 'like') { return $rows; }
    }
    return [];
}

function log_search(string $query, bool $used_ai, int $count): void
{
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    db_run('INSERT INTO search_query_log (raw_query, user_id, used_ai, result_count) VALUES (?, ?, ?, ?)', 'siii', [$query, $user_id, (int) $used_ai, $count]);
}

function render_product_cards(array $products, string $class = 'product-grid'): void
{
    // Enrich cards in batches, without changing keyword or chatbot retrieval.
    $details = []; $specifications = [];
    $ids = array_values(array_unique(array_map(static fn($p) => (int) $p['product_id'], $products)));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $rows = db_run("SELECT p.product_id, p.stock_qty, p.status, AVG(r.rating) AS average_rating, COUNT(r.review_id) AS review_count
            FROM products p LEFT JOIN reviews r ON r.product_id = p.product_id AND r.is_flagged = 0
            WHERE p.product_id IN ($placeholders) GROUP BY p.product_id, p.stock_qty, p.status", $types, $ids)->get_result();
        while ($row = $rows->fetch_assoc()) { $details[$row['product_id']] = $row; }
        $rows = db_run("SELECT product_id, spec_key, spec_value FROM product_specs WHERE product_id IN ($placeholders) ORDER BY spec_id", $types, $ids)->get_result();
        while ($row = $rows->fetch_assoc()) {
            if (count($specifications[$row['product_id']] ?? []) < 2) { $specifications[$row['product_id']][] = $row; }
        }
    }
    echo '<div class="' . h($class) . '">';
    foreach ($products as $product) {
        $id = (int) $product['product_id'];
        $detail = $details[$id] ?? [];
        echo '<article class="product-card"><div class="product-card-media"><img loading="lazy" src="' . h(product_image($product['image_url'] ?? '')) . '" alt="' . h($product['name']) . '"></div>';
        echo '<h3>' . h($product['name']) . '</h3><p class="muted">' . h($product['brand'] ?? '') . '</p>';
        if (!empty($specifications[$id])) {
            echo '<ul class="card-specs">';
            foreach ($specifications[$id] as $spec) { echo '<li><strong>' . h($spec['spec_key']) . ':</strong> ' . h($spec['spec_value']) . '</li>'; }
            echo '</ul>';
        }
        if (!empty($detail['review_count'])) {
            $average = (float) $detail['average_rating'];
            $stars = (int) round($average);
            echo '<p class="card-rating" aria-label="' . h(number_format($average, 1)) . ' out of 5 stars"><span aria-hidden="true">' . str_repeat('<i class="star-icon"></i>', $stars) . str_repeat('<i class="star-icon star-empty"></i>', 5 - $stars) . '</span> ' . number_format($average, 1) . ' (' . (int) $detail['review_count'] . ')</p>';
        } else { echo '<p class="card-rating muted">No reviews yet</p>'; }
        echo '<p class="price">৳' . number_format((float) $product['price'], 2) . '</p><a class="btn-add" href="/Project/product.php?id=' . $id . '">View Details</a>';
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer') {
            echo '<form class="card-cart-form" method="POST" action="/Project/product.php?id=' . $id . '">' . csrf_field() . '<input type="hidden" name="quantity" value="1"><button class="btn-add" name="add_to_cart"' . ((int) ($detail['stock_qty'] ?? 0) > 0 && ($detail['status'] ?? '') === 'active' ? '>Add to Cart' : ' disabled>Out of Stock') . '</button></form>';
        }
        echo '</article>';
    }
    echo '</div>';
}
