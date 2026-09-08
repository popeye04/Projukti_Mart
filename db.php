<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "projukti_mart";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

function get_product_image_url($stored_url, $product_name)
{
    if ($stored_url && file_exists(__DIR__ . '/' . ltrim($stored_url, '/'))) {
        return ltrim($stored_url, '/');
    }

    $product_key = preg_replace('/[^a-z0-9]/', '', strtolower($product_name));
    $image_files = glob(__DIR__ . '/uploads/products/*');
    foreach ($image_files as $image_file) {
        if (!is_file($image_file)) {
            continue;
        }

        $extension = strtolower(pathinfo($image_file, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            continue;
        }

        $filename_key = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($image_file, PATHINFO_FILENAME)));
        if ($product_key !== '' && strpos($filename_key, $product_key) === 0) {
            return 'uploads/products/' . basename($image_file);
        }
    }

    return '';
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        order_id INT DEFAULT NULL,
        message VARCHAR(255) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (notification_id),
        INDEX (user_id),
        INDEX (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
?>
