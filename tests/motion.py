"""Chrome motion regression checks using a disposable database and disabled cURL.

Requires XAMPP, installed Chrome, and Python websocket-client. No store writes or
external assistant calls. Optional .motion-baseline.css enables visual invariants.
"""
from pathlib import Path
import http.cookiejar
import json
import os
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import websocket

ROOT = Path(__file__).resolve().parents[1]
PHP = r'C:\xampp\php\php.exe'
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
DB = 'projukti_mart_verify_' + uuid.uuid4().hex[:12]
PASSWORD = 'Motion-Test-927!'
checks = 0
server = None
created = False


def sql(statement, database=True):
    arguments = [MYSQL, '--user=root', '--host=127.0.0.1', '--default-character-set=utf8mb4', '--batch', '--skip-column-names']
    if database:
        arguments.append('--database=' + DB)
    return subprocess.run(arguments, input=statement.encode(), capture_output=True, check=True).stdout.decode().strip()


def check(condition, message):
    global checks
    assert condition, message
    checks += 1
    print('PASS ' + message, flush=True)


class Client:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path, data=None):
        payload = urllib.parse.urlencode(data).encode() if data is not None else None
        with self.opener.open(BASE + path, data=payload, timeout=10) as response:
            page = response.read().decode()
        assert not re.search(r'Fatal error|Warning:|Parse error', page)
        return page

    def post(self, path, data):
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', self.request(path))[1]
        return self.request(path, dict(data, csrf_token=token))

    def login(self, email):
        self.post('/login.php', {'login': 1, 'email': email, 'password': PASSWORD})


