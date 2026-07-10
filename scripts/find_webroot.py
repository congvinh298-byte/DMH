import ftplib, io, sys
sys.stdout.reconfigure(encoding='utf-8')

ftp = ftplib.FTP('123.30.136.221')
ftp.login('dth@dienmayhieu.com', 'Anhthien369@')
ftp.encoding = 'latin-1'

# Try uploading a test asset to assets/ folder
content = b'WHEEL_CHECK_v2'
for path in ['assets/js/wheel_check.js', 'public_html/assets/js/wheel_check.js']:
    try:
        ftp.storbinary('STOR ' + path, io.BytesIO(content))
        print('Uploaded:', path)
    except Exception as e:
        print('FAIL:', path, str(e))

ftp.quit()
