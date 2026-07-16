<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define("IN_SITE", true);
require_once(__DIR__."/core/config.php");
require_once(__DIR__."/core/function.php");

if (!isset($DMH)) {
    $DMH = new DMH();
}

$title = "Điện Máy Hiếu - Marketplace & Gọi Thợ";
require_once(__DIR__."/pages/client/Head.php");
require_once(__DIR__."/pages/client/Header.php");

$productError = '';
$products = $DMH->get_list("SELECT p.*, c.name as category_name FROM `products` p LEFT JOIN `product_categories` c ON c.id = p.category_id WHERE p.`status` = 1 AND p.`featured` = 1 ORDER BY p.id DESC LIMIT 60");
if (!is_array($products)) {
    $products = [];
}

$services = array(
    array('group' => 'Thợ điện lạnh', 'name' => 'Vệ sinh máy lạnh', 'base' => 150000, 'note' => 'Trọn gói (Không phí ẩn)'),
    array('group' => 'Thợ điện lạnh', 'name' => 'Lắp đặt máy lạnh 1HP / 1.5HP', 'base' => 400000, 'note' => 'Chưa gồm vật tư phát sinh'),
    array('group' => 'Thợ điện lạnh', 'name' => 'Lắp đặt máy lạnh 2HP / 3HP', 'base' => 500000, 'note' => 'Chưa gồm vật tư phát sinh'),
    array('group' => 'Thợ điện lạnh', 'name' => 'Máy lạnh âm trần', 'base' => 0, 'note' => 'Khảo sát và báo giá riêng'),
    array('group' => 'Thợ điện lạnh', 'name' => 'Sửa chữa điện lạnh', 'base' => 200000, 'note' => 'Công thợ + linh kiện công khai'),
    array('group' => 'Thợ tivi', 'name' => 'Treo tivi (32-43")', 'base' => 150000, 'note' => 'Công thợ + giá khung treo công khai'),
    array('group' => 'Thợ tivi', 'name' => 'Treo tivi (50-55")', 'base' => 200000, 'note' => 'Công thợ + giá khung treo công khai'),
    array('group' => 'Thợ tivi', 'name' => 'Treo tivi (65-75")', 'base' => 300000, 'note' => 'Công thợ + giá khung treo công khai'),
    array('group' => 'Thợ máy lọc nước', 'name' => 'Lắp máy lọc nước', 'base' => 200000, 'note' => 'Công thợ + phụ kiện'),
    array('group' => 'Thợ gia dụng', 'name' => 'Lắp máy giặt', 'base' => 200000, 'note' => 'Công thợ + phụ kiện'),
    array('group' => 'Thợ gia dụng', 'name' => 'Sửa điện gia dụng', 'base' => 100000, 'note' => 'Công thợ kiểm tra sửa chữa'),
    array('group' => 'Thợ điện thoại', 'name' => 'Kiểm tra / sửa điện thoại', 'base' => 100000, 'note' => 'Công thợ + linh kiện nếu có')
);
?>

