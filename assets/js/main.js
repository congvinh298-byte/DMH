'use strict';

const cards = Array.from(document.querySelectorAll('.product'));
const searchInput = document.getElementById('searchInput');
let activeCategory = '';

function normalize(value) {
    return String(value || '').toLowerCase();
}

function formatVnd(value) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' VND';
}

function filterProducts() {
    const q = normalize(searchInput?.value);
    cards.forEach(card => {
        const okSearch = !q || card.dataset.name.indexOf(q) !== -1 || card.dataset.category.indexOf(q) !== -1;
        const okCategory = !activeCategory || card.dataset.category.indexOf(normalize(activeCategory)) !== -1;
        card.style.display = okSearch && okCategory ? '' : 'none';
    });
}

document.getElementById('searchForm')?.addEventListener('submit', event => {
    event.preventDefault();
    filterProducts();
});
searchInput?.addEventListener('input', filterProducts);

document.querySelectorAll('[data-category]').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-category]').forEach(item => item.classList.remove('active'));
        button.classList.add('active');
        activeCategory = button.dataset.category || '';
        filterProducts();
        const prodSection = document.getElementById('products');
        if (prodSection) prodSection.scrollIntoView({ behavior: 'smooth' });
    });
});

function showOrderStatus(type, text) {
    const box = document.getElementById('orderStatus');
    if (!box) return;
    box.className = 'status ' + (type === 'ok' ? 'ok' : 'err');
    box.textContent = text;
}

function closeOrderModal() {
    const modal = document.getElementById('orderModal');
    if (modal) modal.style.display = 'none';
}

function openOrderModal(card) {
    const name = card.dataset.productName || '';
    const price = Number(card.dataset.productPrice || 0);
    document.getElementById('order_product_id').value = card.dataset.productId || '0';
    document.getElementById('order_product_type').value = card.dataset.productType || 'product';
    document.getElementById('order_product_name').value = name;
    document.getElementById('order_product_price').value = String(price);
    document.getElementById('order_product_display').value = name + ' - ' + formatVnd(price);
    document.getElementById('orderStatus').className = 'status';
    document.getElementById('orderStatus').textContent = '';
    document.getElementById('orderModal').style.display = 'block';
    document.getElementById('order_customer_name').focus();
}

document.querySelectorAll('.buy-product').forEach(button => {
    button.addEventListener('click', () => {
        const card = button.closest('.product');
        if (card) openOrderModal(card);
    });
});

document.getElementById('orderForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = document.getElementById('orderSubmit');
    const phone = document.getElementById('order_customer_phone').value.trim();
    if (!/^[0-9]{8,15}$/.test(phone)) {
        showOrderStatus('err', 'Số điện thoại chỉ nhập số, từ 8 đến 15 chữ số.');
        return;
    }
    const formData = new FormData(form);
    submit.disabled = true;
    submit.textContent = 'Đang gửi...';
    showOrderStatus('ok', 'Đang gửi đơn mua...');
    try {
        const response = await fetch('api_master.php?action=create_order', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'Không gửi được đơn mua.');
        }
        showOrderStatus('ok', 'Đã gửi đơn #' + (data.order_code || data.order_id || '') + '. Shop sẽ gọi xác nhận.');
        ['order_customer_name', 'order_customer_phone', 'order_customer_address', 'order_voucher_code', 'order_note'].forEach(id => {
            const field = document.getElementById(id);
            if (field) field.value = '';
        });
    } catch (error) {
        showOrderStatus('err', error.message || 'Lỗi kết nối backend.');
    } finally {
        submit.disabled = false;
        submit.textContent = 'Gửi đơn mua';
    }
});

const serviceSearchInput = document.getElementById('serviceSearchInput');
if (serviceSearchInput) {
    serviceSearchInput.addEventListener('input', () => {
        const q = normalize(serviceSearchInput.value);
        document.querySelectorAll('.service-option').forEach(option => {
            option.style.display = !q || normalize(option.textContent).indexOf(q) !== -1 ? '' : 'none';
        });
    });
}

