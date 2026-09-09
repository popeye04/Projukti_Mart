"""Integration checks using an isolated temporary database and PHP server.
Run: python tests/verify.py (XAMPP MySQL must be running).
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
import sys

ROOT = Path(__file__).resolve().parents[1]
PHP = r'C:\xampp\php\php.exe'
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
DB = 'projukti_mart_verify_' + uuid.uuid4().hex[:12]
PASSWORD = 'Verify-Local-827!'
created = False
server = None
checks = 0
timings = []


def sql(statement, database=True):
    args = [MYSQL, '--user=root', '--host=127.0.0.1', '--default-character-set=utf8mb4', '--batch', '--skip-column-names']
    if database:
        args += ['--database=' + DB]
    result = subprocess.run(args, input=statement.encode('utf-8'), stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
    return result.stdout.decode('utf-8').strip()


def check(condition, label):
    global checks
    assert condition, label
    checks += 1
    print('PASS ' + label, flush=True)


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None):
        body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
        request = urllib.request.Request(BASE + path, data=body)
        try:
            response = self.opener.open(request, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        text = response.read().decode('utf-8')
        assert not re.search(r'(Fatal error|Warning:|Parse error|Uncaught)', text), text[:1500]
        return response.status, text, response.url

    def post(self, path, fields):
        _, page, _ = self.request(path)
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', page)
        assert token, path + ' missing CSRF field'
        return self.request(path, dict(fields, csrf_token=token.group(1)))

    def login(self, email):
        code, page, url = self.post('/login.php', {'login': '1', 'email': email, 'password': PASSWORD})
        check(code == 200 and '/login.php' not in url, 'login ' + email)


try:
    assert re.fullmatch(r'projukti_mart_verify_[a-f0-9]{12}', DB)
    sql('CREATE DATABASE ' + DB + ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;', False)
    created = True
    sql((ROOT / 'projukti_mart.sql').read_text(encoding='utf-8'))
    hashed = subprocess.check_output([PHP, '-r', "echo password_hash('" + PASSWORD + "', PASSWORD_DEFAULT);"]).decode()
    sql("UPDATE users SET password = '" + hashed + "';")
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    BASE = f'http://127.0.0.1:{port}/Project'
    log = tempfile.TemporaryFile()
    env = dict(os.environ, PM_DB_NAME=DB)
    server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT.parent)], env=env, stdout=log, stderr=log, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    guest = Client()
    for attempt in range(30):
        try:
            guest.request('/index.php')
            break
        except urllib.error.URLError:
            time.sleep(0.1)
    for path in ['/index.php', '/category.php?category_id=3', '/product.php?id=1', '/login.php', '/register.php', '/search_results.php?q=PC', '/assets/css/style.css', '/assets/js/chatbot.js', '/assets/product-placeholder.svg']:
        check(guest.request(path)[0] == 200, 'GET ' + path)
    for path in ['/cart.php', '/checkout.php', '/orders.php', '/profile.php', '/seller/dashboard.php', '/admin/dashboard.php']:
        check('/login.php' in guest.request(path)[2], 'guest access restriction ' + path)
    check(guest.request('/ai_search.php')[0] == 405, 'AI rejects GET with JSON')
    check(guest.request('/ai_search.php', {'query': ''})[0] == 422, 'AI rejects empty query')
    for query in ['gaming laptop under 140k', 'cheap PC below 120000', 'headphones', 'above 5000', 'no-such-product-zzzz']:
        started = time.monotonic()
        status, body, _ = guest.request('/ai_search.php', {'query': query, 'user_id': '1'})
        elapsed = time.monotonic() - started
        assert body, f'Empty AI response: HTTP {status}, query {query}'
        data = json.loads(body)
        timings.append(elapsed)
        check(status == 200 and data['success'] and len(data['results']) <= 6 and elapsed < 5, 'AI contract/deadline: ' + query)
        check(all('relevance_score' in item for item in data['results']), 'AI scores: ' + query)
        if query.startswith('gaming'):
            check(data['detected_max_price'] == 140000 and data['detected_category'] == 'Laptop' and data['results'], 'NLP category/budget/spec intent')
        if query.startswith('no-such'):
            check(data['fallback'] and data['confidence'] == 'low', 'low-confidence fallback')
    check(sql('SELECT user_id IS NULL FROM search_query_log ORDER BY query_id DESC LIMIT 1') == '1', 'forged AI user ID ignored')
    started = time.monotonic()
    _, page, _ = guest.request('/search_results.php?q=PC&max_price=120000&sort=price_asc')
    check('Office PC' in page and time.monotonic() - started < 2, 'short keyword LIKE search deadline')
    _, page, _ = guest.request('/category.php?category_id=3&spec_key=RAM&spec_value=16GB&max_price=100000')
    check('Dell Inspiron' in page and 'ASUS TUF' not in page, 'category specification and price filters')
    _, page, _ = guest.request('/search_results.php?q=laptop&spec_key=GPU&spec_value=RTX')
    check('ASUS TUF' in page and 'Dell Inspiron' not in page, 'search specification filter')
    _, page, _ = guest.request('/search_results.php?q=%3Cscript%3Ealert%281%29%3C%2Fscript%3E')
    check('<script>alert(1)</script>' not in page, 'escaped query output')
    sql('ALTER TABLE products DROP INDEX ft_product_search')
    check(guest.request('/search_results.php?q=laptop')[0] == 200, 'missing FULLTEXT index falls back')
    sql('ALTER TABLE products ADD FULLTEXT INDEX ft_product_search(name, description)')

    customer, seller, admin = Client(), Client(), Client()
    customer.login('rahim@example.com')
    seller.login('seller1@projuktimart.com')
    admin.login('admin@projuktimart.com')
    for path in ['/profile.php', '/cart.php', '/checkout.php', '/orders.php']:
        check(customer.request(path)[0] == 200, 'customer ' + path)
    for path in ['/seller/dashboard.php', '/seller/manage_products.php', '/seller/seller_orders.php', '/seller/sales_summary.php']:
        check(seller.request(path)[0] == 200, 'seller ' + path)
    for path in ['/admin/dashboard.php', '/admin/manage_categories.php', '/admin/manage_users.php', '/admin/manage_orders.php', '/admin/moderate_reviews.php']:
        check(admin.request(path)[0] == 200, 'admin ' + path)
    check('/seller/' not in customer.request('/seller/manage_products.php')[2], 'customer cannot access seller panel')
    check(customer.request('/profile.php', {'update_profile': 1})[0] == 403, 'CSRF blocks unprotected writes')
    before = sql('SELECT stock_qty FROM products WHERE product_id=3')
    seller.post('/seller/manage_products.php', {'toggle_status': 1, 'product_id': 3})
    check(sql('SELECT status FROM products WHERE product_id=3') == 'active', 'seller cannot modify another seller product')
    seller.post('/seller/manage_products.php', {'add_product': 1, 'name': 'Bad Product', 'category_id': 3, 'price': -5, 'stock_qty': -1})
    check(sql("SELECT COUNT(*) FROM products WHERE name='Bad Product'") == '0', 'invalid seller product rejected')
    seller.post('/seller/manage_products.php', {'add_product': 1, 'name': 'Verification Laptop', 'category_id': 4, 'price': 1000, 'stock_qty': 5, 'spec_key[]': ['RAM'], 'spec_value[]': ['32GB'], 'image_url[]': ['']})
    pid = int(sql("SELECT product_id FROM products WHERE name='Verification Laptop'"))
    check(sql(f'SELECT COUNT(*) FROM product_specs WHERE product_id={pid}') == '1', 'seller product/spec transaction')
    seller.post('/seller/manage_products.php', {'edit_product': 1, 'product_id': pid, 'name': 'Verification Laptop', 'category_id': 4, 'price': 1000, 'stock_qty': 5, 'spec_key[]': ['RAM'], 'spec_value[]': ['16GB'], 'image_url[]': ['javascript:alert(1)']})
    check(sql(f'SELECT spec_value FROM product_specs WHERE product_id={pid}') == '32GB', 'invalid image rolls back product/spec edits')
    customer.post('/profile.php', {'remove_address': 1, 'address_id': 1})
    check(sql('SELECT COUNT(*) FROM addresses WHERE address_id=1') == '1', 'historical address preserved')
    customer.post('/profile.php', {'update_profile': 1, 'full_name': 'Rahim', 'email': 'nabila@example.com', 'phone': ''})
    check(sql('SELECT email FROM users WHERE user_id=5') == 'rahim@example.com', 'duplicate profile email handled')
    sql('DELETE FROM cart_items WHERE cart_id=1')
    customer.post(f'/product.php?id={pid}', {'add_to_cart': 1, 'quantity': 2})
    check(sql(f'SELECT quantity FROM cart_items WHERE cart_id=1 AND product_id={pid}') == '2', 'add to cart')
    customer.post('/checkout.php', {'place_order': 1, 'address_id': 2})
    check(sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '5', 'checkout rejects another user address')
    customer.post('/checkout.php', {'place_order': 1, 'address_id': 1})
    oid = int(sql('SELECT MAX(order_id) FROM orders'))
    check(sql(f'SELECT total_amount FROM orders WHERE order_id={oid}') == '2060.00', 'checkout shipping below threshold')
    check(sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '3' and sql('SELECT COUNT(*) FROM cart_items WHERE cart_id=1') == '0', 'checkout decrements stock and clears cart')
    customer.post('/orders.php', {'cancel_order': 1, 'order_id': oid})
    customer.post('/orders.php', {'cancel_order': 1, 'order_id': oid})
    check(sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '5', 'repeat cancellation restores stock once')
    seller.post('/seller/seller_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'delivered'})
    check(sql(f'SELECT status FROM orders WHERE order_id={oid}') == 'cancelled', 'seller cannot reopen cancelled order')
    admin.post('/admin/manage_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'processing'})
    check(sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '3', 'admin reopening re-reserves stock')
    seller.post('/seller/seller_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'delivered'})
    customer.post(f'/product.php?id={pid}', {'submit_review': 1, 'rating': 5, 'comment': '<script>unsafe</script>'})
    rid = int(sql(f'SELECT review_id FROM reviews WHERE product_id={pid} AND user_id=5'))
    _, page, _ = guest.request(f'/product.php?id={pid}')
    check('&lt;script&gt;unsafe&lt;/script&gt;' in page, 'delivered review and escaped rendering')
    admin.post('/admin/moderate_reviews.php', {'toggle_flag': rid})
    check('&lt;script&gt;unsafe&lt;/script&gt;' not in guest.request(f'/product.php?id={pid}')[1], 'flagged review hidden')
    check('Recommended for You' in customer.request('/index.php')[1], 'personalized home recommendations')
    check('Customers Also Viewed' in guest.request('/product.php?id=1')[1], 'same-category recommendations')
    admin.post('/admin/manage_categories.php', {'add_category': 1, 'category_name': 'Invalid Third Level', 'parent_category_id': 4})
    check(sql("SELECT COUNT(*) FROM categories WHERE category_name='Invalid Third Level'") == '0', 'third-level category rejected')
    admin.post('/admin/manage_users.php', {'toggle_status': 2})
    check('/login.php' in seller.request('/seller/dashboard.php')[2], 'suspension revokes existing session')
    admin.post('/admin/manage_users.php', {'toggle_status': 2})
    _, page, url = guest.post('/register.php', {'register': 1, 'username': 'verify_new', 'full_name': 'New User', 'email': 'verify_new@example.com', 'password': PASSWORD, 'confirm_password': PASSWORD, 'role': 'admin', 'redirect': 'https://example.org'})
    check(url.startswith(BASE) and sql("SELECT role FROM users WHERE username='verify_new'") == 'customer', 'registration blocks admin role and external redirect')
    sql(f'UPDATE products SET stock_qty=5 WHERE product_id={pid}')
    customer.post(f'/product.php?id={pid}', {'add_to_cart': 1, 'quantity': 5})
    customer.post('/checkout.php', {'place_order': 1, 'address_id': 1})
    second_oid = int(sql('SELECT MAX(order_id) FROM orders'))
    check(sql(f'SELECT total_amount FROM orders WHERE order_id={second_oid}') == '5000.00', 'free shipping at exactly 5000')
    workers = [subprocess.Popen([PHP, str(ROOT / 'tests/order_worker.php'), str(second_oid)], env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0)) for _ in range(2)]
    for worker in workers:
        output, error = worker.communicate(timeout=10)
        assert worker.returncode == 0, error.decode()
    check(sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '5', 'concurrent cancellation restores stock once')
    check(subprocess.run([PHP, str(ROOT / 'tests/search_contract.php')], env=env, capture_output=True).returncode == 0, 'parser boundaries and deadline enforcement')
    sql('DELETE FROM product_views')
    check('Trending This Week' not in customer.request('/index.php')[1] and 'Recommended for You' not in customer.request('/index.php')[1], 'empty recommendation sections hidden')
    missing = 5000 - int(sql('SELECT COUNT(*) FROM products'))
    values = ','.join(f"(4,2,'Scale Laptop {i}','Scale', 'Gaming laptop with fast memory',50000,10)" for i in range(missing))
    sql('INSERT INTO products(category_id,seller_id,name,brand,description,price,stock_qty) VALUES ' + values)
    started = time.monotonic()
    check(customer.request('/search_results.php?q=laptop')[0] == 200 and time.monotonic() - started < 2, 'FULLTEXT page under 2s at 5000 products')
    print(f'PASS {checks} checks; max AI response {max(timings):.3f}s. Test database will be removed.', flush=True)
finally:
    if server:
        server.terminate()
        server.wait(timeout=10)
        if sys.exc_info()[0]:
            log.seek(0)
            print(log.read().decode('utf-8', errors='replace')[-8000:])
    if created:
        assert re.fullmatch(r'projukti_mart_verify_[a-f0-9]{12}', DB)
        sql('DROP DATABASE ' + DB, False)
