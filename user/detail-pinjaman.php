<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM pinjaman WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user['id']]);
$pinjaman = $stmt->fetch();
if (!$pinjaman) {
    set_flash('danger', 'Pinjaman tidak ditemukan.');
    redirect('/user/pinjaman.php');
}
sync_late_fines((int)$user['id']);

$scheduleStmt = $pdo->prepare("
    SELECT a.*, d.jumlah_hari, d.total_denda, d.status AS status_denda
    FROM angsuran a
    LEFT JOIN denda d ON d.angsuran_id = a.id
    WHERE a.pinjaman_id = ?
    ORDER BY a.angsuran_ke ASC
");
$scheduleStmt->execute([$pinjaman['id']]);
$schedule = $scheduleStmt->fetchAll();

$historyStmt = $pdo->prepare("SELECT * FROM angsuran WHERE pinjaman_id = ? AND tanggal_bayar IS NOT NULL ORDER BY tanggal_bayar DESC");
$historyStmt->execute([$pinjaman['id']]);
$history = $historyStmt->fetchAll();

$page_title = 'Detail Pinjaman';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="card card-stat p-4 mb-4">
    <div class="row g-3">
        <div class="col-md-4"><strong>No Pinjaman:</strong> <?= e($pinjaman['nomor_pinjaman']); ?></div>
        <div class="col-md-4"><strong>Tanggal Pengajuan:</strong> <?= e($pinjaman['tanggal_pengajuan']); ?></div>
        <div class="col-md-4"><strong>Status:</strong> <span class="badge bg-secondary"><?= e($pinjaman['status']); ?></span></div>
        <div class="col-md-4"><strong>Nominal:</strong> <?= format_rupiah($pinjaman['nominal']); ?></div>
        <div class="col-md-4"><strong>Bunga:</strong> <?= e($pinjaman['bunga_persen']); ?>%</div>
        <div class="col-md-4"><strong>Tenor:</strong> <?= e($pinjaman['tenor']); ?> bulan</div>
        <div class="col-md-4"><strong>Angsuran/Bulan:</strong> <?= format_rupiah($pinjaman['angsuran_per_bulan']); ?></div>
        <div class="col-md-4"><strong>Total Bayar:</strong> <?= format_rupiah($pinjaman['total_bayar']); ?></div>
        <div class="col-md-12"><strong>Tujuan:</strong> <?= e($pinjaman['tujuan']); ?></div>
        <?php if (!empty($pinjaman['catatan'])): ?>
            <div class="col-md-12"><strong>Catatan:</strong> <?= e($pinjaman['catatan']); ?></div>
        <?php endif; ?>
    </div>
</div>

<h5>Jadwal Angsuran</h5>
<div class="table-responsive mb-4">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Angsuran Ke</th>
                <th>Jatuh Tempo</th>
                <th>Nominal</th>
                <th>Status</th>
                <th>Denda</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $row): ?>
                <tr>
                    <td><?= e($row['angsuran_ke']); ?></td>
                    <td><?= e($row['jatuh_tempo']); ?></td>
                    <td><?= format_rupiah($row['nominal']); ?></td>
                    <td><span class="badge-status <?= status_badge_class($row['status']); ?>"><?= e($row['status']); ?></span></td>
                    <td>
                        <?php if (!empty($row['total_denda'])): ?>
                            <strong style="color:var(--accent-red)"><?= format_rupiah($row['total_denda']); ?></strong>
                            <div style="font-size:.72rem;color:var(--text-muted)"><?= e($row['jumlah_hari']); ?> hari - <?= e($row['status_denda']); ?></div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($schedule)): ?>
                <tr><td colspan="5" class="text-muted">Jadwal belum dibuat.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h5>Riwayat Pembayaran</h5>
<div class="table-responsive mb-4">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Angsuran Ke</th>
                <th>Tanggal Bayar</th>
                <th>Nominal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= e($row['angsuran_ke']); ?></td>
                    <td><?= e($row['tanggal_bayar']); ?></td>
                    <td><?= format_rupiah($row['nominal']); ?></td>
                    <td><span class="badge bg-secondary"><?= e($row['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
                <tr><td colspan="4" class="text-muted">Belum ada pembayaran.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
