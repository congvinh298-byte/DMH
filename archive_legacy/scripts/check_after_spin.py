import urllib.request, ssl, sys
sys.stdout.reconfigure(encoding='utf-8')
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
response = urllib.request.urlopen('https://dienmayhieu.com/', context=ctx)
html = response.read().decode('utf-8')

# Find last 200 JS after spinWheel
spin_idx = html.rfind('async function spinWheel')
if spin_idx != -1:
    area = html[spin_idx:spin_idx+3000]
    # Look for wheelModal HTML
    if 'id="wheelModal"' in area:
        print('FOUND wheelModal HTML in spinWheel area')
    else:
        print('wheelModal HTML NOT in spinWheel area')
        # Find where wheelModal HTML should be - after </script>
        script_end = html.rfind('</script>', spin_idx)
        if script_end != -1:
            after_script = html[script_end:script_end+500]
            print('After last </script>:', repr(after_script[:300]))
        else:
            print('No </script> found after spinWheel')
