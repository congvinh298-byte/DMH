import urllib.request
import ssl
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
try:
    response = urllib.request.urlopen('https://dienmayhieu.com/', context=ctx)
    html = response.read().decode('utf-8')
    checks = {
        'wheelModal HTML exists': 'id="wheelModal"' in html,
        'wheelCanvas HTML exists': 'id="wheelCanvas"' in html,
        'spinBtn HTML exists': 'id="spinBtn"' in html,
        'No broken isCasync': 'isCasync' not in html,
    }
    for k, v in checks.items():
        print(('PASS' if v else 'FAIL') + ': ' + k)
except Exception as e:
    print('ERROR:', str(e))
