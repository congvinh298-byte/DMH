import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
try:
    r = urllib.request.urlopen('https://dienmayhieu.com/?v=99999', context=ctx)
    html = r.read().decode('utf-8')
    print('HTML length:', len(html))
    idx = html.find('async function spinWheel')
    print('spinWheel at char:', idx)
    search_str = 'id="wheelModal"'
    if search_str in html[idx:idx+5000]:
        print('FOUND wheelModal HTML!')
    else:
        print('NOT FOUND wheelModal HTML')
except Exception as e:
    print('ERROR:', e)
