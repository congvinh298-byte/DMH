import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
response = urllib.request.urlopen('https://dienmayhieu.com/', context=ctx)
html = response.read().decode('utf-8')

# Find the section near the end for wheel
# Look for 'openWheelModal' and 'spinBtn' area
idx = html.find('openWheelModal')
if idx != -1:
    print('openWheelModal at char:', idx)
    area = html[idx:idx+4000]
    # Look for end of this section
    if 'id="wheelModal"' in area:
        print('FOUND wheelModal in openWheelModal area')
    else:
        print('NOT found. Area around openWheelModal (2000 chars after):')
        # Find the next script close
        next_close = area.find('</script>')
        print('Next </script> at offset:', next_close)
        print('After that:', repr(area[next_close:next_close+500]))
