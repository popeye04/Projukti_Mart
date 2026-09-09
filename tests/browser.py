"""Headless Chrome/Edge smoke checks. Uses installed browsers and websocket-client."""
from pathlib import Path
import json
import subprocess
import tempfile
import time
import urllib.request
import websocket

ROOT = Path(__file__).resolve().parents[1]


def run_browser(executable):
    with tempfile.TemporaryDirectory(prefix='projukti_browser_') as profile:
        process = subprocess.Popen([executable, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', '--remote-allow-origins=*', '--user-data-dir=' + profile, 'about:blank'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
        connection = None
        try:
            port_file = Path(profile) / 'DevToolsActivePort'
            for _ in range(100):
                if port_file.exists():
                    break
                time.sleep(.1)
            port = port_file.read_text().splitlines()[0]
            with urllib.request.urlopen(f'http://127.0.0.1:{port}/json/list') as response:
                pages = json.load(response)
            page = next(p for p in pages if p['type'] == 'page')
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

            command('Runtime.enable')
            command('Page.enable')
            command('Emulation.setDeviceMetricsOverride', {'width': 1365, 'height': 900, 'deviceScaleFactor': 1, 'mobile': False})
            command('Page.navigate', {'url': 'http://localhost/Project/index.php'})
            for _ in range(100):
                if evaluate("document.readyState === 'complete' && !!document.getElementById('chatbotForm')"):
                    break
                time.sleep(.1)
            assert evaluate("getComputedStyle(document.getElementById('chatbotPanel')).display === 'none'")
            evaluate("document.getElementById('chatbotToggle').click()")
            assert evaluate("getComputedStyle(document.getElementById('chatbotPanel')).display !== 'none'")
            evaluate("document.getElementById('chatbotInput').value = 'gaming laptop under 140k'; document.getElementById('chatbotForm').requestSubmit()")
            for _ in range(80):
                if evaluate("!document.getElementById('chatbotSend').disabled"):
                    break
                time.sleep(.1)
            count = evaluate("document.querySelectorAll('.chatbot-product-card').length")
            assert 0 < count <= 6, 'Missing chat result cards'
            assert evaluate("Array.from(document.querySelectorAll('.chatbot-product-card')).every(a => a.pathname === '/Project/product.php')")
            evaluate("document.getElementById('chatbotInput').value = '<img src=x onerror=alert(1)>'; document.getElementById('chatbotForm').requestSubmit()")
            for _ in range(80):
                if evaluate("!document.getElementById('chatbotSend').disabled"):
                    break
                time.sleep(.1)
            assert evaluate("document.querySelector('.user-message:last-of-type') === null || !Array.from(document.querySelectorAll('.user-message')).some(p => p.children.length)")
            assert evaluate("document.getElementById('chatbotMessages').textContent.includes('Showing keyword results')")
            command('Emulation.setDeviceMetricsOverride', {'width': 390, 'height': 844, 'deviceScaleFactor': 1, 'mobile': True})
            assert evaluate("document.getElementById('chatbotPanel').getBoundingClientRect().right <= innerWidth")
            evaluate("document.getElementById('chatbotClose').click()")
            assert evaluate("document.getElementById('chatbotPanel').hidden && !document.getElementById('chatbotToggle').hidden")
            assert not errors, errors
            print('PASS ' + Path(executable).name + ': chatbot open/close, live AJAX, cards, fallback, safe text, mobile panel, no JS exceptions', flush=True)
        finally:
            if connection:
                connection.close()
            process.terminate()
            process.wait(timeout=10)


for browser in [r'C:\Program Files\Google\Chrome\Application\chrome.exe', r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe']:
    run_browser(browser)
