-- Database: koperasi

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    nik VARCHAR(50) NOT NULL UNIQUE,
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    jenis_kelamin VARCHAR(20),
    alamat TEXT,
    no_hp VARCHAR(30),
    email VARCHAR(150) NOT NULL UNIQUE,
    pekerjaan VARCHAR(100),
    penghasilan DECIMAL(15,2),
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    status_verifikasi VARCHAR(50) DEFAULT 'Menunggu Verifikasi',
    foto_ktp VARCHAR(255),
    foto_diri VARCHAR(255),
    created_at DATETIME,
    updated_at DATETIME
);

CREATE TABLE IF NOT EXISTS simpanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    jenis_simpanan ENUM('pokok', 'wajib', 'sukarela') NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    bukti_transfer VARCHAR(255),
    status ENUM('Menunggu konfirmasi', 'Diterima', 'Ditolak') DEFAULT 'Menunggu konfirmasi',
    keterangan TEXT,
    tanggal_transaksi DATE,
    created_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS pinjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nomor_pinjaman VARCHAR(50) NOT NULL UNIQUE,
    nominal DECIMAL(15,2) NOT NULL,
    bunga_persen DECIMAL(5,2) NOT NULL,
    tenor INT NOT NULL,
    total_bayar DECIMAL(15,2) NOT NULL,
    angsuran_per_bulan DECIMAL(15,2) NOT NULL,
    tujuan TEXT,
    penghasilan DECIMAL(15,2),
    catatan TEXT,
    dokumen_pendukung VARCHAR(255),
    status ENUM('Menunggu review', 'Disetujui', 'Ditolak', 'Dicairkan', 'Lunas') DEFAULT 'Menunggu review',
    alasan_penolakan TEXT,
    tanggal_pengajuan DATE,
    tanggal_disetujui DATE,
    tanggal_pencairan DATE,
    created_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS angsuran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pinjaman_id INT NOT NULL,
    angsuran_ke INT NOT NULL,
    jatuh_tempo DATE,
    nominal DECIMAL(15,2) NOT NULL,
    tanggal_bayar DATE,
    bukti_transfer VARCHAR(255),
    status ENUM('Belum dibayar', 'Menunggu konfirmasi', 'Diterima', 'Ditolak') DEFAULT 'Belum dibayar',
    keterangan TEXT,
    created_at DATETIME,
    FOREIGN KEY (pinjaman_id) REFERENCES pinjaman(id)
);

CREATE TABLE IF NOT EXISTS transaksi_kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipe ENUM('masuk', 'keluar') NOT NULL,
    kategori VARCHAR(50),
    nominal DECIMAL(15,2) NOT NULL,
    keterangan TEXT,
    tanggal DATE,
    created_at DATETIME
);

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(50) PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS denda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    angsuran_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    jumlah_hari INT NOT NULL DEFAULT 0,
    tarif_per_hari DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_denda DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('Belum Dibayar', 'Dibayar') DEFAULT 'Belum Dibayar',
    tanggal_denda DATE NOT NULL,
    tanggal_bayar DATE NULL,
    created_at DATETIME,
    updated_at DATETIME,
    INDEX idx_denda_user_status (user_id, status),
    FOREIGN KEY (angsuran_id) REFERENCES angsuran(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    judul VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME,
    INDEX idx_notifications_user_read (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    aktivitas VARCHAR(255) NOT NULL,
    created_at DATETIME,
    INDEX idx_activity_logs_user_created (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO settings (name, value) VALUES
('default_bunga_persen', '2'),
('max_active_loans', '1'),
('denda_per_hari', '5000'),
('reminder_days_before_due', '3')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Seed users (password: "password")
INSERT INTO users (nama, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, no_hp, email, pekerjaan, penghasilan, username, password, role, status_verifikasi, foto_ktp, foto_diri, created_at, updated_at) VALUES
('Admin Koperasi', '0000000000000000', 'Jakarta', '1990-01-01', 'Laki-laki', 'Kantor Koperasi', '081234567890', 'admin@koperasi.test', 'Admin', 0, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Disetujui', '', '', NOW(), NOW()),
('Siti Rahma', '3174010101010001', 'Bandung', '1994-03-12', 'Perempuan', 'Jl. Melati No 10', '081298765432', 'siti@example.com', 'Karyawan', 5000000, 'siti', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Disetujui', '', '', NOW(), NOW()),
('Budi Santoso', '3174010101010002', 'Surabaya', '1988-07-21', 'Laki-laki', 'Jl. Anggrek No 22', '081277788899', 'budi@example.com', 'Wiraswasta', 7000000, 'budi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Disetujui', '', '', NOW(), NOW());

-- Seed simpanan
INSERT INTO simpanan (user_id, jenis_simpanan, nominal, bukti_transfer, status, keterangan, tanggal_transaksi, created_at) VALUES
(2, 'pokok', 500000, '', 'Diterima', 'Simpanan pokok awal', CURDATE(), NOW()),
(2, 'wajib', 200000, '', 'Diterima', 'Simpanan wajib', CURDATE(), NOW()),
(3, 'sukarela', 150000, '', 'Diterima', 'Simpanan sukarela', CURDATE(), NOW());

-- Seed pinjaman
INSERT INTO pinjaman (user_id, nomor_pinjaman, nominal, bunga_persen, tenor, total_bayar, angsuran_per_bulan, tujuan, penghasilan, status, alasan_penolakan, tanggal_pengajuan, tanggal_disetujui, tanggal_pencairan, created_at) VALUES
(2, 'PJ-202405-0001', 3000000, 2.00, 6, 3360000, 560000, 'Modal usaha', 5000000, 'Dicairkan', '', CURDATE(), CURDATE(), CURDATE(), NOW()),
(3, 'PJ-202405-0002', 2000000, 2.00, 4, 2160000, 540000, 'Biaya pendidikan', 7000000, 'Menunggu review', '', CURDATE(), NULL, NULL, NOW());

-- Seed angsuran for pinjaman id 1 (as example)
INSERT INTO angsuran (pinjaman_id, angsuran_ke, jatuh_tempo, nominal, status, created_at) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 560000, 'Belum dibayar', NOW()),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 2 MONTH), 560000, 'Belum dibayar', NOW()),
(1, 3, DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 560000, 'Belum dibayar', NOW()),
(1, 4, DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 560000, 'Belum dibayar', NOW()),
(1, 5, DATE_ADD(CURDATE(), INTERVAL 5 MONTH), 560000, 'Belum dibayar', NOW()),
(1, 6, DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 560000, 'Belum dibayar', NOW());

-- Seed kas
INSERT INTO transaksi_kas (tipe, kategori, nominal, keterangan, tanggal, created_at) VALUES
('masuk', 'simpanan', 850000, 'Total simpanan awal', CURDATE(), NOW()),
('keluar', 'pinjaman', 3000000, 'Pencairan pinjaman', CURDATE(), NOW());
