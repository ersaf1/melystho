<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];
sync_late_fines((int)$user['id']);
sync_due_reminders((int)$user['id']);

$stmt = $pdo->prepare("SELECT id, nomor_pinjaman FROM pinjaman WHERE user_id = ? AND status IN ('Disetujui', 'Dicairkan')");
$stmt->execute([$user['id']]);
$pinjamanList = $stmt->fetchAll();

$angsuranList = [];
if (!empty($pinjamanList)) {
    $loanIds = array_column($pinjamanList, 'id');
    $placeholders = implode(',', array_fill(0, count($loanIds), '?'));
    $stmt = $pdo->prepare("
        SELECT a.id, a.angsuran_ke, a.nominal, a.pinjaman_id, a.jatuh_tempo, p.nomor_pinjaman, d.total_denda, d.status AS status_denda
        FROM angsuran a
        JOIN pinjaman p ON a.pinjaman_id = p.id
        LEFT JOIN denda d ON d.angsuran_id = a.id AND d.status = 'Belum Dibayar'
        WHERE a.status IN ('Belum dibayar', 'Ditolak') AND a.pinjaman_id IN ($placeholders)
        ORDER BY a.jatuh_tempo ASC
    ");
    $stmt->execute($loanIds);
    $angsuranList = $stmt->fetchAll();
}

$dendaStmt = $pdo->prepare("
    SELECT d.*, a.angsuran_ke, p.nomor_pinjaman
    FROM denda d
    JOIN angsuran a ON a.id = d.angsuran_id
    JOIN pinjaman p ON p.id = a.pinjaman_id
    WHERE d.user_id = ? AND d.status = 'Belum Dibayar'
    ORDER BY d.created_at DESC
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
        $stmt = $pdo->prepare("SELECT a.id FROM angsuran a JOIN pinjaman p ON a.pinjaman_id = p.id WHERE a.id = ? AND p.user_id = ?");
        $stmt->execute([$angsuranId, $user['id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'Angsuran tidak valid.';
        } else {
            $stmt = $pdo->prepare("UPDATE angsuran SET tanggal_bayar = ?, bukti_transfer = ?, nominal = ?, status = 'Menunggu konfirmasi', keterangan = ? WHERE id = ?");
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
            <span style="font-size:.78rem;color:var(--text-muted)">Total <?= format_rupiah(array_sum(array_column($dendaList, 'total_denda'))); ?></span>
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
                            <td style="font-weight:700;color:var(--accent-red)"><?= format_rupiah($fine['total_denda']); ?></td>
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
                <input type="number" name="nominal" class="form-control" min="1" required>
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
