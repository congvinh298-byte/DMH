import ftplib
import os
import sys

sys.stdout.reconfigure(encoding='utf-8')

FTP_HOST = '123.30.136.221'
FTP_USER = 'dth@dienmayhieu.com'
FTP_PASS = 'Anhthien369@'
REMOTE_BASE = 'home/kwkrbcce/public_html'
LOCAL_BASE = '.'

EXCLUDE_DIRS = {'.git', '.cursor', 'node_modules', 'scripts', 'docs', 'examples', 'Ho_so_BCT', 'skills', 'tests', 'storage', 'AutoSign', 'archive', 'downloaded_index.php', 'index_clean_from_ftp.php', 'fix_reads.py', 'fix_auth_reads.py', 'AGENTS.md', 'README.md', 'dth@dienmayhieu.com.coreftp'}
EXCLUDE_EXTS = {'.ps1', '.md', '.lnk', '.url', '.xlsx', '.docx', '.doc', '.pdf', '.py', '.sql', '.lock', '.log', '.txt', '.bak'}
EXCLUDE_FILES = {'downloaded_index.php', 'index_clean_from_ftp.php', 'fix_reads.py', 'fix_auth_reads.py', 'AGENTS.md', 'README.md', 'dth@dienmayhieu.com.coreftp', '.gitignore', 'HUONG_DAN_XU_LY_LOI.md'}

