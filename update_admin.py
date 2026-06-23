import sys

with open('admin_xxx.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = '''function loadStores(){
    api('admin_get_stores').then(d=>{
        document.getElementById('storesBody').innerHTML=(d.data||[]).map(s=>`<tr><td>${s.id}</td><td><b>${esc(s.store_name)}</b></td><td>MST: ${esc(s.tax_code)}<br>SDT: ${esc(s.phone)}</td><td>${esc(s.store_type)}<br><small>${esc(s.address)}</small></td><td><b style="color:#dc2626">${fmt(s.total_sales || 0)}</b></td><td>${statusBadge(s.status==='active'?'ok':'warn',s.status||'active')}</td><td><button class="btn" onclick="alert('Dang phat trien: Xem menu')">Xem Menu</button></td></tr>`).join('') || '<tr><td colspan="7">Chua co cua hang nao.</td></tr>';
    });
}'''

replacement = '''function loadStores(){
    api('admin_get_stores').then(d=>{
        document.getElementById('storesBody').innerHTML=(d.data||[]).map(s=>`
        <tr>
            <td>${s.id}</td>
            <td><b>${esc(s.store_name)}</b></td>
            <td>MST: ${esc(s.tax_code)}<br>SDT: ${esc(s.phone)}</td>
            <td>${esc(s.store_type)}<br><small>${esc(s.address)}</small></td>
            <td><b style="color:#dc2626">${fmt(s.total_sales || 0)}</b></td>
            <td>${statusBadge(s.status==='active'?'ok':'warn',s.status||'pending')}</td>
            <td>
                ${s.status === 'pending' ? `<button class="btn success" onclick="approveStore(${s.id})">Duyệt</button>` : ''}
                <button class="btn" onclick="showStoreQR('${s.report_token}', '${esc(s.store_name)}')">Lấy QR</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="7">Chua co cua hang nao.</td></tr>';
    });
}

function approveStore(id) {
    if(!confirm('Xác nhận duyệt cửa hàng này?')) return;
    api('admin_approve_store', {store_id: id}).then(d=>{
        if(d.status==='success') { msg(d.message); loadStores(); }
    });
}

function showStoreQR(token, name) {
    const url = 'https://dienmayhieu.com/store_report.php?token=' + token;
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(url);
    const w = window.open('', '_blank');
    w.document.write(`<html><body style="text-align:center;font-family:sans-serif;padding:50px"><h2>Mã QR Báo Cáo: ${name}</h2><img src="${qrUrl}"/><p>Quét mã này bằng Camera điện thoại để xem báo cáo doanh thu.</p></body></html>`);
}'''

if target in content:
    content = content.replace(target, replacement)
    with open('admin_xxx.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print('Replaced successfully')
else:
    print('Target not found')
