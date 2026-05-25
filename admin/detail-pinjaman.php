<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$authUser = current_user();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, p.id_pinjaman AS id, p.nama_pinjaman AS nomor_pinjaman, p.besar_pinjaman AS nominal, p.id_anggota AS user_id, u.nama, u.id_anggota AS anggota_id, p.ket AS catatan, p.ket AS tujuan FROM pinjaman p JOIN anggota u ON p.id_anggota = u.id_anggota WHERE p.id_pinjaman = ?");
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
        if ($pinjaman['status'] !== 'Menunggu review') {
            set_flash('danger', 'Hanya pinjaman berstatus menunggu yang dapat disetujui.');
            redirect('/admin/detail-pinjaman.php?id=' . $id);
        }

        $bunga = (float)($_POST['bunga_persen'] ?? $pinjaman['bunga_persen']);
        $tenor = (int)($_POST['tenor'] ?? $pinjaman['tenor']);
        if ($bunga < 0 || $tenor <= 0) {
            set_flash('danger', 'Bunga dan tenor tidak valid.');
            redirect('/admin/detail-pinjaman.php?id=' . $id);
        }
        
        $totalBunga = $pinjaman['nominal'] * ($bunga / 100) * $tenor;
        $totalBayar = $pinjaman['nominal'] + $totalBunga;
        $angsuran = $tenor > 0 ? $totalBayar / $tenor : 0;
        
        // 1. Update status of the loan to 'Dicairkan' (Active)
        $stmt = $pdo->prepare("UPDATE pinjaman SET bunga_persen = ?, tenor = ?, total_bayar = ?, angsuran_per_bulan = ?, status = 'Dicairkan', tgl_acc_peminjam = CURDATE(), tgl_pinjaman = CURDATE() WHERE id_pinjaman = ?");
        $stmt->execute([$bunga, $tenor, $totalBayar, $angsuran, $id]);

        // 2. Generate installment schedules immediately
        $exists = $pdo->prepare("SELECT COUNT(*) AS total FROM angsuran WHERE id_pinjaman = ?");
        $exists->execute([$id]);
        if ((int)$exists->fetch()['total'] === 0) {
            for ($i = 1; $i <= $tenor; $i++) {
                $jatuhTempo = date('Y-m-d', strtotime("+{$i} month"));
                
                // Insert into angsuran
                $stmt = $pdo->prepare("INSERT INTO angsuran (id_katagori, id_anggota, id_pinjaman, tgl_pembayaran, angsuran_ke, besar_angsuran, ket, status, created_at) VALUES (1, ?, ?, NULL, ?, ?, '', 'Belum dibayar', NOW())");
                $stmt->execute([$pinjaman['anggota_id'], $id, $i, $angsuran]);
                $angsuranId = (int)$pdo->lastInsertId();
                
                // Insert into detail_angsuran
                $stmt = $pdo->prepare("INSERT INTO detail_angsuran (id_angsuran, tgl_jatuh_tempo, besar_angsuran, ket, jumlah_hari_terlambat, denda_tarif_per_hari, denda_total, status_denda, tanggal_denda, tanggal_bayar_denda, created_at, updated_at) VALUES (?, ?, ?, '', 0, 0, 0, 'Belum Dibayar', NULL, NULL, NOW(), NOW())");
                $stmt->execute([$angsuranId, $jatuhTempo, $angsuran]);
            }
        }

        create_notification((int)$pinjaman['anggota_id'], 'Pinjaman disetujui & dicairkan', 'Pinjaman ' . $pinjaman['nomor_pinjaman'] . ' telah disetujui dan dicairkan dengan angsuran ' . format_rupiah($angsuran) . ' per bulan.');
        log_activity((int)$authUser['id'], 'Menyetujui & mencairkan pinjaman ' . $pinjaman['nomor_pinjaman']);
        
        set_flash('success', 'Pinjaman berhasil disetujui & dicairkan. Jadwal angsuran bulanan telah dibuat otomatis.');
        
    } elseif ($action === 'reject') {
        if ($pinjaman['status'] !== 'Menunggu review') {
            set_flash('danger', 'Hanya pinjaman berstatus menunggu yang dapat ditolak.');
            redirect('/admin/detail-pinjaman.php?id=' . $id);
        }

        $alasan = trim($_POST['alasan_penolakan'] ?? '');
        $stmt = $pdo->prepare("UPDATE pinjaman SET status = 'Ditolak', alasan_penolakan = ? WHERE id_pinjaman = ?");
        $stmt->execute([$alasan, $id]);
        
        create_notification((int)$pinjaman['anggota_id'], 'Pinjaman ditolak', 'Pinjaman ' . $pinjaman['nomor_pinjaman'] . ' ditolak. ' . ($alasan !== '' ? 'Alasan: ' . $alasan : ''));
        log_activity((int)$authUser['id'], 'Menolak pinjaman ' . $pinjaman['nomor_pinjaman']);
        
        set_flash('success', 'Pinjaman ditolak.');
    }
    redirect('/admin/detail-pinjaman.php?id=' . $id);
}

