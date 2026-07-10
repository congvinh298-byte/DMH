import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE

for url in ['https://dienmayhieu.com/assets/js/wheel_check.js']:
    try:
        r = urllib.request.urlopen(url, context=ctx)
        content = r.read().decode('utf-8')
        print(f'{url}: {repr(content[:100])}')
    except Exception as e:
        print(f'{url}: ERROR {e}')
