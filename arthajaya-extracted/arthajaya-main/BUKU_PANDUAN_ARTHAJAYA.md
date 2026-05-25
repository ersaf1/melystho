# BUKU PANDUAN PENGGUNAAN SISTEM
# ARTHAJAYA — Sistem Manajemen Koperasi Simpan Pinjam

---

**Nama Sistem** : ARTHAJAYA  
**Versi**        : 1.0.0  
**Tahun**        : 2026  
**Dibuat oleh**  : [Nama Anda]  
**Kelas**        : [Kelas Anda]  

---

## DAFTAR ISI

1. [Pendahuluan](#1-pendahuluan)
2. [Alur Umum Sistem](#2-alur-umum-sistem)
3. [Panduan Role: Administrator](#3-panduan-role-administrator)
4. [Panduan Role: Bendahara](#4-panduan-role-bendahara)
5. [Panduan Role: Anggota](#5-panduan-role-anggota)
6. [Data Akses Login (Uji Coba)](#6-data-akses-login-uji-coba)

---

## 1. PENDAHULUAN

ARTHAJAYA adalah sistem manajemen koperasi simpan pinjam berbasis web yang dirancang untuk memudahkan pengelolaan keuangan koperasi secara digital, transparan, dan akuntabel.

Sistem ini memiliki **3 peran pengguna (role)**:

| Role | Deskripsi |
|---|---|
| **Administrator** | Pengelola penuh sistem. Dapat menambah anggota, mengelola semua transaksi, dan menyetujui pinjaman. |
| **Bendahara** | Bertugas mencatat transaksi keuangan harian (setoran, penarikan, pembayaran angsuran) dan memproses pinjaman. |
| **Anggota** | Pengguna akhir. Dapat melihat saldo pribadi, mengajukan pinjaman, dan mencatat setoran mandiri. |

---

## 2. ALUR UMUM SISTEM

```
[ Landing Page ]
       ↓
[ Halaman Login / Daftar ]
       ↓
[ Dashboard (sesuai role) ]
       ↓
  ┌────┴────┐
  ↓         ↓
Admin/    Anggota
Bendahara
```

Alur operasional koperasi secara umum:

1. **Anggota mendaftar** → Admin mengaktifkan akun
2. **Bendahara mencatat simpanan** (pokok, wajib, sukarela)
3. **Anggota mengajukan pinjaman** → Admin/Bendahara menyetujui
4. **Bendahara mencatat pembayaran angsuran** setiap bulan
5. **Admin memantau laporan** melalui dashboard overview

---

## 3. PANDUAN ROLE: ADMINISTRATOR

### 3.1 Login sebagai Administrator

1. Buka aplikasi di browser
2. Klik tombol **"Masuk"** di halaman utama
3. Masukkan email dan password Administrator
4. Klik **"Masuk Sekarang"**

> ✏️ *Upload screenshot: Tampilan halaman login dengan email admin diisi*

---

### 3.2 Dashboard Overview (Admin)

Setelah login, Admin akan melihat halaman **Dashboard Overview** yang menampilkan:
- Total jumlah anggota koperasi
- Total saldo simpanan seluruh anggota
- Total pinjaman aktif
- Grafik performa keuangan bulanan
- Daftar transaksi terbaru

> ✏️ *Upload screenshot: Tampilan Dashboard Overview setelah login sebagai Admin*

---

### 3.3 Menambah Anggota Baru

1. Klik menu **"Anggota"** di sidebar kiri
2. Klik tombol **"Tambah Anggota"** (pojok kanan atas)
3. Isi formulir:
   - Nama Lengkap
   - Email
   - Password Sementara
   - Nomor Telepon
   - Alamat
4. Klik **"Tambah Anggota"**
5. Sistem otomatis membuat nomor anggota (contoh: AJ-2026-0001)

> ✏️ *Upload screenshot: Halaman Daftar Anggota*

> ✏️ *Upload screenshot: Modal/form Tambah Anggota yang sudah diisi*

---

### 3.4 Mengaktifkan / Menonaktifkan Anggota

1. Buka halaman **"Anggota"**
2. Cari anggota yang ingin diubah statusnya
3. Klik tombol **"Nonaktifkan"** (jika aktif) atau **"Aktifkan"** (jika nonaktif)
4. Status anggota akan berubah secara langsung

> ✏️ *Upload screenshot: Kartu anggota dengan tombol Nonaktifkan/Aktifkan*

---

### 3.5 Melihat Detail Anggota

1. Buka halaman **"Anggota"**
2. Klik tombol **"Detail"** pada kartu anggota yang ingin dilihat
3. Akan muncul popup berisi informasi lengkap anggota

> ✏️ *Upload screenshot: Modal detail anggota*

---

### 3.6 Mencatat Setoran Simpanan

1. Klik menu **"Simpanan"** di sidebar kiri
2. Pilih anggota dari dropdown **"Filter per Anggota"**
3. Klik tombol **"Setoran"**
4. Isi formulir:
   - Pilih anggota (jika belum dipilih)
   - Jenis simpanan: Pokok / Wajib / Sukarela
   - Jumlah setoran (Rp)
   - Keterangan (opsional)
5. Klik **"Simpan Setoran"**

> ✏️ *Upload screenshot: Halaman Simpanan dengan dropdown anggota*

> ✏️ *Upload screenshot: Modal form setoran yang sudah diisi*

---

### 3.7 Mencatat Penarikan Simpanan

1. Di halaman **"Simpanan"**, klik tombol **"Penarikan"**
2. Pilih anggota dari dropdown
3. Pilih jenis simpanan yang ditarik
4. Masukkan jumlah penarikan
5. Klik **"Simpan Penarikan"**

> ✏️ *Upload screenshot: Modal form penarikan*

---

### 3.8 Menyetujui atau Menolak Pinjaman

1. Klik menu **"Pinjaman"** di sidebar kiri
2. Cari pinjaman dengan status **"Menunggu"** (badge kuning)
3. Klik tombol:
   - **"Setujui"** → pinjaman diaktifkan, jadwal angsuran otomatis dibuat
   - **"Tolak"** → pinjaman ditolak
4. Status pinjaman akan berubah seketika

> ✏️ *Upload screenshot: Halaman Pinjaman dengan pinjaman berstatus Menunggu*

> ✏️ *Upload screenshot: Setelah pinjaman disetujui (status berubah jadi Aktif)*

---

### 3.9 Melihat Jadwal Angsuran

1. Di halaman **"Pinjaman"**, cari pinjaman berstatus **"Aktif"**
2. Klik tombol **"Angsuran"** di sebelah kanan baris pinjaman
3. Jadwal angsuran akan muncul di bawah baris tersebut
4. Terlihat nomor cicilan, jumlah, tanggal bayar, dan status

> ✏️ *Upload screenshot: Jadwal angsuran yang sudah terbuka (expanded)*

---

### 3.10 Membayar Angsuran

1. Buka jadwal angsuran (lihat langkah 3.9)
2. Pada cicilan yang belum dibayar (status "Belum Bayar"), klik tombol **"Bayar"**
3. Status cicilan akan berubah menjadi **"Lunas"** dengan tanggal pembayaran

> ✏️ *Upload screenshot: Tombol Bayar pada cicilan yang belum dibayar*

> ✏️ *Upload screenshot: Cicilan setelah dibayar (status Lunas)*

---

### 3.11 Menandai Pinjaman Lunas

1. Setelah semua cicilan terbayar, klik tombol **"Lunas"** pada baris pinjaman
2. Status pinjaman berubah dari "Aktif" menjadi "Lunas"

> ✏️ *Upload screenshot: Tombol Lunas pada pinjaman aktif*

---

### 3.12 Menghapus Transaksi Simpanan

1. Di halaman **"Simpanan"**, arahkan kursor ke transaksi yang ingin dihapus
2. Ikon tempat sampah (🗑️) akan muncul di sebelah kanan
3. Klik ikon tersebut → konfirmasi penghapusan
4. Transaksi terhapus dan saldo diperbarui

> ✏️ *Upload screenshot: Ikon hapus yang muncul saat hover pada transaksi*

---

### 3.13 Logout

1. Scroll ke bawah sidebar kiri
2. Klik tombol **"Keluar"** (ikon pintu)
3. Sistem akan kembali ke halaman login

> ✏️ *Upload screenshot: Tombol Keluar di sidebar*

---

## 4. PANDUAN ROLE: BENDAHARA

### 4.1 Login sebagai Bendahara

1. Buka aplikasi di browser
2. Klik **"Masuk"**
3. Masukkan email dan password Bendahara
4. Klik **"Masuk Sekarang"**

> ✏️ *Upload screenshot: Halaman login dengan email bendahara diisi*

---

### 4.2 Dashboard Overview (Bendahara)

Setelah login, Bendahara melihat ringkasan keuangan koperasi:
- Total anggota
- Total simpanan
- Total pinjaman aktif
- Grafik keuangan
- Transaksi terbaru

> ✏️ *Upload screenshot: Dashboard Overview tampilan Bendahara*

---

### 4.3 Melihat Daftar Anggota

1. Klik menu **"Anggota"** di sidebar
2. Bendahara dapat melihat seluruh daftar anggota
3. Gunakan kolom pencarian untuk mencari anggota tertentu
4. Klik **"Detail"** untuk melihat informasi lengkap anggota

> ✏️ *Upload screenshot: Halaman Daftar Anggota tampilan Bendahara*

---

### 4.4 Mencatat Setoran Simpanan

Sama seperti Admin (lihat langkah 3.6):
1. Klik menu **"Simpanan"**
2. Pilih anggota dari dropdown
3. Klik **"Setoran"** → isi form → simpan

> ✏️ *Upload screenshot: Halaman Simpanan tampilan Bendahara*

> ✏️ *Upload screenshot: Form setoran yang sudah diisi*

---

### 4.5 Mencatat Penarikan Simpanan

Sama seperti Admin (lihat langkah 3.7):
1. Klik **"Penarikan"** di halaman Simpanan
2. Pilih anggota, jenis simpanan, dan jumlah
3. Simpan

> ✏️ *Upload screenshot: Form penarikan simpanan*

---

### 4.6 Memproses Pinjaman

1. Klik menu **"Pinjaman"**
2. Lihat pinjaman berstatus **"Menunggu"**
3. Klik **"Setujui"** atau **"Tolak"**

> ✏️ *Upload screenshot: Halaman Pinjaman tampilan Bendahara dengan pinjaman pending*

---

### 4.7 Membayar Angsuran

1. Di halaman **"Pinjaman"**, klik **"Angsuran"** pada pinjaman aktif
2. Klik **"Bayar"** pada cicilan yang jatuh tempo

> ✏️ *Upload screenshot: Jadwal angsuran dengan tombol Bayar*

---

## 5. PANDUAN ROLE: ANGGOTA

### 5.1 Mendaftar sebagai Anggota Baru

1. Buka aplikasi di browser
2. Di halaman utama (Landing Page), klik **"Bergabung Sekarang"**
3. Isi formulir pendaftaran:
   - Nama Lengkap
   - Nomor Telepon
   - Alamat Email
   - Alamat Lengkap
   - Kata Sandi
   - Ulangi Kata Sandi
4. Klik **"Daftar Sekarang"**
5. Sistem otomatis membuat akun dan nomor anggota

> ✏️ *Upload screenshot: Halaman Landing Page*

> ✏️ *Upload screenshot: Halaman Daftar Anggota dengan form yang sudah diisi*

---

### 5.2 Login sebagai Anggota

1. Klik **"Masuk"** di halaman utama
2. Masukkan email dan password
3. Klik **"Masuk Sekarang"**
4. Sistem mengarahkan ke Dashboard pribadi

> ✏️ *Upload screenshot: Halaman Login dengan email anggota diisi*

---

### 5.3 Dashboard Overview (Anggota)

Setelah login, Anggota melihat **data pribadi**:
- Saldo simpanan saya
- Pinjaman aktif saya
- Transaksi terbaru saya
- Grafik keuangan

> ✏️ *Upload screenshot: Dashboard Overview tampilan Anggota (data pribadi)*

---

### 5.4 Melihat Ringkasan Simpanan

1. Klik menu **"Simpanan"** di sidebar
2. Terlihat saldo untuk 3 jenis simpanan:
   - **Simpanan Pokok** — dibayar saat pertama bergabung
   - **Simpanan Wajib** — dibayar rutin setiap bulan
   - **Simpanan Sukarela** — bebas, bisa ditarik kapan saja
3. Di bawahnya terdapat riwayat setoran dan penarikan

> ✏️ *Upload screenshot: Halaman Simpanan tampilan Anggota dengan 3 kartu saldo*

---

### 5.5 Mencatat Setoran Simpanan Mandiri

1. Di halaman **"Simpanan"**, klik tombol **"Setoran"**
2. Pilih jenis simpanan
3. Masukkan jumlah
4. Tambahkan keterangan (opsional)
5. Klik **"Simpan Setoran"**

> ✏️ *Upload screenshot: Modal form setoran anggota*

> ✏️ *Upload screenshot: Riwayat setoran setelah transaksi berhasil*

---

### 5.6 Mengajukan Pinjaman

1. Klik menu **"Pinjaman"** di sidebar
2. Klik tombol **"Ajukan Pinjaman"**
3. Isi formulir:
   - Jumlah pinjaman (Rp)
   - Tenor (6 / 12 / 24 / 36 bulan)
   - Bunga per bulan (%)
4. Perhatikan **estimasi cicilan per bulan** yang muncul otomatis
5. Klik **"Ajukan Sekarang"**
6. Status pinjaman akan menjadi **"Menunggu"** sampai disetujui Admin/Bendahara

> ✏️ *Upload screenshot: Halaman Pinjaman tampilan Anggota*

> ✏️ *Upload screenshot: Modal form pengajuan pinjaman dengan estimasi cicilan*

---

### 5.7 Memantau Status Pinjaman

1. Di halaman **"Pinjaman"**, lihat daftar pinjaman beserta statusnya:
   - 🟡 **Menunggu** — sedang diproses Admin/Bendahara
   - 🔵 **Aktif** — sudah disetujui, cicilan berjalan
   - 🟢 **Lunas** — semua cicilan sudah terbayar
   - 🔴 **Ditolak** — pengajuan tidak disetujui
2. Klik **"Angsuran"** pada pinjaman aktif untuk melihat jadwal cicilan

> ✏️ *Upload screenshot: Daftar pinjaman dengan berbagai status*

> ✏️ *Upload screenshot: Jadwal angsuran pinjaman aktif*

---

### 5.8 Logout

1. Scroll ke bawah sidebar
2. Klik **"Keluar"**

> ✏️ *Upload screenshot: Tombol Keluar di sidebar tampilan Anggota*

---

## 6. DATA AKSES LOGIN (UJI COBA)

Gunakan akun berikut untuk keperluan demonstrasi dan pengujian:

| Role | Email | Password |
|---|---|---|
| **Administrator** | admin@arthajaya.com | password123 |
| **Bendahara** | bendahara@arthajaya.com | password123 |
| **Anggota 1** | anggota@arthajaya.com | password123 |
| **Anggota 2** | budi@arthajaya.com | password123 |
| **Anggota 3** | siti@arthajaya.com | password123 |

---

## PENUTUP

Sistem ARTHAJAYA dirancang untuk mempermudah pengelolaan koperasi simpan pinjam secara digital. Dengan pembagian peran yang jelas antara Administrator, Bendahara, dan Anggota, setiap proses keuangan dapat dilakukan secara transparan dan tercatat dengan baik.

---

*Dokumen ini dibuat sebagai bagian dari laporan proyek akhir.*  
*© 2026 ARTHAJAYA Cooperative. Seluruh hak dilindungi.*
