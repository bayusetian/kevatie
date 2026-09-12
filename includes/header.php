<?php
// includes/header.php

requireLogin();

$user = currentUser();
$db   = getDB();

$warna_hex = [
    'Hitam' => '#1C1C1C',
    'Navy'  => '#1E2D4E',
    'Cream' => '#D8C9A8',
    'Army'  => '#4A5733',
    'Sage'  => '#7A9A82',
    'Merah' => '#B83232',
    'Pink'  => '#D4728A',
];

if (isGudang()) {
    $menu = [
        ['label'=>'Lihat Data Produk', 'url'=>'produk.php',        'page'=>'produk', 'icon'=>'list', 'group'=>'Pencatatan Stok'],
        ['label'=>'Barang Masuk',      'url'=>'barang_masuk.php',  'page'=>'masuk',  'icon'=>'down', 'group'=>'Pencatatan Stok'],
        ['label'=>'Barang Keluar',     'url'=>'barang_keluar.php', 'page'=>'keluar', 'icon'=>'up',   'group'=>'Pencatatan Stok'],
        ['label'=>'Laporan Stok',       'url'=>'laporan.php',       'page'=>'laporan', 'icon'=>'report','group'=>'Pelaporan'],
    ];
} else {
    $menu = [
        ['label'=>'Dashboard',      'url'=>'dashboard.php',   'page'=>'dashboard','icon'=>'grid',  'group'=>''],
        ['label'=>'Data Produk',    'url'=>'produk.php',      'page'=>'produk',   'icon'=>'list',  'group'=>'Pengendalian Stok'],
        ['label'=>'Alert Stok',     'url'=>'alert_stok.php',  'page'=>'alert',    'icon'=>'alert', 'group'=>'Pengendalian Stok'],
        ['label'=>'Laporan Stok',   'url'=>'laporan.php',     'page'=>'laporan',  'icon'=>'report','group'=>'Pelaporan'],
    ];
}

$alertCount = $db->query("
    SELECT COUNT(*) c
    FROM produk
    WHERE stok <= stok_minimum
")->fetch_assoc()['c'];

$ic = [
    'grid'  => '<svg viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity=".4"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity=".4"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor"/></svg>',
    'list'  => '<svg viewBox="0 0 14 14" fill="none"><rect x="1.5" y="1.5" width="11" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M4 5h6M4 7h4M4 9h5" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/></svg>',
    'down'  => '<svg viewBox="0 0 14 14" fill="none"><path d="M7 2v8M3.5 7.5l3.5 3 3.5-3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M1.5 11.5h11" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/></svg>',
    'up'    => '<svg viewBox="0 0 14 14" fill="none"><path d="M7 12V4M3.5 6.5l3.5-3 3.5 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M1.5 11.5h11" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/></svg>',
    'box'   => '<svg viewBox="0 0 14 14" fill="none"><rect x="1.5" y="3.5" width="11" height="8" rx="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 3.5V2.5a1 1 0 011-1h3a1 1 0 011 1v1" stroke="currentColor" stroke-width="1.1"/></svg>',
    'alert' => '<svg viewBox="0 0 14 14" fill="none"><path d="M7 1.5L1 13h12L7 1.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M7 5v4M7 10.5v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>',
    'report'=> '<svg viewBox="0 0 14 14" fill="none"><path d="M2 11V4l5-2.5L12 4v7" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><rect x="5" y="7" width="4" height="4" rx=".5" stroke="currentColor" stroke-width="1"/></svg>',
    'clock' => '<svg viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M7 4.5V7.2l1.8 1.3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>',
];

$rl = [
    'owner'   => 'Owner',
    'gudang'  => ' Bagian Gudang',
];

$currentPage = $currentPage ?? '';
$pageTitle   = $pageTitle   ?? 'Kevatie';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Kevatie</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&family=Cormorant+Garamond:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="app">

<aside class="sidebar">

    <div class="brand">
        <div class="brand-name">Kevatie</div>
    </div>

    <div class="role-box">
        <div class="role-lbl">Login sebagai</div>
        <div class="role-name"><?= htmlspecialchars($user['nama'] ?? '') ?></div>
        <span class="role-badge"><?= $rl[$user['role']] ?? '' ?></span>
    </div>

    <nav>
        <?php
        $groups = array_unique(array_column($menu, 'group'));

        foreach ($groups as $group):
            $items = array_filter($menu, fn($m) => $m['group'] === $group);
        ?>

        <div class="ng">
            <span class="ng-lbl"><?= $group ?></span>

            <?php foreach ($items as $item): ?>
                <a href="<?= BASE_URL . '/' . $item['url'] ?>"
                   class="nav-item <?= $currentPage === $item['page'] ? 'on' : '' ?>">

                    <?= $ic[$item['icon']] ?>
                    <?= $item['label'] ?>

                    <?php if ($item['page'] === 'alert' && $alertCount > 0): ?>
                        <span class="nav-badge"><?= $alertCount ?></span>
                    <?php endif; ?>

                </a>
            <?php endforeach; ?>
        </div>

        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/logout.php" class="nav-item logout-btn">
            <svg viewBox="0 0 14 14" fill="none">
                <path d="M5 7h7M9 5l2 2-2 2" stroke="currentColor" stroke-width="1.2"/>
                <path d="M9 2H3a1 1 0 00-1 1v8a1 1 0 001 1h6" stroke="currentColor" stroke-width="1.2"/>
            </svg>
            Keluar
        </a>
    </div>

</aside>


<div class="main">

    <div class="topbar">

        <h1><?= htmlspecialchars($pageTitle) ?></h1>

        <div class="topbar-right">

            <span class="date-tag"><?= date('d M Y') ?></span>

            <?php if ($alertCount > 0 && !isGudang()): ?>
                <a href="<?= BASE_URL ?>/alert_stok.php" class="pill-danger">
                    ⚑ <?= $alertCount ?> stok perlu perhatian
                </a>
            <?php endif; ?>

            <div class="avatar">
                <?= strtoupper(substr($user['nama'] ?? 'U', 0, 2)) ?>
            </div>

        </div>

    </div>

    <div class="content">