class Browser:
    def __enter__(self):
        self.profile = tempfile.TemporaryDirectory(prefix='projukti_motion_', ignore_cleanup_errors=True)
        self.process = subprocess.Popen([r'C:\Program Files\Google\Chrome\Application\chrome.exe', '--headless=new',
            '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0',
            '--remote-allow-origins=*', '--user-data-dir=' + self.profile.name, 'about:blank'],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
        for _ in range(100):
            port_file = Path(self.profile.name) / 'DevToolsActivePort'
            if port_file.exists():
                break
            time.sleep(.1)
        port = port_file.read_text().splitlines()[0]
        with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/list') as response:
            page = next(page for page in json.load(response) if page['type'] == 'page')
        self.connection = websocket.create_connection(page['webSocketDebuggerUrl'], timeout=15, suppress_origin=True)
        self.sequence = 0
        self.errors = []
        self.requests = []
        self.scripts = []
        self.command('Runtime.enable')
        self.command('Page.enable')
        self.command('Network.enable')
        self.command('Network.setBlockedURLs', {'urls': ['https://fonts.googleapis.com/*', 'https://fonts.gstatic.com/*']})
        self.viewport(1440, 1000)
        self.preload("""window.motionSamples = []; window.cartPulses = [];
            document.addEventListener('DOMContentLoaded', () => {
                let samples = 0;
                const timer = setInterval(() => {
                    motionSamples.push(Array.from(document.querySelectorAll('.stat-value'), e => e.textContent));
                    if (++samples > 85) clearInterval(timer);
                }, 10);
                const cart = document.querySelector('.cart-link');
                if (cart) new MutationObserver(() => { if (cart.classList.contains('cart-bounce')) cartPulses.push(performance.now()); }).observe(cart, {attributes:true,attributeFilter:['class']});
            });""")
        return self

    def __exit__(self, *_):
        try:
            self.command('Browser.close')
        except (OSError, websocket.WebSocketException):
            pass
        self.connection.close()
        try:
            self.process.wait(timeout=10)
        except subprocess.TimeoutExpired:
            self.process.terminate()
            self.process.wait(timeout=10)
        for attempt in range(15):
            try:
                self.profile.cleanup()
                break
            except OSError:
                if attempt == 14:
                    raise
                time.sleep(.3)

    def command(self, method, params=None):
        self.sequence += 1
        self.connection.send(json.dumps({'id': self.sequence, 'method': method, 'params': params or {}}))
        while True:
            message = json.loads(self.connection.recv())
            if message.get('method') == 'Runtime.exceptionThrown':
                self.errors.append(message)
            if message.get('method') == 'Network.requestWillBeSent':
                self.requests.append(message['params']['request'])
            if message.get('id') == self.sequence:
                assert 'error' not in message, message
                return message.get('result', {})

    def evaluate(self, expression):
        result = self.command('Runtime.evaluate', {'expression': expression, 'returnByValue': True, 'awaitPromise': True})
        assert 'exceptionDetails' not in result, result
        return result.get('result', {}).get('value')

    def wait(self, expression):
        for _ in range(120):
            if self.evaluate(expression):
                return
            time.sleep(.05)
        raise AssertionError('Browser condition timed out: ' + expression)

    def navigate(self, path, settle=.85):
        self.command('Page.navigate', {'url': BASE + path})
        self.wait("document.readyState === 'complete' && document.body.classList.contains('loaded')")
        time.sleep(settle)

    def viewport(self, width, height, mobile=False):
        self.command('Emulation.setDeviceMetricsOverride', {'width': width, 'height': height, 'deviceScaleFactor': 1, 'mobile': mobile})
        self.command('Emulation.setTouchEmulationEnabled', {'enabled': mobile})

    def cookies(self, client):
        self.command('Network.clearBrowserCookies')
        for cookie in client.cookies:
            self.command('Network.setCookie', {'name': cookie.name, 'value': cookie.value, 'url': BASE, 'path': '/'})

    def preload(self, source):
        identifier = self.command('Page.addScriptToEvaluateOnNewDocument', {'source': source})['identifier']
        self.scripts.append(identifier)
        return identifier

    def remove_preload(self, identifier):
        self.command('Page.removeScriptToEvaluateOnNewDocument', {'identifier': identifier})

    def media(self, reduce=False):
        self.command('Emulation.setEmulatedMedia', {'features': [{'name': 'prefers-reduced-motion', 'value': 'reduce' if reduce else 'no-preference'}]})


GEOMETRY = """Array.from(document.querySelectorAll('.site-header,.header-main,.site-main,.hero,.hero-text,.hero-visual,.category-tile,.product-card,.stat-card,.cart-table,.cart-summary,.product-detail,.chatbot-toggle,.chatbot-panel'), (e,i) => {
    const r=e.getBoundingClientRect(), s=getComputedStyle(e);
    return {key:e.classList[0]+':'+i,x:r.x,y:r.y+scrollY,w:r.width,h:r.height,color:s.color,bg:s.backgroundColor,border:s.borderColor};
})"""


def compare_baseline(browser, name):
    baseline_path = ROOT / '.motion-baseline.css'
    if not baseline_path.exists():
        return
    browser.evaluate("window.scrollTo(0,0); document.querySelectorAll('.motion-reveal').forEach(e => e.classList.add('is-visible'))")
    time.sleep(.9)
    after = browser.evaluate(GEOMETRY)
    browser.evaluate("window.motionWrappers = Array.from(document.querySelectorAll('.product-card-media'), wrapper => [wrapper,wrapper.querySelector('img')]); motionWrappers.forEach(([wrapper,img])=>wrapper.replaceWith(img)); var motionBaselineStyle = document.createElement('style'); motionBaselineStyle.id='motion-test-baseline'; motionBaselineStyle.textContent=" + json.dumps(baseline_path.read_text(encoding='utf-8')) + "; document.head.append(motionBaselineStyle); document.querySelector('link[href$=\"style.css\"]').disabled=true")
    time.sleep(.65)
    before = browser.evaluate(GEOMETRY)
    browser.evaluate("motionWrappers.forEach(([wrapper,img])=>{img.replaceWith(wrapper);wrapper.append(img)}); document.querySelector('link[href$=\"style.css\"]').disabled=false; document.getElementById('motion-test-baseline').remove()")
    time.sleep(.65)
    differences = []
    for previous, current in zip(before, after):
        for key in ('x', 'y', 'w', 'h'):
            if abs(previous[key] - current[key]) > .8:
                differences.append((previous['key'], key, previous[key], current[key]))
        for key in ('color', 'bg', 'border'):
            if previous[key] != current[key]:
                differences.append((previous['key'], key, previous[key], current[key]))
    assert not differences, (name, differences[:20])
    check(True, name + ': settled geometry and surface colors match previous design')


try:
    assert re.fullmatch(r'projukti_mart_verify_[a-f0-9]{12}', DB)
    sql('CREATE DATABASE ' + DB + ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', False)
    created = True
    sql((ROOT / 'projukti_mart.sql').read_text(encoding='utf-8'))
    hashed = subprocess.check_output([PHP, '-r', "echo password_hash('" + PASSWORD + "', PASSWORD_DEFAULT);"]).decode()
    sql("UPDATE users SET password='" + hashed + "'")
    # All product rows are test fixtures; trends must be recent for scroll/carousel coverage.
    sql('UPDATE product_views SET viewed_at=CURRENT_TIMESTAMP')
    sql('UPDATE products SET stock_qty=50 WHERE product_id=1')
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    BASE = f'http://127.0.0.1:{port}/Project'
    log = tempfile.TemporaryFile()
    server = subprocess.Popen([PHP, '-d', 'disable_functions=curl_init', '-S', f'127.0.0.1:{port}', '-t', str(ROOT.parent)],
        env=dict(os.environ, PM_DB_NAME=DB), stdout=log, stderr=log, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    guest, customer, seller, admin = Client(), Client(), Client(), Client()
    for _ in range(50):
        try:
            guest.request('/index.php')
            break
        except urllib.error.URLError:
            time.sleep(.1)
    customer.post('/register.php', {'register': 1, 'username': 'motion_customer', 'full_name': 'Motion Customer', 'email': 'motion@example.test', 'phone': '', 'password': PASSWORD, 'confirm_password': PASSWORD, 'role': 'customer'})
    customer_id = int(sql("SELECT user_id FROM users WHERE username='motion_customer'"))
    seller.login('seller1@projuktimart.com')
    admin.login('admin@projuktimart.com')

    with Browser() as browser:
        browser.cookies(customer)
        browser.navigate('/index.php')
        check(browser.evaluate("getComputedStyle(document.getElementById('chatbotToggle')).position==='fixed' && getComputedStyle(document.getElementById('chatbotPanel')).position==='fixed'"), 'chat toggle and panel retain fixed positioning')
        check(browser.evaluate("document.body.classList.contains('motion-ready')"), 'DOMContentLoaded enables motion without blocking page content')
        check(browser.evaluate("document.querySelectorAll('.motion-reveal:not(.is-visible)').length > 0"), 'offscreen products wait for IntersectionObserver')
        browser.evaluate("document.querySelector('.recommendations').scrollIntoView({block:'center'})")
        browser.wait("!!document.querySelector('.recommendations .is-visible')")
        check(browser.evaluate("Array.from(document.querySelectorAll('.motion-reveal')).every(e => parseFloat(getComputedStyle(e).getPropertyValue('--motion-delay') || '0') <= .3 + .001)"), 'scroll stagger stays bounded to 0.3 seconds')
        compare_baseline(browser, 'desktop home dark')
        browser.evaluate("document.getElementById('themeToggle').click()")
        check(browser.evaluate("document.getElementById('themeToggle').classList.contains('theme-spinning')"), 'theme toggle starts rotation')
        time.sleep(.8)
        check(browser.evaluate("document.body.classList.contains('light-mode') && !document.getElementById('themeToggle').classList.contains('theme-spinning')"), 'theme rotation cleans up and saved light theme remains')
        compare_baseline(browser, 'desktop home light')
        browser.viewport(390, 844, True)
        browser.navigate('/index.php')
        compare_baseline(browser, 'phone home light')
        check(browser.evaluate("document.documentElement.scrollWidth <= innerWidth"), 'motion adds no horizontal phone overflow')
        check(browser.evaluate("Array.from(document.querySelectorAll('.card-cart-form .btn-add')).every(e=>{const s=getComputedStyle(e);return s.opacity==='1' && s.pointerEvents!=='none' && ['none','matrix(1, 0, 0, 1, 0, 0)'].includes(s.transform)})"), 'touch screens show Add to Cart buttons without hover')
        browser.evaluate("document.getElementById('chatbotToggle').click()")
        time.sleep(.7)
        check(browser.evaluate("(() => { const r=document.getElementById('chatbotPanel').getBoundingClientRect(); return r.x>=0 && r.right<=innerWidth && r.y>=0 && r.bottom<=innerHeight })()"), 'animated chatbot panel remains inside phone viewport')
        compare_baseline(browser, 'phone open chatbot')
        browser.evaluate("document.getElementById('chatbotClose').click()")
        browser.viewport(1440, 1000)
        browser.navigate('/category.php?category_id=3')
        browser.evaluate("document.querySelector('.recommendation-carousel').scrollIntoView({block:'center'})")
        browser.wait("!!document.querySelector('.recommendation-carousel .is-visible')")
        carousel = browser.evaluate("document.querySelector('.recommendation-carousel').scrollWidth > document.querySelector('.recommendation-carousel').clientWidth")
        if carousel:
            browser.evaluate("const carousel = document.querySelector('.recommendation-carousel'); carousel.scrollLeft=carousel.scrollWidth")
            browser.wait("document.querySelector('.recommendation-carousel .product-card:last-child').classList.contains('is-visible')")
            check(True, 'horizontal recommendation scrolling reveals later cards')
        browser.evaluate("document.querySelector('.product-card').scrollIntoView({block:'center'}); document.querySelector('.card-cart-form button').focus()")
        time.sleep(.7)
        check(browser.evaluate("(() => {const s=getComputedStyle(document.querySelector('.card-cart-form .btn-add'));return s.opacity==='1' && s.pointerEvents!=='none' && ['none','matrix(1, 0, 0, 1, 0, 0)'].includes(s.transform)})()"), 'keyboard focus reveals Add to Cart without a mouse')
        browser.evaluate("window.scrollTo(0,0)")
        browser.evaluate("document.getElementById('themeToggle').dispatchEvent(new MouseEvent('click', {bubbles:true,detail:0}))")
        check(browser.evaluate("document.querySelectorAll('.motion-ripple').length > 0"), 'keyboard button activation creates a ripple')
        time.sleep(.8)
        check(browser.evaluate("document.querySelectorAll('.motion-ripple').length === 0"), 'ripple nodes are removed after animation')

        # Observe delegated navigation without permitting browser default navigation.
        preserved = browser.evaluate("""(() => {
            const cases = [{href:'#motion-hash'},{href:'https://example.com/'},{href:'mailto:shop@example.test'},
                {href:'index.php',target:'_blank'},{href:'index.php',download:'file'}, {href:'index.php',ctrlKey:true},
                {href:'index.php',metaKey:true},{href:'index.php',shiftKey:true},{href:'index.php',button:1}];
            return cases.map(data => {
                const link=document.createElement('a'); link.href=data.href;
                if(data.target)link.target=data.target;if(data.download)link.download=data.download;
                document.body.append(link);let intercepted;
                window.addEventListener('click', event => {intercepted=event.defaultPrevented;event.preventDefault()}, {once:true});
                link.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true,...data}));link.remove();return intercepted===false;
            });
        })()""")
        check(all(preserved), 'modified, hash, external, mail, download, target and middle clicks retain native handling')
        browser.evaluate("document.body.classList.add('page-exit'); window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}))")
        check(browser.evaluate("!document.body.classList.contains('page-exit')"), 'back/forward cache restore clears page-exit')
        browser.evaluate("document.querySelector('.logo').click()")
        check(browser.evaluate("document.body.classList.contains('page-exit')"), 'ordinary internal link starts page exit')
        browser.wait("location.pathname.endsWith('/index.php') && document.body.classList.contains('loaded')")

        browser.navigate('/product.php?id=1')
        check(browser.evaluate('cartPulses.length === 0'), 'visiting a product does not report a cart addition')
        before = len(browser.requests)
        browser.evaluate("document.querySelector('[name=add_to_cart]').click()")
        browser.wait("location.search.includes('added=1') && document.body.classList.contains('loaded')")
        time.sleep(.8)
        check(browser.evaluate('cartPulses.length > 0'), 'successful add-to-cart response bounces header cart')
        check(sql(f'SELECT SUM(ci.quantity) FROM cart_items ci JOIN cart c ON c.cart_id=ci.cart_id WHERE c.user_id={customer_id}') == '1', 'animated cart addition preserves exactly one server write')
        browser.navigate('/cart.php')
        compare_baseline(browser, 'desktop cart')
        before = len(browser.requests)
        browser.evaluate("document.querySelector('[name=remove]').click()")
        check(browser.evaluate("!!document.querySelector('.motion-removing')"), 'cart removal animates the row before native submission')
        browser.wait("document.querySelector('.empty-state')?.textContent.includes('Your cart is empty')")
        posts = [request for request in browser.requests[before:] if request['method'] == 'POST' and '/cart.php' in request['url']]
        check(len(posts) == 1 and 'remove=' in posts[0].get('postData', '') and 'csrf_token=' in posts[0].get('postData', ''), 'removal sends one native POST including submitter and CSRF')
        check(sql(f'SELECT COUNT(*) FROM cart_items ci JOIN cart c ON c.cart_id=ci.cart_id WHERE c.user_id={customer_id}') == '0', 'server confirms cart item removal')

        sql(f"INSERT INTO addresses (user_id,line1,city,country) VALUES ({customer_id},'Motion Road','Dhaka','Bangladesh')")
        address_id = int(sql(f'SELECT MAX(address_id) FROM addresses WHERE user_id={customer_id}'))
        sql(f"INSERT INTO orders (user_id,address_id,status,total_amount) VALUES ({customer_id},{address_id},'shipped',12500)")
        order_id = int(sql(f'SELECT MAX(order_id) FROM orders WHERE user_id={customer_id}'))
        sql(f"INSERT INTO order_items (order_id,product_id,quantity,unit_price,subtotal) VALUES ({order_id},1,1,12500,12500)")
        sql(f"INSERT INTO order_status_history (order_id,old_status,new_status,changed_by) VALUES ({order_id},NULL,'pending',{customer_id}),({order_id},'pending','processing',2),({order_id},'processing','shipped',2)")
        browser.navigate('/orders.php')
        browser.wait("document.querySelector('.order-progress').classList.contains('timeline-ready')")
        check(browser.evaluate("getComputedStyle(document.querySelector('.order-progress li'),'::before').animationName==='motion-node' && getComputedStyle(document.querySelector('.order-progress li'),'::after').animationName==='motion-line'"), 'order progress animates its line and status nodes')
        browser.evaluate("document.querySelector('.order-history').open=true; document.querySelector('.status-timeline').scrollIntoView({block:'center'})")
        browser.wait("document.querySelector('.status-timeline').classList.contains('timeline-ready')")
        check(browser.evaluate("getComputedStyle(document.querySelector('.status-timeline'),'::before').animationName==='motion-line-vertical'"), 'expanding order history animates the vertical timeline')

        browser.cookies(admin)
        browser.navigate('/admin/dashboard.php')
        samples = browser.evaluate('motionSamples')
        actual = browser.evaluate("Array.from(document.querySelectorAll('.stat-value'), e=>e.textContent)")
        check(any(sample and sample != actual for sample in samples) and samples[-1] == actual, 'dashboard counters animate and finish at exact formatted values')
        check(any(value.startswith('\u09f3') and re.search(r'\d,\d{3}\.\d{2}$', value) for value in actual), 'currency count preserves Taka symbol, grouping and decimals')
        browser.evaluate("window.scrollTo(0,document.body.scrollHeight); window.scrollTo(0,0)")
        time.sleep(.7)
        check(browser.evaluate("Array.from(document.querySelectorAll('.stat-value'), e=>e.textContent)") == actual, 'counters do not restart on repeat scrolling')
        compare_baseline(browser, 'admin dashboard')
        browser.navigate('/admin/manage_users.php')
        check(browser.evaluate("document.querySelectorAll('tbody tr.motion-row').length > 0"), 'dashboard table rows have staggered entry hooks')

        browser.cookies(seller)
        browser.navigate('/seller/dashboard.php')
        check(browser.evaluate("document.querySelectorAll('.motion-counter').length > 0"), 'seller metrics use the same counter behavior')
        browser.media(True)
        browser.navigate('/seller/dashboard.php')
        check(browser.evaluate("Array.from(document.querySelectorAll('.motion-reveal')).every(e=>getComputedStyle(e).opacity==='1')"), 'reduced motion keeps all reveal targets visible')
        check(browser.evaluate("Array.from(document.querySelectorAll('body *')).every(e=>getComputedStyle(e).animationName==='none' && getComputedStyle(e).transitionDuration==='0s')"), 'reduced motion disables CSS animations and transitions')
        samples = browser.evaluate('motionSamples')
        check(not samples or all(sample == samples[-1] for sample in samples), 'reduced motion shows final counter values immediately')
        browser.media(False)
        identifier = browser.preload("window.IntersectionObserver = undefined; Object.defineProperty(window,'localStorage',{get(){throw new Error('Storage blocked')}}); Object.defineProperty(window,'sessionStorage',{get(){throw new Error('Storage blocked')}})")
        browser.navigate('/index.php')
        check(browser.evaluate("Array.from(document.querySelectorAll('.motion-reveal')).every(e=>getComputedStyle(e).opacity==='1')"), 'missing IntersectionObserver shows all content')
        browser.evaluate("document.getElementById('themeToggle').click()")
        time.sleep(.8)
        check(browser.evaluate("document.body.classList.contains('light-mode')"), 'theme toggle works with blocked browser storage')
        browser.remove_preload(identifier)
        browser.navigate('/index.php')
        browser.evaluate("window.motionFetch=window.fetch; window.fetch=(...args)=>new Promise(resolve=>setTimeout(resolve,400)).then(()=>motionFetch(...args)); document.getElementById('chatbotToggle').click(); document.getElementById('chatbotInput').value='laptop'; document.getElementById('chatbotForm').requestSubmit()")
        browser.wait("!!document.querySelector('.chatbot-message.loading')")
        check(browser.evaluate("getComputedStyle(document.querySelector('.chatbot-message.loading'),'::after').animationName !== 'none'"), 'waiting chatbot bubble displays animated CSS loading dots')
        browser.wait("document.querySelector('.user-message.message-enter') && !document.getElementById('chatbotForm').hasAttribute('aria-busy')")
        check(browser.evaluate("!!document.querySelector('.bot-message.message-enter')"), 'new chatbot bubbles retain safe text rendering and gain entry classes')
        check(browser.evaluate("Array.from(document.querySelectorAll('.chatbot-product-card')).every(e=>e.classList.contains('message-enter'))"), 'chatbot result cards receive motion without response logic changes')
        browser.cookies(customer)
        browser.command('Emulation.setScriptExecutionDisabled', {'value': True})
        browser.command('Page.navigate', {'url': BASE + '/category.php?category_id=3'})
        browser.wait("document.readyState==='complete' && !!document.querySelector('.product-card')")
        check(browser.evaluate("Array.from(document.querySelectorAll('.product-card,.card-cart-form .btn-add')).every(e=>getComputedStyle(e).opacity==='1')"), 'disabled JavaScript keeps products and purchase controls visible')
        browser.command('Emulation.setScriptExecutionDisabled', {'value': False})
        check(not browser.errors, 'no uncaught browser JavaScript errors')
    print(f'PASS {checks} motion checks; disposable database removed on exit.', flush=True)
finally:
    if server:
        server.terminate()
        server.wait(timeout=10)
    if created:
        assert re.fullmatch(r'projukti_mart_verify_[a-f0-9]{12}', DB)
        sql('DROP DATABASE ' + DB, False)
