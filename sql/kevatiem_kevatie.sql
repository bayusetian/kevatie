-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 11 Sep 2026 pada 12.34
-- Versi server: 11.4.13-MariaDB
-- Versi PHP: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `kevatiem_kevatie`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `kode_pelanggan` varchar(10) DEFAULT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `no_telepon` varchar(20) NOT NULL,
  `alamat_asal` text NOT NULL,
  `alamat_pengiriman` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `kode_sku` varchar(30) NOT NULL,
  `model` enum('Prime Series','Luminous','Backpack','Nova Series','Ponco','Prime Kids') NOT NULL,
  `warna` enum('Hitam','Navy','Cream','Army','Sage','Merah','Pink') NOT NULL,
  `ukuran` enum('S','M','L','XL','All Size') NOT NULL DEFAULT 'All Size',
  `stok` int(11) NOT NULL DEFAULT 0,
  `stok_minimum` int(11) NOT NULL DEFAULT 30,
  `supplier` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `surat_order`
--

CREATE TABLE `surat_order` (
  `id` int(11) NOT NULL,
  `kode_order` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `dibuat_oleh` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `surat_order_item`
--

CREATE TABLE `surat_order_item` (
  `id` int(11) NOT NULL,
  `surat_order_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `jumlah` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `kode_transaksi` varchar(20) DEFAULT NULL,
  `tipe` enum('masuk','keluar') NOT NULL,
  `produk_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `platform` enum('Shopee','TikTok Shop','Tokopedia','Transaksi Ditempat') DEFAULT NULL,
  `pelanggan_id` int(11) DEFAULT NULL,
  `nomor_pesanan` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `kode_user` varchar(10) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','gudang') NOT NULL DEFAULT 'gudang',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_sku` (`kode_sku`);

--
-- Indeks untuk tabel `surat_order`
--
ALTER TABLE `surat_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_order` (`kode_order`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indeks untuk tabel `surat_order_item`
--
ALTER TABLE `surat_order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `surat_order_id` (`surat_order_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pelanggan_id` (`pelanggan_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `kode_user` (`kode_user`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `surat_order`
--
ALTER TABLE `surat_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `surat_order_item`
--
ALTER TABLE `surat_order_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD CONSTRAINT `detail_transaksi_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`),
  ADD CONSTRAINT `detail_transaksi_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `surat_order`
--
ALTER TABLE `surat_order`
  ADD CONSTRAINT `surat_order_ibfk_1` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `surat_order_item`
--
ALTER TABLE `surat_order_item`
  ADD CONSTRAINT `surat_order_item_ibfk_1` FOREIGN KEY (`surat_order_id`) REFERENCES `surat_order` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `surat_order_item_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transaksi_ibfk_3` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id_pelanggan`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
