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

<button id="chatbotToggle" class="chatbot-toggle" aria-label="Open chat assistant">💬</button>

<div id="chatbotPanel" class="chatbot-panel" hidden>
    <div class="chatbot-header">
        <span>Ask Projukti Assistant</span>
        <button id="chatbotClose" aria-label="Close chat">&times;</button>
    </div>
    <div class="chatbot-messages">
        <p class="bot-msg">Hi! I know the Projukti Mart project structure and catalog. Ask about categories, products, checkout, sellers, admin, or database tables.</p>
    </div>
    <form class="chatbot-input" id="chatbotForm">
        <input type="text" id="chatbotInput" name="message" placeholder="Type your question..." autocomplete="off" required>
        <button type="submit">Send</button>
    </form>
</div>

<script>
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbotPanel = document.getElementById('chatbotPanel');
    const chatbotClose = document.getElementById('chatbotClose');
    const chatbotForm = document.getElementById('chatbotForm');
    const chatbotInput = document.getElementById('chatbotInput');
    const chatbotMessages = document.querySelector('.chatbot-messages');

    function addBotMessage(text) {
        const p = document.createElement('p');
        p.className = 'bot-msg';
        p.textContent = text;
        chatbotMessages.appendChild(p);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }

    chatbotToggle.addEventListener('click', () => {
        chatbotPanel.hidden = false;
        chatbotToggle.hidden = true;
        chatbotInput.focus();
    });

    chatbotClose.addEventListener('click', () => {
        chatbotPanel.hidden = true;
        chatbotToggle.hidden = false;
    });

    chatbotForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = chatbotInput.value.trim();
        if (!message) {
            return;
        }

        const userMsg = document.createElement('p');
        userMsg.className = 'user-msg';
        userMsg.textContent = message;
        chatbotMessages.appendChild(userMsg);

        chatbotInput.value = '';

        try {
            const response = await fetch('/projukti_mart/ai_search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: 'message=' + encodeURIComponent(message)
            });

            const data = await response.json();
            addBotMessage(data.answer || 'I could not answer that question yet.');
        } catch (error) {
            addBotMessage('The assistant could not reach the project knowledge endpoint.');
        }
    });
</script>

</body>
</html>
