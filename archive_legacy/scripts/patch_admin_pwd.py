import sys
with open('c:/Users/pcpv/OneDrive/Desktop/DTH/admin/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_btn = "const historyBtn = `<button class=\"btn-small btn-blue\" onclick=\"openWorkerHistory(${w.worker_id}, '${escHtml(w.telegram_name || 'Thợ')}')\">Lịch sử</button>`;"
new_btn = "const historyBtn = `<button class=\"btn-small btn-blue\" onclick=\"openWorkerHistory(${w.worker_id}, '${escHtml(w.telegram_name || 'Thợ')}')\">Lịch sử</button> <button class=\"btn-small\" style=\"background:#8b5cf6;color:white;\" onclick=\"promptSetWorkerPin('${escHtml(w.phone || '')}')\">Cấp MK</button>`;"
content = content.replace(old_btn, new_btn)

func = '''
async function promptSetWorkerPin(phone) {
    if (!phone) return alert('Thợ này chưa có số điện thoại.');
    const pin = prompt('Nhập mật khẩu mới cho số điện thoại ' + phone + ':');
    if (!pin) return;
    if (pin.length < 4) return alert('Mật khẩu quá ngắn!');
    const res = await fetch('/api_master.php?action=admin_set_worker_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone, pin })
    });
    const json = await res.json();
    alert(json.message);
}
'''

content = content.replace('function renderWorkerRow(w){', func + '\nfunction renderWorkerRow(w){')

with open('c:/Users/pcpv/OneDrive/Desktop/DTH/admin/index.php', 'w', encoding='utf-8') as f:
    f.write(content)
print('Patched admin/index.php')
