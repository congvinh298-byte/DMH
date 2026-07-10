import sys
with open('c:/Users/pcpv/OneDrive/Desktop/DTH/admin/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

new_text = '''Cấp MK</button> <button class="btn-small" style="background:#dc2626;color:white;" onclick="promptDeleteWorker('${escHtml(w.phone || '')}')">Xóa thợ</button>`;'''

content = content.replace('Cấp MK</button>`;', new_text)

func = '''
async function promptDeleteWorker(phone) {
    if (!phone) return alert('Thợ này chưa có số điện thoại.');
    if (!confirm('Bạn có chắc chắn muốn VÔ HIỆU HÓA thợ có số điện thoại ' + phone + ' không? Hành động này sẽ khóa quyền đăng nhập của thợ.')) return;
    const res = await fetch('/api_master.php?action=admin_delete_worker', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone })
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') loadDashWorkers();
}
'''

if 'promptDeleteWorker' not in content:
    content = content.replace('function renderWorkerRow(w){', func + '\nfunction renderWorkerRow(w){')
    with open('c:/Users/pcpv/OneDrive/Desktop/DTH/admin/index.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('Patched admin/index.php successfully')
else:
    print('Already patched')