$scheduleStmt = $pdo->prepare("
    SELECT 
        a.id_angsuran AS id,
        a.angsuran_ke,
        da.tgl_jatuh_tempo AS jatuh_tempo,
        a.besar_angsuran AS nominal,
        a.tgl_pembayaran AS tanggal_bayar,
        a.status,
        da.jumlah_hari_terlambat AS jumlah_hari,
        da.denda_total,
        da.status_denda AS status_denda
    FROM angsuran a
    JOIN detail_angsuran da ON da.id_angsuran = a.id_angsuran
    WHERE a.id_pinjaman = ?
    ORDER BY a.angsuran_ke ASC
");
$scheduleStmt->execute([$id]);
$schedule = $scheduleStmt->fetchAll();

$page_title = 'Detail Pinjaman';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="card card-stat p-4 mb-4">
    <div class="row g-3">
        <div class="col-md-4"><strong>No Pinjaman:</strong> <?= e($pinjaman['nomor_pinjaman']); ?></div>
        <div class="col-md-4"><strong>Anggota:</strong> <?= e($pinjaman['nama']); ?></div>
        <div class="col-md-4"><strong>Status:</strong> <span class="badge-status <?= status_badge_class($pinjaman['status']); ?>"><?= e($pinjaman['status']); ?></span></div>
        <div class="col-md-4"><strong>Nominal:</strong> <?= format_rupiah($pinjaman['nominal']); ?></div>
        <div class="col-md-4"><strong>Tenor:</strong> <?= e($pinjaman['tenor']); ?> bulan</div>
        <div class="col-md-4"><strong>Bunga:</strong> <?= e($pinjaman['bunga_persen']); ?>%</div>
        <div class="col-md-4"><strong>Angsuran/Bulan:</strong> <?= format_rupiah($pinjaman['angsuran_per_bulan']); ?></div>
        <div class="col-md-4"><strong>Total Bayar:</strong> <?= format_rupiah($pinjaman['total_bayar']); ?></div>
        <?php if (in_array($pinjaman['status'], ['Dicairkan', 'Lunas'], true) && !empty($pinjaman['tgl_pinjaman'])): ?>
            <div class="col-md-4"><strong>Tanggal Pinjam:</strong> <?= e(date('d/m/Y', strtotime($pinjaman['tgl_pinjaman']))); ?></div>
            <div class="col-md-4"><strong>Tanggal Pengembalian:</strong> <?= e(date('d/m/Y', strtotime("+{$pinjaman['tenor']} month", strtotime($pinjaman['tgl_pinjaman'])))); ?></div>
        <?php endif; ?>
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
                <a href="<?= base_url('/uploads/dokumen_pinjaman/' . e($pinjaman['dokumen_pendukung'])) ?>" target="_blank">Lihat Dokumen</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($pinjaman['status'] === 'Menunggu review'): ?>
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="form-section">
            <h5 class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Setujui Pinjaman</h5>
            <p class="text-muted" style="font-size: .83rem;">Menyetujui pengajuan ini akan langsung mencairkan dana pinjaman dan membuat seluruh jadwal angsuran secara otomatis.</p>
            <form method="post">
                <input type="hidden" name="action" value="approve">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Bunga (%) per Bulan</label>
                        <input type="number" name="bunga_persen" class="form-control" step="0.1" value="<?= e($pinjaman['bunga_persen']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tenor (Bulan)</label>
                        <input type="number" name="tenor" class="form-control" value="<?= e($pinjaman['tenor']); ?>" required readonly disabled>
                        <input type="hidden" name="tenor" value="<?= e($pinjaman['tenor']); ?>">
                    </div>
                </div>
                <button class="btn btn-success mt-3"><i class="bi bi-check2-all me-1"></i>Setujui &amp; Cairkan Instan</button>
            </form>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="form-section">
            <h5 class="mb-3"><i class="bi bi-x-circle-fill text-danger me-2"></i>Tolak Pinjaman</h5>
            <p class="text-muted" style="font-size: .83rem;">Berikan alasan penolakan yang jelas agar dapat dipantau oleh anggota koperasi.</p>
            <form method="post">
                <input type="hidden" name="action" value="reject">
                <div class="mb-3">
                    <label class="form-label">Alasan Penolakan</label>
                    <textarea name="alasan_penolakan" class="form-control" rows="2" placeholder="Tulis alasan di sini..." required></textarea>
                </div>
                <button class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Tolak Pengajuan</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<h5 class="mt-4"><i class="bi bi-calendar3 me-2 text-primary-custom"></i>Jadwal Angsuran Pinjaman</h5>
<div class="table-responsive bg-white rounded shadow-sm border p-2">
    <table class="table table-striped table-hover mb-0">
        <thead>
            <tr>
                <th>Angsuran Ke</th>
                <th>Jatuh Tempo</th>
                <th>Nominal</th>
                <th>Tanggal Bayar</th>
                <th>Status</th>
                <th>Denda</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $row): ?>
                <tr>
                    <td class="font-monospace">#<?= e($row['angsuran_ke']); ?></td>
                    <td><?= e(date('d/m/Y', strtotime($row['jatuh_tempo']))); ?></td>
                    <td class="fw-bold"><?= format_rupiah($row['nominal']); ?></td>
                    <td><?= $row['tanggal_bayar'] ? e(date('d/m/Y', strtotime($row['tanggal_bayar']))) : '<span class="text-muted">-</span>'; ?></td>
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
                <tr><td colspan="6" class="text-muted text-center py-4">Jadwal belum dibuat. Pinjaman harus disetujui terlebih dahulu.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
