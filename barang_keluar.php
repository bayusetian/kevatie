<?php

define('BASE_URL', '');

require_once 'config/database.php';
require_once 'includes/auth.php';

$currentPage = 'keluar';
$pageTitle   = 'Pencatatan Stok Barang Keluar';

requireLogin();

$db  = getDB();

if (isset($_GET['cetak'])) {

    $cetakId = (int) $_GET['cetak'];

    $lb = $db->prepare("
        SELECT t.*, p.model, p.warna, p.ukuran, p.kode_sku,
               pl.nama_pelanggan, pl.no_telepon, pl.alamat_pengiriman
        FROM transaksi t
        JOIN produk p ON t.produk_id = p.id
        LEFT JOIN pelanggan pl ON t.pelanggan_id = pl.id_pelanggan
        WHERE t.id = ? AND t.tipe = 'keluar'
    ");
    $lb->bind_param('i', $cetakId);
    $lb->execute();
    $label = $lb->get_result()->fetch_assoc();

    if (!$label) {
        die('Data pengiriman tidak ditemukan.');
    }

    $uklLabel = $label['ukuran'] !== 'All Size' ? ' ' . $label['ukuran'] : '';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8">
    <title>Label Pengiriman — <?= htmlspecialchars($label['kode_transaksi']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 24px; background: #f5f5f5; }
        .label { width: 78mm; min-height: 100mm; margin: 0 auto; background: #fff; border: 1px solid #3B2A15; padding: 4mm; font-size: 12px; display: flex; flex-direction: column; }
        .label-head { display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px dashed #3B2A15; padding-bottom: 8px; margin-bottom: 8px; }
        .brand { font-size: 16px; font-weight: 700; color: #3B2A15; }
        .platform { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #7A6A56; }
        .ref-bar { display: flex; justify-content: space-between; gap: 6px; background: #F5EFE3; border-radius: 4px; padding: 6px 8px; margin-bottom: 14px; }
        .ref-item { text-align: left; }
        .ref-item.right { text-align: right; }
        .ref-item .rl { font-size: 7px; text-transform: uppercase; letter-spacing: .5px; color: #A89880; }
        .ref-item .rv { font-family: monospace; font-size: 11px; font-weight: 700; color: #1C1C1C; }
        .field { margin-bottom: 12px; }
        .field-label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #A89880; }
        .field-value { font-size: 15px; font-weight: 700; color: #1C1C1C; margin-top: 1px; }
        .field-sub { font-size: 12px; color: #444; margin-top: 2px; line-height: 1.4; }
        .isi { border-top: 1px dashed #D9CBAF; border-bottom: 1px dashed #D9CBAF; margin-top: 4px; padding: 10px 0; font-size: 11px; color: #444; line-height: 1.5; }
        .spacer { flex: 1; }
        .footnote { text-align: center; font-size: 8px; color: #C2A882; margin-top: 8px; }
        .actions { text-align: center; margin-top: 16px; }
        .actions button { padding: 8px 20px; border: none; background: #3B2A15; color: #fff; border-radius: 6px; cursor: pointer; font-size: 13px; }
        @page { size: 78mm 100mm; margin: 0; }
        @media print { body { background: #fff; padding: 0; } .label { border: none; width: 78mm; min-height: 100mm; } .actions { display: none; } }
    </style>
    </head>
    <body>
        <div class="label">
            <div class="label-head">
                <div class="brand">Kevatie</div>
                <div class="platform"><?= htmlspecialchars($label['platform'] ?? '') ?></div>
            </div>

            <div class="ref-bar">
                <div class="ref-item">
                    <div class="rl">Kode Transaksi</div>
                    <div class="rv"><?= htmlspecialchars($label['kode_transaksi']) ?></div>
                </div>
                <?php if (!empty($label['nomor_pesanan'])): ?>
                    <div class="ref-item right">
                        <div class="rl">No. Pesanan</div>
                        <div class="rv"><?= htmlspecialchars($label['nomor_pesanan']) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="field">
                <div class="field-label">Penerima</div>
                <div class="field-value"><?= htmlspecialchars($label['nama_pelanggan'] ?? '—') ?></div>
                <div class="field-sub"><?= htmlspecialchars($label['no_telepon'] ?? '—') ?></div>
            </div>
            <div class="field">
                <div class="field-label">Alamat Tujuan</div>
                <div class="field-sub"><?= nl2br(htmlspecialchars($label['alamat_pengiriman'] ?? '—')) ?></div>
            </div>
            <div class="isi">
                <strong>Isi paket:</strong><br>
                <?= htmlspecialchars($label['kode_sku']) ?> —
                <?= htmlspecialchars($label['model']) ?>, <?= htmlspecialchars($label['warna']) ?><?= $uklLabel ?>
                × <?= (int) $label['jumlah'] ?> unit
            </div>

            <div class="spacer"></div>
        </div>
        <div class="actions">
            <button onclick="window.print()">Cetak Label</button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Ambil flash message dari session jika ada

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pid  = (int) $_POST['produk_id'];
    $jml  = (int) $_POST['jumlah'];
    $plat = $_POST['platform'];
    $tgl  = $_POST['tanggal'];
    $uid  = $_SESSION['user_id'];

    $namaPelanggan  = trim($_POST['nama_pelanggan'] ?? '');
    $teleponPlgn    = trim($_POST['no_telepon'] ?? '');
    $alamatAsal     = trim($_POST['alamat_asal'] ?? '');
    $alamatKirim    = trim($_POST['alamat_pengiriman'] ?? '');
    $nomorResi      = trim($_POST['nomor_pesanan'] ?? '');

    $stokSaat = $db->prepare("SELECT stok FROM produk WHERE id=?");
    $stokSaat->bind_param('i', $pid);
    $stokSaat->execute();

    $stokNow = $stokSaat->get_result()->fetch_assoc()['stok'] ?? 0;

    if ($jml > $stokNow) {

        $_SESSION['flash'] = [
            'type' => 'error',
            'text' => "Stok tidak cukup. Tersedia: $stokNow unit."
        ];

    } elseif ($pid && $jml > 0 && $plat && $namaPelanggan && $teleponPlgn && $alamatAsal && $alamatKirim) {

        $db->begin_transaction();

        try {

            // Simpan data pelanggan baru untuk setiap transaksi
            $cekJmlPlgn = $db->query("SELECT COUNT(*) AS jml FROM pelanggan");
            $urutPlgn = $cekJmlPlgn->fetch_assoc()['jml'] + 1;
            $kodePelanggan = 'PLG' . str_pad($urutPlgn, 4, '0', STR_PAD_LEFT);

            $insPlgn = $db->prepare("
                INSERT INTO pelanggan
                (kode_pelanggan, nama_pelanggan, no_telepon, alamat_asal, alamat_pengiriman)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insPlgn->bind_param('sssss', $kodePelanggan, $namaPelanggan, $teleponPlgn, $alamatAsal, $alamatKirim);
            $insPlgn->execute();
            $pelangganId = $insPlgn->insert_id;

            $tglKode = date('dmY', strtotime($tgl));
            $cekKode = $db->prepare("SELECT COUNT(*) AS jml FROM transaksi WHERE tanggal = ?");
            $cekKode->bind_param('s', $tgl);
            $cekKode->execute();
            $urutKe = $cekKode->get_result()->fetch_assoc()['jml'] + 1;
            $kodeTransaksi = $tglKode . str_pad($urutKe, 3, '0', STR_PAD_LEFT);

            $st = $db->prepare("
                INSERT INTO transaksi
                (kode_transaksi, tipe, produk_id, jumlah, platform, pelanggan_id, nomor_pesanan, user_id, tanggal)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $t = 'keluar';
            $pesananVal = $nomorResi !== '' ? $nomorResi : null;

            $st->bind_param('ssiisisis', $kodeTransaksi, $t, $pid, $jml, $plat, $pelangganId, $pesananVal, $uid, $tgl);

            $st->execute();

            $up = $db->prepare("
                UPDATE produk
                SET stok = stok - ?
                WHERE id = ?
            ");

            $up->bind_param('ii', $jml, $pid);
            $up->execute();

            $db->commit();

            $_SESSION['flash'] = [
                'type' => 'success',
                'text' => "Barang keluar $jml unit ke $plat berhasil dicatat."
            ];

        } catch (Exception $e) {

            $db->rollback();

            $_SESSION['flash'] = [
                'type' => 'error',
                'text' => $e->getMessage()
            ];
        }

    } else {

        $_SESSION['flash'] = [
            'type' => 'error',
            'text' => 'Harap isi semua field wajib, termasuk data pelanggan.'
        ];
    }

    header('Location: ' . BASE_URL . '/barang_keluar.php');
    exit();
}

$plist = $db->query("
    SELECT id, kode_sku, model, warna, ukuran, stok
    FROM produk
    WHERE stok > 0
    ORDER BY model, warna, ukuran
");

$riw = $db->query("
    SELECT t.*, p.model, p.warna, p.kode_sku, u.nama petugas,
           pl.nama_pelanggan, pl.no_telepon, pl.alamat_pengiriman
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    JOIN users u  ON t.user_id = u.id
    LEFT JOIN pelanggan pl ON t.pelanggan_id = pl.id_pelanggan
    WHERE t.tipe = 'keluar'
    AND MONTH(t.tanggal) = MONTH(CURDATE())
    AND YEAR(t.tanggal)  = YEAR(CURDATE())
    ORDER BY t.created_at DESC
");

$plats = ['Shopee', 'Tiktok Shop', 'Tokopedia'];

$wh = [
    'Hitam' => '#1C1C1C',
    'Navy'  => '#1E2D4E',
    'Cream' => '#D8C9A8',
    'Army'  => '#4A5733',
    'Sage'  => '#7A9A82',
    'Merah' => '#B83232',
    'Pink'  => '#D4728A'
];

require_once 'includes/header.php';
?>

<div class="card" style="margin-bottom:16px">

    <div class="card-head">
        <h2>Kolom Pencatatan Barang Keluar ke Marketplace</h2>
    </div>

    <form method="POST">

        <div class="form-grid">

            <div class="fg">
                <label>Tanggal *</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="fg">
                <label>Produk *</label>
                <select name="produk_id" required>
                    <option value="">— Pilih Produk —</option>

                    <?php while ($p = $plist->fetch_assoc()):
                        $ukl = $p['ukuran'] !== 'All Size' ? ' ' . $p['ukuran'] : '';
                    ?>

                        <option value="<?= $p['id'] ?>">
                            <?= $p['kode_sku'] ?> |
                            <?= $p['model'] ?> –
                            <?= $p['warna'] ?><?= $ukl ?>
                            (<?= $p['stok'] ?>)
                        </option>

                    <?php endwhile; ?>
                </select>
            </div>

            <div class="fg">
                <label>Jumlah (unit) *</label>
                <input type="number" name="jumlah" min="1" placeholder="0" required>
            </div>

            <div class="fg">
                <label>Platform Marketplace *</label>
                <select name="platform" required>
                    <option value="">— Pilih —</option>

                    <?php foreach ($plats as $pl): ?>
                        <option value="<?= $pl ?>"><?= $pl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fg">
                <label>Nama Pelanggan *</label>
                <input type="text" name="nama_pelanggan" placeholder="Nama pelanggan" required>
            </div>

            <div class="fg">
                <label>No. Telepon Pelanggan *</label>
                <input type="text" name="no_telepon" placeholder="08xxxxxxxxxx" required>
            </div>

            <div class="fg">
                <label>Alamat Pesanan *</label>
                <textarea name="alamat_asal" rows="2" placeholder="Alamat pelanggan" required></textarea>
            </div>

            <div class="fg">
                <label>Alamat Tujuan *</label>
                <textarea name="alamat_pengiriman" rows="2" placeholder="Alamat tujuan pengiriman (bisa berbeda dari alamat asal)" required></textarea>
            </div>

            <div class="fg">
                <label>Nomor Pesanan</label>
                <input type="text" name="nomor_pesanan" placeholder="">
            </div>

        </div>

        <div class="form-footer">
            <a href="produk.php" class="btn">Batal</a>
            <button type="submit" class="btn btn-p">Simpan</button>
        </div>

    </form>
</div>

<div class="card">

    <div class="card-head">
        <h2>Riwayat Barang Keluar — Bulan Ini</h2>
    </div>

    <div class="table-wrap">
        <table>

            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Produk</th>
                    <th>Warna</th>
                    <th>Qty</th>
                    <th>Platform</th>
                    <th>Pelanggan</th>
                    <th>No. Pesanan</th>
                    <th>Oleh</th>
                    <th>Label</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($riw->num_rows > 0): ?>
                <?php while ($r = $riw->fetch_assoc()): ?>

                    <tr>
                        <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>

                        <td><?= htmlspecialchars($r['model']) ?></td>

                        <td>
                            <span class="wdot">
                                <span style="background:<?= $wh[$r['warna']] ?? '#ccc' ?>"></span>
                                <?= $r['warna'] ?>
                            </span>
                        </td>

                        <td style="color:var(--danger)">
                            −<?= $r['jumlah'] ?>
                        </td>

                        <td>
                            <span class="badge b-info"><?= htmlspecialchars($r['platform']) ?></span>
                        </td>

                        <td>
                            <?= htmlspecialchars($r['nama_pelanggan'] ?? '—') ?>
                            <?php if (!empty($r['no_telepon'])): ?>
                                <div style="font-size:11px;color:var(--hint)"><?= htmlspecialchars($r['no_telepon']) ?></div>
                            <?php endif; ?>
                        </td>

                        <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['nomor_pesanan'] ?? '—') ?></td>

                        <td><?= htmlspecialchars($r['petugas']) ?></td>

                        <td>
                            <?php if ($r['pelanggan_id']): ?>
                                <a href="barang_keluar.php?cetak=<?= $r['id'] ?>" target="_blank" class="btn" style="font-size:11px;padding:5px 9px">Cetak</a>
                            <?php else: ?>
                                <span style="color:var(--hint);font-size:11px">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <p>Belum ada barang keluar bulan ini.</p>
                        </div>
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>