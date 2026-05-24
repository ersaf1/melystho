<?php 
$role = $authUser['role'] ?? (current_user()['role'] ?? 'user');
if ($role === 'admin'): ?>
    <div class="list-group list-group-flush">
        <a href="/admin/dashboard.php" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="/admin/anggota.php" class="list-group-item list-group-item-action">Anggota</a>
        <a href="/admin/simpanan.php" class="list-group-item list-group-item-action">Simpanan</a>
        <a href="/admin/pinjaman.php" class="list-group-item list-group-item-action">Pinjaman</a>
        <a href="/admin/angsuran.php" class="list-group-item list-group-item-action">Angsuran</a>
        <a href="/admin/laporan.php" class="list-group-item list-group-item-action">Laporan</a>
        <a href="/admin/notifikasi.php" class="list-group-item list-group-item-action">Notifikasi</a>
        <a href="/admin/audit-log.php" class="list-group-item list-group-item-action">Audit Log</a>
        <a href="/admin/pengaturan.php" class="list-group-item list-group-item-action">Pengaturan</a>
    </div>
<?php else: ?>
    <div class="list-group list-group-flush">
        <a href="/user/dashboard.php" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="/user/profil.php" class="list-group-item list-group-item-action">Profil</a>
        <a href="/user/simpanan.php" class="list-group-item list-group-item-action">Simpanan</a>
        <a href="/user/pinjaman.php" class="list-group-item list-group-item-action">Pinjaman</a>
        <a href="/user/bayar-angsuran.php" class="list-group-item list-group-item-action">Angsuran</a>
        <a href="/user/notifikasi.php" class="list-group-item list-group-item-action">Notifikasi</a>
        <a href="/user/riwayat-aktivitas.php" class="list-group-item list-group-item-action">Riwayat Aktivitas</a>
    </div>
<?php endif; ?>
