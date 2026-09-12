<?php
/*
    import_barang_keluar.php
    ------------------
    Fitur import data pesanan dari file (CSV) yang didownload dari
    marketplace (Shopee/Tokopedia/TikTok Shop) supaya bagian gudang
    tidak perlu input manual satu per satu kalau pesanan dalam sehari
    banyak.

    Alurnya dibuat 3 langkah karena format kolom laporan pesanan tiap
    marketplace berbeda-beda dan bisa berubah sewaktu-waktu:

    Langkah 1 - Upload file CSV + pilih platform & tanggal transaksi
    Langkah 2 - Cocokkan kolom di file dengan data yang dibutuhkan sistem
                (Model Produk, Variasi Warna/Ukuran, Jumlah, Nama Pembeli,
                dst). Sistem coba menebak otomatis, tapi gudang bisa
                koreksi manual kalau tebakannya salah.
    Langkah 3 - Preview hasil pencocokan produk (Model+Warna+Ukuran ketemu
                / tidak, stok cukup / tidak) sebelum benar-benar disimpan
                ke database.

    CATATAN PENTING soal kolom SKU:
    Kolom "SKU Induk" / "Nomor Referensi SKU" yang ada di laporan
    marketplace TIDAK bisa dipakai langsung sebagai kode_sku pada tabel
    produk, karena kode_sku di sistem ini formatnya sendiri
    (contoh: KV-LUM-HIT-AS) dan hanya dikenal oleh sistem, sedangkan
    marketplace hanya tahu nama singkat model (contoh: "Luminous").
    Begitu juga warna & ukuran produk di marketplace biasanya digabung
    jadi satu kolom "Nama Variasi" dengan format "Warna,Ukuran"
    (contoh: "Hitam,All Size"), padahal di tabel produk warna dan ukuran
    itu kolom terpisah.

    Karena itu proses pencocokan produk di halaman ini dilakukan dengan:
    1. Kolom Model/Nama Produk dari file -> dicocokkan ke kolom `model`
    2. Kolom Variasi dari file dipecah (dipisah tanda koma) jadi
       Warna dan Ukuran -> dicocokkan ke kolom `warna` dan `ukuran`
    Pencocokan bersifat exact match tapi huruf besar/kecil dan spasi
    berlebih diabaikan (misal "AllSize" dianggap sama dengan "All Size").
*/

define('BASE_URL', '');
require_once 'config/database.php';
require_once 'includes/auth.php';

$currentPage = 'import';
$pageTitle   = 'Import Barang Keluar dari Marketplace';

requireLogin();
$db = getDB();
startSess();

// folder sementara buat nyimpen file yang diupload selama proses mapping berlangsung
$tmpDir = __DIR__ . '/tmp_import';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}
// cegah file di folder ini diakses langsung lewat URL
$htaccess = $tmpDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
}

$plats = ['Shopee', 'Tiktok Shop', 'Tokopedia'];

// kata kunci buat nebak kolom mana yang cocok, dipakai di langkah 2
function tebakKolom($headers, $keywords)
{
    foreach ($headers as $h) {
        $hLower = strtolower(trim($h));
        foreach ($keywords as $k) {
            if (strpos($hLower, $k) !== false) {
                return $h;
            }
        }
    }
    return '';
}

// file CSV dari Excel/Google Sheets versi Indonesia biasanya pakai titik koma (;)
// sebagai pemisah kolom, bukan koma (,). Fungsi ini nyoba beberapa pemisah yang umum
// dipakai dan pilih yang menghasilkan jumlah kolom paling banyak.
function deteksiDelimiter($contohBaris)
{
    $kandidat = [',', ';', "\t"];
    $terbaik  = ',';
    $maxKolom = 0;

    foreach ($kandidat as $d) {
        $jumlahKolom = count(str_getcsv($contohBaris, $d));
        if ($jumlahKolom > $maxKolom) {
            $maxKolom = $jumlahKolom;
            $terbaik  = $d;
        }
    }

    return $terbaik;
}

// baca 1 baris CSV dan buang karakter BOM (kalau ada) di kolom pertama
function bacaBarisCsv($handle, $delimiter)
{
    $row = fgetcsv($handle, 0, $delimiter);
    if ($row !== false && isset($row[0])) {
        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
    }
    return $row;
}

