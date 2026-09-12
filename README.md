# Kevatie — Sistem Pengelolaan Stok

Aplikasi web untuk pengelolaan stok barang, dibangun dengan **PHP native** (tanpa framework)
dan **MySQL**. Dibuat untuk menggantikan pencatatan stok manual berbasis buku catatan.

---

## Fitur Utama

- Manajemen data produk
- Pencatatan barang masuk dan barang keluar
- Import data pesanan dari marketplace (CSV)
- Alert stok untuk barang yang menipis/habis
- Pembuatan surat order dengan kode transaksi unik
- Laporan stok

---

## Teknologi

- **Backend:** PHP native (prosedural, tanpa framework)
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript

---

## Struktur Proyek

```
├── assets/                    
├── config/                    
├── includes/                  
├── sql/                       # Skema database
├── index.php                  # Landing page
├── login.php                  # Halaman login
├── dashboard.php              # Ringkasan Dashboard
├── produk.php                 # Manajemen data produk
├── barang_masuk.php           # Pencatatan barang masuk
├── barang_keluar.php          # Pencatatan barang keluar
├── import_barang_keluar.php   # Import data barang keluar (CSV)
├── laporan.php                # Laporan stok
└── alert_stok.php             # Notifikasi stok menipis
