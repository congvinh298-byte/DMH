<?php
$phpPath = 'C:\Users\pcpv\OneDrive\Desktop\DTH\index.php';
$content = file_get_contents($phpPath);

// 1. Remove "Thông tin hoạt động" section
$content = preg_replace('/<section class="section" id="quy-che">.*?<\/section>\s*/s', '', $content);

// 2. Remove "Hero" section (because the Goi Tho block is replacing it)
$content = preg_replace('/<section class="hero".*?<\/section>\s*/s', '', $content);

file_put_contents($phpPath, $content);
echo "Phase 3 applied.\n";
?>
