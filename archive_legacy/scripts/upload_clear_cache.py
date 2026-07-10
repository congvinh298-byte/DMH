import ftplib, io, sys
sys.stdout.reconfigure(encoding='utf-8')

ftp = ftplib.FTP('123.30.136.221')
ftp.login('dth@dienmayhieu.com', 'Anhthien369@')
ftp.encoding = 'latin-1'

# PHP script to clear OPcache, APCu, and create a physical cache clear request
php_script = b'''<?php
$output = [];
if (function_exists('opcache_reset')) {
    opcache_reset();
    $output[] = "OPcache reset: OK";
}
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    $output[] = "APCu reset: OK";
}

// LiteSpeed Cache purge header
header("X-LiteSpeed-Purge: *");
$output[] = "LiteSpeed Purge Header sent";

echo implode("<br>", $output);
?>'''

# Upload to public_html/api/ so it bypasses .htaccess rewrite
try:
    ftp.storbinary('STOR public_html/api/clear_cache_now.php', io.BytesIO(php_script))
    print('Uploaded to public_html/api/clear_cache_now.php')
except Exception as e:
    print('Error uploading:', e)

ftp.quit()
