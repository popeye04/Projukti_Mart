"""Optional real-browser specification editor test used by compliance.py --browser.
Requires installed Chrome and Python websocket-client. Uses only the test server.
"""
from pathlib import Path
import json
import subprocess
import tempfile
import time
import urllib.request
import websocket


def verify_spec_editor(url, cookies):
    with tempfile.TemporaryDirectory(prefix='projukti_specs_', ignore_cleanup_errors=True) as profile:
        process = subprocess.Popen([r'C:\Program Files\Google\Chrome\Application\chrome.exe', '--headless=new',
            '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0',
            '--remote-allow-origins=*', '--user-data-dir=' + profile, 'about:blank'],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
        connection = None
        try:
            port_file = Path(profile) / 'DevToolsActivePort'
            for _ in range(100):
                if port_file.exists():
                    break
                time.sleep(.1)
            port = port_file.read_text().splitlines()[0]
            with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/list') as response:
                page = next(p for p in json.load(response) if p['type'] == 'page')
            connection = websocket.create_connection(page['webSocketDebuggerUrl'], timeout=10, suppress_origin=True)
            sequence = 0
            errors = []

            def command(method, params=None):
                nonlocal sequence
                sequence += 1
                connection.send(json.dumps({'id': sequence, 'method': method, 'params': params or {}}))
                while True:
                    message = json.loads(connection.recv())
                    if message.get('method') == 'Runtime.exceptionThrown':
                        errors.append(message)
                    if message.get('id') == sequence:
                        assert 'error' not in message, message
                        return message.get('result', {})

            def evaluate(expression):
                result = command('Runtime.evaluate', {'expression': expression, 'returnByValue': True, 'awaitPromise': True})
                assert 'exceptionDetails' not in result, result
                return result.get('result', {}).get('value')

            def wait_for(expression):
                for _ in range(100):
                    if evaluate(expression):
                        return
                    time.sleep(.1)
                raise AssertionError('Browser condition timed out: ' + expression)

            command('Runtime.enable')
            command('Page.enable')
            command('Network.enable')
            for cookie in cookies:
                command('Network.setCookie', {'name': cookie.name, 'value': cookie.value, 'url': url, 'path': '/'})
            command('Page.navigate', {'url': url})
            wait_for("document.readyState === 'complete' && !!document.getElementById('addSpec')")
            assert evaluate("document.querySelectorAll('#specRows .spec-row').length === 8")
            evaluate("document.querySelector('#specRows .remove-spec').click(); document.getElementById('addSpec').click()")
            assert evaluate("document.querySelectorAll('#specRows .spec-row').length === 8")
            evaluate("const row = document.querySelector('#specRows .spec-row:last-child'); row.querySelector('[name=\"spec_key[]\"]').value = 'Browser Spec'; row.querySelector('[name=\"spec_value[]\"]').value = 'Browser Value'; document.getElementById('price').value = '-1'; document.querySelector('[name=edit_product]').click()")
            wait_for("document.readyState === 'complete' && document.querySelector('.cart-message')?.textContent.includes('positive price')")
            assert evaluate("document.querySelectorAll('#specRows .spec-row').length === 8 && document.querySelector('[name=\"spec_value[]\"]:last-of-type') !== null && Array.from(document.querySelectorAll('[name=\"spec_value[]\"]')).some(x => x.value === 'Browser Value')")
            evaluate("document.getElementById('price').value = '1300.00'; document.querySelector('[name=edit_product]').click()")
            wait_for("document.readyState === 'complete' && !location.search && document.querySelector('.seller-product-list') !== null")
            assert not errors, errors
            print('PASS Chrome: all specs, add/remove, validation retention, real form submission', flush=True)
        finally:
            if connection:
                try:
                    command('Browser.close')
                except (OSError, websocket.WebSocketException):
                    pass
                connection.close()
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.terminate()
                process.wait(timeout=10)
