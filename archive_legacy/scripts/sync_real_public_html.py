import ftplib
import os
import sys
sys.stdout.reconfigure(encoding='utf-8')

FTP_HOST = '123.30.136.221'
FTP_USER = 'kwkrbcce'
FTP_PASS = '06in7OIh)[YJ7e'

UPLOAD_MAP = [
    ('index.php', 'public_html/index.php'),
    ('api_master.php', 'public_html/api_master.php'),
    ('auth/guest.php', 'public_html/auth/guest.php'),
    ('auth/partner.php', 'public_html/auth/partner.php'),
    ('scripts/trigger_cache_reset.py', 'public_html/trigger_cache_reset.py') # just a dummy to test
]

try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.encoding = 'latin-1'

    for local, remote in UPLOAD_MAP:
        if not os.path.exists(local):
            continue
        try:
            with open(local, 'rb') as f:
                ftp.storbinary('STOR ' + remote, f)
            print(f'OK: {local} -> {remote}')
        except Exception as e:
            print(f'FAIL: {local} -> {e}')

    ftp.quit()
    print('Sync to REAL public_html complete.')

except Exception as e:
    print(f'FTP connection error: {e}')
