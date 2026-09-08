<?php
// NOTE: The page including this file must already have called session_start()
// and included db.php (so $conn is available) BEFORE including this header.

// Session timeout: log out after 30 minutes of inactivity (NFR-05)
$timeout_duration = 1800;
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

$nav_categories = $conn->query(
    "SELECT category_id, category_name FROM categories WHERE parent_category_id IS NULL AND is_active = 1 ORDER BY category_name ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Projukti Mart' : 'Projukti Mart'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/projukti_mart/assets/css/style.css?v=3">
</head>
<body>

<header class="site-header">
    <div class="header-main">
        <a href="/projukti_mart/index.php" class="logo">Projukti<span>Mart</span></a>

        <form class="search-bar" action="/projukti_mart/search_results.php" method="GET" autocomplete="off">
            <div class="search-input-wrap">
                <input
                    type="text"
                    id="site-search-input"
                    name="q"
                    placeholder="Search for mobiles, laptops, PCs..."
                    value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                    required
                >
                <div id="search-suggestions" class="search-suggestions" role="listbox"></div>
            </div>
            <button type="submit" aria-label="Search">Search</button>
        </form>

        <div class="header-actions">
            <details class="account-menu">
                <summary>
                    <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Account'; ?>
                </summary>
                <ul>
                    <?php if (isset($_SESSION['username'])): ?>
                        <?php if ($_SESSION['role'] === 'customer'): ?>
                            <li><a href="/projukti_mart/orders.php">My Orders</a></li>
                            <li><a href="/projukti_mart/profile.php">My Addresses</a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'seller'): ?>
                            <li><a href="/projukti_mart/seller/manage_products.php">Seller Dashboard</a></li>
                            <li><a href="/projukti_mart/seller/seller_orders.php">Seller Orders</a></li>
                            <li><a href="/projukti_mart/seller/sales_summary.php">Sales Summary</a></li>
                        <?php elseif ($_SESSION['role'] === 'admin'): ?>
                            <li><a href="/projukti_mart/admin/dashboard.php">Admin Dashboard</a></li>
                        <?php endif; ?>
                        <li><a href="/projukti_mart/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="/projukti_mart/login.php">Login</a></li>
                        <li><a href="/projukti_mart/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </details>

            <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
            <a href="/projukti_mart/cart.php" class="cart-link">
                Cart
                <?php if (isset($_SESSION['user_id'])):
                    $uid = intval($_SESSION['user_id']);
                    $count_result = $conn->query(
                        "SELECT SUM(ci.quantity) AS total_items FROM cart_items ci
                         JOIN cart c ON ci.cart_id = c.cart_id
                         WHERE c.user_id = $uid"
                    );
                    $count_row = $count_result->fetch_assoc();
                    $cart_count = $count_row['total_items'] ? $count_row['total_items'] : 0;
                    echo '<span class="cart-count">' . $cart_count . '</span>';
                endif; ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        (() => {
            const input = document.getElementById('site-search-input');
            const suggestions = document.getElementById('search-suggestions');
            let requestId = 0;

            if (!input || !suggestions) return;

            input.addEventListener('input', async () => {
                const query = input.value.trim();
                suggestions.innerHTML = '';
                suggestions.classList.remove('is-visible');
                if (query.length < 1) return;

                const currentRequest = ++requestId;
                const response = await fetch('/projukti_mart/search_suggestions.php?q=' + encodeURIComponent(query));
                if (currentRequest !== requestId || !response.ok) return;

                const results = await response.json();
                results.forEach((product) => {
                    const link = document.createElement('a');
                    link.href = '/projukti_mart/product.php?id=' + encodeURIComponent(product.product_id);
                    link.className = 'search-suggestion';
                    const name = document.createElement('span');
                    name.textContent = product.name;
                    const price = document.createElement('strong');
                    price.textContent = '৳' + Number(product.price).toLocaleString();
                    link.append(name, price);
                    suggestions.appendChild(link);
                });

                if (results.length > 0) suggestions.classList.add('is-visible');
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('.search-bar')) {
                    suggestions.classList.remove('is-visible');
                }
            });
        })();
    </script>

    <nav class="category-nav">
        <ul>
            <?php while ($cat = $nav_categories->fetch_assoc()): ?>
                <li>
                    <a href="/projukti_mart/category.php?category_id=<?php echo $cat['category_id']; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    </nav>
</header>

<main class="site-main">
