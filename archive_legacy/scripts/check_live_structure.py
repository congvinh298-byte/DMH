import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
response = urllib.request.urlopen('https://dienmayhieu.com/', context=ctx)
html = response.read().decode('utf-8')
print('HTML length:', len(html))

# Look for last script tag and what comes after it
last_script_close = html.rfind('</script>')
print('Last </script> at:', last_script_close)
if last_script_close != -1:
    after = html[last_script_close:last_script_close+200]
    print('After last script:', repr(after))

# Also check if there are multiple </body> or </html>
body_count = html.count('</body>')
html_count = html.count('</html>')
print('</body> count:', body_count)
print('</html> count:', html_count)
