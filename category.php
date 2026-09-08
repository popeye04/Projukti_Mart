<?php
session_start();
require 'db.php';


// ==================================================
// GET CATEGORY ID
// ==================================================

$category_id = isset($_GET['category_id'])
    ? intval($_GET['category_id'])
    : 0;


// ==================================================
// GET CATEGORY INFORMATION
// ==================================================

$cat_stmt = $conn->prepare(
    "SELECT category_id, category_name, parent_category_id
     FROM categories
     WHERE category_id = ?"
);

$cat_stmt->bind_param("i", $category_id);
$cat_stmt->execute();

$category = $cat_stmt->get_result()->fetch_assoc();


// If category doesn't exist, go back to home
if (!$category) {
    header("Location: index.php");
    exit();
}


$page_title = $category['category_name'];


// Header is directly inside the Projukti Mart folder
include 'header.php';


// ==================================================
// GET SUBCATEGORIES
// ==================================================

$sub_stmt = $conn->prepare(
    "SELECT category_id, category_name
     FROM categories
     WHERE parent_category_id = ?
     ORDER BY category_name ASC"
);

$sub_stmt->bind_param("i", $category_id);
$sub_stmt->execute();

$subcategories = $sub_stmt->get_result();


// ==================================================
// CATEGORY IDS
// Include this category + its subcategories
// ==================================================

$category_ids = [$category_id];

if ($subcategories->num_rows > 0) {

    $subcategories->data_seek(0);

    while ($sub = $subcategories->fetch_assoc()) {
        $category_ids[] = $sub['category_id'];
    }

    $subcategories->data_seek(0);
}


// ==================================================
// READ FILTERS
// ==================================================

$min_price = (
    isset($_GET['min_price']) &&
    $_GET['min_price'] !== ''
)
    ? floatval($_GET['min_price'])
    : null;


$max_price = (
    isset($_GET['max_price']) &&
    $_GET['max_price'] !== ''
)
    ? floatval($_GET['max_price'])
    : null;


$brand = (
    isset($_GET['brand']) &&
    $_GET['brand'] !== ''
)
    ? $_GET['brand']
    : null;


$sort = isset($_GET['sort'])
    ? $_GET['sort']
    : 'newest';


// ==================================================
// PREPARE CATEGORY PLACEHOLDERS
// ==================================================

$placeholders = implode(
    ',',
    array_fill(0, count($category_ids), '?')
);

$id_types = str_repeat(
    'i',
    count($category_ids)
);


// ==================================================
// GET AVAILABLE BRANDS
// ==================================================

$brand_sql = "
    SELECT DISTINCT brand
    FROM products
    WHERE category_id IN ($placeholders)
      AND status = 'active'
      AND brand IS NOT NULL
      AND brand != ''
    ORDER BY brand ASC
";

$brand_stmt = $conn->prepare($brand_sql);

$brand_stmt->bind_param(
    $id_types,
    ...$category_ids
);

$brand_stmt->execute();

$brands = $brand_stmt->get_result();


// ==================================================
// MAIN PRODUCT QUERY
// ==================================================

$sql = "
    SELECT
        product_id,
        name,
        brand,
        price,
        stock_qty
    FROM products
    WHERE category_id IN ($placeholders)
      AND status = 'active'
";

$params = $category_ids;

$param_types = $id_types;


// ==================================================
// MINIMUM PRICE FILTER
// ==================================================

if ($min_price !== null) {

    $sql .= " AND price >= ?";

    $params[] = $min_price;

    $param_types .= 'd';
}


// ==================================================
// MAXIMUM PRICE FILTER
// ==================================================

if ($max_price !== null) {

    $sql .= " AND price <= ?";

    $params[] = $max_price;

    $param_types .= 'd';
}


// ==================================================
// BRAND FILTER
// ==================================================

if ($brand !== null) {

    $sql .= " AND brand = ?";

    $params[] = $brand;

    $param_types .= 's';
}


// ==================================================
// SORTING
// ==================================================

switch ($sort) {

    case 'price_asc':

        $sql .= " ORDER BY price ASC";

        break;


    case 'price_desc':

        $sql .= " ORDER BY price DESC";

        break;


    default:

        $sql .= " ORDER BY created_at DESC";

        break;
}


// ==================================================
// EXECUTE PRODUCT QUERY
// ==================================================

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $param_types,
    ...$params
);

$stmt->execute();

$products = $stmt->get_result();

?>

<!-- ==================================================
     BREADCRUMB
================================================== -->

<nav class="breadcrumb">

    <a href="index.php">
        Home
    </a>

    /

    <span>
        <?php
        echo htmlspecialchars($category['category_name']);
        ?>
    </span>

</nav>


<!-- ==================================================
     CATEGORY LAYOUT
