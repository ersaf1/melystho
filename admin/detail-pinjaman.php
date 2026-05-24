<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$authUser = current_user();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, u.nama, u.id AS anggota_id FROM pinjaman p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$id]);
$pinjaman = $stmt->fetch();
if (!$pinjaman) {
    set_flash('danger', 'Pinjaman tidak ditemukan.');
    redirect('/admin/pinjaman.php');
}
sync_late_fines((int)$pinjaman['anggota_id']);

if (is_post()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $bunga = (float)($_POST['bunga_persen'] ?? $pinjaman['bunga_persen']);
        $tenor = (int)($_POST['tenor'] ?? $pinjaman['tenor']);
        if ($bunga < 0 || $tenor <= 0) {
            set_flash('danger', 'Bunga dan tenor tidak valid.');
            redirect('/admin/detail-pinjaman.php?id=' . $id);
        }
        $totalBunga = $pinjaman['nominal'] * ($bunga / 100) * $tenor;
        $totalBayar = $pinjaman['nominal'] + $totalBunga;
        $angsuran = $tenor > 0 ? $totalBayar / $tenor : 0;
        $stmt = $pdo->prepare("UPDATE pinjaman SET bunga_persen = ?, tenor = ?, total_bayar = ?, angsuran_per_bulan = ?, status = 'Disetujui', tanggal_disetujui = CURDATE() WHERE id = ?");
        $stmt->execute([$bunga, $tenor, $totalBayar, $angsuran, $id]);
        create_notification((int)$pinjaman['anggota_id'], 'Pinjaman disetujui', 'Pinjaman ' . $pinjaman['nomor_pinjaman'] . ' disetujui dengan angsuran ' . format_rupiah($angsuran) . ' per bulan.');
        log_activity((int)$authUser['id'], 'Approve pinjaman ' . $pinjaman['nomor_pinjaman']);
        set_flash('success', 'Pinjaman disetujui.');
    } elseif ($action === 'reject') {
        $alasan = trim($_POST['alasan_penolakan'] ?? '');
        $stmt = $pdo->prepare("UPDATE pinjaman SET status = 'Ditolak', alasan_penolakan = ? WHERE id = ?");
        $stmt->execute([$alasan, $id]);
        create_notification((int)$pinjaman['anggota_id'], 'Pinjaman ditolak', 'Pinjaman ' . $pinjaman['nomor_pinjaman'] . ' ditolak. ' . ($alasan !== '' ? 'Alasan: ' . $alasan : 'Silakan hubungi admin untuk informasi lebih lanjut.'));
        log_activity((int)$authUser['id'], 'Menolak pinjaman ' . $pinjaman['nomor_pinjaman']);
        set_flash('success', 'Pinjaman ditolak.');
    } elseif ($action === 'disburse') {
        if ($pinjaman['status'] !== 'Disetujui') {
            set_flash('danger', 'Pinjaman belum disetujui.');
            redirect('/admin/detail-pinjaman.php?id=' . $id);
        }
        $tanggal = $_POST['tanggal_pencairan'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("UPDATE pinjaman SET status = 'Dicairkan', tanggal_pencairan = ? WHERE id = ?");
        $stmt->execute([$tanggal, $id]);

        $exists = $pdo->prepare("SELECT COUNT(*) AS total FROM angsuran WHERE pinjaman_id = ?");
        $exists->execute([$id]);
        if ((int)$exists->fetch()['total'] === 0) {
            for ($i = 1; $i <= (int)$pinjaman['tenor']; $i++) {
                $jatuhTempo = date('Y-m-d', strtotime("+{$i} month", strtotime($tanggal)));
                $stmt = $pdo->prepare("INSERT INTO angsuran (pinjaman_id, angsuran_ke, jatuh_tempo, nominal, status, created_at) VALUES (?, ?, ?, ?, 'Belum dibayar', NOW())");
                $stmt->execute([$id, $i, $jatuhTempo, $pinjaman['angsuran_per_bulan']]);
            }
        }

        $pdo->prepare("INSERT INTO transaksi_kas (tipe, kategori, nominal, keterangan, tanggal, created_at) VALUES ('keluar', 'pinjaman', ?, 'Pencairan pinjaman', ?, NOW())")
            ->execute([$pinjaman['nominal'], $tanggal]);
        log_activity((int)$authUser['id'], 'Mencairkan pinjaman ' . $pinjaman['nomor_pinjaman']);
        set_flash('success', 'Pinjaman dicairkan dan jadwal angsuran dibuat.');
    }
    redirect('/admin/detail-pinjaman.php?id=' . $id);
}

