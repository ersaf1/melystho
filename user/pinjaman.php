<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM pinjaman WHERE user_id = ? ORDER BY tanggal_pengajuan DESC");
$stmt->execute([$user['id']]);
$pinjaman = $stmt->fetchAll();

$canApply = in_array($user['status_verifikasi'], ['Disetujui', 'Aktif'], true);
$page_title = 'Pinjaman';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Daftar Pinjaman</h4>
    <a href="/user/ajukan-pinjaman.php" class="btn btn-primary <?= $canApply ? '' : 'disabled'; ?>" <?= $canApply ? '' : 'aria-disabled="true" tabindex="-1"'; ?>>Ajukan Pinjaman</a>
</div>

<?php if (!$canApply): ?>
    <div class="alert alert-warning">
        Akun Anda belum diverifikasi. Anda belum bisa mengajukan pinjaman.
    </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>No Pinjaman</th>
                <th>Tanggal</th>
                <th>Nominal</th>
                <th>Tenor</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pinjaman as $item): ?>
                <tr>
                    <td><?= e($item['nomor_pinjaman']); ?></td>
                    <td><?= e($item['tanggal_pengajuan']); ?></td>
                    <td><?= format_rupiah($item['nominal']); ?></td>
                    <td><?= e($item['tenor']); ?> bulan</td>
                    <td><span class="badge bg-secondary"><?= e($item['status']); ?></span></td>
                    <td><a href="/user/detail-pinjaman.php?id=<?= e($item['id']); ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pinjaman)): ?>
                <tr><td colspan="6" class="text-muted">Belum ada pinjaman.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
