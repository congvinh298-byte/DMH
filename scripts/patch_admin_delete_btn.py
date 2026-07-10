import sys
with open('c:/Users/pcpv/OneDrive/Desktop/DTH/admin/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

new_text = '''Cấp MK</button> <button class="btn-small" style="background:#dc2626;color:white;" onclick="promptDeleteWorker('${escHtml(w.phone || '')}')">Xóa thợ</button>`;'''

content = content.replace('Cấp MK</button>`;', new_text)

with open('c:/Users/pcpv/OneDrive/Desktop/DTH/admin/index.php', 'w', encoding='utf-8') as f:
    f.write(content)
print('Patched admin/index.php successfully')