// BARU: samakan format teks sebelum dibandingkan supaya beda kapital
// atau beda spasi ("All Size" vs "AllSize") tetap dianggap sama.
function normalisasiTeks($teks)
{
    $teks = strtolower(trim((string) $teks));
    $teks = preg_replace('/\s+/', '', $teks); // buang semua spasi
    return $teks;
}

// BARU: pecah kolom "Nama Variasi" (format umum: "Warna,Ukuran") jadi
// dua bagian. Kalau cuma ada 1 bagian (nggak ada tanda koma), ukuran
// dianggap kosong supaya baris tsb ditandai "tidak lengkap" nantinya,
// bukan ditebak sembarangan.
function pisahVariasi($teks)
{
    $teks = trim((string) $teks);
    if ($teks === '') {
        return ['', ''];
    }
    $bagian = array_map('trim', explode(',', $teks, 2));
    $warna  = $bagian[0] ?? '';
    $ukuran = $bagian[1] ?? '';
    return [$warna, $ukuran];
}

// BARU: marketplace sering nambahin keterangan tambahan di belakang ukuran,
// misal "S(4-6 tahun)" atau "All Size(L fit XL)". Keterangan dalam kurung
// itu dibuang dulu supaya yang dibandingkan cuma kode ukurannya saja
// ("S", "All Size") sesuai yang ada di database.
function bersihkanUkuran($ukuran)
{
    $ukuran = preg_replace('/\(.*?\)/', '', (string) $ukuran);
    return trim($ukuran);
}

// BARU: cari nama model asli di database berdasarkan nama model dari file
// marketplace, walaupun penulisannya tidak 100% sama.
// Urutannya:
// 1. Coba exact match dulu (setelah dinormalisasi).
// 2. Kalau tidak ketemu, coba cocokkan sebagai "awalan" -- misal "Nova"
//    dianggap cocok dengan "Nova Series" karena "Nova Series" diawali
//    kata "Nova". Kalau ternyata nama dari file itu cocok jadi awalan
//    lebih dari satu model berbeda di database (ambigu), sengaja
//    dianggap TIDAK ketemu supaya tidak salah catat ke produk yang keliru.
function cariNamaModelAsli($lookupModel, $modelMentah)
{
    $norm = normalisasiTeks($modelMentah);
    if ($norm === '') {
        return null;
    }
    if (isset($lookupModel[$norm])) {
        return $lookupModel[$norm];
    }

    $kandidat = [];
    foreach ($lookupModel as $normDb => $namaAsliDb) {
        if (strpos($normDb, $norm) === 0 || strpos($norm, $normDb) === 0) {
            $kandidat[$namaAsliDb] = true;
        }
    }
    $kandidat = array_keys($kandidat);

    return count($kandidat) === 1 ? $kandidat[0] : null;
}

// BARU: ambil semua data produk sekali saja lalu bikin "kamus pencarian"
// berdasarkan kombinasi model + warna + ukuran yang sudah dinormalisasi,
// ditambah daftar nama model asli (dipakai buat pencocokan model yang
// fleksibel di cariNamaModelAsli()).
// Dengan begini pencocokan tidak perlu nebak-nebak format kode_sku.
function muatLookupProduk($db)
{
    $lookupProduk = [];
    $lookupModel  = []; // model_dinormalisasi => nama model asli di database

    $res = $db->query("SELECT id, kode_sku, model, warna, ukuran, stok FROM produk");
    while ($row = $res->fetch_assoc()) {
        $modelNorm = normalisasiTeks($row['model']);
        $kunci     = $modelNorm . '|' . normalisasiTeks($row['warna']) . '|' . normalisasiTeks($row['ukuran']);

        $lookupProduk[$kunci]     = $row;
        $lookupModel[$modelNorm]  = $row['model'];
    }

    return ['produk' => $lookupProduk, 'model' => $lookupModel];
}

// BARU: cari produk di kamus lookup berdasarkan model, warna, ukuran (mentah dari file).
// Model dicocokkan fleksibel (lihat cariNamaModelAsli), ukuran dibersihkan dulu
// dari keterangan tambahan dalam kurung (lihat bersihkanUkuran).
function cariProduk($lookup, $model, $warna, $ukuran)
{
    $namaModelAsli = cariNamaModelAsli($lookup['model'], $model);
    if ($namaModelAsli === null) {
        return null;
    }

    $ukuranBersih = bersihkanUkuran($ukuran);
    $kunci = normalisasiTeks($namaModelAsli) . '|' . normalisasiTeks($warna) . '|' . normalisasiTeks($ukuranBersih);

    return $lookup['produk'][$kunci] ?? null;
}

