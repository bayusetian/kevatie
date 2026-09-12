<?php
// includes/helpers.php
// [BARU] File ini dibuat supaya logika "mencatat satu baris barang keluar"
// tidak ditulis dua kali: dipakai oleh input manual (barang_keluar.php)
// dan oleh fitur import massal (import_barang_keluar.php).

/**
 * Generate kode transaksi unik berdasarkan tanggal, format: ddmmyyXXX
 */
function generateKodeTransaksi(mysqli $db, string $tgl): string {
    $tglKode = date('dmY', strtotime($tgl));
    $cek = $db->prepare("SELECT COUNT(*) AS jml FROM transaksi WHERE tanggal = ?");
    $cek->bind_param('s', $tgl);
    $cek->execute();
    $urut = $cek->get_result()->fetch_assoc()['jml'] + 1;
    return $tglKode . str_pad($urut, 3, '0', STR_PAD_LEFT);
}

/**
 * Mencatat satu transaksi barang keluar: buat data pelanggan baru,
 * buat baris transaksi, dan kurangi stok produk.
 * Dipakai baik dari form manual maupun dari proses import CSV marketplace.
 *
 * Return: ['ok' => bool, 'msg' => string, 'kode_transaksi' => string|null]
 */
function catatBarangKeluar(mysqli $db, array $data): array {

    $pid          = (int) $data['produk_id'];
    $jml          = (int) $data['jumlah'];
    $plat         = trim($data['platform'] ?? '');
    $tgl          = $data['tanggal'] ?? date('Y-m-d');
    $uid          = (int) $data['user_id'];
    $namaPelanggan = trim($data['nama_pelanggan'] ?? '') ?: 'Pembeli Langsung';
    $telepon       = trim($data['no_telepon'] ?? '') ?: '-';
    $alamatAsal    = trim($data['alamat_asal'] ?? '') ?: '-';
    $alamatKirim   = trim($data['alamat_pengiriman'] ?? '') ?: '-';
    $nomorPesanan  = trim($data['nomor_pesanan'] ?? '');

    if (!$pid || $jml <= 0 || !$plat) {
        return ['ok' => false, 'msg' => 'Data produk, jumlah, atau platform tidak valid.', 'kode_transaksi' => null];
    }

    $stokSaat = $db->prepare("SELECT stok FROM produk WHERE id = ?");
    $stokSaat->bind_param('i', $pid);
    $stokSaat->execute();
    $rowStok = $stokSaat->get_result()->fetch_assoc();

    if (!$rowStok) {
        return ['ok' => false, 'msg' => 'Produk tidak ditemukan.', 'kode_transaksi' => null];
    }

    $stokNow = (int) $rowStok['stok'];
    if ($jml > $stokNow) {
        return ['ok' => false, 'msg' => "Stok tidak cukup (tersedia $stokNow, diminta $jml).", 'kode_transaksi' => null];
    }

    $db->begin_transaction();
    try {
        $cekJmlPlgn = $db->query("SELECT COUNT(*) AS jml FROM pelanggan");
        $urutPlgn = $cekJmlPlgn->fetch_assoc()['jml'] + 1;
        $kodePelanggan = 'PLG' . str_pad($urutPlgn, 4, '0', STR_PAD_LEFT);

        $insPlgn = $db->prepare("
            INSERT INTO pelanggan
            (kode_pelanggan, nama_pelanggan, no_telepon, alamat_asal, alamat_pengiriman)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insPlgn->bind_param('sssss', $kodePelanggan, $namaPelanggan, $telepon, $alamatAsal, $alamatKirim);
        $insPlgn->execute();
        $pelangganId = $insPlgn->insert_id;

        $kodeTransaksi = generateKodeTransaksi($db, $tgl);

        $st = $db->prepare("
            INSERT INTO transaksi
            (kode_transaksi, tipe, produk_id, jumlah, platform, pelanggan_id, nomor_pesanan, user_id, tanggal)
            VALUES (?, 'keluar', ?, ?, ?, ?, ?, ?, ?)
        ");
        $pesananVal = $nomorPesanan !== '' ? $nomorPesanan : null;
        $st->bind_param('siisisis', $kodeTransaksi, $pid, $jml, $plat, $pelangganId, $pesananVal, $uid, $tgl);
        $st->execute();

        $up = $db->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
        $up->bind_param('ii', $jml, $pid);
        $up->execute();

        $db->commit();

        return ['ok' => true, 'msg' => "Barang keluar $jml unit ($kodeTransaksi) berhasil dicatat.", 'kode_transaksi' => $kodeTransaksi];

    } catch (Exception $e) {
        $db->rollback();
        return ['ok' => false, 'msg' => 'Gagal simpan: ' . $e->getMessage(), 'kode_transaksi' => null];
    }
}

/**
 * Cari produk berdasarkan kode SKU (exact match, case-insensitive, trim spasi).
 * Dipakai oleh fitur import supaya baris CSV bisa dicocokkan ke produk yang benar.
 */
function cariProdukBySku(mysqli $db, string $sku): ?array {
    $sku = trim($sku);
    if ($sku === '') return null;

    $st = $db->prepare("SELECT id, kode_sku, model, warna, ukuran, stok FROM produk WHERE UPPER(kode_sku) = UPPER(?) LIMIT 1");
    $st->bind_param('s', $sku);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}