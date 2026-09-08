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
    <script>
        (() => {
            let theme = 'dark';
            try { if (localStorage.getItem('pm-theme') === 'light') theme = 'light'; } catch (_) {}
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Projukti Mart' : 'Projukti Mart'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/projukti_mart/assets/css/style.css">
</head>
<body>
<script>document.body.classList.add(document.documentElement.dataset.theme === 'light' ? 'light-mode' : 'dark-mode');</script>

<header class="site-header">
    <div class="header-utility">
        <div class="header-utility-inner">
            <span>Thoughtfully chosen tech. Everyday possibilities.</span>
            <span class="utility-location"><span aria-hidden="true">&#9679;</span> Bangladesh <span aria-hidden="true">/</span> BDT &#2547;</span>
        </div>
    </div>
    <div class="header-main">
        <a href="/projukti_mart/index.php" class="logo" aria-label="Projukti Mart home"><span class="logo-mark" aria-hidden="true"><svg viewBox="0 0 28 28" fill="none"><path d="M5 22V6h10a6 6 0 0 1 0 12h-5" stroke="currentColor" stroke-width="3.5"/><path d="M11 22V12h4" stroke="currentColor" stroke-width="3.5"/></svg></span><span class="logo-wordmark">Projukti<span>Mart</span></span></a>

        <form class="search-bar" action="/projukti_mart/search_results.php" method="GET" autocomplete="off">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>
            <input
                type="text"
                name="q"
                placeholder="Find your next upgrade..."
                aria-label="Search products"
                value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                required
            >
            <button type="submit" aria-label="Search">Search</button>
        </form>

        <div class="header-actions">
            <button id="themeToggle" class="theme-toggle" type="button" aria-label="Switch to light mode" aria-pressed="false" title="Switch color theme">
                <svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4m0-14.2-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>
                <svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20.5 13.4A8.6 8.6 0 0 1 10.6 3.5 8.7 8.7 0 1 0 20.5 13.4Z"/></svg>
            </button>
            <details class="account-menu">
                <summary>
                    <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                    <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Account'; ?>
                </summary>
                <ul>
                    <?php if (isset($_SESSION['username'])): ?>
                        <li><a href="/projukti_mart/orders.php">My Orders</a></li>
                        <li><a href="/projukti_mart/profile.php">My Addresses</a></li>
                        <?php if ($_SESSION['role'] === 'customer'): ?>
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
                <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 8h13l1 13H4L5 8Zm3 0V6a3 3 0 0 1 6 0v2"/></svg>
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

    <nav class="category-nav" aria-label="Product categories">
        <span class="nav-label">THE COLLECTION</span>
        <ul>
            <?php while ($cat = $nav_categories->fetch_assoc()): ?>
                <li>
                    <a href="/projukti_mart/category.php?category_id=<?php echo $cat['category_id']; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
        <span class="nav-note">Discover what&apos;s next <span aria-hidden="true">&#8599;</span></span>
    </nav>
</header>

<script>
    (() => {
        const button = document.getElementById('themeToggle');
        const applyTheme = (theme) => {
            const light = theme === 'light';
            document.documentElement.dataset.theme = light ? 'light' : 'dark';
            document.body.classList.toggle('light-mode', light);
            document.body.classList.toggle('dark-mode', !light);
            button.setAttribute('aria-pressed', String(light));
            button.setAttribute('aria-label', light ? 'Switch to dark mode' : 'Switch to light mode');
            button.title = light ? 'Switch to dark mode' : 'Switch to light mode';
        };
        applyTheme(document.documentElement.dataset.theme);
        button.addEventListener('click', () => {
            const theme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
            applyTheme(theme);
            try { localStorage.setItem('pm-theme', theme); } catch (_) {}
        });
        window.addEventListener('storage', (event) => {
            if (event.key === 'pm-theme') applyTheme(event.newValue === 'light' ? 'light' : 'dark');
        });
    })();
</script>

<script>
    // Presentation only: native navigation and commerce forms remain the source of truth.
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        const counters = new Map();
        const pendingForms = new Map();
        const resumingForms = new WeakSet();
        let exitTimer;
        let recoveryTimer;
        const all = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
        const delay = (element, index, limit = 5) => element.style.setProperty('--motion-delay', Math.min(index, limit) * .1 + 's');
        const restart = (element, name, duration = 550) => {
            if (!element || reduced.matches) return;
            element.classList.remove(name);
            void element.offsetWidth;
            element.classList.add(name);
            window.setTimeout(() => element.classList.remove(name), duration);
        };
        const finishCounter = record => {
            window.cancelAnimationFrame(record.frame);
            record.element.textContent = record.original;
            record.done = true;
        };
        const count = element => {
            const record = counters.get(element);
            if (!record || record.started || record.done) return;
            if (reduced.matches) { finishCounter(record); return; }
            record.started = true;
            const start = performance.now() + (parseFloat(getComputedStyle(element).getPropertyValue('--motion-delay')) || 0) * 1000;
            const format = new Intl.NumberFormat('en-BD', {
                minimumFractionDigits: record.decimals, maximumFractionDigits: record.decimals,
                useGrouping: record.grouped
            });
            const tick = now => {
                if (reduced.matches || document.hidden) { finishCounter(record); return; }
                const progress = Math.max(0, Math.min((now - start) / 500, 1));
                if (progress === 1) { finishCounter(record); return; }
                element.textContent = record.prefix + format.format(record.value * (1 - Math.pow(1 - progress, 3))) + record.suffix;
                record.frame = window.requestAnimationFrame(tick);
            };
            element.textContent = record.prefix + format.format(0) + record.suffix;
            record.frame = window.requestAnimationFrame(tick);
        };
        all('.stat-value').forEach(element => {
            const original = element.textContent;
            const match = original.trim().match(/^([^0-9-]*)(-?\d[\d,]*(?:\.\d+)?)([^0-9]*)$/);
            if (!match) return;
            const value = Number(match[2].replace(/,/g, ''));
            if (!Number.isFinite(value)) return;
            element.classList.add('motion-counter');
            counters.set(element, {
                element, original, value, prefix: match[1], suffix: match[3],
                decimals: (match[2].split('.')[1] || '').length, grouped: match[2].includes(','),
                started: false, done: false, frame: 0
            });
        });
        ['.header-main', '.hero-text', '.page-heading', '.auth-card'].forEach(selector => {
            all(selector).forEach(group => {
                all(':scope > .logo, :scope > .search-bar, :scope > .header-actions, :scope > .page-eyebrow, :scope > .eyebrow, :scope > h1, :scope > p, :scope > .hero-actions, :scope > .hero-meta', group)
                    .forEach((element, index) => { element.classList.add('motion-load'); delay(element, index); });
            });
        });
        all('.hero-visual, .category-nav, .breadcrumb, .site-main > h1, .product-info > h1').forEach(element => {
            element.classList.add('motion-load');
            delay(element, 1);
        });
        if (/\/(?:seller|admin)\//i.test(location.pathname)) {
            all('.cart-table').forEach(table => {
                table.classList.add('motion-table');
                all('tbody > tr', table).forEach((row, index) => {
                    row.classList.add('motion-row');
                    delay(row, index, 3);
                });
            });
        }
        const reveals = all('.product-card, .section-heading, .recommendations > h2, .category-tiles > h2, .product-reviews > h2, .address-list > h2, .seller-product-list > h2, .stat-card');
        reveals.forEach(element => {
            element.classList.add('motion-reveal');
            if (element.matches('.section-heading, h2')) element.classList.add('motion-left');
        });
        all('.product-grid, .recommendation-carousel, .dashboard-stats').forEach(group => {
            const staggerSize = group.matches('.product-grid') ? 4 : 6;
            all(':scope > .motion-reveal', group).forEach((element, index) => delay(element, index % staggerSize));
        });
        const timelines = all('.order-progress, .status-timeline');
        timelines.forEach(timeline => all('li', timeline).forEach((item, index) => delay(item, index)));
        const reveal = element => {
            element.classList.add('is-visible');
            if (element.matches('.order-progress, .status-timeline')) element.classList.add('timeline-ready');
            all('.stat-value', element).forEach(count);
        };
        const revealAll = () => {
            reveals.forEach(reveal);
            timelines.forEach(reveal);
            counters.forEach(finishCounter);
        };
        try {
            if (!('IntersectionObserver' in window)) {
                revealAll();
            } else {
                const observer = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        if (!entry.isIntersecting) return;
                        reveal(entry.target);
                        observer.unobserve(entry.target);
                    });
                }, { threshold: .08, rootMargin: '0px 0px -16px 0px' });
                [...reveals, ...timelines].forEach(element => observer.observe(element));
                body.classList.toggle('motion-ready', !reduced.matches);
                if (reduced.matches) revealAll();
            }
        } catch (_) {
            body.classList.remove('motion-ready');
            revealAll();
        }
        // No script/observer support means the original, fully visible interface.
        body.classList.add('loaded');
        const resetExit = () => {
            window.clearTimeout(exitTimer);
            window.clearTimeout(recoveryTimer);
            body.classList.remove('page-exit');
        };
        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;
            resetExit();
            pendingForms.forEach(({ timer, row }, form) => {
                window.clearTimeout(timer);
                row.classList.remove('motion-removing');
                resumingForms.delete(form);
            });
            pendingForms.clear();
            all('.motion-removing').forEach(row => row.classList.remove('motion-removing'));
        });
        const preferenceChanged = () => {
            if (!reduced.matches) return;
            body.classList.remove('motion-ready');
            revealAll();
        };
        if (reduced.addEventListener) reduced.addEventListener('change', preferenceChanged);
        else if (reduced.addListener) reduced.addListener(preferenceChanged);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) counters.forEach(record => { if (record.started) finishCounter(record); });
        });
        all('.category-nav a, .account-menu a, .footer-links a').forEach(link => {
            const url = new URL(link.href);
            if (url.pathname === location.pathname && [...url.searchParams].every(([key, value]) => new URLSearchParams(location.search).get(key) === value)) {
                link.setAttribute('aria-current', 'page');
            }
        });
        const rippleSelector = 'button, a.btn-hero, a.btn-add, a.btn-filter, a.btn-secondary, a.btn-cancel-edit, a.remove-link';
        all(rippleSelector).forEach(element => element.classList.add('motion-ripple-host'));
        document.addEventListener('click', event => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;
            const button = target.closest(rippleSelector);
            if (button && !button.disabled && !reduced.matches) {
                button.classList.add('motion-ripple-host');
                const rect = button.getBoundingClientRect();
                const ripple = document.createElement('span');
                ripple.className = 'motion-ripple';
                ripple.setAttribute('aria-hidden', 'true');
                ripple.style.setProperty('--ripple-size', Math.hypot(rect.width, rect.height) * 2 + 'px');
                ripple.style.setProperty('--ripple-x', (event.detail ? event.clientX - rect.left : rect.width / 2) + 'px');
                ripple.style.setProperty('--ripple-y', (event.detail ? event.clientY - rect.top : rect.height / 2) + 'px');
                button.appendChild(ripple);
                ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
                window.setTimeout(() => ripple.remove(), 600);
            }
            if (target.closest('#themeToggle')) restart(document.getElementById('themeToggle'), 'theme-spinning');
            const link = target.closest('a[href]');
            if (!link || event.defaultPrevented || reduced.matches || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.hasAttribute('download') || link.hasAttribute('data-no-transition') || (link.target && link.target !== '_self') || link.relList.contains('external')) return;
            const url = new URL(link.href);
            if (url.origin !== location.origin || !/^https?:$/.test(url.protocol) || !url.pathname.startsWith('/projukti_mart/')) return;
            if (!url.pathname.endsWith('.php') && !url.pathname.endsWith('/')) return;
            if (url.pathname === location.pathname && url.search === location.search) return;
            event.preventDefault();
            resetExit();
            body.classList.add('page-exit');
            exitTimer = window.setTimeout(() => {
                try { window.location.assign(url.href); } catch (_) { resetExit(); }
                // Recover if navigation is cancelled; pageshow handles browser Back/Forward.
                recoveryTimer = window.setTimeout(resetExit, 400);
            }, 180);
        });
        // Animate before the existing POST, preserving the actual clicked submitter and CSRF fields.
        document.addEventListener('submit', event => {
            const form = event.target;
            const submitter = event.submitter;
            if (!(form instanceof HTMLFormElement) || !form.matches('.cart-table-form') || !submitter?.matches('[name="remove"]')) return;
            if (resumingForms.has(form)) { resumingForms.delete(form); return; }
            if (event.defaultPrevented || reduced.matches || typeof form.requestSubmit !== 'function') return;
            const row = submitter.closest('tr');
            if (!row) return;
            event.preventDefault();
            if (pendingForms.has(form)) return;
            row.classList.add('motion-removing');
            const timer = window.setTimeout(() => {
                pendingForms.delete(form);
                if (submitter.isConnected && form.isConnected) {
                    resumingForms.add(form);
                    try { form.requestSubmit(submitter); } finally { resumingForms.delete(form); }
                }
                window.setTimeout(() => row.classList.remove('motion-removing'), 400);
            }, 240);
            pendingForms.set(form, { timer, row });
        });
        const cartCount = document.querySelector('.cart-count');
        const updateCartMotion = () => {
            if (!cartCount) return;
            const count = Number(cartCount.textContent.trim());
            if (!Number.isFinite(count)) return;
            const account = document.querySelector('.account-menu summary')?.textContent.trim();
            try {
                const previous = JSON.parse(sessionStorage.getItem('pm-cart-motion') || 'null');
                if (previous?.account === account && count > previous.count) restart(document.querySelector('.cart-link'), 'cart-bounce');
                sessionStorage.setItem('pm-cart-motion', JSON.stringify({ account, count }));
            } catch (_) { /* Cart behavior never depends on browser storage. */ }
        };
        updateCartMotion();
        if (cartCount && 'MutationObserver' in window) new MutationObserver(updateCartMotion).observe(cartCount, { childList: true, characterData: true, subtree: true });
        // Optional dismissal hook for alerts/dialogs with an existing dismiss control.
        document.addEventListener('click', event => {
            const dismiss = event.target instanceof Element ? event.target.closest('[data-motion-dismiss]') : null;
            const panel = dismiss?.closest('.cart-message, .stock-warning-box, [role="alert"], .modal, dialog');
            if (!panel || event.defaultPrevented) return;
            event.preventDefault();
            const finish = () => { if (panel.tagName === 'DIALOG' && typeof panel.close === 'function') panel.close(); else panel.remove(); };
            if (reduced.matches) { finish(); return; }
            panel.classList.add('motion-dismiss');
            window.setTimeout(finish, 240);
        });
    }, { once: true });
</script>

<main class="site-main">
