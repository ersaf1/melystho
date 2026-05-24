<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];

$stmt = $pdo->prepare("SELECT id, nomor_pinjaman FROM pinjaman WHERE user_id = ? AND status IN ('Disetujui', 'Dicairkan')");
$stmt->execute([$user['id']]);
$pinjamanList = $stmt->fetchAll();

$angsuranList = [];
if (!empty($pinjamanList)) {
    $loanIds = array_column($pinjamanList, 'id');
    $placeholders = implode(',', array_fill(0, count($loanIds), '?'));
    $stmt = $pdo->prepare("SELECT a.id, a.angsuran_ke, a.nominal, a.pinjaman_id, p.nomor_pinjaman FROM angsuran a JOIN pinjaman p ON a.pinjaman_id = p.id WHERE a.status IN ('Belum dibayar', 'Ditolak') AND a.pinjaman_id IN ($placeholders)");
    $stmt->execute($loanIds);
    $angsuranList = $stmt->fetchAll();
}

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
            set_flash('success', 'Pembayaran berhasil diupload. Menunggu konfirmasi admin.');
            redirect('/user/bayar-angsuran.php');
        }
    }
}

$page_title = 'Pembayaran Angsuran';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

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
                            <?= e($row['nomor_pinjaman']); ?> - Angsuran <?= e($row['angsuran_ke']); ?> (<?= format_rupiah($row['nominal']); ?>)
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