/* ============ LANGKAH 1: terima upload file ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_step'])) {

    $platform = trim($_POST['platform'] ?? '');
    $tanggal  = trim($_POST['tanggal'] ?? date('Y-m-d'));

    if (!in_array($platform, $plats)) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Pilih marketplace dulu sebelum upload file.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    if (!isset($_FILES['file_csv']) || $_FILES['file_csv']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'File gagal diupload. Coba lagi.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    $ext = strtolower(pathinfo($_FILES['file_csv']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'File harus format CSV. Kalau file dari marketplace masih .xlsx, buka dulu pakai Excel/Google Sheets lalu simpan/export ulang sebagai CSV.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    $tmpName = 'import_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.csv';
    $tmpPath = $tmpDir . '/' . $tmpName;

    if (!move_uploaded_file($_FILES['file_csv']['tmp_name'], $tmpPath)) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Gagal menyimpan file di server.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    $fh = fopen($tmpPath, 'r');
    $baris1 = fgets($fh);
    fclose($fh);

    if ($baris1 === false || trim($baris1) === '') {
        @unlink($tmpPath);
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'File CSV tidak terbaca atau kosong. Pastikan file diekspor dengan header kolom di baris pertama.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    // deteksi otomatis pemisah kolom (koma / titik koma / tab)
    $delimiter = deteksiDelimiter($baris1);

    $fh = fopen($tmpPath, 'r');
    $headerRow = bacaBarisCsv($fh, $delimiter);
    fclose($fh);

    if (!$headerRow || count($headerRow) < 2) {
        @unlink($tmpPath);
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'File CSV tidak terbaca atau kosong. Pastikan file diekspor dengan header kolom di baris pertama.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    $_SESSION['import_file']      = $tmpPath;
    $_SESSION['import_headers']   = $headerRow;
    $_SESSION['import_delimiter'] = $delimiter;
    $_SESSION['import_platform']  = $platform;
    $_SESSION['import_tanggal']   = $tanggal;
    unset($_SESSION['import_mapping']);

    header('Location: import_barang_keluar.php?step=2');
    exit();
}

/* ============ LANGKAH 2: simpan pemetaan kolom ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mapping_step'])) {

    if (!isset($_SESSION['import_headers'])) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Sesi import kadaluarsa, silakan upload ulang file.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    $mapModel   = trim($_POST['map_model'] ?? '');
    $mapVariasi = trim($_POST['map_variasi'] ?? '');
    $mapQty     = trim($_POST['map_qty'] ?? '');

    if ($mapModel === '' || $mapVariasi === '' || $mapQty === '') {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Kolom Model, Variasi (Warna & Ukuran), dan Jumlah wajib dipilih.'];
        header('Location: import_barang_keluar.php?step=2');
        exit();
    }

    $_SESSION['import_mapping'] = [
        'model'   => $mapModel,
        'variasi' => $mapVariasi,
        'qty'     => $mapQty,
        'nama'    => $_POST['map_nama'] ?? '',
        'telepon' => $_POST['map_telepon'] ?? '',
        'alamat'  => $_POST['map_alamat'] ?? '',
        'pesanan' => $_POST['map_pesanan'] ?? '',
    ];

    header('Location: import_barang_keluar.php?step=3');
    exit();
}

/* ============ LANGKAH 3 (final): eksekusi import ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {

    if (!isset($_SESSION['import_file'], $_SESSION['import_mapping'])) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Sesi import kadaluarsa, silakan upload ulang file.'];
        header('Location: import_barang_keluar.php');
        exit();
    }

    $mapping   = $_SESSION['import_mapping'];
    $tmpPath   = $_SESSION['import_file'];
    $platform  = $_SESSION['import_platform'];
    $tanggal   = $_SESSION['import_tanggal'];
    $delimiter = $_SESSION['import_delimiter'] ?? ',';
    $uid       = $_SESSION['user_id'];

    $lookupProduk = muatLookupProduk($db);

    $fh = fopen($tmpPath, 'r');
    $head      = bacaBarisCsv($fh, $delimiter);
    $headIndex = array_flip($head);

    $sukses = 0;
    $gagal  = 0;
    $gagalDetail = [];

    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {

        $modelMentah   = isset($headIndex[$mapping['model']]) ? trim($row[$headIndex[$mapping['model']]] ?? '') : '';
        $variasiMentah = isset($headIndex[$mapping['variasi']]) ? trim($row[$headIndex[$mapping['variasi']]] ?? '') : '';
        $qty           = isset($headIndex[$mapping['qty']]) ? (int) trim($row[$headIndex[$mapping['qty']]] ?? '0') : 0;

        if ($modelMentah === '' || $qty <= 0) {
            continue; // baris kosong / bukan baris pesanan, dilewati
        }

        [$warnaMentah, $ukuranMentah] = pisahVariasi($variasiMentah);

        $nama    = ($mapping['nama']    && isset($headIndex[$mapping['nama']]))    ? trim($row[$headIndex[$mapping['nama']]] ?? '')    : '';
        $telepon = ($mapping['telepon'] && isset($headIndex[$mapping['telepon']])) ? trim($row[$headIndex[$mapping['telepon']]] ?? '') : '';
        $alamat  = ($mapping['alamat']  && isset($headIndex[$mapping['alamat']]))  ? trim($row[$headIndex[$mapping['alamat']]] ?? '')  : '';
        $pesanan = ($mapping['pesanan'] && isset($headIndex[$mapping['pesanan']])) ? trim($row[$headIndex[$mapping['pesanan']]] ?? '') : '';

        $nama    = $nama    !== '' ? $nama    : 'Pembeli ' . $platform;
        $telepon = $telepon !== '' ? $telepon : '-';
        $alamat  = $alamat  !== '' ? $alamat  : '-';

        if ($ukuranMentah === '') {
            $gagal++;
            $gagalDetail[] = "Model \"$modelMentah\" (variasi \"$variasiMentah\") tidak lengkap, warna/ukuran tidak terbaca.";
            continue;
        }

        $produk = cariProduk($lookupProduk, $modelMentah, $warnaMentah, $ukuranMentah);

        if (!$produk) {
            $gagal++;
            $gagalDetail[] = "Produk \"$modelMentah - $warnaMentah - $ukuranMentah\" tidak ditemukan di data produk.";
            continue;
        }

        if ($produk['stok'] < $qty) {
            $gagal++;
            $gagalDetail[] = "SKU \"{$produk['kode_sku']}\" stok tidak cukup (tersedia {$produk['stok']}, diminta $qty).";
            continue;
        }

        $db->begin_transaction();
        try {
            $cekJmlPlgn    = $db->query("SELECT COUNT(*) AS jml FROM pelanggan");
            $urutPlgn      = $cekJmlPlgn->fetch_assoc()['jml'] + 1;
            $kodePelanggan = 'PLG' . str_pad($urutPlgn, 4, '0', STR_PAD_LEFT);

            $insPlgn = $db->prepare("
                INSERT INTO pelanggan (kode_pelanggan, nama_pelanggan, no_telepon, alamat_asal, alamat_pengiriman)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insPlgn->bind_param('sssss', $kodePelanggan, $nama, $telepon, $alamat, $alamat);
            $insPlgn->execute();
            $pelangganId = $insPlgn->insert_id;

            $tglKode = date('dmY', strtotime($tanggal));
            $cekKode = $db->prepare("SELECT COUNT(*) AS jml FROM transaksi WHERE tanggal = ?");
            $cekKode->bind_param('s', $tanggal);
            $cekKode->execute();
            $urutKe        = $cekKode->get_result()->fetch_assoc()['jml'] + 1;
            $kodeTransaksi = $tglKode . str_pad($urutKe, 3, '0', STR_PAD_LEFT);

            $tipe       = 'keluar';
            $pesananVal = $pesanan !== '' ? $pesanan : null;
            $pid        = $produk['id'];

            $st = $db->prepare("
                INSERT INTO transaksi
                (kode_transaksi, tipe, produk_id, jumlah, platform, pelanggan_id, nomor_pesanan, user_id, tanggal)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $st->bind_param('ssiisisis', $kodeTransaksi, $tipe, $pid, $qty, $platform, $pelangganId, $pesananVal, $uid, $tanggal);
            $st->execute();
            
            $transaksiId = $db->insert_id;
            $dt = $db->prepare("INSERT INTO detail_transaksi (transaksi_id, produk_id, jumlah) VALUES (?, ?, ?)");
            $dt->bind_param('iii', $transaksiId, $pid, $qty);
            $dt->execute();

            $up = $db->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
            $up->bind_param('ii', $qty, $pid);
            $up->execute();

            $db->commit();
            $sukses++;
        } catch (Exception $e) {
            $db->rollback();
            $gagal++;
            $gagalDetail[] = "Produk \"{$produk['kode_sku']}\" gagal disimpan: " . $e->getMessage();
        }
    }
    fclose($fh);

    @unlink($tmpPath);
    unset($_SESSION['import_file'], $_SESSION['import_headers'], $_SESSION['import_mapping'], $_SESSION['import_platform'], $_SESSION['import_tanggal'], $_SESSION['import_delimiter']);

    $ringkasText = "Import selesai. Berhasil dicatat: $sukses baris, gagal: $gagal baris.";
    if ($gagalDetail) {
        $ringkasText .= ' Contoh kegagalan: ' . implode(' | ', array_slice($gagalDetail, 0, 5));
        if (count($gagalDetail) > 5) {
            $ringkasText .= ' (dan ' . (count($gagalDetail) - 5) . ' lainnya)';
        }
    }

    $_SESSION['flash'] = ['type' => $gagal > 0 ? 'error' : 'success', 'text' => $ringkasText];
    header('Location: barang_keluar.php');
    exit();
}

/* ============ Batalkan proses import yang sedang berjalan ============ */
if (isset($_GET['batal'])) {
    if (isset($_SESSION['import_file']) && file_exists($_SESSION['import_file'])) {
        @unlink($_SESSION['import_file']);
    }
    unset($_SESSION['import_file'], $_SESSION['import_headers'], $_SESSION['import_mapping'], $_SESSION['import_platform'], $_SESSION['import_tanggal'], $_SESSION['import_delimiter']);
    header('Location: import_barang_keluar.php');
    exit();
}

