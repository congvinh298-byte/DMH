import os
import re

admin_index_path = 'admin/index.php'

with open(admin_index_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Extract dashboard content
# It starts at <div class="admin-layout"> and ends before </body>
layout_start = content.find('<div class="admin-layout">')
layout_end = content.find('</body>', layout_start)

dashboard_html = content[layout_start:layout_end]

# Extract styles and add to top of dashboard_html
style_start = content.find('<style>')
style_end = content.find('</style>') + 8
styles = content[style_start:style_end]

full_dashboard = f"""<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Điện Máy Hiếu</title>
    {styles}
</head>
<body>
{dashboard_html}
</body>
</html>
"""

os.makedirs('views/admin', exist_ok=True)
with open('views/admin/dashboard.php', 'w', encoding='utf-8') as f:
    f.write(full_dashboard)

print("Created views/admin/dashboard.php")
