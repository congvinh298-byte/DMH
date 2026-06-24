<?php
/**
 * Layout chung cho cac trang phap ly - dienmayhieu.com
 */
if (!isset($PAGE_TITLE)) $PAGE_TITLE = 'Cho Xa Lap Vo Online';
if (!isset($PAGE_DESC)) $PAGE_DESC = 'Cho Xa Lap Vo Online - Cho so the he moi';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($PAGE_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars($PAGE_DESC) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg:#f6f7f9; --panel:#fff; --line:#e5e7eb; --text:#111827; --muted:#667085; --brand:#dc2626; --dark:#111827; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fef2f2; color: var(--text); line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        .wrap { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }
        header { position: sticky; inset-block-start: 0; z-index: 10; background: #fff; border-block-end: 1px solid var(--line); }
        .head { min-height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 10px 0; }
        .logo { font-size: 20px; font-weight: 900; color: var(--brand); }
        .logo small { display: block; color: var(--muted); font-size: 12px; }
        .btn-page { display: inline-block; border: 0; border-radius: 8px; background: var(--brand); color: #fff; font-weight: 800; padding: 10px 16px; cursor: pointer; text-align: center; }
        .btn-page:hover { background: #b91c1c; color: #fff; }
        main { padding: 32px 0 48px; }
        .page-panel { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 10px 24px rgba(15,23,42,.06); padding: 32px; }
        .page-title { font-size: 28px; font-weight: 900; margin-block-end: 8px; color: var(--dark); }
        .page-subtitle { color: var(--muted); font-size: 15px; margin-block-end: 24px; }
        .page-content h2 { font-size: 20px; font-weight: 800; margin-block-start: 28px; margin-block-end: 12px; color: var(--dark); border-block-end: 2px solid var(--brand); padding-block-end: 8px; display: inline-block; }
        .page-content h3 { font-size: 17px; font-weight: 800; margin-block-start: 20px; margin-block-end: 10px; color: #374151; }
        .page-content p { margin-block-end: 12px; text-align: justify; }
        .page-content ul, .page-content ol { margin-block-end: 16px; padding-inline-start: 24px; }
        .page-content li { margin-block-end: 8px; }
        .page-content table { width: 100%; border-collapse: collapse; margin-block-end: 16px; }
        .page-content th, .page-content td { border: 1px solid var(--line); padding: 10px 12px; text-align: left; }
        .page-content th { background: #f8fafc; font-weight: 800; }
        .breadcrumb { font-size: 13px; color: var(--muted); margin-block-end: 16px; }
        .breadcrumb a { color: var(--brand); }
        .breadcrumb a:hover { text-decoration: underline; }
        footer { background: #111827; color: #d1d5db; padding: 24px 0; font-size: 13px; margin-block-start: auto; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 18px; }
        .footer-grid h3 { margin: 0 0 8px; color: #fff; font-size: 15px; }
        .footer-grid p { margin: 4px 0; }
        .footer-grid a { color: #d1d5db; }
        .footer-grid a:hover { color: #fff; }
        .footer-bottom { border-block-start: 1px solid #374151; margin-block-start: 18px; padding-block-start: 12px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .back-home { display: inline-flex; align-items: center; gap: 6px; margin-block-end: 16px; }
        @media(max-width:900px){ .footer-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width:620px){ .head { flex-wrap: wrap; } .page-panel { padding: 20px; } .footer-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <div class="wrap head">
        <a class="logo" href="/" style="display:flex; align-items:center; gap: 8px;">
            <img src="/LOGO.png" alt="Logo" style="height: 40px; border-radius: 6px; object-fit: contain;">
            <div>Cho Xa Lap Vo Online<small>Cho so the he moi</small></div>
        </a>
        <a href="/" class="btn-page" style="font-size: 14px;">Ve trang chu</a>
    </div>
</header>
<main>
    <div class="wrap">
        <div class="breadcrumb">
            <a href="/">Trang chu</a> / <?= htmlspecialchars($PAGE_TITLE) ?>
        </div>
        <div class="page-panel">
            <h1 class="page-title"><?= htmlspecialchars($PAGE_TITLE) ?></h1>
            <p class="page-subtitle"><?= htmlspecialchars($PAGE_DESC) ?></p>
            <div class="page-content">