/* ============ Tentukan langkah mana yang mau ditampilkan (GET) ============ */
$step = 1;
if (($_GET['step'] ?? '') === '2' && isset($_SESSION['import_headers'])) {
    $step = 2;
}
if (($_GET['step'] ?? '') === '3' && isset($_SESSION['import_mapping'])) {
    $step = 3;
}

$headers = $_SESSION['import_headers'] ?? [];

// siapkan tebakan default kolom buat langkah 2
$guess = [];
if ($step === 2) {
    $guess['model']   = tebakKolom($headers, ['sku induk', 'nomor referensi sku', 'model', 'nama produk']);
    $guess['variasi'] = tebakKolom($headers, ['nama variasi', 'variasi', 'variant']);
    $guess['qty']     = tebakKolom($headers, ['jumlah', 'qty', 'quantity', 'kuantitas']);
    $guess['nama']    = tebakKolom($headers, ['nama penerima', 'penerima', 'nama pembeli', 'buyer', 'username']);
    $guess['telepon'] = tebakKolom($headers, ['telepon', 'no. handphone', 'phone', 'no hp']);
    $guess['alamat']  = tebakKolom($headers, ['alamat']);
    $guess['pesanan'] = tebakKolom($headers, ['no. pesanan', 'order id', 'no pesanan', 'invoice']);
}

