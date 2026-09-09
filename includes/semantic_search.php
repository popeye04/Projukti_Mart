<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/search.php';

// Replace this service without changing keyword search or the chatbot contract.
function parse_search_intent(string $query): array
{
    $text = mb_strtolower($query, 'UTF-8');
    $remaining = $text;
    $category = null;
    $patterns = [
        'Laptop' => '/\b(laptops?|notebooks?|macbooks?|ultrabooks?)\b/u',
        'Mobile' => '/\b(mobiles?|phones?|smartphones?|iphones?|android)\b/u',
        'PC' => '/\b(pcs?|desktops?|computers?|towers?)\b/u',
    ];
    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $text)) { $category = $name; $remaining = preg_replace($pattern, ' ', $remaining); break; }
    }
    $filters = search_filters(['sort' => 'relevance']);
    $number = '(\d[\d,]*(?:\.\d+)?)\s*(k)?';
    foreach (['max_price' => '(?:under|below|less than|within|max(?:imum)?|up to)', 'min_price' => '(?:above|over|more than|minimum|at least)'] as $key => $prefix) {
        $pattern = '/\b' . $prefix . '\s*(?:৳|tk\.?|bdt)?\s*' . $number . '(?![\w.])/u';
        if (preg_match($pattern, $remaining, $match)) {
            $filters[$key] = min(99999999.99, (float) str_replace(',', '', $match[1]) * (!empty($match[2]) ? 1000 : 1));
            $remaining = preg_replace($pattern, ' ', $remaining);
        }
    }
    if ($filters['max_price'] === null && preg_match('/\b(\d+(?:\.\d+)?)\s*k\b/u', $remaining, $match)) {
        $filters['max_price'] = min(99999999.99, (float) $match[1] * 1000);
        $remaining = preg_replace('/\b\d+(?:\.\d+)?\s*k\b/u', ' ', $remaining);
    }
    if (preg_match('/\b(budget|cheap|affordable)\b/u', $text)) { $filters['sort'] = 'price_asc'; }
    elseif (preg_match('/\b(premium|high[ -]end|expensive|best)\b/u', $text)) { $filters['sort'] = 'price_desc'; }
    $intent_keys = [];
    foreach ([
        '/\bgaming\b/u' => ['gpu', 'graphics'],
        '/\b(fast|performance)\b/u' => ['ram', 'processor', 'cpu'],
        '/\b(light|lightweight|thin)\b/u' => ['weight'],
        '/\b(long battery|battery life)\b/u' => ['battery'],
    ] as $pattern => $keys) {
        if (preg_match($pattern, $text)) { $intent_keys[] = $keys; $remaining = preg_replace($pattern, ' ', $remaining); }
    }
    $remaining = preg_replace('/\b(budget|cheap|affordable|premium|high[ -]end|expensive|best|please|show|find|suggest|recommend|looking|look|want|need|me|my|some|a|an|the|for|to|of|with|and|in|is|i|can|you|buy|taka|tk|bdt)\b/u', ' ', $remaining);
    $remaining = trim(preg_replace('/\s+/u', ' ', $remaining));
    return ['category' => $category, 'filters' => $filters, 'keywords' => $remaining, 'intent_keys' => $intent_keys];
}

function semantic_search(string $query, array $context, float $deadline): array
{
    $intent = parse_search_intent($query);
    $filters = $intent['filters'];
    if ($intent['category']) {
        search_budget($deadline);
        $category = db_run('SELECT category_id FROM categories WHERE category_name = ? AND parent_category_id IS NULL AND is_active = 1', 's', [$intent['category']])->get_result()->fetch_assoc();
        // A recognized but unavailable category must not broaden into unrelated products.
        $filters['cat'] = $category ? (int) $category['category_id'] : -1;
    } elseif (!empty($context['cat'])) { $filters['cat'] = (int) $context['cat']; }
    $fallback = false;
    $products = [];
    $meaningful = $intent['category'] || $intent['keywords'] !== '' || $intent['intent_keys'] || $filters['min_price'] !== null || $filters['max_price'] !== null || $filters['sort'] !== 'relevance';
    try {
        if ($meaningful) { $products = search_products($intent['keywords'], $filters, 6, min($deadline - 1.0, microtime(true) + 3.5), $intent['intent_keys']); }
    } catch (Throwable $exception) {
        error_log('Semantic search fallback: ' . $exception->getMessage());
    }
    if (!$products) {
        $fallback = true;
        // Relax semantic spec inference only; preserve explicit category and budget.
        $fallback_query = $intent['keywords'] !== '' ? $intent['keywords'] : implode(' ', search_tokens($query));
        try { $products = search_products($fallback_query, $filters, 6, $deadline); }
        catch (Throwable $exception) { error_log('Keyword fallback: ' . $exception->getMessage()); }
    }
    $message = 'Found ' . count($products) . ' product' . (count($products) === 1 ? '' : 's');
    if ($intent['category']) { $message .= ' in ' . $intent['category']; }
    if ($filters['min_price'] !== null) { $message .= ' above ৳' . number_format($filters['min_price'], 2); }
    if ($filters['max_price'] !== null) { $message .= ' under ৳' . number_format($filters['max_price'], 2); }
    return ['success' => true, 'results' => $products, 'result_count' => count($products), 'used_ai' => true,
        'confidence' => $fallback ? 'low' : 'high', 'fallback' => $fallback,
        'detected_category' => $intent['category'], 'detected_min_price' => $filters['min_price'],
        'detected_max_price' => $filters['max_price'], 'message' => $message . '.'];
}
