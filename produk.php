<?php
define('BASE_URL', '');
require_once 'config/database.php';
require_once 'includes/auth.php';

$currentPage = 'produk';
$pageTitle   = 'Data Produk';

requireLogin();

$db = getDB();
startSess();
$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* POST: Buat Surat Order ke Supplier — hanya owner, generator dokumen saja (tidak disimpan ke database) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_order']) && !isGudang()) {

    $pilih = $_POST['pilih'] ?? [];
    $qty   = $_POST['qty'] ?? [];
    $order = [];

    foreach ($pilih as $pid) {
        $pid = (int) $pid;
        $q   = (int) ($qty[$pid] ?? 0);
        if ($q > 0) {
            $stp = $db->prepare("SELECT model, warna, ukuran FROM produk WHERE id = ?");
            $stp->bind_param('i', $pid);
            $stp->execute();
            $row = $stp->get_result()->fetch_assoc();
            if ($row) {
                $row['jumlah'] = $q;
                $order[] = $row;
            }
        }
    }

    if (empty($order)) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Pilih minimal satu produk dan isi jumlah pesanannya.'];
        header('Location: ' . BASE_URL . '/produk.php');
        exit();
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8">
    <title>Surat Order Supplier — Kevatie</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 28px; background: #F5F0E8; color: #1C1C1C; }
        .doc { max-width: 800px; margin: 0 auto; background: #fff; padding: 32px 36px; }
        @media print { body { background: #fff; padding: 0; } .doc { max-width: none; margin: 0; } .actions { display: none; } }
        .head { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #3B2A15; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { font-size: 20px; font-weight: 700; color: #3B2A15; }
        .doctitle { text-align: right; }
        .doctitle .t { font-size: 15px; font-weight: 700; }
        .doctitle .d { font-size: 11px; color: #7A6A56; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { border: 1px solid #D9CBAF; padding: 8px 10px; text-align: left; }
        th { background: #F5EFE3; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
        td.num { text-align: center; }
        .note { margin-top: 20px; font-size: 11px; color: #7A6A56; }
        .actions { margin-top: 24px; text-align: center; }
        .actions button { padding: 8px 20px; border: none; background: #3B2A15; color: #fff; border-radius: 6px; cursor: pointer; font-size: 13px; }
    </style>
    </head>
    <body>
        <div class="doc">
        <div class="head">
            <div>
                <div class="brand">Kevatie</div>
            </div>
            <div class="doctitle">
                <div class="t">SURAT ORDER SUPPLIER</div>
                <div class="d">Tanggal: <?= date('d/m/Y') ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:36px">No</th>
                    <th>Model</th>
                    <th>Warna</th>
                    <th>Ukuran</th>
                    <th class="num" style="width:100px">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order as $i => $o): ?>
                    <tr>
                        <td class="num"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($o['model']) ?></td>
                        <td><?= htmlspecialchars($o['warna']) ?></td>
                        <td><?= htmlspecialchars($o['ukuran']) ?></td>
                        <td class="num"><?= (int) $o['jumlah'] ?> unit</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="actions">
            <button onclick="window.print()">Cetak sebagai PDF</button>
        </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/* POST: Edit Stok — hanya owner */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    if (!isGudang()) {
        $id   = (int) ($_POST['id'] ?? 0);
        $stok = (int) ($_POST['stok'] ?? 0);
        $min  = (int) ($_POST['stok_minimum'] ?? 0);

        if ($id > 0 && $stok >= 0 && $min >= 1) {
            $st = $db->prepare("UPDATE produk SET stok = ?, stok_minimum = ? WHERE id = ?");
            $st->bind_param('iii', $stok, $min, $id);
            
            if ($st->execute()) {
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Stok berhasil diperbarui.'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'text' => 'Gagal memperbarui stok.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Input tidak valid.'];
        }
    }
    header('Location: ' . BASE_URL . '/produk.php');
    exit();
}

/* GET: Filter produk */
$filterModel = $_GET['model'] ?? '';
$filterWarna = $_GET['warna'] ?? '';
$search      = $_GET['q']     ?? '';

$where  = '1=1';
$params = [];
$types  = '';

if ($filterModel) { $where .= ' AND model = ?'; $params[] = $filterModel; $types .= 's'; }
if ($filterWarna) { $where .= ' AND warna = ?'; $params[] = $filterWarna; $types .= 's'; }
if ($search) {
    $where .= ' AND (kode_sku LIKE ? OR model LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$st = $db->prepare("SELECT * FROM produk WHERE $where ORDER BY model, warna, FIELD(ukuran, 'S', 'M', 'L', 'XL')");
if ($params) { $st->bind_param($types, ...$params); }
$st->execute();
$produkList = $st->get_result();

$totalSku = $db->query("SELECT COUNT(*) c FROM produk")->fetch_assoc()['c'];

$models   = ['Prime Series', 'Luminous', 'Backpack', 'Nova Series', 'Ponco', 'Prime Kids'];
$warnas   = ['Hitam', 'Navy', 'Cream', 'Army', 'Sage', 'Merah', 'Pink'];
$warnaHex = [
    'Hitam' => '#1C1C1C', 'Navy'  => '#1E2D4E',
    'Cream' => '#D8C9A8', 'Army'  => '#4A5733',
    'Sage'  => '#7A9A82', 'Merah' => '#B83232', 'Pink' => '#D4728A',
];

require_once 'includes/header.php';
?>

<!-- Modal Edit Stok - Versi Rapi & Clean -->
<?php if (!isGudang()): ?>
<div id="m-edit" style="display:none; position:fixed; inset:0; background:rgba(59,42,21,.5); z-index:1000; align-items:center; justify-content:center">
    <div style="background:var(--c50); border-radius:16px; border:1px solid var(--c300); width:400px; max-width:95%; box-shadow:0 15px 40px rgba(0,0,0,0.18); overflow:hidden">
        
        <!-- Header -->
        <div style="padding:18px 24px; border-bottom:1px solid var(--c200); display:flex; justify-content:space-between; align-items:center">
            <h3 id="edit-title" style="margin:0; font-family:'Cormorant Garamond',serif; font-size:18px; font-weight:400;">Edit Stok Produk</h3>
            <button onclick="closeModal('m-edit')" style="background:none; border:none; font-size:26px; cursor:pointer; color:var(--hint); padding:0; line-height:1;">✕</button>
        </div>

        <!-- Body -->
        <form method="POST">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="edit-id">

            <div style="padding:28px 24px 10px;">
                <div style="margin-bottom:22px; padding:14px 16px; background:var(--c100); border-radius:10px;">
                    <p style="font-size:11px; color:var(--hint); margin:0 0 4px; text-transform:uppercase; letter-spacing:0.5px;">Kode SKU</p>
                    <p id="edit-sku-display" style="font-family:monospace; font-size:15.5px; font-weight:500; color:var(--brown); margin:0;"></p>
                </div>

                <div class="form-grid" style="gap:18px;">
                    <div class="fg">
                        <label style="font-size:12px; font-weight:500;">Stok Saat Ini</label>
                        <input type="number" name="stok" id="edit-stok" min="0" required style="font-size:17px; padding:12px 14px; width:100%;">
                    </div>
                    <div class="fg">
                        <label style="font-size:12px; font-weight:500;">Stok Minimum</label>
                        <input type="number" name="stok_minimum" id="edit-min" min="1" required style="font-size:17px; padding:12px 14px; width:100%;">
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div style="padding:16px 24px 24px; border-top:1px solid var(--c200); display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeModal('m-edit')" style="min-width:80px;">Batal</button>
                <button type="submit" class="btn btn-p" style="min-width:80px;">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($msg): ?>
    <div class="flash <?= $msg['type'] ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<div style="font-size:12px; color:var(--hint); margin-bottom:14px">
    Total: <strong style="color:var(--brown)"><?= $totalSku ?> SKU</strong> · <?= count($models) ?> model
</div>

<div class="card">
    <div class="card-head">
        <h2>Daftar Produk Kevatie</h2>
        <div class="acts">
            <form method="GET" class="fbar">
                <input type="text" name="q" placeholder="Cari SKU / model..."
                       value="<?= htmlspecialchars($search) ?>" style="width:160px">
                <select name="model">
                    <option value="">Semua model</option>
                    <?php foreach ($models as $m): ?>
                        <option value="<?= $m ?>" <?= $filterModel === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="warna">
                    <option value="">Semua warna</option>
                    <?php foreach ($warnas as $w): ?>
                        <option value="<?= $w ?>" <?= $filterWarna === $w ? 'selected' : '' ?>><?= $w ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm">Filter</button>
                <a href="produk.php" class="btn btn-sm">Reset</a>
            </form>
        </div>
    </div>

    <?php if (!isGudang()): ?><form method="POST" id="order-form"><?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode SKU</th>
                    <th>Model</th>
                    <th>Warna</th>
                    <th>Ukuran</th>
                    <th>Stok</th>
                    <th>Min.</th>
                    <th>Status</th>
                    <?php if (!isGudang()): ?><th>Aksi</th><th>Pesan</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($produkList->num_rows > 0):
                $produkList->data_seek(0);
                while ($row = $produkList->fetch_assoc()):
                    if ($row['stok'] == 0)                       { $statusLabel = 'Habis';   $statusClass = 'b-danger'; }
                    elseif ($row['stok'] < $row['stok_minimum']) { $statusLabel = 'Menipis'; $statusClass = 'b-warn'; }
                    else                                         { $statusLabel = 'Aman';    $statusClass = 'b-ok'; }
            ?>
                <tr>
                    <td class="td-sku"><?= htmlspecialchars($row['kode_sku']) ?></td>
                    <td><?= htmlspecialchars($row['model']) ?></td>
                    <td>
                        <span class="wdot">
                            <span style="background:<?= $warnaHex[$row['warna']] ?? '#ccc' ?>"></span>
                            <?= htmlspecialchars($row['warna']) ?>
                        </span>
                    </td>
                    <td><span class="badge b-n"><?= $row['ukuran'] ?></span></td>
                    <td><strong><?= $row['stok'] ?></strong></td>
                    <td><?= $row['stok_minimum'] ?></td>
                    <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                    <?php if (!isGudang()): ?>
                    <td>
                        <button type="button" class="btn btn-sm edit-btn"
                            data-id="<?= $row['id'] ?>"
                            data-kode="<?= htmlspecialchars($row['kode_sku']) ?>"
                            data-stok="<?= (int)$row['stok'] ?>"
                            data-stokmin="<?= (int)$row['stok_minimum'] ?>">
                            Edit
                        </button>
                    </td>
                    <td>
                        <?php if ($statusLabel !== 'Aman'): ?>
                            <div class="pesan-cell">
                                <input type="checkbox" name="pilih[]" value="<?= $row['id'] ?>">
                                <input type="number" name="qty[<?= $row['id'] ?>]" min="1" placeholder="Qty" class="pesan-qty">
                            </div>
                        <?php else: ?>
                            <span style="color:var(--hint)">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state"><p>Tidak ada produk ditemukan.</p></div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!isGudang()): ?>
        <div class="order-footer">
            <button type="submit" name="buat_order" value="1" class="btn btn-p">Buat Surat Order</button>
        </div>
        </form>
    <?php endif; ?>
</div>

<style>
.pesan-cell {
    display: flex;
    align-items: center;
    gap: 6px;
}
.pesan-cell input[type="checkbox"] {
    width: 15px;
    height: 15px;
    cursor: pointer;
    accent-color: var(--brown);
}
.pesan-qty {
    width: 56px;
    padding: 5px 7px;
    border: 1px solid var(--c300);
    border-radius: 6px;
    font-size: 12px;
    text-align: center;
}
.pesan-qty:focus { outline: 1px solid var(--brown); }

.order-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    border-top: 1px solid var(--c200);
    flex-wrap: wrap;
}
</style>

<?php require_once 'includes/footer.php'; ?>

<script>
// Script Edit Stok
function openEdit(data) {
    const modal = document.getElementById('m-edit');
    if (!modal) {
        alert("Modal tidak ditemukan!");
        return;
    }

    modal.style.display = 'flex';

    document.getElementById('edit-id').value = data.id;
    document.getElementById('edit-stok').value = data.stok;
    document.getElementById('edit-min').value = data.stok_minimum;

    const skuDisplay = document.getElementById('edit-sku-display');
    if (skuDisplay) skuDisplay.innerText = data.kode_sku || '-';
}

// Event Listener untuk tombol Edit
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            openEdit({
                id: this.dataset.id,
                kode_sku: this.dataset.kode,
                stok: parseInt(this.dataset.stok),
                stok_minimum: parseInt(this.dataset.stokmin)
            });
        });
    });
});

console.log("✅ Edit Stok siap digunakan");
</script>