<main>
    <div class="wrap storefront fade-in">
        
        <!-- Premium Hero Section -->
        <section class="hero">
            <div class="hero-main">
                <div class="blob"></div>
                <h1>Điện Máy Hiếu</h1>
                <p>Hệ sinh thái bán lẻ, dịch vụ sửa chữa, in mô hình 3D dựa trên nền tảng công nghệ số tư nhân do chính công ty phát hành.</p>
                <div class="hero-actions">
                    <a class="btn light" href="#products"><i class="fa-solid fa-shopping-cart"></i> Khám phá Sản phẩm</a>
                    <a class="btn accent" href="#goi-tho"><i class="fa-solid fa-tools"></i> Đặt lịch Gọi Thợ</a>
                    <a class="btn outline" href="/in-3d.php"><i class="fa-solid fa-cube"></i> Dịch vụ In 3D</a>
                </div>
            </div>
        </section>

        <!-- Products Section -->
        <section class="section" id="products">
            <div class="title">
                <h2>Sản phẩm Nổi bật</h2>
                <span class="muted"><?= count($products) ?> sản phẩm</span>
            </div>
            <div class="grid" id="productGrid">
                <?php if (empty($products)): ?>
                    <div class="empty">
                        <i class="fa-solid fa-box-open"></i>
                        Hiện chưa có sản phẩm nào
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php
                        $name = isset($p['name']) ? (string)$p['name'] : '';
                        $category = $p['category_name'] ? $p['category_name'] : ($p['type'] == '3d' ? 'Mo hinh In 3D' : 'Dien May & Gia Dung');
                        $image = isset($p['image']) ? (string)$p['image'] : '';
                        $price = isset($p['price']) ? (float)$p['price'] : 0;
                        ?>
                        <article class="product" data-name="<?= htmlspecialchars(strtolower($name)) ?>" data-category="<?= htmlspecialchars(strtolower($category)) ?>">
                            <div class="img">
                                <?php if ($image !== ''): ?>
                                    <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($name) ?>" onerror="this.parentNode.textContent='Chưa có ảnh'">
                                <?php else: ?>
                                    <div style="color: var(--muted); font-size: 13px;">Chưa có ảnh</div>
                                <?php endif; ?>
                            </div>
                            <div class="body">
                                <div class="cat"><?= htmlspecialchars($category) ?></div>
                                <div class="name" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></div>
                                <div class="price"><?= number_format($price, 0, ',', '.') ?>đ</div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px;">
                                    <button onclick="addToCart(<?= $p['id'] ?>)" class="btn outline" style="width: 100%; padding: 8px; font-size: 13px; border-color: rgba(255,255,255,0.2);"><i class="fa-solid fa-cart-plus"></i> Thêm giỏ</button>
                                    <button onclick="buyNow(<?= $p['id'] ?>)" class="btn accent" style="width: 100%; padding: 8px; font-size: 13px;"><i class="fa-solid fa-bolt"></i> Mua Ngay</button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Pricing & Services Section -->
        <section class="section" id="bang-gia" style="margin-top: 40px;">
            <div class="title" style="text-align: center; margin-bottom: 40px;">
                <h2 style="font-size: 32px; font-weight: 900; letter-spacing: -1px; color: var(--brand-accent);">BẢNG GIÁ DỊCH VỤ</h2>
                <span class="muted" style="display: block; margin-top: 8px; font-size: 16px;">Minh bạch - Trọn gói - Không phí ẩn</span>
            </div>
            
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; padding: 0 16px;">
                    <?php 
                    $colors = [
                        'Thợ điện lạnh' => '#38bdf8',
                        'Thợ tivi' => '#fbbf24',
                        'Thợ máy lọc nước' => '#10b981',
                        'Thợ gia dụng' => '#f43f5e',
                        'Thợ điện thoại' => '#a855f7'
                    ];
                    
                    foreach ($services as $item): 
                        $base = (int)$item['base']; 
                        $publicPrice = $base > 0 ? number_format($base, 0, ',', '.') . 'đ' : 'Khảo sát';
                        $c = isset($colors[$item['group']]) ? $colors[$item['group']] : '#38bdf8';
                    ?>
                    <div style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255,255,255,0.1); border-left: 4px solid <?= $c ?>; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; transition: all 0.2s ease; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.2);" onmouseover="this.style.transform='translateY(-3px)'; this.style.borderColor='<?= $c ?>'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.2)'" onclick="document.getElementById('goi-tho').scrollIntoView({behavior: 'smooth'});">
                        <div>
                            <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: <?= $c ?>; margin-bottom: 6px;"><?= htmlspecialchars($item['group']) ?></div>
                            <div style="font-size: 16px; font-weight: 700; color: #fff; line-height: 1.3;"><?= htmlspecialchars($item['name']) ?></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                            <div style="font-size: 12px; color: #94a3b8; max-width: 60%; line-height: 1.3;"><i class="fa-solid fa-circle-info" style="font-size: 10px; margin-right: 4px; opacity: 0.7;"></i><?= htmlspecialchars($item['note']) ?></div>
                            <div style="font-size: 17px; font-weight: 900; color: <?= $c ?>; background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 6px;"><?= $publicPrice ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
        </section>

        <!-- Booking App Section -->
        <section class="section booking-shell" id="goi-tho" style="margin-top: 60px; max-width: 800px; margin-inline: auto;">
            <div class="title" style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 32px; font-weight: 900; letter-spacing: -1px;">GỌI THỢ NGAY</h2>
                <span class="muted" style="display: block; margin-top: 8px;">Điền thông tin - 15 phút thợ có mặt</span>
            </div>
            
            <form id="bookingForm" autocomplete="off">
                <div id="thongbao_datlich" style="margin-block-end: 24px;"></div>
                <h3>Thông tin Yêu cầu Dịch vụ</h3>
                <div class="form">
                    <div class="field full">
                        <label for="service_selector">1. Bạn cần dịch vụ gì? <span style="color:var(--brand-accent);">*</span></label>
                        <div id="custom-service-selector">
                            <?php 
                            $icons = [
                                'Vệ sinh máy lạnh' => '❄️',
                                'Lắp đặt máy lạnh 1HP / 1.5HP' => '🛠️',
                                'Lắp đặt máy lạnh 2HP / 3HP' => '🛠️',
                                'Máy lạnh âm trần' => '🏢',
                                'Sửa chữa điện lạnh' => '🔧',
                                'Treo tivi (32-43")' => '📺',
                                'Treo tivi (50-55")' => '📺',
                                'Treo tivi (65-75")' => '📺',
                                'Lắp máy lọc nước' => '💧',
                                'Lắp máy giặt' => '🧺',
                                'Sửa điện gia dụng' => '🔌',
                                'Kiểm tra / sửa điện thoại' => '📱'
                            ];
                            $colors = ['#38bdf8', '#fbbf24', '#10b981', '#f43f5e', '#a855f7', '#94a3b8'];
                            $c_idx = -1;
                            $currentGroup = '';
                            
                            foreach ($services as $svc): 
                                if ($svc['group'] !== $currentGroup) {
                                    if ($currentGroup !== '') echo '</div></div>';
                                    $c_idx++;
                                    $accent = $colors[$c_idx % count($colors)];
                                    echo '<div style="margin-bottom: 12px;">';
                                    echo '<div style="font-size: 13px; font-weight: 800; color: ' . $accent . '; margin-bottom: 6px; text-transform: uppercase;">' . htmlspecialchars($svc['group']) . '</div>';
                                    echo '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
                                    $currentGroup = $svc['group'];
                                }
                                $base = (int)$svc['base']; 
                                $publicPrice = $base > 0 ? number_format($base, 0, ',', '.') . ' VND' : 'Báo giá sau khi khảo sát';
                                $icon = isset($icons[$svc['name']]) ? $icons[$svc['name']] : '✨';
                            ?>
                                <button type="button" class="cute-btn" data-val="<?= htmlspecialchars($svc['name']) ?>" data-price="<?= htmlspecialchars($publicPrice) ?>" data-group="<?= htmlspecialchars($svc['group']) ?>" data-note="<?= htmlspecialchars($svc['note']) ?>" style="background: rgba(255,255,255,0.05); border: 2px solid rgba(255,255,255,0.1); color: #fff; padding: 8px 14px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;" onmouseover="this.style.background='rgba(255,255,255,0.1)';" onmouseout="if(!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.05)';">
                                    <span style="font-size: 16px;"><?= $icon ?></span> <?= htmlspecialchars($svc['name']) ?>
                                </button>
                            <?php endforeach; ?>
                            <?php if ($currentGroup !== '') echo '</div></div>'; ?>
                        </div>
                    </div>
                    
                    <input type="hidden" id="service_type" name="service_type">
                    <input type="hidden" id="selected_service_name" name="selected_service_name">
                    
                    <div class="field full">
                        <label for="customer_price_display">Giá tham khảo (Đã gồm VAT)</label>
                        <input class="readonly-price" id="customer_price_display" type="text" readonly placeholder="Chọn dịch vụ ở trên để xem giá" style="color: var(--brand-accent); font-weight: bold; background: rgba(0,0,0,0.3);">
                        <small id="service_note" style="color: #94a3b8; display: block; margin-top: 8px; font-weight: 500;"></small>
                    </div>
                    
                    <div class="field">
                        <label for="customer_name">2. Tên của bạn <span style="color:var(--brand-accent);">*</span></label>
                        <input id="customer_name" name="customer_name" required maxlength="150" placeholder="VD: Anh Minh">
                    </div>
                    
                    <div class="field">
                        <label for="phone">3. Số điện thoại liên hệ <span style="color:var(--brand-accent);">*</span></label>
                        <input id="phone" name="phone" type="tel" inputmode="numeric" pattern="[0-9]{8,15}" required maxlength="15" placeholder="09xx.xxx.xxx">
                    </div>
                    
                    <div class="field full">
                        <label for="address">4. Địa chỉ chính xác <span style="color:var(--brand-accent);">*</span></label>
                        <input id="address" name="address" required maxlength="500" placeholder="Số nhà, tên đường, khu vực...">
                        <div class="map-actions">
                            <button class="btn accent" id="useCurrentLocation" type="button" style="padding: 10px 16px; font-size: 13px;"><i class="fa-solid fa-location-crosshairs"></i> Lấy tọa độ GPS hiện tại</button>
                            <button class="btn" id="clearLocation" type="button" style="padding: 10px 16px; font-size: 13px; background: rgba(255,255,255,0.1);"><i class="fa-solid fa-eraser"></i> Xóa</button>
                        </div>
                        <input type="hidden" id="map_location" name="map_location">
                        <input type="hidden" id="map_lat" name="map_lat">
                        <input type="hidden" id="map_lng" name="map_lng">
                        <div class="map-preview">
                            <div class="location-map" id="locationMap" aria-label="Bản đồ chọn vị trí"></div>
                            <div class="location-status" id="locationStatus">Bấm vào bản đồ để chọn điểm hoặc dùng nút Lấy GPS phía trên.</div>
                        </div>
                    </div>
                    
                    <div class="field full">
                        <label for="issue_description">5. Mô tả chi tiết vấn đề <span style="color:var(--brand-accent);">*</span></label>
                        <textarea id="issue_description" name="issue_description" required maxlength="2000" placeholder="Máy bị lỗi gì, hiện tượng như thế nào..."></textarea>
                    </div>
                    
                    <div class="field full" style="margin-top: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px; background: rgba(56, 189, 248, 0.1); padding: 12px; border-radius: 8px; border: 1px dashed rgba(56, 189, 248, 0.3);">
                            <input type="checkbox" id="vat_request_booking" style="width: 20px; height: 20px; accent-color: #38bdf8; cursor: pointer;">
                            <label for="vat_request_booking" style="cursor: pointer; font-weight: bold; color: #38bdf8; user-select: none; margin: 0;">Yêu cầu xuất hóa đơn VAT</label>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <p style="color: #94a3b8; font-size: 14px; margin-bottom: 20px;"><i class="fa-solid fa-circle-info"></i> Giá báo trên web là giá công khai. Nếu có phát sinh vật tư linh kiện, thợ sẽ báo giá chi tiết và xin phép bạn trước khi tiến hành sửa chữa.</p>
                    <button id="btnDatLich" class="btn accent" type="button" style="width: 100%; padding: 18px; font-size: 18px; font-weight: 800; letter-spacing: 0.5px; box-shadow: 0 10px 30px rgba(56,189,248,0.3);"><i class="fa-solid fa-paper-plane"></i> GỬI YÊU CẦU NGAY</button>
                </div>
            </form>
        </section>

    </div>
