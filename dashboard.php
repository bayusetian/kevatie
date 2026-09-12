<?php

define('BASE_URL', '');

require_once 'config/database.php';
require_once 'includes/auth.php';

$currentPage = 'dashboard';
$pageTitle   = 'Dashboard';

requireLogin();
if (isGudang()) {
    header('Location:' . BASE_URL . '/produk.php');
    exit();
}
$db = getDB();

$totalSku   = $db->query("SELECT COUNT(*) c FROM produk")->fetch_assoc()['c'];
$totalStok  = $db->query("SELECT IFNULL(SUM(stok),0) s FROM produk")->fetch_assoc()['s'];
$skuMenipis = $db->query("SELECT COUNT(*) c FROM produk WHERE stok>0 AND stok<stok_minimum")->fetch_assoc()['c'];
$skuHabis   = $db->query("SELECT COUNT(*) c FROM produk WHERE stok=0")->fetch_assoc()['c'];

$modelData = $db->query("
    SELECT model, SUM(stok) total
    FROM produk
    GROUP BY model
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

$warnaData = $db->query("
    SELECT warna, SUM(stok) total
    FROM produk
    GROUP BY warna
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// Tren terlaris bulan ini (warna & model), berdasarkan barang keluar
$trenWarnaDash = $db->query("
    SELECT p.warna, SUM(t.jumlah) total
    FROM transaksi t JOIN produk p ON t.produk_id = p.id
    WHERE t.tipe = 'keluar'
    AND MONTH(t.tanggal) = MONTH(CURDATE()) AND YEAR(t.tanggal) = YEAR(CURDATE())
    GROUP BY p.warna ORDER BY total DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$trenModelDash = $db->query("
    SELECT p.model, SUM(t.jumlah) total
    FROM transaksi t JOIN produk p ON t.produk_id = p.id
    WHERE t.tipe = 'keluar'
    AND MONTH(t.tanggal) = MONTH(CURDATE()) AND YEAR(t.tanggal) = YEAR(CURDATE())
    GROUP BY p.model ORDER BY total DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$wh = [
    'Hitam' => '#1C1C1C', 'Navy'  => '#1E2D4E', 'Cream' => '#D8C9A8',
    'Army'  => '#4A5733', 'Sage'  => '#7A9A82', 'Merah' => '#B83232', 'Pink'  => '#D4728A'
];

require_once 'includes/header.php';
?>

<!-- ── Summary Cards ── -->
<div class="metrics g4">
    <div class="metric">
        <div class="ml">Total SKU</div>
        <div class="mv"><?= $totalSku ?></div>
        <div class="ms">6 model · <?= $totalSku ?> varian</div>
    </div>
    <div class="metric ok">
        <div class="ml">Total Stok</div>
        <div class="mv"><?= number_format($totalStok) ?></div>
        <div class="ms">Unit tersedia</div>
    </div>
    <div class="metric warn">
        <div class="ml">Stok Menipis</div>
        <div class="mv"><?= $skuMenipis ?></div>
        <div class="ms">Di bawah minimum</div>
    </div>
    <div class="metric danger">
        <div class="ml">Stok Habis</div>
        <div class="mv"><?= $skuHabis ?></div>
        <div class="ms">Restock segera</div>
    </div>
</div>

<!-- ── Tren Terlaris Bulan Ini ── -->
<div class="two-col" style="margin-top:16px">
    <div class="card">
        <div class="card-head"><h2>Warna Terlaris Bulan Ini</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Warna</th><th>Terjual</th></tr></thead>
                <tbody>
                <?php if (!empty($trenWarnaDash)): ?>
                    <?php foreach ($trenWarnaDash as $tw): ?>
                        <tr>
                            <td>
                                <span class="wdot">
                                    <span style="background:<?= $wh[$tw['warna']] ?? '#ccc' ?>"></span>
                                    <?= htmlspecialchars($tw['warna']) ?>
                                </span>
                            </td>
                            <td style="font-weight:600"><?= number_format($tw['total']) ?> unit</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2"><div class="empty-state"><p>Belum ada barang keluar bulan ini.</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Model Terlaris Bulan Ini</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Model</th><th>Terjual</th></tr></thead>
                <tbody>
                <?php if (!empty($trenModelDash)): ?>
                    <?php foreach ($trenModelDash as $tm): ?>
                        <tr>
                            <td><?= htmlspecialchars($tm['model']) ?></td>
                            <td style="font-weight:600"><?= number_format($tm['total']) ?> unit</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2"><div class="empty-state"><p>Belum ada barang keluar bulan ini.</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Bar per Model + Bar per Warna ── -->
<div class="two-col" style="margin-top:16px">
    <div class="card">
        <div class="card-head"><h2>Distribusi Stok per Model</h2></div>
        <div style="padding:16px 18px 16px;">
            <canvas id="modelBar" height="160"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Distribusi Stok per Warna</h2></div>
        <div style="padding:0 18px;font-size:11px;color:var(--hint)">Klik salah satu warna untuk lihat detail produknya</div>
        <div style="padding:16px 18px 16px;">
            <canvas id="warnaBar" height="160"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var modelColors    = ['#8B5A2B','#C19A6B','#5C7A5C','#8FBF8F','#A67C5D','#D4B090'];
var warnaHexMap    = {
    'Hitam':'#3A3A3A','Navy':'#1E2D4E','Cream':'#C8A96A',
    'Army':'#4A5733','Sage':'#7A9A82','Merah':'#B83232','Pink':'#D4728A'
};
var baseFont = { family: 'DM Sans, sans-serif', size: 11 };

var modelLabels    = <?= json_encode(array_column($modelData, 'model')) ?>;
var modelValues    = <?= json_encode(array_map('intval', array_column($modelData, 'total'))) ?>;
var warnaLabels    = <?= json_encode(array_column($warnaData, 'warna')) ?>;
var warnaValues    = <?= json_encode(array_map('intval', array_column($warnaData, 'total'))) ?>;
var warnaBarColors = warnaLabels.map(function(w){ return warnaHexMap[w] || '#AAAAAA'; });

// Bar per Model
new Chart(document.getElementById('modelBar'), {
    type: 'bar',
    data: {
        labels: modelLabels,
        datasets: [{
            label: 'Jumlah Stok',
            data: modelValues,
            backgroundColor: modelColors,
            borderRadius: 4,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        plugins: { legend:{ display:false } },
        scales: {
            y: { beginAtZero:true, grid:{ color:'#EAE0CC' }, ticks:{ font:baseFont } },
            x: { grid:{ display:false }, ticks:{ font:baseFont, maxRotation:30 } }
        }
    }
});

// Bar per Warna
new Chart(document.getElementById('warnaBar'), {
    type: 'bar',
    data: {
        labels: warnaLabels,
        datasets: [{
            label: 'Jumlah Stok',
            data: warnaValues,
            backgroundColor: warnaBarColors,
            borderRadius: 4,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        onClick: function(evt, elements) {
            if (elements.length > 0) {
                var idx = elements[0].index;
                var warna = warnaLabels[idx];
                window.location.href = 'produk.php?warna=' + encodeURIComponent(warna);
            }
        },
        onHover: function(evt, elements) {
            evt.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        },
        plugins: { legend:{ display:false } },
        scales: {
            y: { beginAtZero:true, grid:{ color:'#EAE0CC' }, ticks:{ font:baseFont } },
            x: { grid:{ display:false }, ticks:{ font:baseFont } }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>