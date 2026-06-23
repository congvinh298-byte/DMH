import json
import ftplib
import os
import subprocess

def get_changed_files():
    result = subprocess.run(['git', 'status', '--porcelain'], capture_output=True, text=True)
    lines = result.stdout.split('\n')
    files = []
    for line in lines:
        if len(line) < 3:
            continue
        status = line[:2]
        filepath = line[3:].strip().strip('"')
        
        # Un-escape git string if needed
        if filepath.startswith('\\'):
            # Just rough decoding, we skip complex names for simplicity
            pass 

        # We only upload files, not directories
        if not filepath.endswith('/'):
            files.append(filepath)
            
    # Also include the specific files we know we touched just in case they are not in git properly
    manual_files = [
        'admin_xxx.php',
        'api_master.php',
        'api/orders.php',
        'api/viettel.php',
        'alter_viettel.php',
        'database_migration.sql',
        'index.php',
        '.env',
        'run_migration.php',
        'create_invoices_table.php'
    ]
    for f in manual_files:
        if f not in files and os.path.exists(f):
            files.append(f)
            
    return files

def upload_files(files):
    # Load config
    try:
        with open('.vscode/sftp.json') as f:
            config = json.load(f)
    except Exception as e:
        print("Lỗi: Không thể đọc file .vscode/sftp.json:", e)
        return

    host = config.get('host')
    user = config.get('username')
    password = config.get('password')
    port = config.get('port', 21)
    remote_path = config.get('remotePath', '/public_html')

    print(f"Đang kết nối tới FTP server: {host}...")
    try:
        ftp = ftplib.FTP()
        ftp.connect(host, port)
        ftp.login(user, password)
        print("Đăng nhập thành công!")
    except Exception as e:
        print("Lỗi kết nối FTP:", e)
        return

    try:
        ftp.cwd(remote_path)
    except Exception as e:
        print(f"Lỗi truy cập thư mục {remote_path}:", e)
        return

    success_count = 0
    for file in files:
        if not os.path.isfile(file):
            continue
            
        print(f"Đang upload: {file} ...", end=' ')
        
        # Ensure remote directory exists
        dirname = os.path.dirname(file).replace('\\', '/')
        if dirname:
            try:
                # very simple mkdir -p over ftp
                ftp.cwd(dirname)
                ftp.cwd('/')
                ftp.cwd(remote_path)
            except:
                dirs = dirname.split('/')
                current = ""
                for d in dirs:
                    current = f"{current}/{d}" if current else d
                    try:
                        ftp.mkd(current)
                    except:
                        pass
        
        try:
            with open(file, 'rb') as f_in:
                ftp.storbinary(f'STOR {file.replace(chr(92), "/")}', f_in)
            print("OK")
            success_count += 1
        except Exception as e:
            print("LỖI:", e)

    ftp.quit()
    print(f"\nHoàn tất! Đã upload thành công {success_count} files.")

if __name__ == '__main__':
    files_to_sync = get_changed_files()
    print("Các file sẽ được upload:")
    for f in files_to_sync:
        print(" -", f)
    print()
    upload_files(files_to_sync)