</main>

<script>
'use strict';

// Product Filtering
const cards = Array.from(document.querySelectorAll('.product'));
const searchInput = document.getElementById('searchInput');

function normalize(value) {
    return String(value || '').toLowerCase();
}

function filterProducts() {
    const q = normalize(searchInput.value);
    cards.forEach(card => {
        const okSearch = !q || card.dataset.name.indexOf(q) !== -1 || card.dataset.category.indexOf(q) !== -1;
        card.style.display = okSearch ? '' : 'none';
    });
}

if(searchInput) {
    searchInput.addEventListener('input', filterProducts);
}

// Service Selector Logic
const cuteBtns = document.querySelectorAll('.cute-btn');
cuteBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        // Remove active class from all
        cuteBtns.forEach(b => {
            b.classList.remove('active');
            b.style.background = 'rgba(255,255,255,0.05)';
            b.style.borderColor = 'rgba(255,255,255,0.1)';
            b.style.boxShadow = 'none';
        });
        
        // Add active class to clicked
        this.classList.add('active');
        this.style.background = 'var(--brand-accent)';
        this.style.borderColor = 'var(--brand-accent)';
        this.style.boxShadow = '0 4px 15px rgba(56, 189, 248, 0.4)';
        
        const group = this.dataset.group || '';
        const serviceName = this.dataset.val || '';
        const price = this.dataset.price || 'Liên hệ để báo giá chi tiết';
        const note = this.dataset.note || '';
        
        document.getElementById('service_type').value = group;
        document.getElementById('selected_service_name').value = serviceName;
        document.getElementById('customer_price_display').value = price;
        document.getElementById('service_note').innerHTML = `<i class="fa-solid fa-asterisk" style="font-size: 12px; margin-right: 4px;"></i> ${note}`;
        
        // Auto-fill issue description if it's empty
        const issueDesc = document.getElementById('issue_description');
        if (!issueDesc.value || issueDesc.value.length < 5) {
            issueDesc.value = 'Tôi cần ' + serviceName;
        }
    });
});

