<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/semantic_search.php';
if (PHP_SAPI !== 'cli' || !preg_match('/^projukti_mart_verify_[a-f0-9]{12}$/', $db)) { exit(1); }
foreach (['under 50k' => 50000, 'below 50,000' => 50000, 'less than 12.5k' => 12500, 'within 80000' => 80000, 'max 40k' => 40000] as $query => $expected) {
    if (parse_search_intent($query)['filters']['max_price'] !== (float) $expected) { throw new RuntimeException('Price parse: ' . $query); }
}
if (parse_search_intent('minimum 20k')['filters']['min_price'] !== 20000.0) { throw new RuntimeException('Minimum price'); }
try {
    search_products('laptop', search_filters([]), 6, microtime(true) - 1);
    throw new LogicException('Deadline did not stop query');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Search deadline exceeded') { throw $exception; }
}
echo 'PASS parser boundaries and expired search deadline';
