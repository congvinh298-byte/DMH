<?php
// vendor.php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" href="data:,">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Quản lý Cửa Hàng - Chợ Xã Lấp Vò</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #f3f4f6; color: #1f2937; }
        .header { background: #047857; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 18px; }
        .logout-btn { background: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; }
        
        .tabs { display: flex; background: white; border-block-end: 1px solid #e5e7eb; overflow-x: auto; }
        .tab { padding: 15px 20px; cursor: pointer; font-weight: 600; color: #6b7280; white-space: nowrap; border-block-end: 3px solid transparent; }
        .tab.active { color: #047857; border-block-end-color: #047857; }
        
        .content { padding: 20px; }
        .panel { display: none; }
        .panel.active { display: block; }
        
        .card { background: white; border-radius: 8px; padding: 15px; margin-block-end: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 15px; align-items: center; }
        .card img { inline-size: 80px; block-size: 80px; object-fit: cover; border-radius: 6px; }
        .card-info { flex: 1; }
        .card-title { font-weight: 700; margin-block-end: 5px; color: #111827; }
        .card-price { color: #dc2626; font-weight: bold; }
        
        .order-card { background: white; border-radius: 8px; padding: 15px; margin-block-end: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-inline-start: 4px solid #f59e0b; }
        .order-card.completed { border-inline-start-color: #10b981; }
        .order-header { display: flex; justify-content: space-between; margin-block-end: 10px; font-size: 13px; color: #6b7280; }
        .order-title { font-weight: bold; margin-block-end: 5px; }
        .order-customer { font-size: 14px; margin-block-end: 5px; }
        .order-price { font-weight: bold; color: #dc2626; text-align: end; }
        
        .btn-add { background: #047857; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; inline-size: 100%; margin-block-end: 20px; }
        
        .modal { display: none; position: fixed; inset-block-start: 0; inset-inline-start: 0; inset-inline-end: 0; inset-block-end: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; inline-size: 100%; max-inline-size: 500px; max-block-size: 90vh; overflow-y: auto; padding: 20px; position: relative; }
        .modal-close { position: absolute; inset-block-start: 15px; inset-inline-end: 15px; font-size: 24px; cursor: pointer; color: #6b7280; line-height: 1; }
        .modal-title { font-size: 18px; font-weight: bold; margin-block-end: 20px; color: #111827; }
        .form-group { margin-block-end: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; margin-block-end: 5px; color: #374151; }
        .form-group input, .form-group select { inline-size: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #047857; box-shadow: 0 0 0 3px rgba(4,120,87,0.1); }
        .btn-submit { background: #047857; color: white; inline-size: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer; margin-block-start: 10px; }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="header">
    <h1 id="storeName">Đang tải...</h1>
    <div style="display: flex; align-items: center; gap: 15px;">
        <div id="notificationBell" style="position: relative; cursor: pointer;" onclick="switchTab('orders', document.querySelectorAll('.tab')[1])">
            <svg style="inline-size: 24px; block-size: 24px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            <span id="notificationBadge" style="display: none; position: absolute; inset-block-start: -5px; inset-inline-end: -5px; background: #dc2626; color: white; border-radius: 50%; padding: 2px 5px; font-size: 10px; font-weight: bold;">0</span>
        </div>
        <button class="logout-btn" onclick="logout()">Đăng xuất</button>
    </div>
</div>

<div class="tabs">
    <div class="tab active" onclick="switchTab('products', this)">Sản phẩm của tôi</div>
    <div class="tab" onclick="switchTab('orders', this)">Đơn hàng</div>
</div>

<div class="content">
    <div id="productsPanel" class="panel active">
        <button class="btn-add" onclick="openAddProductModal()">+ Thêm sản phẩm mới</button>
        <div id="productsList">Đang tải sản phẩm...</div>
    </div>
    
    <div id="ordersPanel" class="panel">
        <button class="btn-add" style="background: #2563eb;" onclick="closeShift()">+ Chốt sổ cuối ngày</button>
        <div id="ordersList">Đang tải đơn hàng...</div>
    </div>
</div>

<div id="receiptModal" class="modal">
    <div class="modal-content" style="max-inline-size: 400px; text-align: center; background: #fff; box-shadow: inset 0 0 10px rgba(0,0,0,0.05);">
        <div class="modal-close" onclick="document.getElementById('receiptModal').classList.remove('active')">&times;</div>
        <h2 style="margin-block-start: 10px; margin-block-end: 5px; color: #111827;">HÓA ĐƠN CHỐT CA</h2>
        <div style="font-size: 14px; color: #6b7280; margin-block-end: 20px; border-block-end: 2px dashed #e5e7eb; padding-block-end: 15px;">
            Ngày lập: <b id="receiptDate">...</b><br>
            Cửa hàng: <b id="receiptStore">...</b>
        </div>
        
        <div style="display: flex; justify-content: space-between; font-size: 16px; margin-block-end: 10px;">
            <span>Tổng số đơn:</span>
            <b id="receiptCount">0</b>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 18px; margin-block-end: 20px; font-weight: bold; color: #dc2626;">
            <span>Tổng doanh thu:</span>
            <b id="receiptTotal">0 đ</b>
        </div>
        
        <div style="font-size: 13px; color: #9ca3af; border-block-start: 1px solid #e5e7eb; padding-block-start: 15px;">
            Lưu hóa đơn này để khai báo thuế môn bài.
        </div>
    </div>
</div>

<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-close" onclick="document.getElementById('productModal').classList.remove('active')">&times;</div>
        <div class="modal-title" id="productModalTitle">Thêm sản phẩm mới</div>
        <form id="productForm" onsubmit="saveProduct(event)">
            <input type="hidden" id="prodId" value="0">
            <div class="form-group">
                <label>Tên sản phẩm *</label>
                <input type="text" id="prodName" required placeholder="Ví dụ: Trà sữa trân châu">
            </div>
            <div class="form-group">
                <label>Mô tả sản phẩm</label>
                <textarea id="prodDescription" rows="3" placeholder="Ví dụ: Trà sữa thái xanh đậm vị, trân châu dai giòn..." style="inline-size: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-family: inherit; box-sizing: border-box;"></textarea>
            </div>
            <div class="form-group">
                <label>Giá bán (VNĐ) *</label>
                <input type="number" id="prodPrice" required min="0" placeholder="Ví dụ: 25000">
            </div>
            <div class="form-group">
                <label>Số lượng kho (Tùy chọn)</label>
                <input type="number" id="prodStock" min="0" value="1000" placeholder="Mặc định: 1000">
            </div>
            <div class="form-group">
                <label>Danh mục</label>
                <input type="text" id="prodCategory" placeholder="Ví dụ: Đồ uống, Đồ ăn vặt...">
            </div>
            <div class="form-group">
                <label>Link ảnh sản phẩm (Tùy chọn)</label>
                <input type="url" id="prodImage" placeholder="https://...">
            </div>
            <button type="submit" class="btn-submit" id="btnSubmitProduct">LƯU SẢN PHẨM</button>
        </form>
    </div>
</div>

<script>
const STORE_KEY = localStorage.getItem('dth_store_key');
if (!STORE_KEY) {
    window.location.href = 'index.php';
}

function switchTab(tabId, el) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById(tabId + 'Panel').classList.add('active');
    if (tabId === 'orders') {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.style.display = 'none';
            badge.textContent = '0';
        }
    }
}

function logout() {
    if(confirm('Xác nhận đăng xuất?')) {
        localStorage.removeItem('dth_store_key');
        window.location.href = 'index.php';
    }
}

async function saveProduct(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitProduct');
    btn.disabled = true;
    btn.textContent = 'ĐANG LƯU...';
    try {
        await api('app_store_save_product', {
            id: document.getElementById('prodId').value,
            name: document.getElementById('prodName').value,
            description: document.getElementById('prodDescription').value,
            price: document.getElementById('prodPrice').value,
            stock: document.getElementById('prodStock').value,
            category: document.getElementById('prodCategory').value,
            image_url: document.getElementById('prodImage').value
        });
        document.getElementById('productModal').classList.remove('active');
        document.getElementById('productForm').reset();
        document.getElementById('prodId').value = '0';
        
        // Reload products
        const prods = await api('app_store_get_products');
        renderProducts(prods.data || []);
        alert('Đã lưu sản phẩm thành công!');
    } catch(err) {
        alert('Lỗi: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'LƯU SẢN PHẨM';
    }
}

function openAddProductModal() {
    document.getElementById('productForm').reset();
    document.getElementById('prodId').value = '0';
    document.getElementById('productModalTitle').textContent = 'Thêm sản phẩm mới';
    document.getElementById('productModal').classList.add('active');
}

function editProduct(p) {
    document.getElementById('productForm').reset();
    document.getElementById('productModalTitle').textContent = 'Sửa sản phẩm';
    document.getElementById('prodId').value = p.id || '0';
    document.getElementById('prodName').value = p.name || '';
    document.getElementById('prodDescription').value = p.description || '';
    document.getElementById('prodPrice').value = p.price || 0;
    document.getElementById('prodStock').value = p.stock || 0;
    document.getElementById('prodCategory').value = p.type || '';
    document.getElementById('prodImage').value = p.image_url || '';
    document.getElementById('productModal').classList.add('active');
}

async function deleteProduct(id) {
    if(!confirm('Bạn có chắc chắn muốn xóa sản phẩm này không?')) return;
    try {
        await api('app_store_delete_product', { id: id });
        alert('Đã xóa sản phẩm.');
        const prods = await api('app_store_get_products');
        renderProducts(prods.data || []);
    } catch(err) {
        alert('Lỗi khi xóa: ' + err.message);
    }
}

function formatMoney(num) {
    return new Intl.NumberFormat('vi-VN').format(num) + ' đ';
}

async function closeShift() {
    if(!confirm('Bạn có chắc muốn chốt sổ doanh thu hôm nay?')) return;
    try {
        const res = await api('vendor_close_shift');
        const d = res.data;
        document.getElementById('receiptDate').textContent = d.report_date;
        document.getElementById('receiptStore').textContent = document.getElementById('storeName').textContent;
        document.getElementById('receiptCount').textContent = d.total_orders;
        document.getElementById('receiptTotal').textContent = formatMoney(d.total_revenue);
        document.getElementById('receiptModal').classList.add('active');
    } catch(err) {
        alert('Lỗi: ' + err.message);
    }
}

async function api(action, body = {}) {
    body.login_key = STORE_KEY;
    const res = await fetch(`api_master.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    const d = await res.json();
    if(d.status !== 'success') throw new Error(d.message || d.error || 'Lỗi API');
    return d;
}

let lastPendingCount = -1;

async function checkNewOrders() {
    try {
        const orders = await api('vendor_get_orders');
        const list = orders.data || [];
        const pendingCount = list.filter(o => o.status === 'pending' || o.status === 'Chờ xác nhận').length;
        
        const badge = document.getElementById('notificationBadge');
        if (pendingCount > 0) {
            badge.style.display = 'block';
            badge.textContent = pendingCount;
            // Play sound if new order arrived
            if (lastPendingCount !== -1 && pendingCount > lastPendingCount) {
                playNotificationSound();
            }
        } else {
            badge.style.display = 'none';
        }
        
        // If we are on the orders tab, we might want to refresh the list silently
        if (document.getElementById('ordersPanel').classList.contains('active')) {
            renderOrders(list);
        }
        
        lastPendingCount = pendingCount;
    } catch(e) {
        // silently fail polling
    }
}

function playNotificationSound() {
    try {
        const audio = new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg');
        audio.play();
    } catch(e) {}
}

async function loadData() {
    try {
        const auth = await api('verify_login_key');
        document.getElementById('storeName').textContent = auth.data.store_name || auth.data.owner_name;
        
        const prods = await api('app_store_get_products');
        renderProducts(prods.data || []);
        
        await checkNewOrders();
        // Start polling every 15 seconds
        setInterval(checkNewOrders, 15000);
    } catch(e) {
        alert('Lỗi: ' + e.message);
        if(e.message.includes('khong hop le') || e.message.includes('Thieu key')) {
            logout();
        }
    }
}

function renderProducts(list) {
    const box = document.getElementById('productsList');
    if(list.length === 0) {
        box.innerHTML = '<div style="text-align:center; padding: 20px; color: #6b7280;">Chưa có sản phẩm nào.</div>';
        return;
    }
    let html = '';
    list.forEach(p => {
        const img = p.image_url ? (p.image_url.startsWith('http') ? p.image_url : p.image_url) : 'https://placehold.co/100x100?text=No+Image';
        const pJson = JSON.stringify(p).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
        html += `
        <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <img src="${img}" alt="${p.name}">
                <div class="card-info">
                    <div class="card-title">${p.name}</div>
                    <div style="font-size: 13px; color: #6b7280; margin-block-end: 5px;">Kho: ${p.stock} | ${p.category || p.type || 'Khác'}</div>
                    ${p.description ? `<div style="font-size: 12px; color: #4b5563; margin-block-end: 5px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${p.description}</div>` : ''}
                    <div class="card-price">${formatMoney(p.price)}</div>
                </div>
            </div>
            <div style="display:flex; justify-content: space-between; align-items:center; padding: 10px; border-block-start: 1px solid #e5e7eb;">
                <button class="btn" style="padding: 6px 12px; font-size: 12px; background: #e5e7eb; color: #374151;" onclick='editProduct(${pJson})'>Sửa</button>
                <button class="btn danger" style="padding: 6px 12px; font-size: 12px;" onclick='deleteProduct(${p.id})'>Xóa</button>
            </div>
        </div>`;
    });
    box.innerHTML = html;
}

function renderOrders(list) {
    const box = document.getElementById('ordersList');
    if(list.length === 0) {
        box.innerHTML = '<div style="text-align:center; padding: 20px; color: #6b7280;">Chưa có đơn hàng nào.</div>';
        return;
    }
    let html = '';
    list.forEach(o => {
        const isOk = o.status === 'completed';
        const isCancelled = o.status === 'cancelled' || o.status === 'Đã hủy';
        
        let statusDisplay = o.status;
        if (o.status === 'pending' || o.status === 'Chờ xác nhận') statusDisplay = 'Chờ xử lý';
        else if (o.status === 'delivering') statusDisplay = 'Đang giao';
        else if (o.status === 'completed') statusDisplay = 'Thành công';
        else if (isCancelled) statusDisplay = 'Đã hủy';
        else if (o.status === 'customer_received') statusDisplay = 'Đã nhận (Chờ thu tiền)';
        
        const statusBg = isOk ? '#d1fae5' : (isCancelled ? '#fee2e2' : '#fef3c7');
        const statusColor = isOk ? '#065f46' : (isCancelled ? '#991b1b' : '#92400e');
        
        html += `
        <div class="order-card ${isOk ? 'completed' : ''}">
            <div class="order-header">
                <span>Mã: <b>${o.order_code}</b></span>
                <span>${o.created_at}</span>
            </div>
            <div class="order-title">${o.product_name}</div>
            <div class="order-customer">Khách: <b>${o.customer_name}</b> - ${o.customer_phone}</div>
            <div style="font-size: 13px; color: #4b5563; margin-block-end: 10px;">Đ/c: ${o.customer_address}</div>
            ${o.note ? `<div style="font-size: 13px; color: #d97706; margin-block-end: 10px; background: #fffbeb; padding: 6px; border-radius: 4px; border: 1px dashed #fcd34d;">📝 Ghi chú: ${o.note}</div>` : ''}
            <div style="display:flex; justify-content: space-between; align-items: center; margin-block-end: 10px;">
                <span style="font-size: 12px; font-weight: bold; padding: 4px 8px; border-radius: 4px; background: ${statusBg}; color: ${statusColor}">${statusDisplay}</span>
                <div class="order-price">${formatMoney((o.subtotal || o.total_price) - (o.discount || 0))}</div>
            </div>
            ${(!isOk && !isCancelled) ? `
            <div style="display:flex; gap: 10px; margin-block-start: 10px; flex-wrap:wrap;">
                ${(o.status !== 'delivering' && o.status !== 'customer_received') ? `<button style="flex: 1; min-inline-size: 100px; padding: 8px; border: none; border-radius: 4px; background: #3b82f6; color: white; font-weight: bold; cursor: pointer;" onclick="updateOrderStatus(${o.id}, 'delivering')">🚚 Đã giao hàng</button>` : ''}
                <button style="flex: 1; min-inline-size: 140px; padding: 8px; border: none; border-radius: 4px; background: #10b981; color: white; font-weight: bold; cursor: pointer;" onclick="updateOrderStatus(${o.id}, 'completed')">✅ Xác nhận hoàn thành</button>
                ${(o.status !== 'customer_received') ? `<button style="flex: 1; min-inline-size: 100px; padding: 8px; border: none; border-radius: 4px; background: #ef4444; color: white; font-weight: bold; cursor: pointer;" onclick="updateOrderStatus(${o.id}, 'cancelled')">❌ Hủy đơn</button>` : ''}
            </div>
            ` : ''}
            ${o.status === 'completed' ? `
            <div style="display:flex; gap: 10px; margin-block-start: 10px;">
                <button style="flex: 1; padding: 8px; border: none; border-radius: 4px; background: #4b5563; color: white; font-weight: bold; cursor: pointer;" onclick="printInvoice(${o.id})">🖨 In hóa đơn</button>
            </div>
            ` : ''}
        </div>`;
    });
    box.innerHTML = html;
}

function printInvoice(id) {
    const w = window.open('', '_blank');
    w.document.write('<h2>Hóa Đơn Mua Hàng</h2><p>Đơn hàng: #' + id + '</p><p>Trạng thái: Đã thanh toán</p><p><i>Cửa hàng vui lòng sử dụng phần mềm hóa đơn điện tử của Bộ Tài Chính để xuất hóa đơn hợp lệ.</i></p><button onclick="window.print()">In Hóa Đơn Này</button>');
    w.document.close();
}

async function updateOrderStatus(id, status) {
    let msg = 'Xác nhận hủy đơn hàng này?';
    if (status === 'completed') msg = 'Xác nhận hoàn thành và ghi nhận doanh thu cho đơn hàng này?';
    if (status === 'delivering') msg = 'Xác nhận bắt đầu giao đơn hàng này?';
    if(!confirm(msg)) return;
    try {
        await api('vendor_update_order_status', { order_id: id, status: status });
        // Reload data
        loadData();
    } catch(err) {
        alert('Lỗi: ' + err.message);
    }
}

loadData();
</script>
</body>
</html>
