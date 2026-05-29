-- Database: koperasi_pinjaman
-- Schema revisi lengkap — perbaikan logika bisnis koperasi simpan pinjam
-- Revisi: Tenor dinamis, profil dinamis, pembulatan DECIMAL, validasi dana koperasi

CREATE DATABASE IF NOT EXISTS koperasi_pinjaman CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE koperasi_pinjaman;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detail_angsuran;
DROP TABLE IF EXISTS angsuran;
DROP TABLE IF EXISTS simpanan;
DROP TABLE IF EXISTS pinjaman;
DROP TABLE IF EXISTS petugas_koperasi;
DROP TABLE IF EXISTS anggota;
DROP TABLE IF EXISTS katagori_pinjaman;
SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════
-- 1. KATAGORI PINJAMAN
-- ═══════════════════════════════════════════
CREATE TABLE katagori_pinjaman (
    id_katagori_pinjaman INT AUTO_INCREMENT PRIMARY KEY,
    nama_pinjaman VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════
-- 2. PETUGAS KOPERASI (Admin Accounts)
-- ═══════════════════════════════════════════
CREATE TABLE petugas_koperasi (
    id_petugas INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    alamat TEXT,
    no_tlp VARCHAR(30),
    tmp_lhr VARCHAR(100),
    tgl_lhr DATE,
    ket TEXT,
    -- Authentication credentials
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role VARCHAR(20) DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════
-- 3. ANGGOTA (Member Accounts)
-- ═══════════════════════════════════════════
CREATE TABLE anggota (
    id_anggota INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    alamat TEXT,
    tgl_lhr DATE,
    tmp_lhr VARCHAR(100),
    j_kel VARCHAR(20),
    -- status: Menunggu Verifikasi | Disetujui | Ditolak | Nonaktif
    status VARCHAR(50) DEFAULT 'Menunggu Verifikasi',
    no_tlp VARCHAR(30),
    ket TEXT,                           -- digunakan untuk menyimpan pekerjaan
    -- Authentication & Verification
    nik VARCHAR(50) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role VARCHAR(20) DEFAULT 'user',
    foto_ktp VARCHAR(255),
    foto_diri VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════
-- 4. SIMPANAN (Savings Transactions)
-- Aturan:
--   - Simpanan Wajib: minimal Rp 50.000/bulan (dikonfigurasi di settings)
--   - Simpanan Sukarela: bebas nominal
--   - Semua simpanan perlu upload bukti & konfirmasi admin
-- ═══════════════════════════════════════════
CREATE TABLE simpanan (
    id_simpanan INT AUTO_INCREMENT PRIMARY KEY,
    nm_simpanan VARCHAR(50) NOT NULL,   -- 'pokok' | 'wajib' | 'sukarela'
    id_anggota INT NOT NULL,
    tgl_simpanan DATE,
    besar_simpanan DECIMAL(15,2) NOT NULL,
    ket TEXT,
    bukti_transfer VARCHAR(255),
    status ENUM('Menunggu konfirmasi', 'Diterima', 'Ditolak') DEFAULT 'Menunggu konfirmasi',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_anggota) REFERENCES anggota(id_anggota) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════
-- 5. PINJAMAN (Loan Applications)
-- Aturan:
--   - Maks. 3 pinjaman aktif per anggota
--   - Total pinjaman aktif ≤ 5× total simpanan anggota
--   - Nominal ≤ dana koperasi tersedia (total simpanan - total pinjaman aktif)
--   - Tenor: ≤1jt → 3/6 bln | 1jt-3jt → 6/12 bln | >3jt → 12/24 bln
--   - Angsuran dihitung DECIMAL(15,2) untuk menghindari floating-point error
--   - Angsuran terakhir menyerap selisih pembulatan
-- ═══════════════════════════════════════════
CREATE TABLE pinjaman (
    id_pinjaman INT AUTO_INCREMENT PRIMARY KEY,
    nama_pinjaman VARCHAR(50) NOT NULL UNIQUE, -- nomor pinjaman (PJ-YYYYMM-XXXX)
    id_anggota INT NOT NULL,
    besar_pinjaman DECIMAL(15,2) NOT NULL,
    tgl_pengajuan_pinjaman DATE,
    tgl_acc_peminjam DATE,              -- tanggal persetujuan
    tgl_pinjaman DATE,                  -- tanggal pencairan
    tgl_pelunasan DATE,                 -- tanggal lunas
    id_angsuran INT NULL,
    ket TEXT,
    -- Keuangan (semua DECIMAL untuk menghindari floating-point error)
    bunga_persen DECIMAL(5,2) NOT NULL DEFAULT 1.5,
    tenor INT NOT NULL,
    total_bayar DECIMAL(15,2) NOT NULL,
    angsuran_per_bulan DECIMAL(15,2) NOT NULL,  -- angsuran bulan 1 s/d (n-1)
    penghasilan DECIMAL(15,2),
    dokumen_pendukung VARCHAR(255),
    status ENUM('Menunggu review','Disetujui','Ditolak','Dicairkan','Lunas') DEFAULT 'Menunggu review',
    alasan_penolakan TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_anggota) REFERENCES anggota(id_anggota) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════
-- 6. ANGSURAN (Repayment Transactions)
-- Flow:
--   1. Dibuat otomatis saat pinjaman disetujui (status: Belum dibayar)
--   2. User upload bukti → status: Menunggu konfirmasi
--   3. Admin approve → status: Diterima (saldo pinjaman berkurang)
--   4. Admin reject → status: Ditolak (data tersimpan, saldo tidak berubah)
-- ═══════════════════════════════════════════
CREATE TABLE angsuran (
    id_angsuran INT AUTO_INCREMENT PRIMARY KEY,
    id_katagori INT NOT NULL,
    id_anggota INT NOT NULL,
    id_pinjaman INT NULL,
    tgl_pembayaran DATE,
    angsuran_ke INT NOT NULL,
    besar_angsuran DECIMAL(15,2) NOT NULL, -- DECIMAL untuk konsistensi pembulatan
    ket TEXT,
    bukti_transfer VARCHAR(255),
    status ENUM('Belum dibayar','Menunggu konfirmasi','Diterima','Ditolak') DEFAULT 'Belum dibayar',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_katagori) REFERENCES katagori_pinjaman(id_katagori_pinjaman) ON DELETE CASCADE,
    FOREIGN KEY (id_anggota) REFERENCES anggota(id_anggota) ON DELETE CASCADE,
    FOREIGN KEY (id_pinjaman) REFERENCES pinjaman(id_pinjaman) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════
-- 7. DETAIL ANGSURAN (Jadwal & Denda)
-- ═══════════════════════════════════════════
CREATE TABLE detail_angsuran (
    id_angsuran INT PRIMARY KEY,
    tgl_jatuh_tempo DATE,
    besar_angsuran DECIMAL(15,2) NOT NULL,
    ket TEXT,
    -- Denda keterlambatan
    jumlah_hari_terlambat INT DEFAULT 0,
    denda_tarif_per_hari DECIMAL(15,2) DEFAULT 0,
    denda_total DECIMAL(15,2) DEFAULT 0,
    status_denda ENUM('Belum Dibayar','Dibayar') DEFAULT 'Belum Dibayar',
    tanggal_denda DATE NULL,
    tanggal_bayar_denda DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_angsuran) REFERENCES angsuran(id_angsuran) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════
-- SEED DATA AWAL
-- ═══════════════════════════════════════════

-- Kategori pinjaman
INSERT INTO katagori_pinjaman (id_katagori_pinjaman, nama_pinjaman) VALUES (1, 'Pinjaman Flat');

-- Admin default (password: "password")
INSERT INTO petugas_koperasi (id_petugas, nama, alamat, no_tlp, tmp_lhr, tgl_lhr, ket, username, password, email, role) VALUES
(1, 'Admin Koperasi', 'Kantor Koperasi', '081234567890', 'Jakarta', '1990-01-01', 'Admin Utama Koperasi',
 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin@koperasi.test', 'admin');

-- Catatan Konfigurasi Sistem (disimpan di config/settings.php):
-- max_active_loans        = 3       (maks. 3 pinjaman aktif per anggota)
-- simpanan_wajib_minimal  = 50000   (min. simpanan wajib Rp 50.000/bulan)
-- default_bunga_persen    = 1.5     (bunga flat 1.5%/bulan)
-- denda_per_hari          = 5000    (denda keterlambatan Rp 5.000/hari)
-- Dana tersedia koperasi  = total simpanan diterima - total pinjaman aktif (dicairkan)