# Core files to always upload
FORCE_FILES = [
    ('index.php', 'home/kwkrbcce/public_html/index.php'),
    ('api_master.php', 'home/kwkrbcce/public_html/api_master.php'),
    ('.htaccess', 'home/kwkrbcce/public_html/.htaccess'),
    ('.user.ini', 'home/kwkrbcce/public_html/.user.ini'),
    ('admin.php', 'home/kwkrbcce/public_html/admin.php'),
    ('admin-content.php', 'home/kwkrbcce/public_html/admin-content.php'),
    ('bct_portal.php', 'home/kwkrbcce/public_html/bct_portal.php'),
    ('demo_gate.php', 'home/kwkrbcce/public_html/demo_gate.php'),
    ('vendor.php', 'home/kwkrbcce/public_html/vendor.php'),
    ('api_baocao_bct.php', 'home/kwkrbcce/public_html/api_baocao_bct.php'),
    ('auth/guest.php', 'home/kwkrbcce/public_html/auth/guest.php'),
    ('auth/partner.php', 'home/kwkrbcce/public_html/auth/partner.php'),
    ('assets/css/index.css', 'home/kwkrbcce/public_html/assets/css/index.css'),
    ('assets/js/main.js', 'home/kwkrbcce/public_html/assets/js/main.js'),
    ('assets/js/openclaw-chat.js', 'home/kwkrbcce/public_html/assets/js/openclaw-chat.js'),
    ('admin/index.php', 'home/kwkrbcce/public_html/admin/index.php'),
    ('admin/chat_logs.php', 'home/kwkrbcce/public_html/admin/chat_logs.php'),
    ('admin/db_screenshot.php', 'home/kwkrbcce/public_html/admin/db_screenshot.php'),
    ('api_master.php', 'home/kwkrbcce/public_html/api_master.php'),
    ('api/core.php', 'home/kwkrbcce/public_html/api/core.php'),
    ('api/orders.php', 'home/kwkrbcce/public_html/api/orders.php'),
    ('api/users.php', 'home/kwkrbcce/public_html/api/users.php'),
    ('api/products.php', 'home/kwkrbcce/public_html/api/products.php'),
    ('api/vouchers.php', 'home/kwkrbcce/public_html/api/vouchers.php'),
    ('api/jobs.php', 'home/kwkrbcce/public_html/api/jobs.php'),
    ('api/finances.php', 'home/kwkrbcce/public_html/api/finances.php'),
    ('api/payment.php', 'home/kwkrbcce/public_html/api/payment.php'),
    ('api/ai.php', 'home/kwkrbcce/public_html/api/ai.php'),
    ('api/notify.php', 'home/kwkrbcce/public_html/api/notify.php'),
    ('api/mobile.php', 'home/kwkrbcce/public_html/api/mobile.php'),
    ('api/shipping.php', 'home/kwkrbcce/public_html/api/shipping.php'),
    ('api/viettel.php', 'home/kwkrbcce/public_html/api/viettel.php'),
    ('api/cart.php', 'home/kwkrbcce/public_html/api/cart.php'),
    ('api/webhooks.php', 'home/kwkrbcce/public_html/api/webhooks.php'),
    ('api/openclaw_chat.php', 'home/kwkrbcce/public_html/api/openclaw_chat.php'),
    ('pages/_legal_footer.php', 'home/kwkrbcce/public_html/pages/_legal_footer.php'),
    ('pages/_legal_header.php', 'home/kwkrbcce/public_html/pages/_legal_header.php'),
    ('pages/gioi-thieu.php', 'home/kwkrbcce/public_html/pages/gioi-thieu.php'),
    ('pages/lien-he.php', 'home/kwkrbcce/public_html/pages/lien-he.php'),
    ('pages/chinh-sach-bao-mat.php', 'home/kwkrbcce/public_html/pages/chinh-sach-bao-mat.php'),
    ('pages/chinh-sach-doi-tra.php', 'home/kwkrbcce/public_html/pages/chinh-sach-doi-tra.php'),
    ('pages/chinh-sach-thanh-toan.php', 'home/kwkrbcce/public_html/pages/chinh-sach-thanh-toan.php'),
    ('pages/chinh-sach-van-chuyen.php', 'home/kwkrbcce/public_html/pages/chinh-sach-van-chuyen.php'),
    ('pages/de-an-dich-vu.php', 'home/kwkrbcce/public_html/pages/de-an-dich-vu.php'),
    ('pages/dieu-khoan-su-dung.php', 'home/kwkrbcce/public_html/pages/dieu-khoan-su-dung.php'),
    ('pages/giai-quyet-tranh-chap.php', 'home/kwkrbcce/public_html/pages/giai-quyet-tranh-chap.php'),
    ('pages/huong-dan-mua-hang.php', 'home/kwkrbcce/public_html/pages/huong-dan-mua-hang.php'),
    ('pages/quy-che-hoat-dong.php', 'home/kwkrbcce/public_html/pages/quy-che-hoat-dong.php'),
    ('pages/inc/footer.php', 'home/kwkrbcce/public_html/pages/inc/footer.php'),
    ('pages/inc/header.php', 'home/kwkrbcce/public_html/pages/inc/header.php'),
    ('cron/run_scheduled_action.php', 'home/kwkrbcce/public_html/cron/run_scheduled_action.php'),
    ('public/.htaccess', 'home/kwkrbcce/public_html/public/.htaccess'),
    ('storage/private/.htaccess', 'home/kwkrbcce/public_html/storage/private/.htaccess'),
]

def ensure_dir(ftp, remote_path):
    parts = remote_path.split('/')
    current = ''
    for part in parts:
        if not part:
            continue
        current = (current + '/' + part) if current else part
        try:
            ftp.cwd(current)
            ftp.cwd('/')
        except:
            try:
                ftp.mkd(current)
            except:
                pass

uploaded = 0
failed = 0

try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)

    for local, remote in FORCE_FILES:
        if not os.path.exists(local):
            print(f'SKIP (not found): {local}')
            continue
        remote_dir = '/'.join(remote.split('/')[:-1])
        try:
            ensure_dir(ftp, remote_dir)
        except:
            pass
        try:
            with open(local, 'rb') as f:
                ftp.storbinary('STOR ' + remote, f)
            print(f'OK: {local}')
            uploaded += 1
        except Exception as e:
            print(f'FAIL: {local} -> {e}')
            failed += 1

    ftp.quit()
    print(f'\nSync complete. Uploaded: {uploaded}, Failed: {failed}')

except Exception as e:
    print(f'FTP connection error: {e}')
