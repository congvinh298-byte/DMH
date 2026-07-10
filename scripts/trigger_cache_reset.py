import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE

# Trigger the reset_cache.php
response = urllib.request.urlopen('https://dienmayhieu.com/reset_cache.php', context=ctx)
result = response.read().decode('utf-8')
print('Cache reset result:', result[:200])

# Now check the main page again
response2 = urllib.request.urlopen('https://dienmayhieu.com/', context=ctx)
html = response2.read().decode('utf-8')
print('HTML length:', len(html))
search = 'id="wheelModal"'
found = search in html
print('wheelModal HTML:', 'FOUND' if found else 'NOT FOUND')
if found:
    idx = html.find(search)
    print('At char:', idx)
