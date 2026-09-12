<?php
define('BASE_URL', '');
require_once 'config/database.php';
require_once 'includes/auth.php';

$currentPage = 'laporan';
$pageTitle   = 'Laporan Stok';

requireLogin();
$db = getDB();

$mode   = $_GET['mode']  ?? 'bulan';
$fBulan = $_GET['bulan'] ?? date('Y-m');
$fAwal  = $_GET['tgl_awal']  ?? date('Y-m-d');
$fAkhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$fModel = $_GET['model'] ?? '';
$fTipe  = $_GET['tipe']  ?? '';

if ($mode === 'harian') {
    $where  = "t.tanggal BETWEEN ? AND ?";
    $params = [$fAwal, $fAkhir];
    $types  = 'ss';
    $labelPeriode = date('d/m/Y', strtotime($fAwal)) . ' s.d. ' . date('d/m/Y', strtotime($fAkhir));
} else {
    $where  = "DATE_FORMAT(t.tanggal, '%Y-%m') = ?";
    $params = [$fBulan];
    $types  = 's';
    $labelPeriode = date('F Y', strtotime($fBulan . '-01'));
}

if ($fModel) { $where .= ' AND p.model = ?'; $params[] = $fModel; $types .= 's'; }
if ($fTipe)  { $where .= ' AND t.tipe = ?';  $params[] = $fTipe;  $types .= 's'; }

