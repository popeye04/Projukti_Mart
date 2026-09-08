</main>

<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-brand">
            <p class="logo">Projukti<span>Mart</span></p>
            <p>Your trusted spot for mobiles, PCs, and laptops.</p>
        </div>
        <div class="footer-links">
            <h4>Shop</h4>
            <ul>
                <?php
                // Re-query categories here since the header's result pointer is already exhausted.
                $footer_categories = $conn->query(
                    "SELECT category_id, category_name FROM categories WHERE parent_category_id IS NULL AND is_active = 1 ORDER BY category_name ASC"
                );
                while ($cat = $footer_categories->fetch_assoc()):
                ?>
                <li>
                    <a href="/projukti_mart/category.php?category_id=<?php echo $cat['category_id']; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <div class="footer-links">
            <h4>Account</h4>
            <ul>
                <li><a href="/projukti_mart/orders.php">My Orders</a></li>
                <li><a href="/projukti_mart/login.php">Login</a></li>
            </ul>
        </div>
    </div>
    <p class="footer-note">&copy; <?php echo date('Y'); ?> Projukti Mart — CSE327 demo project.</p>
</footer>

<!-- Chatbot widget: open/close toggle only for now. The actual AI request logic
     will be wired up in chatbot.js once ai_search.php exists. -->
<button id="chatbotToggle" class="chatbot-toggle" aria-label="Open chat assistant">💬</button>

<div id="chatbotPanel" class="chatbot-panel" hidden>
    <div class="chatbot-header">
        <span>Ask Projukti Assistant</span>
        <button id="chatbotClose" aria-label="Close chat">&times;</button>
    </div>
    <div class="chatbot-messages">
        <p class="bot-msg">Hi! Tell me what you're looking for — e.g. "a lightweight laptop for coding under 70k".</p>
    </div>
    <form class="chatbot-input" id="chatbotForm">
        <input type="text" name="message" placeholder="Type your question..." autocomplete="off" required>
        <button type="submit">Send</button>
    </form>
</div>

<script>
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbotPanel = document.getElementById('chatbotPanel');
    const chatbotClose = document.getElementById('chatbotClose');

    chatbotToggle.addEventListener('click', () => {
        chatbotPanel.hidden = false;
        chatbotToggle.hidden = true;
    });
    chatbotClose.addEventListener('click', () => {
        chatbotPanel.hidden = true;
        chatbotToggle.hidden = false;
    });
    // chatbotForm submit handler (fetch call to ai_search.php) added once that file exists.
</script>

</body>
</html>
