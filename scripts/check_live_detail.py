import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
response = urllib.request.urlopen('https://dienmayhieu.com/', context=ctx)
html = response.read().decode('utf-8')

checks = [
    ('wheelModal HTML id', 'id="wheelModal"'),
    ('wheelCanvas HTML id', 'id="wheelCanvas"'),
    ('spinBtn HTML id', 'id="spinBtn"'),
    ('spinWheel function', 'function spinWheel'),
    ('No broken isCasync', 'isCasync'),
]
for name, needle in checks:
    found = needle in html
    if name.startswith('No'):
        print(('PASS' if not found else 'FAIL') + ': ' + name)
    else:
        print(('PASS' if found else 'FAIL') + ': ' + name)

# Show the area right after spinWheel function to see if wheelModal follows
idx = html.rfind('</div>')
# Find wheelModal area
wm_idx = html.find('wheelModal')
if wm_idx != -1:
    print('wheelModal found at char', wm_idx)
    print('Context:', repr(html[wm_idx:wm_idx+100]))
else:
    print('wheelModal NOT found anywhere in HTML')
    print('Total HTML length:', len(html))
