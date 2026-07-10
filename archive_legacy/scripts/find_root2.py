import ftplib, io, sys
sys.stdout.reconfigure(encoding='utf-8')

ftp = ftplib.FTP('123.30.136.221')
ftp.login('dth@dienmayhieu.com', 'Anhthien369@')
ftp.encoding = 'latin-1'

content = b'BINGO'
dirs = [
    'assets',
    'public_html/assets',
    'public_html/public/assets',
    'DTH/assets',
    'public_html/DTH/assets'
]

for d in dirs:
    try:
        ftp.cwd('/')
        try:
            ftp.mkd(d)
        except:
            pass
        ftp.storbinary(f'STOR {d}/findme.txt', io.BytesIO(content))
        print('Uploaded to', d)
    except Exception as e:
        print('Failed', d, e)

ftp.quit()