================================================== -->

<div class="category-layout">


    <!-- ==================================================
         FILTER SIDEBAR
    ================================================== -->

    <aside class="filters">


        <!-- SUBCATEGORIES -->

        <?php if ($subcategories->num_rows > 0): ?>

            <div class="filter-group">

                <label>
                    Subcategory
                </label>

                <ul class="subcat-list">

                    <?php while ($sub = $subcategories->fetch_assoc()): ?>

                        <li>

                            <a
                                href="category.php?category_id=<?php echo $sub['category_id']; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $sub['category_name']
                                );
                                ?>

                            </a>

                        </li>

                    <?php endwhile; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- FILTERS -->

        <h3>
            Filters
        </h3>


        <form
            method="GET"
            action="category.php"
        >

            <input
                type="hidden"
                name="category_id"
                value="<?php echo $category_id; ?>"
            >


            <!-- PRICE RANGE -->

            <div class="filter-group">

                <label>
                    Price Range (৳)
                </label>

                <div class="price-inputs">

                    <input
                        type="number"
                        name="min_price"
                        placeholder="Min"
                        value="<?php
                            echo htmlspecialchars(
                                $min_price ?? ''
                            );
                        ?>"
                    >

                    <input
                        type="number"
                        name="max_price"
                        placeholder="Max"
                        value="<?php
                            echo htmlspecialchars(
                                $max_price ?? ''
                            );
                        ?>"
                    >

                </div>

            </div>


            <!-- BRAND -->

            <?php if ($brands->num_rows > 0): ?>

                <div class="filter-group">

                    <label for="brand">
                        Brand
                    </label>

                    <select
                        name="brand"
                        id="brand"
                    >

                        <option value="">
                            All Brands
                        </option>


                        <?php while ($b = $brands->fetch_assoc()): ?>

                            <option
                                value="<?php echo htmlspecialchars($b['brand']); ?>"
                                <?php
                                echo ($brand === $b['brand'])
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php
                                echo htmlspecialchars($b['brand']);
                                ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

            <?php endif; ?>


            <!-- SORT -->

            <div class="filter-group">

                <label for="sort">
                    Sort by
                </label>

                <select
                    name="sort"
                    id="sort"
                >

                    <option
                        value="newest"
                        <?php
                        echo $sort === 'newest'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Newest
                    </option>


                    <option
                        value="price_asc"
                        <?php
                        echo $sort === 'price_asc'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Price: Low to High
                    </option>


                    <option
                        value="price_desc"
                        <?php
                        echo $sort === 'price_desc'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Price: High to Low
                    </option>

                </select>

            </div>


            <!-- APPLY FILTERS -->

            <button
                type="submit"
                class="btn-filter"
            >
                Apply Filters
            </button>

        </form>

    </aside>


    <!-- ==================================================
         PRODUCT RESULTS
    ================================================== -->

    <section class="results">


        <div class="results-header">

            <h1>
                <?php
                echo htmlspecialchars(
                    $category['category_name']
                );
                ?>
            </h1>

        </div>


        <?php if ($products->num_rows === 0): ?>

            <p class="empty-state">
                No products found. Try adjusting your filters.
            </p>


        <?php else: ?>


            <div class="product-grid">


                <?php while ($p = $products->fetch_assoc()): ?>


                    <div class="product-card">


                        <!-- PRODUCT IMAGE -->

                        <img
                            src="https://placehold.co/300x300?text=<?php echo urlencode($p['name']); ?>"
                            alt="<?php echo htmlspecialchars($p['name']); ?>"
                        >


                        <!-- PRODUCT NAME -->

                        <h3>
                            <?php
                            echo htmlspecialchars(
                                $p['name']
                            );
                            ?>
                        </h3>


                        <!-- BRAND -->

                        <?php if (!empty($p['brand'])): ?>

                            <p class="muted">

                                <?php
                                echo htmlspecialchars(
                                    $p['brand']
                                );
                                ?>

                            </p>

                        <?php endif; ?>


                        <!-- PRICE -->

                        <p class="price">

                            ৳<?php
                            echo number_format(
                                $p['price'],
                                2
                            );
                            ?>

                        </p>


                        <!-- STOCK -->

                        <?php if ($p['stock_qty'] <= 0): ?>

                            <p class="stock-badge out">
                                Out of Stock
                            </p>

                        <?php endif; ?>


                        <!-- PRODUCT DETAILS -->

                        <a
                            class="btn-add"
                            href="product.php?id=<?php echo $p['product_id']; ?>"
                        >
                            View Details
                        </a>


                    </div>


                <?php endwhile; ?>


            </div>


        <?php endif; ?>


    </section>


</div>


<?php

// Footer is directly inside the Projukti Mart folder
include 'footer.php';

?>