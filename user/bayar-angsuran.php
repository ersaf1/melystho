<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];
sync_late_fines((int)$user['id']);
sync_due_reminders((int)$user['id']);

$stmt = $pdo->prepare("SELECT id_pinjaman AS id, nama_pinjaman AS nomor_pinjaman FROM pinjaman WHERE id_anggota = ? AND status IN ('Disetujui', 'Dicairkan')");
$stmt->execute([$user['id']]);
$pinjamanList = $stmt->fetchAll();

$angsuranList = [];
if (!empty($pinjamanList)) {
    $stmt = $pdo->prepare("
        SELECT 
            a.id_angsuran AS id,
            a.angsuran_ke,
            a.besar_angsuran AS nominal,
            da.tgl_jatuh_tempo AS jatuh_tempo,
            p.nama_pinjaman AS nomor_pinjaman,
            da.denda_total AS total_denda,
            da.status_denda AS status_denda
        FROM angsuran a
        JOIN detail_angsuran da ON a.id_angsuran = da.id_angsuran
        JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
        WHERE a.status IN ('Belum dibayar', 'Ditolak') 
          AND a.id_anggota = ?
          AND a.angsuran_ke = (
              SELECT MIN(a2.angsuran_ke) 
              FROM angsuran a2 
              WHERE a2.id_pinjaman = a.id_pinjaman 
                AND a2.status IN ('Belum dibayar', 'Ditolak')
          )
        ORDER BY da.tgl_jatuh_tempo ASC
    ");
    $stmt->execute([$user['id']]);
    $angsuranList = $stmt->fetchAll();
}

$dendaStmt = $pdo->prepare("
    SELECT 
        da.id_angsuran AS id,
        da.jumlah_hari_terlambat AS jumlah_hari,
        da.denda_total,
        da.status_denda AS status,
        da.created_at,
        a.angsuran_ke,
        p.nama_pinjaman AS nomor_pinjaman
    FROM detail_angsuran da
    JOIN angsuran a ON da.id_angsuran = a.id_angsuran
    JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
    WHERE a.id_anggota = ? AND da.status_denda = 'Belum Dibayar' AND da.jumlah_hari_terlambat > 0
    ORDER BY da.created_at DESC
");
$dendaStmt->execute([$user['id']]);
$dendaList = $dendaStmt->fetchAll();

if (is_post()) {
    $angsuranId = (int)($_POST['angsuran_id'] ?? 0);
    $nominal = (float)($_POST['nominal'] ?? 0);
    $tanggal = $_POST['tanggal_bayar'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');

    if ($angsuranId <= 0 || $nominal <= 0 || $tanggal === '') {
        $errors[] = 'Lengkapi data pembayaran.';
    }

    $bukti = null;
    if (empty($errors)) {
        if (!isset($_FILES['bukti_transfer'])) {
            $errors[] = 'Upload bukti transfer wajib.';
        } else {
            $bukti = upload_file($_FILES['bukti_transfer'], __DIR__ . '/../uploads/bukti_angsuran', ['image/jpeg', 'image/png']);
            if (!$bukti) {
                $errors[] = 'Upload bukti transfer wajib dalam format JPG/PNG.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id_angsuran AS id FROM angsuran WHERE id_angsuran = ? AND id_anggota = ?");
        $stmt->execute([$angsuranId, $user['id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'Angsuran tidak valid.';
        } else {
            $stmt = $pdo->prepare("UPDATE angsuran SET tgl_pembayaran = ?, bukti_transfer = ?, besar_angsuran = ?, status = 'Menunggu konfirmasi', ket = ? WHERE id_angsuran = ?");
            $stmt->execute([$tanggal, $bukti, $nominal, $catatan, $angsuranId]);
            log_activity((int)$user['id'], 'Membayar angsuran sebesar ' . format_rupiah($nominal));
            notify_admins('Angsuran baru', $user['nama'] . ' mengunggah pembayaran angsuran sebesar ' . format_rupiah($nominal) . '.');
            set_flash('success', 'Pembayaran berhasil diupload. Menunggu konfirmasi admin.');
            redirect('/user/bayar-angsuran.php');
        }
    }
}

$page_title = 'Pembayaran Angsuran';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<?php if (!empty($dendaList)): ?>
    <div class="panel mb-4">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-receipt-cutoff me-2 text-red"></i>Denda Belum Dibayar</span>
            <span style="font-size:.78rem;color:var(--text-muted)">Total <?= format_rupiah(array_sum(array_column($dendaList, 'denda_total'))); ?></span>
        </div>
        <div class="table-responsive panel-body p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pinjaman</th>
                        <th>Angsuran</th>
                        <th>Terlambat</th>
                        <th>Denda</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dendaList as $fine): ?>
                        <tr>
                            <td><?= e($fine['nomor_pinjaman']); ?></td>
                            <td>Ke-<?= e($fine['angsuran_ke']); ?></td>
                            <td><?= e($fine['jumlah_hari']); ?> hari</td>
                            <td style="font-weight:700;color:var(--accent-red)"><?= format_rupiah($fine['denda_total']); ?></td>
                            <td><span class="badge-status <?= status_badge_class($fine['status']); ?>"><?= e($fine['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="form-section">
    <h4 class="mb-4">Upload Bukti Pembayaran</h4>
    <?php if (empty($angsuranList)): ?>
        <div class="alert alert-warning">Tidak ada angsuran yang dapat dibayar saat ini.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= e($errors[0]); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Pilih Angsuran</label>
                <select name="angsuran_id" class="form-select" required>
                    <option value="">Pilih angsuran</option>
                    <?php foreach ($angsuranList as $row): ?>
                        <option value="<?= e($row['id']); ?>">
                            <?= e($row['nomor_pinjaman']); ?> - Angsuran <?= e($row['angsuran_ke']); ?> (<?= format_rupiah($row['nominal']); ?>)<?= !empty($row['total_denda']) ? ' + denda ' . format_rupiah($row['total_denda']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nominal Bayar</label>
                <input type="text" name="nominal" data-type="currency" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="tanggal_bayar" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Upload Bukti Transfer</label>
                <input type="file" name="bukti_transfer" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-12">
                <label class="form-label">Catatan</label>
                <textarea name="catatan" class="form-control" rows="2"></textarea>
            </div>
        </div>
        <button class="btn btn-primary mt-4">Kirim</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
