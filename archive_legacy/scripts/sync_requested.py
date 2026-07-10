import ftplib
import os
import sys
sys.stdout.reconfigure(encoding='utf-8')

FTP_HOST = '123.30.136.221'
FTP_USER = 'kwkrbcce'
FTP_PASS = '06in7OIh)[YJ7e'

FORCE_FILES = [
    ('index.php', 'public_html/index.php'),
    ('assets/js/feature_collector.js', 'public_html/assets/js/feature_collector.js'),
    ('auth/guest.php', 'public_html/auth/guest.php'),
    ('auth/partner.php', 'public_html/auth/partner.php'),
]

try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)

    print("--- BẮT ĐẦU SYNC SERVER ---")
    for local, remote in FORCE_FILES:
        if not os.path.exists(local):
            print(f'FAIL: {local} does not exist locally')
            continue
            
        try:
            with open(local, 'rb') as f:
                ftp.storbinary('STOR /' + remote, f)
            print(f'[Success] Synced: {local} -> {remote}')
        except Exception as e:
            print(f'[Error] {local} -> {e}')

    ftp.quit()
    print("--- SYNC HOÀN TẤT ---")

except Exception as e:
    print(f'FTP connection error: {e}')
