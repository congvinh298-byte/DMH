import sys

with open('c:/Users/pcpv/OneDrive/Desktop/DTH/worker_js.txt', 'r', encoding='utf-8') as f:
    worker_js = f.read()

old_btn = '<button onclick="logoutWorker()" style="margin-top:10px;background:#dc2626;color:white;border:none;border-radius:8px;padding:10px 16px;width:100%;cursor:pointer;">Đăng xuất</button>'
new_btn = '''
<div style="display:flex;gap:10px;margin-top:10px;">
    <button onclick="promptChangeWorkerPin()" style="flex:1;background:#8b5cf6;color:white;border:none;border-radius:8px;padding:10px;cursor:pointer;">Đổi MK</button>
    <button onclick="logoutWorker()" style="flex:1;background:#dc2626;color:white;border:none;border-radius:8px;padding:10px;cursor:pointer;">Đăng xuất</button>
</div>
'''
if old_btn in worker_js:
    worker_js = worker_js.replace(old_btn, new_btn)
else:
    worker_js += '\n// Fallback\n'

change_pin_func = '''
async function promptChangeWorkerPin() {
    const oldPin = prompt('Nhập mật khẩu (PIN) cũ:');
    if (!oldPin) return;
    const newPin = prompt('Nhập mật khẩu (PIN) mới:');
    if (!newPin || newPin.length < 4) return alert('Mật khẩu mới quá ngắn (tối thiểu 4 ký tự).');
    
    const token = localStorage.getItem('dth_worker_token');
    const res = await fetch('../api_master.php?action=worker_change_pin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, old_pin: oldPin, new_pin: newPin })
    });
    const json = await res.json();
    alert(json.message);
    if (json.status === 'success') {
        logoutWorker();
    }
}
'''
worker_js += change_pin_func

with open('c:/Users/pcpv/OneDrive/Desktop/DTH/auth/partner.php', 'r', encoding='utf-8') as f:
    partner = f.read()

old_dom = '''document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('dth_worker_token')) {
                window.location.href = '../index.php';
            }
        });'''
new_dom = '''document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('dth_worker_token')) {
                document.querySelector('.login-box').style.display = 'none';
                openWorkerPanel();
            }
        });'''
partner = partner.replace(old_dom, new_dom)

# Add worker_js inside the <script> block
partner = partner.replace('function requestGpsAndLogin() {', worker_js + '\n\nfunction requestGpsAndLogin() {')

# Fix paths in JS
partner = partner.replace("'api_master.php", "'../api_master.php")
partner = partner.replace('"api_master.php', '"../api_master.php')

# Fix logoutWorker to not reload location (since we might have just opened the panel in partner.php)
# Wait, actually logoutWorker does localStorage.removeItem, then location.reload().
# location.reload() will trigger DOMContentLoaded again, which won't find the token, and thus won't hide .login-box, effectively showing the login screen! This is PERFECT. 

with open('c:/Users/pcpv/OneDrive/Desktop/DTH/auth/partner.php', 'w', encoding='utf-8') as f:
    f.write(partner)
print('Patched auth/partner.php successfully')
