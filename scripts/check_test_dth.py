import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE

# Check test-dth.html
response = urllib.request.urlopen('https://dienmayhieu.com/test-dth.html', context=ctx)
print('test-dth.html content:', response.read().decode('utf-8'))

# Now check if there is something in .htaccess that could cache/redirect