$ringkasan = $db->prepare("
    SELECT
        SUM(CASE WHEN t.tipe='masuk'  THEN t.jumlah ELSE 0 END) AS total_masuk,
        SUM(CASE WHEN t.tipe='keluar' THEN t.jumlah ELSE 0 END) AS total_keluar,
        COUNT(*) AS total_transaksi
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    WHERE $where
");
$ringkasan->bind_param($types, ...$params);
$ringkasan->execute();
$r = $ringkasan->get_result()->fetch_assoc();

$totalMasuk     = (int)($r['total_masuk']     ?? 0);
$totalKeluar    = (int)($r['total_keluar']    ?? 0);
$totalTransaksi = (int)($r['total_transaksi'] ?? 0);
$stokAkhir      = (int)$db->query("SELECT IFNULL(SUM(stok),0) s FROM produk")->fetch_assoc()['s'];

$st = $db->prepare("
    SELECT t.*, p.model, p.warna, p.kode_sku, p.ukuran, u.nama AS petugas,
           pl.nama_pelanggan
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    JOIN users  u ON t.user_id   = u.id
    LEFT JOIN pelanggan pl ON t.pelanggan_id = pl.id_pelanggan
    WHERE $where
    ORDER BY t.tanggal DESC, t.created_at DESC
");
$st->bind_param($types, ...$params);
$st->execute();
$detailRows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Tren Terlaris (warna & model paling banyak keluar di periode ini) ──
// Pakai filter tanggal yang sama, tapi lepas dari filter model/tipe
// supaya tren tetap membandingkan semua model & warna sekaligus.
if ($mode === 'harian') {
    $whereTren  = "t.tanggal BETWEEN ? AND ? AND t.tipe = 'keluar'";
    $paramsTren = [$fAwal, $fAkhir];
    $typesTren  = 'ss';
} else {
    $whereTren  = "DATE_FORMAT(t.tanggal, '%Y-%m') = ? AND t.tipe = 'keluar'";
    $paramsTren = [$fBulan];
    $typesTren  = 's';
}

$trWarna = $db->prepare("
    SELECT p.warna, SUM(t.jumlah) total
    FROM transaksi t JOIN produk p ON t.produk_id = p.id
    WHERE $whereTren
    GROUP BY p.warna ORDER BY total DESC LIMIT 5
");
$trWarna->bind_param($typesTren, ...$paramsTren);
$trWarna->execute();
$trenWarna = $trWarna->get_result()->fetch_all(MYSQLI_ASSOC);

$trModel = $db->prepare("
    SELECT p.model, SUM(t.jumlah) total
    FROM transaksi t JOIN produk p ON t.produk_id = p.id
    WHERE $whereTren
    GROUP BY p.model ORDER BY total DESC LIMIT 5
");
$trModel->bind_param($typesTren, ...$paramsTren);
$trModel->execute();
$trenModel = $trModel->get_result()->fetch_all(MYSQLI_ASSOC);

$models = ['Prime Series','Luminous','Backpack','Nova Series','Ponco','Prime Kids'];

$wh = [
    'Hitam'=>'#1C1C1C','Navy'=>'#1E2D4E','Cream'=>'#D8C9A8',
    'Army'=>'#4A5733','Sage'=>'#7A9A82','Merah'=>'#B83232','Pink'=>'#D4728A'
];

require_once 'includes/header.php';
?>

<?php if (!isGudang()): ?>
<!-- AREA CETAK PDF — hanya untuk owner -->
<div id="area-cetak">
    <div style="font-family:Arial,sans-serif;font-size:12px;color:#3B2A15;padding:20px">
        <table style="width:100%;border-bottom:2px solid #D9CBAF;padding-bottom:10px;margin-bottom:16px">
            <tr>
                <td>
                    <div style="font-size:20px;font-weight:700;color:#3B2A15">Kevatie</div>
                </td>
                <td style="text-align:right">
                    <div style="font-size:14px;font-weight:700">LAPORAN STOK</div>
                    <div style="font-size:11px;color:#7A6A56;margin-top:2px">Periode: <?= htmlspecialchars($labelPeriode) ?></div>
                    <div style="font-size:10px;color:#A89880;margin-top:2px">Dicetak: <?= date('d/m/Y H:i') ?></div>
                </td>
            </tr>
        </table>

        <table style="width:100%;margin-bottom:16px;border-collapse:separate;border-spacing:8px">
            <tr>
                <td style="border:1px solid #D9CBAF;border-radius:6px;padding:10px;text-align:center;width:25%">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:.6px;color:#A89880">Total Masuk</div>
                    <div style="font-size:20px;font-weight:700;color:#3A5E44"><?= number_format($totalMasuk) ?></div>
                    <div style="font-size:10px;color:#A89880">unit</div>
                </td>
                <td style="border:1px solid #D9CBAF;border-radius:6px;padding:10px;text-align:center;width:25%">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:.6px;color:#A89880">Total Keluar</div>
                    <div style="font-size:20px;font-weight:700;color:#8B3A3A"><?= number_format($totalKeluar) ?></div>
                    <div style="font-size:10px;color:#A89880">unit</div>
                </td>
                <td style="border:1px solid #D9CBAF;border-radius:6px;padding:10px;text-align:center;width:25%">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:.6px;color:#A89880">Total Transaksi</div>
                    <div style="font-size:20px;font-weight:700;color:#3B2A15"><?= number_format($totalTransaksi) ?></div>
                    <div style="font-size:10px;color:#A89880">transaksi</div>
                </td>
                <td style="border:1px solid #D9CBAF;border-radius:6px;padding:10px;text-align:center;width:25%">
                    <div style="font-size:9px;text-transform:uppercase;letter-spacing:.6px;color:#A89880">Stok Akhir</div>
                    <div style="font-size:20px;font-weight:700;color:#3B2A15"><?= number_format($stokAkhir) ?></div>
                    <div style="font-size:10px;color:#A89880">unit</div>
                </td>
            </tr>
        </table>

        <table style="width:100%;border-collapse:collapse;font-size:11px">
            <thead>
                <tr style="background:#F5EFE3">
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:left">Tanggal</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:left">Kode Produk</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:left">Model</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:left">Warna</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:center">Ukuran</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:center">Tipe</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:center">Qty</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:left">Platform</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:left">Pelanggan</th>
                    <th style="padding:7px 10px;border:1px solid #D9CBAF;text-align:left">Petugas</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($detailRows)):
                foreach ($detailRows as $i => $row):
                    $bg = $i % 2 === 0 ? '#FDFAF5' : '#F5EFE3';
            ?>
                <tr style="background:<?= $bg ?>">
                    <td style="padding:6px 10px;border:1px solid #EAE0CC"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC;font-family:monospace;font-size:10px"><?= htmlspecialchars($row['kode_sku']) ?></td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC"><?= htmlspecialchars($row['model']) ?></td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC"><?= htmlspecialchars($row['warna']) ?></td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC;text-align:center"><?= $row['ukuran'] ?></td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC;text-align:center;font-weight:700;color:<?= $row['tipe']==='masuk' ? '#3A5E44' : '#8B3A3A' ?>">
                        <?= $row['tipe'] === 'masuk' ? 'Masuk' : 'Keluar' ?>
                    </td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC;text-align:center;font-weight:700;color:<?= $row['tipe']==='masuk' ? '#3A5E44' : '#8B3A3A' ?>">
                        <?= ($row['tipe']==='masuk' ? '+' : '−') . $row['jumlah'] ?>
                    </td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC"><?= htmlspecialchars($row['platform'] ?? '—') ?></td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC"><?= htmlspecialchars($row['nama_pelanggan'] ?? '—') ?></td>
                    <td style="padding:6px 10px;border:1px solid #EAE0CC"><?= htmlspecialchars($row['petugas']) ?></td>
                </tr>
            <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="10" style="padding:16px;text-align:center;color:#A89880">Tidak ada transaksi pada periode ini.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- END AREA CETAK -->
<?php endif; ?>


<!-- Filter Card — tombol Cetak PDF cuma tampil buat owner, gudang cuma lihat -->
<div class="card no-print" style="margin-bottom:16px">

    <div class="card-head">
        <h2>Filter Laporan</h2>
        <?php if (!isGudang()): ?>
            <button class="btn btn-p" onclick="window.print()" style="display:flex;align-items:center;gap:6px;font-size:13px">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M3 1h8v4H3z" fill="none" stroke="currentColor" stroke-width="1.2"/>
                    <rect x="1" y="5" width="12" height="6" rx="1.2" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M3 8.5h8M3 11h8" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>
                </svg>
                Cetak PDF
            </button>
        <?php else: ?>
            <span class="badge b-n" style="font-size:11px">Tampilan lihat saja</span>
        <?php endif; ?>
    </div>

    <div style="padding:14px 18px 0">
        <div class="tabs">
            <a href="?mode=bulan&bulan=<?= $fBulan ?>&model=<?= urlencode($fModel) ?>&tipe=<?= $fTipe ?>"
               class="tab <?= $mode==='bulan' ? 'on' : '' ?>">Per Bulan</a>
            <a href="?mode=harian&tgl_awal=<?= $fAwal ?>&tgl_akhir=<?= $fAkhir ?>&model=<?= urlencode($fModel) ?>&tipe=<?= $fTipe ?>"
               class="tab <?= $mode==='harian' ? 'on' : '' ?>">Per Hari</a>
        </div>
    </div>

    <form method="GET" style="padding:0 18px 18px">
        <input type="hidden" name="mode" value="<?= $mode ?>">
        <div class="form-grid">

            <?php if ($mode === 'harian'): ?>
                <div class="fg">
                    <label>Tanggal Awal</label>
                    <input type="date" name="tgl_awal" value="<?= htmlspecialchars($fAwal) ?>">
                </div>
                <div class="fg">
                    <label>Tanggal Akhir</label>
                    <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($fAkhir) ?>">
                </div>
            <?php else: ?>
                <div class="fg">
                    <label>Periode (Bulan)</label>
                    <input type="month" name="bulan" value="<?= htmlspecialchars($fBulan) ?>">
                </div>
            <?php endif; ?>

            <div class="fg">
                <label>Model</label>
                <select name="model">
                    <option value="">Semua model</option>
                    <?php foreach ($models as $m): ?>
                        <option value="<?= $m ?>" <?= $fModel===$m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fg">
                <label>Tipe Transaksi</label>
                <select name="tipe">
                    <option value="">Semua</option>
                    <option value="masuk"  <?= $fTipe==='masuk'  ? 'selected' : '' ?>>Barang Masuk</option>
                    <option value="keluar" <?= $fTipe==='keluar' ? 'selected' : '' ?>>Barang Keluar</option>
                </select>
            </div>

        </div>
        <div class="form-footer">
            <a href="laporan.php" class="btn">Reset</a>
            <button type="submit" class="btn btn-p">Terapkan Filter</button>
        </div>
    </form>
</div>


<!-- Ringkasan -->
<div class="metrics g4 no-print" style="margin-bottom:16px">
    <div class="metric ok">
        <div class="ml">Total Masuk</div>
        <div class="mv"><?= number_format($totalMasuk) ?></div>
        <div class="ms">Unit diterima</div>
    </div>
    <div class="metric danger">
        <div class="ml">Total Keluar</div>
        <div class="mv"><?= number_format($totalKeluar) ?></div>
        <div class="ms">Unit terjual</div>
    </div>
    <div class="metric no-print">
        <div class="ml">Transaksi</div>
        <div class="mv"><?= number_format($totalTransaksi) ?></div>
        <div class="ms">Periode ini</div>
    </div>
    <div class="metric no-print">
        <div class="ml">Stok Akhir</div>
        <div class="mv"><?= number_format($stokAkhir) ?></div>
        <div class="ms">Unit tersedia</div>
    </div>
</div>


<!-- Tren Terlaris -->
<div class="two-col no-print" style="margin-bottom:16px">
    <div class="card">
        <div class="card-head"><h2>Tren Warna Terlaris</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Warna</th><th>Terjual</th></tr></thead>
                <tbody>
                <?php if (!empty($trenWarna)): ?>
                    <?php foreach ($trenWarna as $tw): ?>
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
                    <tr><td colspan="2"><div class="empty-state"><p>Belum ada barang keluar pada periode ini.</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Tren Model Terlaris</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Model</th><th>Terjual</th></tr></thead>
                <tbody>
                <?php if (!empty($trenModel)): ?>
                    <?php foreach ($trenModel as $tm): ?>
                        <tr>
                            <td><?= htmlspecialchars($tm['model']) ?></td>
                            <td style="font-weight:600"><?= number_format($tm['total']) ?> unit</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2"><div class="empty-state"><p>Belum ada barang keluar pada periode ini.</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tabel Detail -->
<div class="card no-print">
    <div class="card-head">
        <h2>Detail Aktivitas Barang Masuk &amp; Keluar</h2>
        <span style="font-size:12px;color:var(--hint)"><?= count($detailRows) ?> transaksi</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kode Produk</th>
                    <th>Model</th>
                    <th>Warna</th>
                    <th>Ukuran</th>
                    <th>Tipe</th>
                    <th>Qty</th>
                    <th>Platform</th>
                    <th>Pelanggan</th>
                    <th>Oleh</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($detailRows)):
                foreach ($detailRows as $row):
            ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td class="td-sku"><?= htmlspecialchars($row['kode_sku']) ?></td>
                    <td><?= htmlspecialchars($row['model']) ?></td>
                    <td>
                        <span class="wdot">
                            <span style="background:<?= $wh[$row['warna']] ?? '#ccc' ?>"></span>
                            <?= htmlspecialchars($row['warna']) ?>
                        </span>
                    </td>
                    <td><span class="badge b-n"><?= $row['ukuran'] ?></span></td>
                    <td>
                        <?php if ($row['tipe'] === 'masuk'): ?>
                            <span class="badge b-ok">Masuk</span>
                        <?php else: ?>
                            <span class="badge b-danger">Keluar</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:<?= $row['tipe']==='masuk' ? 'var(--ok)' : 'var(--danger)' ?>;font-weight:600">
                        <?= ($row['tipe']==='masuk' ? '+' : '−') . $row['jumlah'] ?>
                    </td>
                    <td>
                        <?php if ($row['platform']): ?>
                            <span class="badge b-info"><?= htmlspecialchars($row['platform']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--hint)">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row['nama_pelanggan'])): ?>
                            <?= htmlspecialchars($row['nama_pelanggan']) ?>
                        <?php else: ?>
                            <span style="color:var(--hint)">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($row['petugas']) ?></td>
                </tr>
            <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <p>Tidak ada transaksi pada periode ini.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<style>
#area-cetak { display: none; }

@media print {
    @page { size: A4 landscape; margin: 10mm; }
    .sidebar, .topbar, .no-print { display: none !important; }
    .main    { margin-left: 0 !important; }
    .content { padding: 0 !important; }
    #area-cetak { display: block !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>