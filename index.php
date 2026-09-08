<?php
session_start();

require 'db.php';

$page_title = 'Home';

// Header is in the same folder as index.php
include 'header.php';


// --------------------------------------------------
// TOP-LEVEL CATEGORIES
// --------------------------------------------------

$categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     WHERE parent_category_id IS NULL
     ORDER BY category_name ASC"
);


// --------------------------------------------------
// TRENDING / RECENT PRODUCTS
// --------------------------------------------------

$trending = $conn->query(
    "SELECT product_id, name, brand, price
     FROM products
     WHERE status = 'active'
     ORDER BY created_at DESC
     LIMIT 8"
);

?>

<!-- ==================================================
     HERO SECTION
================================================== -->

<section class="hero">

    <div class="hero-text">

        <h1>Tech that keeps up with you.</h1>

        <p>
            Mobiles, PCs, and laptops — picked, priced,
            and ready to ship from Projukti Mart.
        </p>

        <a href="#categories" class="btn-hero">
            Start Browsing
        </a>

    </div>

</section>


<!-- ==================================================
     CATEGORY SECTION
================================================== -->

<section id="categories" class="category-tiles">

    <h2>Shop by Category</h2>

    <div class="tile-grid">

        <?php if ($categories && $categories->num_rows > 0): ?>

            <?php while ($cat = $categories->fetch_assoc()): ?>

                <a
                    class="category-tile"
                    href="category.php?category_id=<?php echo $cat['category_id']; ?>"
                >

                    <span class="tile-icon">
                        <?php
                        echo strtoupper(
                            substr($cat['category_name'], 0, 1)
                        );
                        ?>
                    </span>

                    <span class="tile-name">
                        <?php
                        echo htmlspecialchars($cat['category_name']);
                        ?>
                    </span>

                </a>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-state">
                Categories haven't been added yet.
            </p>

        <?php endif; ?>

    </div>

</section>


<!-- ==================================================
     TRENDING PRODUCTS SECTION
================================================== -->

<section class="trending">

    <h2>Trending Picks</h2>


    <?php if ($trending && $trending->num_rows > 0): ?>

        <div class="product-grid">

            <?php while ($p = $trending->fetch_assoc()): ?>

                <div class="product-card">

                    <img
                        src="https://placehold.co/300x300?text=<?php echo urlencode($p['name']); ?>"
                        alt="<?php echo htmlspecialchars($p['name']); ?>"
                    >

                    <h3>
                        <?php
                        echo htmlspecialchars($p['name']);
                        ?>
                    </h3>


                    <?php if (!empty($p['brand'])): ?>

                        <p class="muted">
                            <?php
                            echo htmlspecialchars($p['brand']);
                            ?>
                        </p>

                    <?php endif; ?>


                    <p class="price">
                        ৳<?php
                        echo number_format($p['price'], 2);
                        ?>
                    </p>


                    <a
                        class="btn-add"
                        href="product.php?id=<?php echo $p['product_id']; ?>"
                    >
                        View Details
                    </a>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <p class="empty-state">
            No products yet — the catalog is just getting started.
            Check back soon.
        </p>

    <?php endif; ?>

</section>


<?php

// Footer is also in the same folder as index.php
include 'footer.php';

?>