# Panduan Penggunaan Sistem ARTHAJAYA
## Sistem Manajemen Koperasi Simpan Pinjam Modern

Selamat datang di Buku Petunjuk Operasional **ARTHAJAYA**. Panduan ini dirancang untuk memudahkan Anda (dan penguji/guru) dalam mengoperasikan seluruh fitur sistem.

---

### 🔑 1. Data Akses Login
Untuk keperluan pengujian, gunakan akun-akun di bawah ini:

| Peran | Alamat Email | Kata Sandi |
| :--- | :--- | :--- |
| **Administrator** | `admin@arthajaya.com` | `password123` |
| **Bendahara** | `bendahara@arthajaya.com` | `password123` |
| **Anggota** | `anggota@arthajaya.com` | `password123` |

---

### 🚀 2. Alur Operasional Sistem

#### **Langkah 1: Mengakses Landing Page**
Buka aplikasi di browser. Anda akan disambut oleh halaman depan yang menjelaskan keunggulan ARTHAJAYA.
- Klik tombol **"Masuk"** di pojok kanan atas atau tombol **"Bergabung Sekarang"** di tengah layar.
- `[FOTO: Tampilan Landing Page]`

#### **Langkah 2: Melakukan Login**
Masukkan email dan password sesuai daftar di atas.
- Sistem akan memvalidasi data dan mengarahkan Anda ke Dashboard sesuai peran Anda.
- `[FOTO: Tampilan Halaman Login]`

#### **Langkah 3: Dashboard Utama**
Setelah masuk, Anda akan melihat ringkasan keuangan koperasi.
- **Admin/Bendahara**: Melihat total anggota, total simpanan, dan total pinjaman aktif seluruh koperasi.
- **Anggota**: Melihat saldo pribadi dan status pinjaman sendiri.
- `[FOTO: Tampilan Dashboard Overview]`

#### **Langkah 4: Manajemen Anggota (Hanya Admin/Bendahara)**
Klik menu **"Anggota"** di sidebar kiri.
- Anda dapat melihat daftar anggota, mencari anggota berdasarkan nama, dan memantau status keanggotaan mereka (Aktif/Nonaktif).
- `[FOTO: Tampilan Tabel Anggota]`

#### **Langkah 5: Manajemen Simpanan**
Klik menu **"Simpanan"** di sidebar kiri.
- Di sini tampil saldo untuk 3 jenis simpanan: **Pokok, Wajib, dan Sukarela**.
- Terdapat riwayat setoran dan penarikan yang tercatat secara otomatis.
- `[FOTO: Tampilan Halaman Simpanan]`

#### **Langkah 6: Manajemen Pinjaman & Cicilan**
Klik menu **"Pinjaman"** di sidebar kiri.
- Di halaman ini, Anda bisa melihat detail pinjaman aktif, persentase pelunasan (progress bar), serta jadwal angsuran di masa mendatang.
- Riwayat cicilan yang sudah dibayar akan tampil dengan status "Lunas".
- `[FOTO: Tampilan Halaman Pinjaman]`

---

### 🛠️ 3. Keunggulan Teknis
Berikan penjelasan ini jika guru bertanya tentang teknologi yang digunakan:
1. **Frontend**: React + TypeScript (untuk kode yang lebih aman dan terstruktur).
2. **Backend**: Supabase (Database PostgreSQL dengan keamanan Row Level Security).
3. **Desain**: Tailwind CSS v4 dengan gaya *Glassmorphism* (efek kaca transparan).
4. **Responsivitas**: Aplikasi bisa dibuka di Laptop, Tablet, maupun HP dengan tampilan yang tetap rapi.

---
**Dibuat oleh: [Nama Anda]**
**Tahun: 2026**