document.querySelectorAll('.choose-service').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.choose-service').forEach(item => item.classList.remove('selected'));
        button.classList.add('selected');
        const base = Number(button.dataset.base || 0);
        document.getElementById('service_type').value = button.dataset.group || 'Thợ điện lạnh';
        document.getElementById('tech_target_base').value = button.dataset.base || '';
        document.getElementById('issue_description').value = button.dataset.service || '';
        document.getElementById('selected_service_name').value = button.dataset.service || '';
        document.getElementById('customer_price_display').value = base > 0 ? formatVnd(Math.round(base * 1.10)) + ' - đã gồm VAT' : 'Liên hệ để báo giá';
    });
});

const addressInput = document.getElementById('address');
const locationStatus = document.getElementById('locationStatus');
const defaultLocation = [10.357422, 105.522124];
let locationMap = null;
let locationMarker = null;

function setLocationStatus(text) {
    if (locationStatus) {
        locationStatus.textContent = text;
    }
}

async function syncSelectedLocation(lat, lng, resolveAddress) {
    const latitude = Number(lat).toFixed(6);
    const longitude = Number(lng).toFixed(6);
    const coords = latitude + ',' + longitude;
    document.getElementById('map_location').value = coords;
    document.getElementById('map_lat').value = latitude;
    document.getElementById('map_lng').value = longitude;

    if (locationMap && window.L) {
        if (!locationMarker) {
            locationMarker = L.marker([lat, lng], {draggable: true}).addTo(locationMap);
            locationMarker.on('dragend', event => {
                const point = event.target.getLatLng();
                syncSelectedLocation(point.lat, point.lng, true);
            });
        } else {
            locationMarker.setLatLng([lat, lng]);
        }
    }

    if (!resolveAddress) {
        setLocationStatus('Vị trí đã đồng bộ: ' + coords);
        return;
    }

    addressInput.value = 'Vị trí đã chọn: ' + coords;
    setLocationStatus('Đang lấy địa chỉ cho vị trí ' + coords + '...');
    try {
        const response = await fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=vi&lat=' + encodeURIComponent(latitude) + '&lon=' + encodeURIComponent(longitude));
        if (!response.ok) {
            throw new Error('Không lấy được địa chỉ');
        }
        const data = await response.json();
        if (data.display_name) {
            addressInput.value = data.display_name;
        }
        setLocationStatus('Đã đồng bộ vị trí: ' + coords);
    } catch (error) {
        setLocationStatus('Đã đồng bộ tọa độ. Có thể sửa lại địa chỉ nếu cần.');
    }
}

if (window.L && document.getElementById('locationMap')) {
    locationMap = L.map('locationMap').setView(defaultLocation, 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(locationMap);

    const LocateControl = L.Control.extend({
        options: { position: 'topleft' },
        onAdd: function (map) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
            container.style.backgroundColor = 'white';
            container.style.width = '34px';
            container.style.height = '34px';
            container.style.cursor = 'pointer';
            container.style.backgroundImage = 'url("data:image/svg+xml,%3Csvg xmlns=\\\'http://www.w3.org/2000/svg\\\' viewBox=\\\'0 0 24 24\\\' fill=\\\'none\\\' stroke=\\\'black\\\' stroke-width=\\\'2\\\' stroke-linecap=\\\'round\\\' stroke-linejoin=\\\'round\\\'%3E%3Ccircle cx=\\\'12\\\' cy=\\\'12\\\' r=\\\'10\\\'/%3E%3Ccircle cx=\\\'12\\\' cy=\\\'12\\\' r=\\\'3\\\'/%3E%3C/svg%3E")';
            container.style.backgroundSize = '20px';
            container.style.backgroundRepeat = 'no-repeat';
            container.style.backgroundPosition = 'center';
            container.title = 'Vị trí của tôi';
            container.onclick = function(e){
                e.preventDefault();
                document.getElementById('useCurrentLocation')?.click();
            }
            return container;
        }
    });
    locationMap.addControl(new LocateControl());

    locationMap.on('click', event => syncSelectedLocation(event.latlng.lat, event.latlng.lng, true));
    window.setTimeout(() => locationMap.invalidateSize(), 100);
} else {
    setLocationStatus('Bản đồ chưa tải được. Vui lòng nhập địa chỉ trực tiếp.');
}