$canDisburse = $pinjaman['status'] === 'Disetujui';
$scheduleStmt = $pdo->prepare("
    SELECT a.*, d.jumlah_hari, d.total_denda, d.status AS status_denda
    FROM angsuran a
    LEFT JOIN denda d ON d.angsuran_id = a.id
    WHERE a.pinjaman_id = ?
    ORDER BY a.angsuran_ke ASC
");
$scheduleStmt->execute([$pinjaman['id']]);
$schedule = $scheduleStmt->fetchAll();

$page_title = 'Detail Pinjaman';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="card card-stat p-4 mb-4">
    <div class="row g-3">
        <div class="col-md-4"><strong>No Pinjaman:</strong> <?= e($pinjaman['nomor_pinjaman']); ?></div>
        <div class="col-md-4"><strong>Anggota:</strong> <?= e($pinjaman['nama']); ?></div>
        <div class="col-md-4"><strong>Status:</strong> <span class="badge bg-secondary"><?= e($pinjaman['status']); ?></span></div>
        <div class="col-md-4"><strong>Nominal:</strong> <?= format_rupiah($pinjaman['nominal']); ?></div>
        <div class="col-md-4"><strong>Tenor:</strong> <?= e($pinjaman['tenor']); ?> bulan</div>
        <div class="col-md-4"><strong>Bunga:</strong> <?= e($pinjaman['bunga_persen']); ?>%</div>
        <div class="col-md-4"><strong>Angsuran/Bulan:</strong> <?= format_rupiah($pinjaman['angsuran_per_bulan']); ?></div>
        <div class="col-md-4"><strong>Total Bayar:</strong> <?= format_rupiah($pinjaman['total_bayar']); ?></div>
        <div class="col-md-12"><strong>Tujuan:</strong> <?= e($pinjaman['tujuan']); ?></div>
        <?php if (!empty($pinjaman['penghasilan'])): ?>
            <div class="col-md-12"><strong>Penghasilan/Bulan:</strong> <?= format_rupiah($pinjaman['penghasilan']); ?></div>
        <?php endif; ?>
        <?php if (!empty($pinjaman['catatan'])): ?>
            <div class="col-md-12"><strong>Catatan:</strong> <?= e($pinjaman['catatan']); ?></div>
        <?php endif; ?>
        <?php if ($pinjaman['status'] === 'Ditolak' && !empty($pinjaman['alasan_penolakan'])): ?>
            <div class="col-md-12"><strong>Alasan Penolakan:</strong> <?= e($pinjaman['alasan_penolakan']); ?></div>
        <?php endif; ?>
        <?php if (!empty($pinjaman['dokumen_pendukung'])): ?>
            <div class="col-md-12">
                <strong>Dokumen Pendukung:</strong>
                <a href="/uploads/dokumen_pinjaman/<?= e($pinjaman['dokumen_pendukung']); ?>" target="_blank">Lihat Dokumen</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="form-section">
            <h5 class="mb-3">Keputusan Pinjaman</h5>
            <form method="post">
                <input type="hidden" name="action" value="approve">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Bunga (%)</label>
                        <input type="number" name="bunga_persen" class="form-control" step="0.1" value="<?= e($pinjaman['bunga_persen']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tenor (bulan)</label>
                        <input type="number" name="tenor" class="form-control" value="<?= e($pinjaman['tenor']); ?>">
                    </div>
                </div>
                <button class="btn btn-success mt-3">Setujui</button>
            </form>
            <hr>
            <form method="post">
                <input type="hidden" name="action" value="reject">
                <label class="form-label">Alasan Penolakan</label>
                <textarea name="alasan_penolakan" class="form-control" rows="2"></textarea>
                <button class="btn btn-danger mt-3">Tolak</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-section">
            <h5 class="mb-3">Pencairan</h5>
            <form method="post">
                <input type="hidden" name="action" value="disburse">
                <div class="mb-2">
                    <label class="form-label">Tanggal Pencairan</label>
                    <input type="date" name="tanggal_pencairan" class="form-control" value="<?= e($pinjaman['tanggal_pencairan'] ?: date('Y-m-d')); ?>">
                </div>
                <button class="btn btn-primary" <?= $canDisburse ? '' : 'disabled'; ?>>Cairkan Pinjaman</button>
                <?php if (!$canDisburse): ?>
                    <small class="text-muted d-block mt-2">Pinjaman harus disetujui sebelum dicairkan.</small>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<h5 class="mt-4">Jadwal Angsuran</h5>
<div class="table-responsive">
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

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
