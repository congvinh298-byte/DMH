import ftplib
import os
import sys
sys.stdout.reconfigure(encoding='utf-8')

FTP_HOST = '123.30.136.221'
FTP_USER = 'kwkrbcce'
FTP_PASS = '06in7OIh)[YJ7e'
REMOTE_BASE = 'public_html'

FORCE_FILES = [
    ('index.php', 'public_html/index.php'),
    ('api_master.php', 'public_html/api_master.php'),
    ('.htaccess', 'public_html/.htaccess'),
    ('.user.ini', 'public_html/.user.ini'),
    ('admin.php', 'public_html/admin.php'),
    ('admin-content.php', 'public_html/admin-content.php'),
    ('bct_portal.php', 'public_html/bct_portal.php'),
    ('demo_gate.php', 'public_html/demo_gate.php'),
    ('vendor.php', 'public_html/vendor.php'),
    ('api_baocao_bct.php', 'public_html/api_baocao_bct.php'),
    ('auth/guest.php', 'public_html/auth/guest.php'),
    ('auth/partner.php', 'public_html/auth/partner.php'),
    ('assets/css/index.css', 'public_html/assets/css/index.css'),
    ('assets/js/main.js', 'public_html/assets/js/main.js'),
    ('assets/js/openclaw-chat.js', 'public_html/assets/js/openclaw-chat.js'),
    ('admin/index.php', 'public_html/admin/index.php'),
    ('admin/chat_logs.php', 'public_html/admin/chat_logs.php'),
    ('admin/db_screenshot.php', 'public_html/admin/db_screenshot.php'),
    ('api_master.php', 'public_html/api_master.php'),
    ('api/core.php', 'public_html/api/core.php'),
    ('api/orders.php', 'public_html/api/orders.php'),
    ('api/users.php', 'public_html/api/users.php'),
    ('api/products.php', 'public_html/api/products.php'),
    ('api/vouchers.php', 'public_html/api/vouchers.php'),
    ('api/jobs.php', 'public_html/api/jobs.php'),
    ('api/finances.php', 'public_html/api/finances.php'),
    ('api/payment.php', 'public_html/api/payment.php'),
    ('api/ai.php', 'public_html/api/ai.php'),
    ('api/notify.php', 'public_html/api/notify.php'),
    ('api/mobile.php', 'public_html/api/mobile.php'),
    ('api/shipping.php', 'public_html/api/shipping.php'),
    ('api/viettel.php', 'public_html/api/viettel.php'),
    ('api/cart.php', 'public_html/api/cart.php'),
    ('api/webhooks.php', 'public_html/api/webhooks.php'),
    ('api/openclaw_chat.php', 'public_html/api/openclaw_chat.php'),
    ('pages/_legal_footer.php', 'public_html/pages/_legal_footer.php'),
    ('pages/_legal_header.php', 'public_html/pages/_legal_header.php'),
    ('pages/gioi-thieu.php', 'public_html/pages/gioi-thieu.php'),
    ('pages/lien-he.php', 'public_html/pages/lien-he.php'),
    ('pages/chinh-sach-bao-mat.php', 'public_html/pages/chinh-sach-bao-mat.php'),
    ('pages/chinh-sach-doi-tra.php', 'public_html/pages/chinh-sach-doi-tra.php'),
    ('pages/chinh-sach-thanh-toan.php', 'public_html/pages/chinh-sach-thanh-toan.php'),
    ('pages/chinh-sach-van-chuyen.php', 'public_html/pages/chinh-sach-van-chuyen.php'),
    ('pages/de-an-dich-vu.php', 'public_html/pages/de-an-dich-vu.php'),
    ('pages/dieu-khoan-su-dung.php', 'public_html/pages/dieu-khoan-su-dung.php'),
    ('pages/giai-quyet-tranh-chap.php', 'public_html/pages/giai-quyet-tranh-chap.php'),
    ('pages/huong-dan-mua-hang.php', 'public_html/pages/huong-dan-mua-hang.php'),
    ('pages/quy-che-hoat-dong.php', 'public_html/pages/quy-che-hoat-dong.php'),
    ('pages/inc/footer.php', 'public_html/pages/inc/footer.php'),
    ('pages/inc/header.php', 'public_html/pages/inc/header.php'),
    ('cron/run_scheduled_action.php', 'public_html/cron/run_scheduled_action.php'),
    ('public/.htaccess', 'public_html/public/.htaccess'),
    ('storage/private/.htaccess', 'public_html/storage/private/.htaccess'),
]

def ensure_dir(ftp, remote_path):
    parts = remote_path.split('/')
    current = ''
    for part in parts:
        if not part:
            continue
        current = (current + '/' + part) if current else part
        try:
            ftp.cwd('/' + current)
        except:
            try:
                ftp.mkd('/' + current)
            except Exception as e:
                pass
    ftp.cwd('/')

uploaded = 0
failed = 0

try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)

    for local, remote in FORCE_FILES:
        if not os.path.exists(local):
            continue
        remote_dir = '/'.join(remote.split('/')[:-1])
        if remote_dir:
            ensure_dir(ftp, remote_dir)
            
        try:
            with open(local, 'rb') as f:
                ftp.storbinary('STOR /' + remote, f)
            print(f'OK: {local}')
            uploaded += 1
        except Exception as e:
            print(f'FAIL: {local} -> {e}')
            failed += 1

    ftp.quit()
    print(f'\nSync to REAL public_html complete. Uploaded: {uploaded}, Failed: {failed}')

except Exception as e:
    print(f'FTP connection error: {e}')
