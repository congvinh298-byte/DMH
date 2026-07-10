import sys
sys.stdout.reconfigure(encoding='utf-8')

# Read the clean backup from FTP
with open('index_clean_from_ftp.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Find the exact location where wheel JS starts
spinwheel_old = '''async function spinWheel() {
    if(isSpinning) return;
    let spins = parseInt((document.getElementById('wheelSpinsCount') ? document.getElementById('wheelSpinsCount').textContent : '')) || 0;
    if(spins <= 0) {
        alert('Bạn đã hết lượt quay!');
        return;
    }

    isSpinning = true;
    const btn = document.getElementById('spinBtn');
    btn.textContent = 'Đang quay...';
    btn.disabled = true;

    try {
        const key = localStorage.getItem('dth_user_key');
        const formData = new FormData();
        formData.append('login_key', key);
        const response = await fetch('api_master.php?action=app_customer_spin_wheel', { method: 'POST', body: formData });
        const res = await readJsonResponse(response);

        if(res.status === 'success') {
            const prizeIndex = res.data.prize_index;
            const extraRotations = 5;
            const sliceAngle = 360 / wheelPrizes.length;
            const targetSliceCenter = prizeIndex * sliceAngle + sliceAngle / 2;
            const targetRotationDeg = 270 - targetSliceCenter + (extraRotations * 360);

            let currentDeg = wheelAngle * 180 / Math.PI;
            const totalRotation = targetRotationDeg - (currentDeg % 360) + (extraRotations * 360);

            const duration = 4000;
            const start = performance.now();

            function animate(time) {
                let progress = (time - start) / duration;
                if(progress > 1) progress = 1;
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const currentAnimatedDeg = currentDeg + totalRotation * easeOut;
                wheelAngle = currentAnimatedDeg * Math.PI / 180;
                drawWheel();

                if(progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    isSpinning = false;
                    btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)';
                    btn.disabled = false;
                    alert('🎉 Kết quả: ' + wheelPrizes[prizeIndex] + '\\\\n' + res.message);

                    { const _el = document.getElementById('wheelSpinsCount'); if(_el) _el.textContent = res.data.lucky_spins; }
                    { const _el = document.getElementById('successLuckySpins'); if(_el) _el.textContent = res.data.lucky_spins + ' lượt quay'; }
                    { const _el = document.getElementById('successUserPoints'); if(_el) _el.textContent = res.data.loyalty_points + ' điểm'; }
                }
            }
            requestAnimationFrame(animate);

        } else {
            alert(res.message);
            isSpinning = false;
            btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)';
            btn.disabled = false;
        }
    } catch(e) {
        alert('Lỗi kết nối.');
        isSpinning = false;
        btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)';
        btn.disabled = false;
    }
}
</script>'''

spinwheel_new = '''async function spinWheel() {
    if(isSpinning) return;
    const btn = document.getElementById('spinBtn');
    let spins = parseInt((document.getElementById('wheelSpinsCount') ? document.getElementById('wheelSpinsCount').textContent : '')) || 0;
    if(spins <= 0) {
        alert('Bạn đã hết lượt quay! Hãy mua hàng để nhận thêm lượt.');
        return;
    }

    isSpinning = true;
    if(btn) { btn.textContent = 'Đang quay...'; btn.disabled = true; }

    try {
        const key = localStorage.getItem('dth_user_key');
        if(!key) { alert('Vui lòng đăng nhập để quay!'); isSpinning = false; if(btn) { btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)'; btn.disabled = false; } return; }
        const formData = new FormData();
        formData.append('login_key', key);
        const response = await fetch('api_master.php?action=app_customer_spin_wheel', { method: 'POST', body: formData });
        const res = await readJsonResponse(response);

        if(res.status === 'success') {
            const prizeIndex = res.data.prize_index;
            const extraRotations = 5;
            const sliceAngle = 360 / wheelPrizes.length;
            const targetSliceCenter = prizeIndex * sliceAngle + sliceAngle / 2;
            const targetRotationDeg = 270 - targetSliceCenter + (extraRotations * 360);

            let currentDeg = wheelAngle * 180 / Math.PI;
            const totalRotation = targetRotationDeg - (currentDeg % 360) + (extraRotations * 360);

            const duration = 4000;
            const start = performance.now();

            function animate(time) {
                let progress = (time - start) / duration;
                if(progress > 1) progress = 1;
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const currentAnimatedDeg = currentDeg + totalRotation * easeOut;
                wheelAngle = currentAnimatedDeg * Math.PI / 180;
                drawWheel();

                if(progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    isSpinning = false;
                    if(btn) { btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)'; btn.disabled = false; }
                    setTimeout(() => alert('🎉 Kết quả: ' + wheelPrizes[prizeIndex] + '\\n' + res.message), 100);

                    { const _el = document.getElementById('wheelSpinsCount'); if(_el) _el.textContent = res.data.lucky_spins; }
                    { const _el = document.getElementById('successLuckySpins'); if(_el) _el.textContent = res.data.lucky_spins + ' lượt quay'; }
                    { const _el = document.getElementById('successUserPoints'); if(_el) _el.textContent = res.data.loyalty_points + ' điểm'; }
                }
            }
            requestAnimationFrame(animate);

        } else {
            alert(res.message || 'Lỗi không xác định.');
            isSpinning = false;
            if(btn) { btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)'; btn.disabled = false; }
        }
    } catch(e) {
        alert('Lỗi kết nối: ' + (e.message || e));
        isSpinning = false;
        if(btn) { btn.textContent = 'CHƠI NGAY (Tốn 1 Lượt)'; btn.disabled = false; }
    }
}
</script>

<!-- Vòng Quay May Mắn Modal -->
<div id="wheelModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100000; justify-content:center; align-items:center; backdrop-filter:blur(4px);" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff; border-radius:24px; padding:28px 24px; max-width:400px; width:95%; text-align:center; box-shadow:0 30px 80px rgba(0,0,0,0.4); position:relative; animation:slideUp 0.35s cubic-bezier(0.175,0.885,0.32,1.1);">
        <button onclick="document.getElementById('wheelModal').style.display='none'" style="position:absolute;top:14px;right:16px;background:#f1f5f9;border:none;width:34px;height:34px;border-radius:50%;font-size:18px;cursor:pointer;color:#64748b;display:flex;align-items:center;justify-content:center;">&times;</button>
        <h2 style="margin:0 0 4px; color:#dc2626; font-size:22px; font-weight:800;">🎡 Vòng Quay May Mắn</h2>
        <p style="color:#64748b; font-size:13px; margin:0 0 16px;">Lượt quay còn lại: <strong id="wheelSpinsCount" style="color:#dc2626;">0</strong></p>
        <div style="position:relative; display:inline-block; margin-bottom:16px;">
            <canvas id="wheelCanvas" width="280" height="280" style="display:block; border-radius:50%; box-shadow:0 8px 32px rgba(0,0,0,0.15);"></canvas>
            <div style="position:absolute;top:50%;left:-22px;transform:translateY(-50%);font-size:28px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.3));">&#9654;</div>
        </div>
        <button id="spinBtn" onclick="spinWheel()" style="width:100%;padding:14px;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;border:none;border-radius:14px;font-size:16px;font-weight:800;cursor:pointer;letter-spacing:0.5px;box-shadow:0 4px 16px rgba(220,38,38,0.35);transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(220,38,38,0.45)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 16px rgba(220,38,38,0.35)'">
            🎰 CHƠI NGAY (Tốn 1 Lượt)
        </button>
        <p style="font-size:11px; color:#94a3b8; margin:10px 0 0;">Mua hàng để nhận thêm lượt quay</p>
    </div>
</div>'''

if spinwheel_old in content:
    new_content = content.replace(spinwheel_old, spinwheel_new, 1)
    with open('index.php', 'w', encoding='utf-8') as f:
        f.write(new_content)
    print('SUCCESS: Replaced spinWheel and added wheelModal HTML')
else:
    print('ERROR: Could not find the target section')
    # Try to debug
    idx = content.find('async function spinWheel()')
    if idx != -1:
        print('Found spinWheel at char:', idx)
        snippet = content[idx:idx+200]
        print('Snippet:', repr(snippet[:100]))
    else:
        print('spinWheel NOT found at all')