document.getElementById('useCurrentLocation')?.addEventListener('click', () => {
    if (!navigator.geolocation) {
        showBookingStatus('err', 'Trình duyệt chưa hỗ trợ lấy vị trí.');
        return;
    }
    navigator.geolocation.getCurrentPosition(position => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        if (locationMap) {
            locationMap.setView([lat, lng], 17);
        }
        syncSelectedLocation(lat, lng, true);
    }, () => showBookingStatus('err', 'Không lấy được vị trí hiện tại.'));
});

document.getElementById('clearLocation')?.addEventListener('click', () => {
    document.getElementById('map_location').value = '';
    document.getElementById('map_lat').value = '';
    document.getElementById('map_lng').value = '';
    if(addressInput) addressInput.value = '';
    if (locationMap && locationMarker) {
        locationMap.removeLayer(locationMarker);
        locationMarker = null;
        locationMap.setView(defaultLocation, 14);
    }
    setLocationStatus('Bấm vào bản đồ hoặc dùng vị trí hiện tại.');
});

const geminiPanel = document.getElementById('geminiPanel');
const geminiReply = document.getElementById('geminiReply');
document.getElementById('geminiQuoteButton')?.addEventListener('click', () => {
    geminiPanel.classList.add('active');
    document.getElementById('geminiQuestion').focus();
});
document.getElementById('closeGeminiButton')?.addEventListener('click', () => {
    geminiPanel.classList.remove('active');
});
document.getElementById('askGeminiButton')?.addEventListener('click', async () => {
    const question = document.getElementById('geminiQuestion').value.trim();
    const selected = document.getElementById('issue_description').value.trim();
    geminiReply.classList.add('active');
    geminiReply.textContent = 'Đang tư vấn báo giá...';
    try {
        const response = await fetch('api_master.php?action=anh_thien_chat', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                message: question || selected || 'Tư vấn Điện Máy Hiếu',
                service_type: document.getElementById('service_type').value,
                selected_service: selected,
                public_price: document.getElementById('customer_price_display').value,
                address: addressInput ? addressInput.value : ''
            })
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'Không tư vấn được lúc này.');
        }
        geminiReply.textContent = data.reply || 'Chưa có nội dung tư vấn.';
    } catch (error) {
        geminiReply.textContent = error.message || 'Lỗi kết nối trợ lí.';
    }
});

function deviceId() {
    let id = localStorage.getItem('dth_device_id');
    if (!id) {
        id = 'dth-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
        localStorage.setItem('dth_device_id', id);
    }
    return id;
}

function showBookingStatus(type, text) {
    const box = document.getElementById('bookingStatus');
    box.className = 'status ' + (type === 'ok' ? 'ok' : 'err');
    box.textContent = text;
}

