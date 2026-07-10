import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE

response = urllib.request.urlopen('https://dienmayhieu.com/', context=ctx)
html = response.read().decode('utf-8')

# Look for spinWheel and what follows
spin_idx = html.find('async function spinWheel')
print('HTML total length:', len(html))
print('spinWheel at char:', spin_idx)

if spin_idx != -1:
    # Get 5000 chars after spinWheel
    area = html[spin_idx:spin_idx + 5000]
    # Search for wheelModal HTML
    wm_html = 'id="wheelModal"'
    wm_idx = area.find(wm_html)
    if wm_idx != -1:
        print('FOUND wheelModal HTML! at offset', wm_idx, 'from spinWheel')
    else:
        print('wheelModal HTML NOT found in 5000 chars after spinWheel')
        # Check end of script section
        script_end = area.find('</script>')
        if script_end != -1:
            after_script = area[script_end:script_end + 500]
            print('After </script> (200 chars):', repr(after_script[:200]))
