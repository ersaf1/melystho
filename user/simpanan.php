<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM simpanan WHERE user_id = ? ORDER BY tanggal_transaksi DESC");
$stmt->execute([$user['id']]);
$simpanan = $stmt->fetchAll();

$page_title = 'Simpanan';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Riwayat Simpanan</h4>
    <a href="/user/ajukan-simpanan.php" class="btn btn-primary">Ajukan Simpanan</a>
</div>

<div class="filter-bar">
    <div class="search-input-wrap">
        <i class="bi bi-search search-icon"></i>
        <input type="search" class="form-control" placeholder="Cari simpanan..." data-table-search="#simpananUserTable">
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped" id="simpananUserTable">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Nominal</th>
                <th>Status</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($simpanan as $item): ?>
                <tr>
                    <td><?= e($item['tanggal_transaksi']); ?></td>
                    <td><?= e(ucfirst($item['jenis_simpanan'])); ?></td>
                    <td><?= format_rupiah($item['nominal']); ?></td>
                    <td><span class="badge-status <?= status_badge_class($item['status']); ?>"><?= e($item['status']); ?></span></td>
                    <td><?= e($item['keterangan']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($simpanan)): ?>
                <tr><td colspan="5" class="text-muted">Belum ada simpanan.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
