<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$filterUser = (int)($_GET['user_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';

$conditions = [];
$params = [];
if ($filterUser) {
    $conditions[] = 'p.user_id = ?';
    $params[] = $filterUser;
}
if ($filterStatus !== '') {
    $conditions[] = 'p.status = ?';
    $params[] = $filterStatus;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare("SELECT p.*, u.nama FROM pinjaman p JOIN users u ON p.user_id = u.id $where ORDER BY p.created_at DESC");
$stmt->execute($params);
$pinjaman = $stmt->fetchAll();

$users = $pdo->query("SELECT id, nama FROM users WHERE role = 'user' ORDER BY nama ASC")->fetchAll();

$page_title = 'Manajemen Pinjaman';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="card card-stat p-3 mb-3">
    <h5 class="mb-3">Filter Pinjaman</h5>
    <form class="row g-2">
        <div class="col-md-4">
            <select name="user_id" class="form-select">
                <option value="">Semua Anggota</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= e($u['id']); ?>" <?= $filterUser === (int)$u['id'] ? 'selected' : ''; ?>><?= e($u['nama']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <?php foreach (['Menunggu review', 'Disetujui', 'Ditolak', 'Dicairkan', 'Lunas'] as $status): ?>
                    <option value="<?= e($status); ?>" <?= $filterStatus === $status ? 'selected' : ''; ?>><?= e($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-grid">
            <button class="btn btn-outline-primary">Filter</button>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>No Pinjaman</th>
                <th>Anggota</th>
                <th>Nominal</th>
                <th>Tenor</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pinjaman as $row): ?>
                <tr>
                    <td><?= e($row['nomor_pinjaman']); ?></td>
                    <td><?= e($row['nama']); ?></td>
                    <td><?= format_rupiah($row['nominal']); ?></td>
                    <td><?= e($row['tenor']); ?> bulan</td>
                    <td><span class="badge bg-secondary"><?= e($row['status']); ?></span></td>
                    <td><a href="/admin/detail-pinjaman.php?id=<?= e($row['id']); ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pinjaman)): ?>
                <tr><td colspan="6" class="text-muted">Belum ada pengajuan.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