// bangun data preview buat langkah 3
$previewRows  = [];
$previewError = '';
$totalDiluarSistem = 0; // BARU: hitung baris yang modelnya sama sekali tidak dikenal (bukan produk Kevatie)
if ($step === 3 && isset($_SESSION['import_file'])) {
    $mapping   = $_SESSION['import_mapping'];
    $tmpPath   = $_SESSION['import_file'];
    $delimiter = $_SESSION['import_delimiter'] ?? ',';

    if (!file_exists($tmpPath)) {
        $previewError = 'File sementara sudah tidak ada, silakan upload ulang.';
    } else {
        $lookupProduk = muatLookupProduk($db);

        $fh        = fopen($tmpPath, 'r');
        $head      = bacaBarisCsv($fh, $delimiter);
        $headIndex = array_flip($head);
        $no        = 0;

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false && $no < 500) {
            $modelMentah   = isset($headIndex[$mapping['model']]) ? trim($row[$headIndex[$mapping['model']]] ?? '') : '';
            $variasiMentah = isset($headIndex[$mapping['variasi']]) ? trim($row[$headIndex[$mapping['variasi']]] ?? '') : '';
            $qty           = isset($headIndex[$mapping['qty']]) ? (int) trim($row[$headIndex[$mapping['qty']]] ?? '0') : 0;

            if ($modelMentah === '' || $qty <= 0) {
                continue;
            }

            // BARU: kalau nama modelnya sama sekali tidak dikenal di sistem (bukan produk Kevatie,
            // contoh: WILDFORM, Running Cap), lewati total dari preview. Ini beda dari kasus model
            // dikenal tapi kombinasi warna/ukurannya belum terdaftar (itu tetap ditampilkan supaya
            // gudang tahu ada produk yang perlu didaftarkan).
            $namaModelAsli = cariNamaModelAsli($lookupProduk['model'], $modelMentah);
            if ($namaModelAsli === null) {
                $totalDiluarSistem++;
                continue;
            }

            $no++;

            [$warnaMentah, $ukuranMentah] = pisahVariasi($variasiMentah);
            $produk = ($ukuranMentah !== '') ? cariProduk($lookupProduk, $modelMentah, $warnaMentah, $ukuranMentah) : null;

            if ($ukuranMentah === '') {
                $status = 'tidak_lengkap';
            } elseif (!$produk) {
                $status = 'tidak_ditemukan';
            } elseif ($produk['stok'] < $qty) {
                $status = 'stok_kurang';
            } else {
                $status = 'ok';
            }

            $previewRows[] = [
                'model'   => $modelMentah,
                'warna'   => $warnaMentah,
                'ukuran'  => $ukuranMentah !== '' ? $ukuranMentah : '-',
                'qty'     => $qty,
                'nama'    => ($mapping['nama'] && isset($headIndex[$mapping['nama']])) ? trim($row[$headIndex[$mapping['nama']]] ?? '-') : '-',
                'produk'  => $produk,
                'status'  => $status,
            ];
        }
        fclose($fh);
    }
}

