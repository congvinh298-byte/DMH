<?php
  if (!defined('IN_SITE')) die('The Request Not Found');
?>
<!DOCTYPE html>
<html lang="vi" dir="ltr" class="light">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Điện Máy Hiếu - Bán lẻ, Sửa chữa & In 3D Chuyên Nghiệp">
    
    <title><?=$title ?? 'Điện Máy Hiếu'?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="/public/assets/logo.png" type="image/png">
    <link rel="shortcut icon" href="/public/assets/logo.png" type="image/png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
    
    <!-- Core App Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- JS Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>
    <script src="/assets/js/toastr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- ADHD Mobile Style Overrides -->
    <style>
    /* Hide approval line */
    .approval-line { display: none !important; }
    
    /* Header gradient glow */
    header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #312e81 100%) !important;
        box-shadow: 0 4px 30px rgba(56, 189, 248, 0.25) !important;
    }
    header .wrap.head { padding: 10px 14px !important; gap: 10px !important; }
    header .logo img {
        height: 48px !important; width: 48px !important; border-radius: 12px !important;
        box-shadow: 0 0 15px rgba(56, 189, 248, 0.5) !important;
    }
    header .logo div { font-size: 16px !important; line-height: 1.2 !important; }
    header .logo small { font-size: 10px !important; opacity: 0.9 !important; }
    
    /* Search bar unified */
    .search {
        display: flex !important; align-items: center !important;
        background: rgba(255,255,255,0.12) !important;
        border: 2px solid rgba(56, 189, 248, 0.4) !important;
        border-radius: 999px !important; padding: 4px 4px 4px 16px !important;
        box-shadow: 0 4px 20px rgba(56,189,248,0.2) !important;
        transition: all 0.3s ease !important; max-width: 100% !important;
    }
    .search:focus-within {
        border-color: #38bdf8 !important; box-shadow: 0 0 25px rgba(56,189,248,0.45) !important;
        background: rgba(255,255,255,0.15) !important;
    }
    .search input { background: transparent !important; border: none !important; color: #fff !important; font-size: 14px !important; padding: 8px 0 !important; flex: 1 !important; }
    .search input::placeholder { color: rgba(255,255,255,0.6) !important; }
    .search button {
        background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%) !important;
        border: none !important; border-radius: 999px !important; padding: 8px 18px !important;
        font-weight: 700 !important; box-shadow: 0 4px 15px rgba(56,189,248,0.4) !important;
    }
    
    /* Mobile nav scroll + colorful */
    @media (max-width: 768px) {
        header .wrap.head { flex-wrap: wrap !important; }
        header .wrap.head > div:last-child {
            width: 100% !important; justify-content: space-between !important; flex-wrap: nowrap !important;
            overflow-x: auto !important; gap: 8px !important; padding: 6px 0 2px !important;
            -webkit-overflow-scrolling: touch !important; scrollbar-width: none !important;
        }
        header .wrap.head > div:last-child::-webkit-scrollbar { display: none !important; }
        header .btn.dark, header a.btn.dark, header .btn, header a.btn {
            flex: 0 0 auto !important; min-width: 88px !important; padding: 8px 12px !important;
            font-size: 12px !important; border-radius: 12px !important; white-space: nowrap !important;
            text-align: center !important; font-weight: 700 !important; border: none !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25) !important; transition: transform 0.15s ease !important;
        }
        header .btn.dark:nth-of-type(1), header a.btn.dark:nth-of-type(1) { background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%) !important; }
        header .btn.dark:nth-of-type(2), header a.btn.dark:nth-of-type(2) { background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%) !important; }
        header .btn.dark:nth-of-type(3), header a.btn.dark:nth-of-type(3) { background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%) !important; }
        header .btn.dark:nth-of-type(4), header a.btn.dark:nth-of-type(4) { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%) !important; }
        header .btn[href="/GioHang.php"] { background: linear-gradient(135deg, #f97316 0%, #fb923c 100%) !important; min-width: auto !important; padding: 8px 14px !important; }
        header .btn.dark:active, header a.btn.dark:active, header .btn:active { transform: scale(0.95) !important; }
    }
    
    /* Hero ADHD */
    .hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e40af 35%, #581c87 70%, #be185d 100%) !important;
        background-size: 200% 200% !important; animation: heroGradient 8s ease infinite !important;
        border-radius: 24px !important; margin: 16px !important; padding: 28px 18px !important;
        position: relative !important; overflow: hidden !important;
        box-shadow: 0 20px 50px rgba(124, 58, 237, 0.3) !important;
    }
    @keyframes heroGradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    .hero .blob {
        position: absolute !important; width: 300px !important; height: 300px !important;
        background: radial-gradient(circle, rgba(56,189,248,0.35) 0%, transparent 70%) !important;
        border-radius: 50% !important; top: -100px !important; right: -80px !important;
        animation: floatBlob 6s ease-in-out infinite !important;
    }
    @keyframes floatBlob { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(-20px,20px) scale(1.1); } }
    .hero h1 {
        font-size: clamp(28px, 7vw, 42px) !important; text-shadow: 0 0 30px rgba(56,189,248,0.5) !important;
        background: linear-gradient(90deg, #fff, #38bdf8, #c084fc, #fff) !important;
        background-size: 200% auto !important; -webkit-background-clip: text !important; background-clip: text !important;
        -webkit-text-fill-color: transparent !important; animation: shimmerText 4s linear infinite !important;
    }
    @keyframes shimmerText { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }
    .hero p { font-size: 14px !important; line-height: 1.5 !important; max-width: 100% !important; color: rgba(255,255,255,0.85) !important; }
    .hero-actions { display: flex !important; flex-direction: column !important; gap: 10px !important; margin-top: 18px !important; }
    .hero-actions .btn {
        width: 100% !important; padding: 14px 18px !important; border-radius: 14px !important;
        font-size: 15px !important; font-weight: 800 !important; justify-content: center !important;
        border: none !important; box-shadow: 0 8px 25px rgba(0,0,0,0.25) !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease !important;
    }
    .hero-actions .btn:active { transform: scale(0.97) !important; }
    .hero-actions .btn.light { background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%) !important; color: #fff !important; animation: pulseCTA 2.5s infinite !important; }
    .hero-actions .btn.accent { background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%) !important; color: #fff !important; }
    .hero-actions .btn.outline { background: rgba(255,255,255,0.15) !important; color: #fff !important; border: 2px solid rgba(255,255,255,0.3) !important; backdrop-filter: blur(8px) !important; }
    @keyframes pulseCTA { 0%,100% { box-shadow: 0 8px 25px rgba(249,115,22,0.4); } 50% { box-shadow: 0 8px 35px rgba(249,115,22,0.7); } }
    
    /* Product cards */
    .product {
        border-radius: 20px !important; border: 1px solid rgba(255,255,255,0.08) !important;
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%) !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important; transition: transform 0.2s ease, box-shadow 0.2s ease !important;
        overflow: hidden !important;
    }
    .product:active { transform: scale(0.98) translateY(-2px) !important; box-shadow: 0 15px 40px rgba(56,189,248,0.2) !important; }
    .product .img { aspect-ratio: 1 / 1 !important; background: #fff !important; display: flex !important; align-items: center !important; justify-content: center !important; }
    .product .img img { width: 100% !important; height: 100% !important; object-fit: contain !important; padding: 16px !important; }
    .product .cat { display: inline-block !important; padding: 4px 10px !important; border-radius: 999px !important; font-size: 10px !important; font-weight: 800 !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%) !important; color: #0f172a !important; margin-bottom: 8px !important; }
    .product .price { font-size: 18px !important; font-weight: 900 !important; color: #fb7185 !important; text-shadow: 0 0 10px rgba(251,113,133,0.3) !important; }
    .product .body { padding: 14px !important; }
    .product .name { font-size: 14px !important; line-height: 1.4 !important; font-weight: 700 !important; color: #f1f5f9 !important; }
    
    /* Titles gradient */
    .section .title h2, .section h2, #bang-gia h2, #goi-tho h2 {
        font-size: clamp(22px, 6vw, 32px) !important;
        background: linear-gradient(90deg, #38bdf8, #c084fc, #fb7185) !important;
        -webkit-background-clip: text !important; background-clip: text !important; -webkit-text-fill-color: transparent !important;
        font-weight: 900 !important;
    }
    
    /* Form glow */
    #goi-tho form { background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%) !important; border: 1px solid rgba(56,189,248,0.2) !important; border-radius: 24px !important; box-shadow: 0 20px 50px rgba(0,0,0,0.3) !important; padding: 20px !important; }
    #goi-tho input, #goi-tho select, #goi-tho textarea { background: rgba(255,255,255,0.08) !important; border: 1px solid rgba(255,255,255,0.15) !important; border-radius: 12px !important; color: #fff !important; padding: 12px 14px !important; font-size: 14px !important; }
    #goi-tho input:focus, #goi-tho select:focus, #goi-tho textarea:focus { border-color: #38bdf8 !important; box-shadow: 0 0 15px rgba(56,189,248,0.25) !important; outline: none !important; }
    #goi-tho button[type="submit"], #goi-tho .btn { background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%) !important; border: none !important; border-radius: 14px !important; padding: 14px !important; font-size: 16px !important; font-weight: 800 !important; box-shadow: 0 8px 25px rgba(239,68,68,0.35) !important; animation: pulseCTA 2.5s infinite !important; }
    
    /* Floating buttons not overlap */
    @media (max-width: 768px) {
        .chat-widget, .zalo-chat-widget, .floating-buttons, #chat-widget, [class*="floating"], a[href*="zalo.me"] { bottom: 90px !important; right: 14px !important; transform: scale(0.9) !important; }
        a[href*="zalo.me"] { bottom: 150px !important; }
        .wrap { padding: 0 14px !important; }
        .storefront { padding: 10px 0 !important; }
        .section { padding: 24px 14px !important; }
        .grid { grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; }
        .hero { margin: 12px 0 !important; border-radius: 20px !important; }
        #bang-gia > div > div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; padding: 0 !important; }
        .footer-grid { grid-template-columns: 1fr 1fr !important; gap: 18px !important; }
        .footer-bottom { flex-direction: column !important; gap: 6px !important; text-align: center !important; font-size: 12px !important; }
    }
    @media (max-width: 420px) {
        .grid { grid-template-columns: 1fr !important; }
        .footer-grid { grid-template-columns: 1fr !important; }
        header .logo div { font-size: 14px !important; }
        header .logo small { font-size: 9px !important; }
    }
    
    /* Footer */
    footer { background: linear-gradient(180deg, #0b1120 0%, #020617 100%) !important; border-top: 1px solid rgba(56,189,248,0.15) !important; box-shadow: 0 -10px 40px rgba(0,0,0,0.4) !important; }
    footer h3 { color: #38bdf8 !important; font-size: 14px !important; font-weight: 800 !important; margin-bottom: 12px !important; }
    footer a { color: #94a3b8 !important; transition: color 0.2s ease !important; }
    footer a:hover { color: #38bdf8 !important; }
    footer img { border-radius: 12px !important; box-shadow: 0 0 20px rgba(56,189,248,0.2) !important; }
    
    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #0f172a; }
    ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #38bdf8, #818cf8); border-radius: 3px; }
    </style>
    
</head>