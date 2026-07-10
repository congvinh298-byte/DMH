import ftplib, io
ftp = ftplib.FTP('123.30.136.221')
ftp.login('dth@dienmayhieu.com', 'Anhthien369@')

marker = b'<?php opcache_reset(); echo "CACHE CLEARED " . date("H:i:s"); ?>'
ftp.storbinary('STOR public_html/reset_cache.php', io.BytesIO(marker))
ftp.quit()
print('Uploaded reset_cache.php')
