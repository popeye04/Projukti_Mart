</main>

<footer class="site-footer">

    <div class="footer-content">

        <!-- FOOTER BRAND -->
        <div class="footer-brand">

            <p class="logo">
                Projukti<span>Mart</span>
            </p>

            <p>
                Your trusted spot for mobiles, PCs, and laptops.
            </p>

        </div>


        <!-- SHOP LINKS -->
        <div class="footer-links">

            <h4>Shop</h4>

            <ul>

                <?php

                // Get the top-level categories.
                // IMPORTANT:
                // The categories table does NOT have an is_active column.
                $footer_categories = $conn->query(
                    "SELECT category_id, category_name
                     FROM categories
                     WHERE parent_category_id IS NULL
                     ORDER BY category_name ASC"
                );

                if ($footer_categories && $footer_categories->num_rows > 0):

                    while ($cat = $footer_categories->fetch_assoc()):

                ?>

                    <li>

                        <a
                            href="category.php?category_id=<?php echo $cat['category_id']; ?>"
                        >

                            <?php
                            echo htmlspecialchars($cat['category_name']);
                            ?>

                        </a>

                    </li>

                <?php

                    endwhile;

                endif;

                ?>

            </ul>

        </div>


        <!-- ACCOUNT LINKS -->
        <div class="footer-links">

            <h4>Account</h4>

            <ul>

                <li>
                    <a href="orders.php">
                        My Orders
                    </a>
                </li>

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

            </ul>

        </div>

    </div>


    <!-- COPYRIGHT -->

    <p class="footer-note">

        &copy;
        <?php echo date('Y'); ?>
        Projukti Mart — CSE327 demo project.

    </p>

</footer>


<!-- ==================================================
     CHATBOT
================================================== -->

<button
    id="chatbotToggle"
    class="chatbot-toggle"
    aria-label="Open chat assistant"
>
    💬
</button>


<div
    id="chatbotPanel"
    class="chatbot-panel"
    hidden
>

    <div class="chatbot-header">

        <span>
            Ask Projukti Assistant
        </span>

        <button
            id="chatbotClose"
            aria-label="Close chat"
        >
            &times;
        </button>

    </div>


    <div class="chatbot-messages">

        <p class="bot-msg">
            Hi! Tell me what you're looking for — e.g.
            "a lightweight laptop for coding under 70k".
        </p>

    </div>


    <form
        class="chatbot-input"
        id="chatbotForm"
    >

        <input
            type="text"
            name="message"
            placeholder="Type your question..."
            autocomplete="off"
            required
        >

        <button type="submit">
            Send
        </button>

    </form>

</div>


<!-- ==================================================
     CHATBOT JAVASCRIPT
================================================== -->

<script>

    const chatbotToggle =
        document.getElementById('chatbotToggle');

    const chatbotPanel =
        document.getElementById('chatbotPanel');

    const chatbotClose =
        document.getElementById('chatbotClose');


    // Open chatbot

    chatbotToggle.addEventListener('click', () => {

        chatbotPanel.hidden = false;

        chatbotToggle.hidden = true;

    });


    // Close chatbot

    chatbotClose.addEventListener('click', () => {

        chatbotPanel.hidden = true;

        chatbotToggle.hidden = false;

    });

</script>


</body>
</html>