// Map Logic
const addressInput = document.getElementById('address');
const locationStatus = document.getElementById('locationStatus');
const defaultLocation = [10.357422, 105.522124];
let locationMap = null;
let locationMarker = null;

function setLocationStatus(text) {
    if (locationStatus) locationStatus.textContent = text;
}

function syncSelectedLocation(lat, lng, resolveAddress) {
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

    if (!resolveAddress) return;

    setLocationStatus('Đang lấy địa chỉ từ GPS...');
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=vi&lat=' + latitude + '&lon=' + longitude)
        .then(res => res.json())
        .then(data => {
            if (data.display_name) addressInput.value = data.display_name;
            setLocationStatus('Đã đồng bộ GPS: ' + coords);
        })
        .catch(() => setLocationStatus('Đã đồng bộ tọa độ. Vui lòng nhập thủ công phần địa chỉ chi tiết.'));
}

if (window.L && document.getElementById('locationMap')) {
    locationMap = L.map('locationMap').setView(defaultLocation, 14);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(locationMap);

    locationMap.on('click', event => syncSelectedLocation(event.latlng.lat, event.latlng.lng, true));
    window.setTimeout(() => locationMap.invalidateSize(), 100);
}

document.getElementById('useCurrentLocation')?.addEventListener('click', () => {
    if (!navigator.geolocation) {
        Swal.fire('Lỗi', 'Trình duyệt của bạn không hỗ trợ định vị GPS', 'error');
        return;
    }
    setLocationStatus('Đang xác định vị trí của bạn...');
    navigator.geolocation.getCurrentPosition(position => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        if (locationMap) locationMap.setView([lat, lng], 17);
        syncSelectedLocation(lat, lng, true);
    }, () => {
        Swal.fire('Lỗi', 'Không thể lấy được vị trí GPS. Vui lòng cấp quyền vị trí cho trang web.', 'error');
        setLocationStatus('Không thể định vị.');
    });
});

