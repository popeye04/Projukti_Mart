<?php
// NOTE:
// The page including this file must already have:
// 1. session_start()
// 2. require 'db.php'
// so that $_SESSION and $conn are available.


// ==================================================
// SESSION TIMEOUT
// ==================================================

$timeout_duration = 1800; // 30 minutes

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity'] > $timeout_duration)
) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

// Update last activity time
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}


// ==================================================
// GET TOP-LEVEL CATEGORIES
// ==================================================

// IMPORTANT:
// The categories table does NOT have an is_active column.
// Therefore, we only check parent_category_id.

$nav_categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     WHERE parent_category_id IS NULL
     ORDER BY category_name ASC"
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php
        echo isset($page_title)
            ? htmlspecialchars($page_title) . ' - Projukti Mart'
            : 'Projukti Mart';
        ?>
    </title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet"
    >

    <!-- Your CSS file is directly inside Projukti Mart -->
    <link rel="stylesheet" href="style.css">

</head>

<body>


<!-- ==================================================
     HEADER
================================================== -->

<header class="site-header">

    <div class="header-main">


        <!-- LOGO -->

        <a href="index.php" class="logo">
            Projukti<span>Mart</span>
        </a>


        <!-- SEARCH BAR -->

        <form
            class="search-bar"
            action="category.php"
            method="GET"
        >

            <input
                type="text"
                name="q"
                placeholder="Search for mobiles, laptops, PCs..."
                value="<?php
                    echo isset($_GET['q'])
                        ? htmlspecialchars($_GET['q'])
                        : '';
                ?>"
                required
            >

            <button type="submit" aria-label="Search">
                Search
            </button>

        </form>


        <!-- HEADER ACTIONS -->

        <div class="header-actions">


            <!-- ACCOUNT MENU -->

            <details class="account-menu">

                <summary>

                    <?php
                    echo isset($_SESSION['username'])
                        ? htmlspecialchars($_SESSION['username'])
                        : 'Account';
                    ?>

                </summary>


                <ul>

                    <?php if (isset($_SESSION['username'])): ?>


                        <!-- Logged-in user -->

                        <li>
                            <a href="orders.php">
                                My Orders
                            </a>
                        </li>


                        <li>
                            <a href="profile.php">
                                My Addresses
                            </a>
                        </li>


                        <?php if (
                            isset($_SESSION['role']) &&
                            (
                                $_SESSION['role'] === 'seller' ||
                                $_SESSION['role'] === 'admin'
                            )
                        ): ?>

                            <li>
                                <a href="dashboard.php">
                                    Dashboard
                                </a>
                            </li>

                        <?php endif; ?>


                        <li>
                            <a href="logout.php">
                                Logout
                            </a>
                        </li>


                    <?php else: ?>


                        <!-- Guest user -->

                        <li>
                            <a href="login.php">
                                Login
                            </a>
                        </li>


                        <li>
                            <a href="register.php">
                                Register
                            </a>
                        </li>


                    <?php endif; ?>

                </ul>

            </details>


            <!-- CART -->

            <a href="cart.php" class="cart-link">

                Cart

                <?php if (isset($_SESSION['user_id'])): ?>

                    <?php

                    $uid = intval($_SESSION['user_id']);

                    $count_result = $conn->query(
                        "SELECT SUM(ci.quantity) AS total_items
                         FROM cart_items ci
                         JOIN cart c
                         ON ci.cart_id = c.cart_id
                         WHERE c.user_id = $uid"
                    );

                    $count_row = $count_result->fetch_assoc();

                    $cart_count = !empty($count_row['total_items'])
                        ? $count_row['total_items']
                        : 0;

                    ?>

                    <span class="cart-count">
                        <?php echo $cart_count; ?>
                    </span>

                <?php endif; ?>

            </a>


        </div>

    </div>


    <!-- ==================================================
         CATEGORY NAVIGATION
    ================================================== -->

    <nav class="category-nav">

        <ul>

            <?php if ($nav_categories && $nav_categories->num_rows > 0): ?>

                <?php while ($cat = $nav_categories->fetch_assoc()): ?>

                    <li>

                        <a
                            href="category.php?category_id=<?php echo $cat['category_id']; ?>"
                        >

                            <?php
                            echo htmlspecialchars($cat['category_name']);
                            ?>

                        </a>

                    </li>

                <?php endwhile; ?>

            <?php endif; ?>

        </ul>

    </nav>

</header>


<!-- ==================================================
     MAIN CONTENT START
================================================== -->

<main class="site-main">