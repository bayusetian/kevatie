<?php
// barang_masuk.php

define('BASE_URL', '');

require_once 'config/database.php';
require_once 'includes/auth.php';

$currentPage = 'masuk';
$pageTitle   = 'Pencatatan Stok Barang Masuk';

requireLogin();

$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pid = (int) $_POST['produk_id'];
    $jml = (int) $_POST['jumlah'];
    $tgl = $_POST['tanggal'];
    $uid = $_SESSION['user_id'];

    if ($pid && $jml > 0) {

        $db->begin_transaction();

        try {

            $tglKode = date('dmY', strtotime($tgl));
            $cekKode = $db->prepare("SELECT COUNT(*) AS jml FROM transaksi WHERE tanggal = ?");
            $cekKode->bind_param('s', $tgl);
            $cekKode->execute();
            $urutKe = $cekKode->get_result()->fetch_assoc()['jml'] + 1;
            $kodeTransaksi = $tglKode . str_pad($urutKe, 3, '0', STR_PAD_LEFT);
            
            $st = $db->prepare("
            INSERT INTO transaksi
            (kode_transaksi, tipe, produk_id, jumlah, user_id, tanggal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $t = 'masuk';

        $st->bind_param('ssiiis', $kodeTransaksi, $t, $pid, $jml, $uid, $tgl);

        $st->execute();

            $up = $db->prepare("
                UPDATE produk
                SET stok = stok + ?
                WHERE id = ?
            ");

            $up->bind_param('ii', $jml, $pid);
            $up->execute();

            $db->commit();

            $msg = [
                'type' => 'success',
                'text' => "Barang masuk $jml unit berhasil dicatat."
            ];

        } catch (Exception $e) {

            $db->rollback();

            $msg = [
                'type' => 'error',
                'text' => 'Gagal: ' . $e->getMessage()
            ];
        }

    } else {
        $msg = [
            'type' => 'error',
            'text' => 'Harap isi semua field wajib.'
        ];
    }
}

$prefill = (int) ($_GET['id'] ?? 0);

$plist = $db->query("
    SELECT id, kode_sku, model, warna, ukuran, stok
    FROM produk
    ORDER BY model, warna, ukuran
");

$riw = $db->query("
    SELECT t.*, p.model, p.warna, p.kode_sku, u.nama petugas
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    JOIN users u  ON t.user_id = u.id
    WHERE t.tipe = 'masuk'
    AND MONTH(t.tanggal) = MONTH(CURDATE())
    AND YEAR(t.tanggal) = YEAR(CURDATE())
    ORDER BY t.created_at DESC
");

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

<?php if ($msg): ?>
    <div class="flash <?= $msg['type'] ?>">
        <?= $msg['text'] ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">

    <div class="card-head">
        <h2>Kolom Pencatatan Barang Masuk</h2>
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

                    <?php
                    $plist->data_seek(0);
                    while ($p = $plist->fetch_assoc()):
                        $ukl = $p['ukuran'] !== 'All Size' ? ' ' . $p['ukuran'] : '';
                    ?>

                        <option value="<?= $p['id'] ?>" <?= $prefill == $p['id'] ? 'selected' : '' ?>>
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
        </div>

        <div class="form-footer">
            <a href="produk.php" class="btn">Batal</a>
            <button type="submit" class="btn btn-p">Simpan</button>
        </div>

    </form>
</div>

<div class="card">

    <div class="card-head">
        <h2>Riwayat Barang Masuk — Bulan Ini</h2>
    </div>

    <div class="table-wrap">
        <table>

            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Produk</th>
                    <th>Warna</th>
                    <th>Qty</th>
                    <th>Oleh</th>
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

                        <td style="color:var(--ok)">
                            +<?= $r['jumlah'] ?>
                        </td>

                        <td><?= htmlspecialchars($r['petugas']) ?></td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <p>Belum ada barang masuk bulan ini.</p>
                        </div>
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>