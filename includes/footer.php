<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../db.php';
?>
</main>

<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-brand">
            <p class="footer-eyebrow">GOOD TECH. GREAT POSSIBILITIES.</p>
            <p class="logo"><span class="logo-wordmark">Projukti<span>Mart</span></span></p>
            <p>For the things you make.<br>The games you play. The life you lead.</p>
        </div>
        <div class="footer-links">
            <h4>Shop</h4>
            <ul>
                <?php
                // Re-query categories here since the header's result pointer is already exhausted.
                $footer_categories = db_run(
                    "SELECT category_id, category_name FROM categories WHERE parent_category_id IS NULL AND is_active = 1 ORDER BY category_name ASC"
                )->get_result();
                while ($cat = $footer_categories->fetch_assoc()):
                ?>
                <li>
                    <a href="/Project/category.php?category_id=<?php echo $cat['category_id']; ?>">
                        <?php echo h($cat['category_name']); ?>
                    </a>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <div class="footer-links">
            <h4>Account</h4>
            <ul>
                <?php if (($_SESSION['role'] ?? '') === 'customer'): ?>
                <li><a href="/Project/orders.php">My Orders</a></li>
                <li><a href="/Project/profile.php">Profile &amp; addresses</a></li>
                <?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <li><a href="/Project/admin/dashboard.php">Admin Dashboard</a></li>
                <?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'seller'): ?>
                <li><a href="/Project/seller/dashboard.php">Seller Dashboard</a></li>
                <?php endif; ?>
                <li><a href="/Project/login.php">Login</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p class="footer-note">&copy; <?php echo date('Y'); ?> Projukti Mart. All rights reserved.</p>
        <p class="footer-signature">Made for your next. <span>Bangladesh / BDT &#2547;</span></p>
    </div>
</footer>

<!-- Visibility listeners stay here; chatbot.js owns only message submission. -->
<button id="chatbotToggle" class="chatbot-toggle" aria-label="Open chat assistant"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8 8H5l-3 2v-10a9 9 0 0 1 18 0Z"/><path d="m12 6 1.2 3.3 3.3 1.2-3.3 1.2L12 15l-1.2-3.3-3.3-1.2 3.3-1.2L12 6Z"/></svg><span>Ask Projukti</span></button>

<div id="chatbotPanel" class="chatbot-panel" hidden>
    <div class="chatbot-header">
        <div class="chatbot-heading"><span>Projukti Assistant</span><small class="chatbot-online">Your next upgrade starts here</small></div>
        <button id="chatbotClose" aria-label="Close chat">&times;</button>
    </div>
    <div class="chatbot-messages" id="chatbotMessages" role="log" aria-live="polite" aria-label="Assistant messages">
        <p class="bot-msg">Hi! কী খুঁজছেন? Ask in বাংলা, English, or both — e.g. "coding-এর জন্য laptop under 70k".</p>
    </div>
    <form class="chatbot-input" id="chatbotForm">
        <input id="chatbotInput" type="text" name="message" maxlength="255" aria-label="Ask the assistant" placeholder="বাংলা / English..." autocomplete="off" dir="auto" required>
        <button id="chatbotMic" type="button" aria-label="Start voice input" aria-pressed="false" title="Speak your message"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v1a7 7 0 0 0 14 0v-1m-7 8v4m-4 0h8"/></svg></button>
        <button id="chatbotSend" type="submit">Send</button>
    </form>
    <div class="chatbot-input chatbot-voice">
        <label for="chatbotVoiceLanguage">Voice language</label>
        <select id="chatbotVoiceLanguage" aria-label="Voice recognition language">
            <option value="bn-BD">বাংলা</option>
            <option value="en-US">English</option>
        </select>
    </div>
    <p id="chatbotVoiceStatus" class="muted" role="status" aria-live="polite"></p>
</div>

<script>
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbotPanel = document.getElementById('chatbotPanel');
    const chatbotClose = document.getElementById('chatbotClose');

    chatbotToggle.addEventListener('click', () => {
        chatbotPanel.hidden = false;
        chatbotToggle.hidden = true;
        document.getElementById('chatbotInput').focus();
    });
    chatbotClose.addEventListener('click', () => {
        chatbotPanel.hidden = true;
        chatbotToggle.hidden = false;
        chatbotToggle.focus();
    });
</script>
<script src="/Project/assets/js/chatbot.js" defer></script>

</body>
</html>
