"""SRS compliance integration checks. Uses and drops only a disposable database.

Run: python tests/compliance.py (XAMPP MySQL and Python standard library).
Does not call Groq, change store accounts, or submit live store orders.
"""
from pathlib import Path
import http.cookiejar
import os
import re
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
PHP = r'C:\xampp\php\php.exe'
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
DB = 'projukti_mart_verify_' + uuid.uuid4().hex[:12]
PASSWORD = 'Compliance-Test-927!'
checks = 0
server = None
created = False


def sql(statement, use_database=True):
    args = [MYSQL, '--user=root', '--host=127.0.0.1', '--default-character-set=utf8mb4', '--batch', '--skip-column-names']
    if use_database:
        args.append('--database=' + DB)
    result = subprocess.run(args, input=statement.encode(), capture_output=True, check=True)
    return result.stdout.decode().strip()


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
        body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
        try:
            response = self.opener.open(urllib.request.Request(BASE + path, data=body), timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        text = response.read().decode()
        assert not re.search(r'(Fatal error|Warning:|Parse error|Uncaught)', text), text[:1000]
        return response.status, text, response.url

    def post(self, path, data):
        _, page, _ = self.request(path)
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', page)
        assert token, 'Missing CSRF form: ' + path
        return self.request(path, dict(data, csrf_token=token[1]))

    def login(self, email):
        return self.post('/login.php', {'login': 1, 'email': email, 'password': PASSWORD})

    def register(self, username, role='customer'):
        return self.post('/register.php', {'register': 1, 'username': username, 'full_name': username,
            'email': username + '@example.test', 'phone': '', 'password': PASSWORD, 'confirm_password': PASSWORD, 'role': role})


try:
    assert re.fullmatch(r'projukti_mart_verify_[a-f0-9]{12}', DB)
    sql('CREATE DATABASE ' + DB + ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', False)
    created = True
    sql((ROOT / 'projukti_mart.sql').read_text(encoding='utf-8'))
    # Rerunning the migration must preserve data and work on fresh imports.
    sql((ROOT / 'migrations/001_compliance.sql').read_text(encoding='utf-8'))
    hashed = subprocess.check_output([PHP, '-r', "echo password_hash('" + PASSWORD + "', PASSWORD_DEFAULT);"]).decode()
    sql("UPDATE users SET password='" + hashed + "'")
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    BASE = f'http://127.0.0.1:{port}/Project'
    log = tempfile.TemporaryFile()
    server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT.parent)],
        env=dict(os.environ, PM_DB_NAME=DB), stdout=log, stderr=log,
        creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    guest = Client()
    for attempt in range(50):
        try:
            guest.request('/index.php')
            break
        except urllib.error.URLError:
            time.sleep(.1)
    admin, seller, customer, pending = Client(), Client(), Client(), Client()
    admin.login('admin@projuktimart.com')
    seller.login('seller1@projuktimart.com')
    code, body, url = pending.register('pending_seller', 'seller')
    pending_id = int(sql("SELECT user_id FROM users WHERE username='pending_seller'"))
    check(sql(f'SELECT status FROM users WHERE user_id={pending_id}') == 'pending_approval' and '/login.php' in url, 'seller registration waits for approval without login')
    check('awaiting admin approval' in pending.login('pending_seller@example.test')[1], 'pending seller login blocked with explanation')
    check('/login.php' in pending.request('/seller/dashboard.php')[2], 'pending seller cannot use seller panel')
    check('pending_seller@example.test' in admin.request('/admin/manage_users.php')[1], 'pending seller listed for admin approval')
    check(guest.request('/admin/manage_users.php', {'approve_seller': pending_id})[0] == 403, 'approval requires CSRF')
    admin.post('/admin/manage_users.php', {'approve_seller': pending_id})
    check(sql(f'SELECT status FROM users WHERE user_id={pending_id}') == 'active', 'admin approves pending seller')
    check('/login.php' not in pending.login('pending_seller@example.test')[2], 'approved seller can log in')
    customer.register('new_customer')
    customer_id = int(sql("SELECT user_id FROM users WHERE username='new_customer'"))
    check(sql(f'SELECT status FROM users WHERE user_id={customer_id}') == 'active' and '/login.php' not in customer.request('/profile.php')[2], 'customer registers active and signed in')

    for name, client in [('seller', seller), ('admin', admin)]:
        for page in ['/cart.php', '/checkout.php', '/orders.php']:
            check('/index.php' in client.request(page)[2], name + ' blocked from ' + page)
        check(client.post('/product.php?id=1', {'add_to_cart': 1, 'quantity': 1})[0] == 403, name + ' cannot POST add-to-cart')
        check(client.post('/product.php?id=1', {'submit_review': 1, 'rating': 5, 'comment': 'Forbidden'})[0] == 403, name + ' cannot POST review')
    check('/admin/manage_products.php' not in seller.request('/admin/manage_products.php')[2], 'seller cannot access admin override')
    check('/admin/activity.php' not in customer.request('/admin/activity.php')[2], 'customer cannot access activity log')
    check('name="add_to_cart"' not in guest.request('/category.php?category_id=3')[1], 'guest cards omit purchase button')

    fields = {'name': 'Compliance Product', 'category_id': 4, 'brand': 'AuditBrand', 'model': 'AuditModel',
        'description': 'Compliance description', 'price': '1200.00', 'stock_qty': 5,
        'spec_key[]': ['Spec' + str(i) for i in range(8)], 'spec_value[]': ['Value' + str(i) for i in range(8)],
        'image_url[]': ['images/test-placeholder.png']}
    seller.post('/seller/manage_products.php', dict(fields, add_product=1))
    pid = int(sql("SELECT product_id FROM products WHERE name='Compliance Product'"))
    edit_path = '/seller/manage_products.php?edit=' + str(pid)
    page = seller.request(edit_path)[1]
    check(page.count('name="spec_key[]"') == 8 and 'Value7' in page, 'editor loads all eight specs')
    if '--browser' in sys.argv:
        from compliance_browser import verify_spec_editor
        verify_spec_editor(BASE + edit_path, seller.cookies)
        check(sql(f"SELECT COUNT(*) FROM product_specs WHERE product_id={pid} AND spec_key='Browser Spec' AND spec_value='Browser Value'") == '1', 'browser spec change persisted')
    seller.post(edit_path, dict(fields, edit_product=1, product_id=pid, price='1300.00'))
    check(sql(f'SELECT COUNT(*) FROM product_specs WHERE product_id={pid}') == '8', 'seller edit preserves all specs')
    check(sql(f'SELECT updated_at IS NOT NULL FROM products WHERE product_id={pid}') == '1', 'seller edits set updated_at')
    _, invalid, _ = seller.post(edit_path, dict(fields, edit_product=1, product_id=pid, name='Retained Name', price='-5'))
    check('value="Retained Name"' in invalid and 'value="-5"' in invalid and 'Value7' in invalid and 'images/test-placeholder.png' in invalid, 'validation retains product values specs and image paths')
    check(sql(f'SELECT price FROM products WHERE product_id={pid}') == '1300.00', 'invalid edit rolls back product')
    _, invalid_create, _ = seller.post('/seller/manage_products.php', dict(fields, add_product=1, name='Retained New Product', price='-5'))
    check('value="Retained New Product"' in invalid_create and 'name="add_product"' in invalid_create, 'invalid creation retains values and create mode')
    pending.post('/seller/manage_products.php', dict(fields, edit_product=1, product_id=pid))
    check(sql(f'SELECT price FROM products WHERE product_id={pid}') == '1300.00', 'different seller cannot edit product')
    admin_path = '/admin/manage_products.php?edit=' + str(pid)
    check('Value7' in admin.request(admin_path)[1], 'admin loads another seller specs')
    admin.post(admin_path, dict(fields, edit_product=1, product_id=pid, price='1400.00', status='inactive'))
    check(sql(f'SELECT CONCAT(price,":",status,":",seller_id) FROM products WHERE product_id={pid}') == '1400.00:inactive:2', 'admin overrides product fields/status without changing owner')
    admin.post('/admin/manage_products.php', {'toggle_status': 1, 'product_id': pid})
    check(sql(f'SELECT status FROM products WHERE product_id={pid}') == 'active', 'admin reactivates product')
    check(sql(f"SELECT COUNT(*) FROM activity_log WHERE action='product_created' AND target_id={pid}") == '1' and sql(f"SELECT COUNT(*) FROM activity_log WHERE action='product_deactivated' AND target_id={pid}") == '1', 'product activity events recorded')
    sql(f"INSERT INTO reviews (product_id,user_id,rating,comment,is_flagged) VALUES ({pid},5,4,'Visible',0),({pid},6,1,'Hidden',1)")
    cards = customer.request('/search_results.php?q=Compliance')[1]
    check('Spec0' in cards and 'Value1' in cards and '4.0 out of 5 stars' in cards and 'name="add_to_cart"' in cards, 'cards include two specs unflagged average and customer cart form')

    customer.post(f'/product.php?id={pid}', {'add_to_cart': 1, 'quantity': 2})
    check('name="add_inline_address"' in customer.request('/checkout.php')[1], 'checkout offers inline address')
    address = {'add_inline_address': 1, 'line1': 'Test Road', 'line2': '', 'city': 'Dhaka', 'postal_code': '1200', 'country': 'Bangladesh'}
    _, confirmation, _ = customer.post('/checkout.php', dict(address, place_order=1))
    oid = int(sql(f'SELECT order_id FROM orders WHERE user_id={customer_id} ORDER BY order_id DESC LIMIT 1'))
    check('Order Placed!' in confirmation and sql(f'SELECT COUNT(*) FROM addresses WHERE user_id={customer_id} AND is_default=1') == '1', 'inline address saved and used without leaving checkout')
    check(sql(f'SELECT total_amount FROM orders WHERE order_id={oid}') == '2860.00' and sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '3', 'inline checkout totals and stock correct')
    check(sql(f"SELECT COUNT(*) FROM order_status_history WHERE order_id={oid} AND old_status IS NULL AND new_status='pending' AND changed_by={customer_id}") == '1', 'creation history has customer and timestamp')
    seller.post('/seller/seller_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'processing', 'notes': '<script>tracking</script>'})
    check(sql(f'SELECT updated_at IS NOT NULL FROM orders WHERE order_id={oid}') == '1', 'status update sets order timestamp')
    check('&lt;script&gt;tracking&lt;/script&gt;' in seller.request('/seller/seller_orders.php')[1], 'seller timeline escapes tracking notes')
    check('&lt;script&gt;tracking&lt;/script&gt;' in admin.request('/admin/manage_orders.php')[1] and sql('SELECT username FROM users WHERE user_id=2') in admin.request('/admin/manage_orders.php')[1], 'admin timeline includes notes and actor')
    history_before = sql(f'SELECT COUNT(*) FROM order_status_history WHERE order_id={oid}')
    pending.post('/seller/seller_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'shipped', 'notes': 'Forbidden'})
    check(sql(f'SELECT COUNT(*) FROM order_status_history WHERE order_id={oid}') == history_before, 'unrelated seller cannot change status/history')
    customer.post('/orders.php', {'cancel_order': 1, 'order_id': oid})
    customer.post('/orders.php', {'cancel_order': 1, 'order_id': oid})
    check(sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '5' and sql(f"SELECT COUNT(*) FROM order_status_history WHERE order_id={oid} AND new_status='cancelled'") == '1', 'processing cancellation restocks/history exactly once')
    check(sql(f"SELECT COUNT(*) FROM activity_log WHERE action='order_cancelled' AND target_id={oid}") == '1', 'cancellation activity once only')
    admin.post('/admin/manage_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'processing'})
    seller.post('/seller/seller_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'delivered'})
    admin.post('/admin/manage_orders.php', {'update_status': 1, 'order_id': oid, 'status': 'cancelled'})
    check(sql(f'SELECT status FROM orders WHERE order_id={oid}') == 'delivered' and sql(f'SELECT stock_qty FROM products WHERE product_id={pid}') == '3', 'admin cannot cancel delivered order or restore delivered stock')

    rollback_customer = Client()
    rollback_customer.register('rollback_customer')
    rollback_id = int(sql("SELECT user_id FROM users WHERE username='rollback_customer'"))
    rollback_customer.post(f'/product.php?id={pid}', {'add_to_cart': 1, 'quantity': 1})
    sql(f'UPDATE products SET stock_qty=0 WHERE product_id={pid}')
    _, failure, _ = rollback_customer.post('/checkout.php', dict(address, place_order=1))
    check('insufficient stock' in failure and 'value="Test Road"' in failure, 'failed inline checkout retains address and explains stock error')
    check(sql(f'SELECT COUNT(*) FROM addresses WHERE user_id={rollback_id}') == '0' and sql(f'SELECT COUNT(*) FROM orders WHERE user_id={rollback_id}') == '0', 'failed checkout rolls back address/order together')
    review_id = int(sql(f'SELECT review_id FROM reviews WHERE product_id={pid} AND user_id=5'))
    admin.post('/admin/moderate_reviews.php', {'toggle_flag': review_id})
    admin.post('/admin/manage_users.php', {'toggle_status': pending_id})
    check('/login.php' in pending.request('/seller/dashboard.php')[2], 'suspension expires approved seller session')
    customer.post('/logout.php', {})
    guest.login('does-not-exist@example.test')
    actions = set(sql('SELECT DISTINCT action FROM activity_log').splitlines())
    check({'login','logout','failed_login','order_placed','order_cancelled','product_created','product_deactivated','user_suspended','review_flagged'} <= actions, 'all nine required activity events recorded')
    _, activity, _ = admin.request('/admin/activity.php?action=failed_login&from=2000-01-01&to=2099-12-31')
    check('failed_login' in activity and '<td>order_placed</td>' not in activity, 'activity action/date filters work')
    check('valid date' in admin.request('/admin/activity.php?from=2026-02-31')[1], 'invalid activity date rejected')
    check(PASSWORD not in activity and 'gsk_' not in activity, 'activity output contains no passwords or API credentials')
    check(sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='" + DB + "'") == '15', 'fresh dump and rerun migration produce 15 tables')
    print(f'PASS {checks} compliance checks. Disposable database will be removed.', flush=True)
finally:
    if server:
        server.terminate()
        server.wait(timeout=10)
    if created:
        assert re.fullmatch(r'projukti_mart_verify_[a-f0-9]{12}', DB)
        sql('DROP DATABASE ' + DB, False)
