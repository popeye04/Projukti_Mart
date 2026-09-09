<?php
session_start();

// The shared product workspace checks this flag to allow an administrator to
// edit or deactivate products from every seller.
define('ADMIN_PRODUCT_OVERRIDE', true);
require __DIR__ . '/../includes/product_management.php';
