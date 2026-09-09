<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/order_actions.php';
if (PHP_SAPI !== 'cli' || !preg_match('/^projukti_mart_verify_[a-f0-9]{12}$/', $db)) { exit(1); }
$_SESSION['user_id'] = 5;
$_SESSION['role'] = 'customer';
try {
    change_order_status((int) ($argv[1] ?? 0), 'cancelled', 'customer');
    echo 'ok';
} catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage()); exit(1); }
