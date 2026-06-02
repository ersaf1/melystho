<?php 
$role = $authUser['role'] ?? (current_user()['role'] ?? 'user');
if ($role === 'admin'): ?>
    <div class="list-group list-group-flush">
        <a href="<?= base_url('/admin/dashboard.php') ?>" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= base_url('/admin/anggota.php') ?>" class="list-group-item list-group-item-action">Anggota</a>
        <a href="<?= base_url('/admin/simpanan.php') ?>" class="list-group-item list-group-item-action">Simpanan</a>
        <a href="<?= base_url('/admin/pinjaman.php') ?>" class="list-group-item list-group-item-action">Pinjaman</a>
        <a href="<?= base_url('/admin/angsuran.php') ?>" class="list-group-item list-group-item-action">Angsuran</a>
        <a href="<?= base_url('/admin/laporan.php') ?>" class="list-group-item list-group-item-action">Laporan</a>

        <a href="<?= base_url('/admin/audit-log.php') ?>" class="list-group-item list-group-item-action">Audit Log</a>
        <a href="<?= base_url('/admin/pengaturan.php') ?>" class="list-group-item list-group-item-action">Pengaturan</a>
    </div>
<?php else: ?>
    <div class="list-group list-group-flush">
        <a href="<?= base_url('/user/dashboard.php') ?>" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= base_url('/user/profil.php') ?>" class="list-group-item list-group-item-action">Profil</a>
        <a href="<?= base_url('/user/simpanan.php') ?>" class="list-group-item list-group-item-action">Simpanan</a>
        <a href="<?= base_url('/user/pinjaman.php') ?>" class="list-group-item list-group-item-action">Pinjaman</a>
        <a href="<?= base_url('/user/bayar-angsuran.php') ?>" class="list-group-item list-group-item-action">Angsuran</a>

        <a href="<?= base_url('/user/riwayat-aktivitas.php') ?>" class="list-group-item list-group-item-action">Riwayat Aktivitas</a>
    </div>
<?php endif; ?>