$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Import Barang Keluar dari Marketplace</h2>
        <a href="barang_keluar.php" class="btn btn-sm">Kembali</a>
    </div>

    <div style="padding:16px 18px 0">
        <div style="display:flex; gap:8px; margin-bottom:18px; font-size:12px;">
            <span class="badge <?= $step >= 1 ? 'b-ok' : 'b-n' ?>">1. Upload File</span>
            <span class="badge <?= $step >= 2 ? 'b-ok' : 'b-n' ?>">2. Cocokkan Kolom</span>
            <span class="badge <?= $step >= 3 ? 'b-ok' : 'b-n' ?>">3. Preview &amp; Konfirmasi</span>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="flash <?= $msg['type'] ?>" style="margin:0 18px 16px"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>

        <div style="padding:0 18px 18px">

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_step" value="1">

                <div class="form-grid">
                    <div class="fg">
                        <label>Marketplace *</label>
                        <select name="platform" required>
                            <option value="">— Pilih —</option>
                            <?php foreach ($plats as $pl): ?>
                                <option value="<?= $pl ?>"><?= $pl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fg">
                        <label>Tanggal Transaksi *</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="fg" style="grid-column: 1 / -1">
                        <label>File CSV Pesanan *</label>
                        <input type="file" name="file_csv" accept=".csv" required>
                    </div>
                </div>

                <div class="form-footer">
                    <a href="barang_keluar.php" class="btn">Batal</a>
                    <button type="submit" class="btn btn-p">Lanjut</button>
                </div>
            </form>
        </div>

    <?php elseif ($step === 2): ?>

        <div style="padding:0 18px 18px">

            <form method="POST">
                <input type="hidden" name="mapping_step" value="1">

                <div class="form-grid">
                    <div class="fg">
                        <label>Kolom Model/Nama Produk *</label>
                        <select name="map_model" required>
                            <option value="">— Pilih kolom —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>" <?= $guess['model'] === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--hint)">Biasanya kolom "SKU Induk" atau "Nomor Referensi SKU", isinya nama model singkat (contoh: Luminous, Ponco, Backpack).</small>
                    </div>

                    <div class="fg">
                        <label>Kolom Warna &amp; Ukuran (Variasi) *</label>
                        <select name="map_variasi" required>
                            <option value="">— Pilih kolom —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>" <?= $guess['variasi'] === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--hint)">Biasanya kolom "Nama Variasi", formatnya "Warna,Ukuran" (contoh: Hitam,All Size). Sistem otomatis memecah jadi Warna dan Ukuran.</small>
                    </div>

                    <div class="fg">
                        <label>Kolom Jumlah *</label>
                        <select name="map_qty" required>
                            <option value="">— Pilih kolom —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>" <?= $guess['qty'] === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fg">
                        <label>Kolom Nama Pembeli/Penerima</label>
                        <select name="map_nama">
                            <option value="">— Tidak dipakai —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>" <?= $guess['nama'] === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fg">
                        <label>Kolom No. Telepon</label>
                        <select name="map_telepon">
                            <option value="">— Tidak dipakai —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>" <?= $guess['telepon'] === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fg">
                        <label>Kolom Alamat</label>
                        <select name="map_alamat">
                            <option value="">— Tidak dipakai —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>" <?= $guess['alamat'] === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fg">
                        <label>Kolom No. Pesanan</label>
                        <select name="map_pesanan">
                            <option value="">— Tidak dipakai —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>" <?= $guess['pesanan'] === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-footer">
                    <a href="import_barang_keluar.php?batal=1" class="btn">Batal</a>
                    <button type="submit" class="btn btn-p">Lanjut</button>
                </div>
            </form>
        </div>

    <?php elseif ($step === 3): ?>

        <div style="padding:0 18px 18px">
            <?php if ($previewError): ?>
                <div class="flash error"><?= htmlspecialchars($previewError) ?></div>
                <a href="import_barang_keluar.php" class="btn">Upload Ulang</a>
            <?php else: ?>

                <?php
                $totalBaris      = count($previewRows);
                $totalBermasalah = count(array_filter($previewRows, function ($r) {
                    return $r['status'] !== 'ok';
                }));
                ?>

                <p style="font-size:13px; color:var(--hint); margin-bottom:14px;">
                    Ditemukan <strong><?= $totalBaris ?></strong> baris pesanan.
                    <?php if ($totalBermasalah > 0): ?>
                        <span style="color:var(--danger)"><strong><?= $totalBermasalah ?></strong> baris bermasalah</span>
                        (produk tidak ketemu, variasi tidak lengkap, atau stok tidak cukup) dan akan <strong>dilewati otomatis</strong>, sisanya tetap akan diproses.
                    <?php else: ?>
                        Semua baris cocok dengan data produk dan stok mencukupi.
                    <?php endif; ?>
                </p>

                <div class="table-wrap" style="max-height:420px; overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Model (file)</th>
                                <th>Warna / Ukuran (file)</th>
                                <th>Produk Ditemukan</th>
                                <th>Nama Pembeli</th>
                                <th>Qty</th>
                                <th>Stok Tersedia</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewRows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['model']) ?></td>
                                    <td><?= htmlspecialchars($r['warna']) ?> / <?= htmlspecialchars($r['ukuran']) ?></td>
                                    <td>
                                        <?php if ($r['produk']): ?>
                                            <?= htmlspecialchars($r['produk']['kode_sku']) ?> — <?= htmlspecialchars($r['produk']['model']) ?> <?= htmlspecialchars($r['produk']['warna']) ?> <?= htmlspecialchars($r['produk']['ukuran']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--danger)">Tidak ditemukan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['nama']) ?></td>
                                    <td><?= (int) $r['qty'] ?></td>
                                    <td><?= $r['produk'] ? (int) $r['produk']['stok'] : '—' ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'tidak_lengkap'): ?>
                                            <span class="badge b-warn">Variasi tidak lengkap</span>
                                        <?php elseif ($r['status'] === 'tidak_ditemukan'): ?>
                                            <span class="badge b-danger">Produk tidak ada</span>
                                        <?php elseif ($r['status'] === 'stok_kurang'): ?>
                                            <span class="badge b-warn">Stok kurang</span>
                                        <?php else: ?>
                                            <span class="badge b-ok">Siap diimport</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($totalBaris === 0): ?>
                                <tr><td colspan="7"><div class="empty-state"><p>Tidak ada baris valid yang terbaca dari file.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" style="margin-top:16px">
                    <input type="hidden" name="confirm_import" value="1">
                    <div class="form-footer">
                        <a href="import_barang_keluar.php?batal=1" class="btn">Batal</a>
                        <button type="submit" class="btn btn-p" <?= $totalBaris === 0 ? 'disabled' : '' ?>>
                            Konfirmasi &amp; Simpan (<?= $totalBaris - $totalBermasalah ?> baris)
                        </button>
                    </div>
                </form>

            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>