document.getElementById('bookingForm')?.addEventListener('submit', async event => {
    event.preventDefault();

    const form = event.currentTarget;
    const submitButton = document.getElementById('bookingSubmit');
    const phone = document.getElementById('phone').value.trim();
    const issue = document.getElementById('issue_description').value.trim();
    const selectedService = document.getElementById('selected_service_name').value.trim();
    const mapLat = document.getElementById('map_lat').value.trim();
    const mapLng = document.getElementById('map_lng').value.trim();

    if (!selectedService) {
        showBookingStatus('err', 'Vui lòng chọn dịch vụ trong danh sách phía trên trước khi gửi form.');
        return;
    }

    if (!/^[0-9]{8,15}$/.test(phone)) {
        showBookingStatus('err', 'Số điện thoại chỉ được nhập số, từ 8 đến 15 chữ số.');
        return;
    }

    if (!mapLat || !mapLng) {
        showBookingStatus('err', 'Vui lòng bấm chọn và xác nhận tọa độ trên bản đồ trước khi gửi yêu cầu.');
        locationStatus?.scrollIntoView({behavior: 'smooth', block: 'center'});
        return;
    }

    document.getElementById('description').value = issue;
    document.getElementById('device_fingerprint').value = deviceId();

    const formData = new FormData(form);
    submitButton.disabled = true;
    submitButton.textContent = 'Đang gửi...';
    showBookingStatus('ok', 'Đang gửi yêu cầu...');

    try {
        const response = await fetch('api_master.php?action=create_job', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (!response.ok || !(data.status === 'success' || data.success === true)) {
            throw new Error(data.message || 'Không gửi được yêu cầu.');
        }
        alert('Đã báo ca thành công!');
        form.reset();
        document.getElementById('customer_price_display').value = '';
        document.getElementById('map_location').value = '';
        document.getElementById('map_lat').value = '';
        document.getElementById('map_lng').value = '';
        document.querySelectorAll('.choose-service').forEach(item => item.classList.remove('selected'));
        if (locationMap && locationMarker) {
            locationMap.removeLayer(locationMarker);
            locationMarker = null;
            locationMap.setView(defaultLocation, 14);
        }
        setLocationStatus('Bấm vào bản đồ hoặc dùng vị trí hiện tại.');
        showBookingStatus('ok', 'Yêu cầu đã gửi thành công.');
    } catch (error) {
        showBookingStatus('err', error.message || 'Lỗi kết nối backend.');
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Gửi yêu cầu gọi thợ';
    }
});

const policies = {
    'quyche': {
        title: 'Quy Chế Hoạt Động',
        body: `
            <h4>1. Nguyên tắc chung</h4>
            <p>Điện Máy Hiếu là website hỗ trợ kết nối khách hàng với thợ kỹ thuật sửa chữa, lắp đặt thiết bị điện tử, điện lạnh, gia dụng tại địa bàn Lấp Vò, Đồng Tháp và khu vực lân cận.</p>
            <h4>2. Quy định dành cho Khách Hàng</h4>
            <ul>
                <li>Cung cấp thông tin liên hệ và tình trạng sự cố trung thực, chính xác.</li>
                <li>Thanh toán đầy đủ chi phí dịch vụ và vật tư trực tiếp cho thợ sau khi nghiệm thu công việc.</li>
                <li>Có quyền đánh giá, phản ánh chất lượng dịch vụ trực tiếp lên hệ thống Hotline để Điện Máy Hiếu xử lý.</li>
            </ul>
            <h4>3. Quy định dành cho Thợ Kỹ Thuật</h4>
            <ul>
                <li><strong>Ký kết hợp tác:</strong> Các thợ tham gia hệ thống phải ký kết hợp đồng lao động/hợp tác với Trần Công Vinh (Điện Máy Hiếu), cung cấp đầy đủ hồ sơ nhân thân để đảm bảo an toàn cho khách hàng.</li>
                <li><strong>Tiếp nhận công việc:</strong> Nhận lệnh điều phối tự động qua website ứng dụng nhóm chat Telegram do Điện Máy Hiếu quản lý và sử dụng bot Telegram để hỗ trợ báo cáo, cập nhật trạng thái đơn hàng.</li>
                <li><strong>Trách nhiệm:</strong> Tuân thủ đạo đức nghề nghiệp, thái độ phục vụ chuẩn mực. Cam kết bảo hành các linh kiện và dịch vụ đã thi công.</li>
                <li><strong>Nghĩa vụ tài chính:</strong> Tuân thủ nghĩa vụ thanh toán chiết khấu (phí website) đúng hạn để duy trì quyền lợi nhận ca.</li>
            </ul>
            <h4>4. Giải quyết tranh chấp</h4>
            <p>Mọi tranh chấp phát sinh giữa khách hàng và thợ sẽ được Trần Công Vinh (Điện Máy Hiếu) đứng ra làm trung gian tiếp nhận, hòa giải dựa trên quy định pháp luật và quyền lợi chính đáng của người tiêu dùng.</p>
        `
    },
    'dean': {
        title: 'Đề Án Hoạt Động & Tầm Nhìn',
        body: `
            <h4>1. Tên đề án</h4>
            <p><strong>Xây dựng website số Dịch vụ Kỹ thuật và Thương mại Điện tử tại địa bàn Nông thôn mới.</strong></p>
            <h4>2. Mục tiêu đề án</h4>
            <ul>
                <li>Ứng dụng công nghệ thông tin vào đời sống thiết thực, mang lại trải nghiệm <em>"gọi thợ số"</em> nhanh chóng, tiện lợi và minh bạch cho người dân trong khu vực xã và huyện.</li>
                <li>Số hóa quy trình làm việc truyền thống của các thợ kỹ thuật tại địa phương.</li>
            </ul>
            <h4>3. Đơn vị phát triển</h4>
            <p>Đề án được đầu tư, nghiên cứu và phát triển bởi Đơn vị tư nhân <strong>Trần Công Vinh (Điện Máy Hiếu)</strong>. Chúng tôi mang khát vọng phát triển quê hương bằng tri thức công nghệ, đóng góp vào công cuộc chuyển đổi số quốc gia từ cấp cơ sở.</p>
            <h4>4. Mô hình hoạt động</h4>
            <ul>
                <li><strong>Mô hình cá nhân:</strong> website do Trần Công Vinh (Điện Máy Hiếu) trực tiếp vận hành. Thợ kỹ thuật tham gia theo hợp đồng lao động/hợp tác với chủ cơ sở.</li>
                <li>Tạo ra công ăn việc làm ổn định, tăng thu nhập cho lao động có tay nghề tại địa phương mà không gò bó thời gian.</li>
                <li>Ứng dụng tự động hóa thông qua Telegram Bot để tiết giảm tối đa chi phí vận hành, từ đó mang lại mức giá dịch vụ tốt nhất cho bà con.</li>
            </ul>
            <h4>5. Tầm nhìn chiến lược</h4>
            <p>Điện Máy Hiếu hướng tới mục tiêu trở thành website dịch vụ kiểu mẫu phục vụ thiết thực cho đời sống, dễ dàng nhân rộng sang các địa bàn cấp xã khác, góp sức kiến tạo nên bức tranh Nông Thôn Mới hiện đại, số hóa và văn minh.</p>
        `
    },
    'baomat': {
        title: 'Chính Sách Bảo Mật',
        body: `
            <h4>1. Mục đích thu thập thông tin</h4>
            <p>Chúng tôi thu thập các thông tin bao gồm Tên, Số điện thoại, Địa chỉ và Tọa độ GPS của khách hàng duy nhất cho mục đích: xử lý đơn đặt hàng, điều phối thợ kỹ thuật đến đúng vị trí, và chăm sóc bảo hành sau dịch vụ.</p>
            <h4>2. Phạm vi sử dụng dữ liệu</h4>
            <ul>
                <li>Thông tin được lưu chuyển nội bộ trên hệ thống máy chủ Điện Máy Hiếu và gửi thông báo qua kênh Telegram bảo mật riêng của nhóm thợ.</li>
                <li>Tất cả thợ tham gia đều đã ký cam kết bảo mật thông tin khách hàng trong hợp đồng lao động/hợp tác.</li>
                <li>Tuyệt đối <strong>KHÔNG</strong> bán, trao đổi hay chia sẻ dữ liệu cá nhân của khách hàng cho bất kỳ bên thứ 3 nào với mục đích thương mại.</li>
            </ul>
            <h4>3. Thời gian lưu trữ</h4>
            <p>Dữ liệu khách hàng được lưu trữ an toàn trên máy chủ cho đến khi khách hàng có yêu cầu hủy bỏ hoặc Điện Máy Hiếu ngừng cung cấp dịch vụ theo quy định pháp luật.</p>
            <h4>4. Cam kết bảo mật</h4>
            <p>Chúng tôi áp dụng các chuẩn mực bảo mật dữ liệu trên website và hệ thống API để ngăn ngừa mọi hành vi truy cập trái phép, rò rỉ dữ liệu.</p>
            <h4>5. Quyền lợi của khách hàng</h4>
            <p>Khách hàng có quyền yêu cầu tra cứu, chỉnh sửa hoặc xóa bỏ hoàn toàn thông tin cá nhân của mình khỏi hệ thống bằng cách liên hệ trực tiếp qua Hotline của Điện Máy Hiếu.</p>
        `
    }
};

function openModal(type) {
    const data = policies[type];
    if (data) {
        document.getElementById('modalTitle').innerHTML = data.title;
        document.getElementById('modalBody').innerHTML = data.body;
        document.getElementById('modalPolicy').style.display = 'block';
    }
}

function closeModal() {
    const modal = document.getElementById('modalPolicy');
    if(modal) modal.style.display = 'none';
}

window.onclick = function(event) {
    const modal1 = document.getElementById('modalPolicy');
    const modal2 = document.getElementById('modalLogin');
    if (event.target == modal1) modal1.style.display = "none";
    if (event.target == modal2) modal2.style.display = "none";
}

/* =========================================
   AUTHENTICATION LOGIC (REAL API)
========================================= */

function updateLoginState() {
    try {
        const str = localStorage.getItem('dth_customer_session');
        if (str) {
            const user = JSON.parse(str);
            if (user && user.id) {
                const badge = user.member_rank || 'Thành Viên';
                document.getElementById('topBarStatus').innerHTML = 'Xin chào, ' + user.fullname + ' (' + badge + ') | <a href="javascript:void(0)" onclick="logoutCustomer()" style="color:#fde047; margin-left:10px; font-weight:bold; text-decoration:underline;">Đăng xuất</a>';
            }
        } else {
            document.getElementById('topBarStatus').innerHTML = '<a href="javascript:void(0)" onclick="openLoginModal()" style="color: white; font-weight: bold; text-decoration: underline;">Đăng nhập / Đăng ký</a>';
        }
    } catch(e) {}
}

function logoutCustomer() {
    localStorage.removeItem('dth_customer_session');
    updateLoginState();
    alert('Đã đăng xuất thành công.');
}

function openLoginModal() {
    document.getElementById('modalLogin').style.display = 'block';
    document.getElementById('loginMethods').style.display = 'block';
    document.getElementById('qrLoginForm').style.display = 'none';
    document.getElementById('memberQrDisplay').style.display = 'none';
}

function checkTos() {
    const tos = document.getElementById('tosCheck');
    if(tos && !tos.checked) {
        alert('Vui lòng đồng ý với Điều khoản dịch vụ và Chính sách bảo mật!');
        return false;
    }
    return true;
}

function alertNotImplemented() {
    if(checkTos()) {
        alert('Hệ thống hiện tại chỉ hỗ trợ đăng nhập bằng Mã/QR Thành viên do cửa hàng cấp. Vui lòng quét QR thẻ thành viên của bạn!');
    }
}

function showQrLogin() {
    if(!checkTos()) return;
    document.getElementById('loginMethods').style.display = 'none';
    document.getElementById('qrLoginForm').style.display = 'block';
    document.getElementById('loginKeyInput').value = '';
    document.getElementById('loginError').style.display = 'none';
}

async function submitLogin() {
    const key = document.getElementById('loginKeyInput').value.trim();
    if (!key) {
        alert('Vui lòng nhập mã thẻ!');
        return;
    }
    
    const btn = document.getElementById('loginSubmitBtn');
    const errBox = document.getElementById('loginError');
    errBox.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Đang xác thực...';
    
    try {
        const response = await fetch('api_master.php?action=app_customer_login_qr', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ login_key: key })
        });
        const data = await response.json();
        
        if (!response.ok || data.status === 'error') {
            throw new Error(data.message || 'Mã không hợp lệ hoặc lỗi kết nối.');
        }
        
        // Success
        localStorage.setItem('dth_customer_session', JSON.stringify(data.data));
        
        // Show success screen
        document.getElementById('qrLoginForm').style.display = 'none';
        document.getElementById('memberQrDisplay').style.display = 'block';
        document.getElementById('successUserName').textContent = data.data.fullname;
        document.getElementById('successUserRank').textContent = data.data.member_rank;
        document.getElementById('successUserPoints').textContent = formatVnd(data.data.loyalty_points) + ' điểm';
        
        // Generate actual QR for them to show in store
        document.getElementById('successQrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(key);
        
        updateLoginState();
        
    } catch (error) {
        errBox.textContent = error.message;
        errBox.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Xác nhận Đăng nhập';
    }
}

// Call on load
document.addEventListener('DOMContentLoaded', updateLoginState);