document.getElementById('clearLocation')?.addEventListener('click', () => {
    document.getElementById('map_location').value = '';
    document.getElementById('map_lat').value = '';
    document.getElementById('map_lng').value = '';
    addressInput.value = '';
    setLocationStatus('Đã xóa dữ liệu vị trí.');
    if (locationMap && locationMarker) {
        locationMap.removeLayer(locationMarker);
        locationMarker = null;
    }
});

// Booking Submit Logic
$("#btnDatLich").on("click", function() {
    var ten = $("#customer_name").val().trim();
    var sdt = $("#phone").val().trim();
    var diachi = $("#address").val().trim();
    var dichvu = $("#service_type").val() + " - " + $("#selected_service_name").val();
    var yeucau = $("#issue_description").val().trim();
    var lat = $("#map_lat").val();
    var lng = $("#map_lng").val();
    var vat = $("#vat_request_booking").is(':checked') ? 1 : 0;
    
    if (!$("#service_type").val()) {
        Swal.fire('Thiếu thông tin', 'Vui lòng chọn một dịch vụ!', 'warning');
        return;
    }
    
    if (!ten || !sdt || !diachi || !yeucau) {
        Swal.fire('Thiếu thông tin', 'Vui lòng điền đầy đủ Tên, SĐT, Địa chỉ và Mô tả!', 'warning');
        return;
    }
    
    let originalText = $(this).html();
    $(this).html('<i class="fa-solid fa-circle-notch fa-spin"></i> ĐANG GỬI...').prop('disabled', true).css('opacity','0.7');
    
    if (lat && lng) {
        diachi += " | GPS: " + lat + "," + lng + " (https://maps.google.com/?q=" + lat + "," + lng + ")";
    }
    
    $.ajax({
        url: "/controller/client/DatLich.php",
        method: "POST",
        data: { type:'DatLich', ten:ten, sdt:sdt, dichvu:dichvu, diachi:diachi, yeucau:yeucau, vat_requested:vat },
        success: function(r) {
            $("#thongbao_datlich").html(r);
            $('#btnDatLich').html('<i class="fa-solid fa-check"></i> Đã Gửi Thành Công').prop('disabled', false).css('opacity','1');
            
            // Clear form after 2 seconds
            setTimeout(() => {
                $('#bookingForm')[0].reset();
                $('#btnDatLich').html(originalText);
                if (locationMap && locationMarker) {
                    locationMap.removeLayer(locationMarker);
                    locationMarker = null;
                }
            }, 3000);
        },
        error: function() {
            Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ. Vui lòng gọi trực tiếp!', 'error');
            $('#btnDatLich').html(originalText).prop('disabled', false).css('opacity','1');
        }
    });
});
// Form Đặt Lịch
</script>

<div style="text-align: center; margin: 40px 0 20px; position: relative; z-index: 5;">
    <a href="/login.php" class="btn dark" style="padding: 10px 24px; font-size: 14px; border: 1px solid rgba(15,23,42,0.1);"><i class="fa-solid fa-user-gear"></i> Khu Vực Dành Cho Thợ (Portal)</a>
</div>

<!-- ============================================== -->
<!-- HỒ SƠ PHÁP LÝ DÀNH CHO BỘ CÔNG THƯƠNG (BCT)     -->
<!-- ============================================== -->
<section id="bct-policies" style="background: #0f172a; padding: 80px 20px; border-top: 1px solid rgba(255,255,255,0.05); font-family: 'Inter', sans-serif;">
    <div style="max-width: 1200px; margin: 0 auto;">
        
        <div style="text-align: center; margin-bottom: 50px;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: rgba(56, 189, 248, 0.1); border-radius: 50%; color: #38bdf8; font-size: 28px; margin-bottom: 20px;">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
            <h2 style="font-size: 36px; font-weight: 900; color: #f8fafc; letter-spacing: -1px; margin: 0 0 10px 0;"><?php require_once(__DIR__."/pages/client/Footer.php"); ?>
