<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];

if (is_post()) {
    $jenis = $_POST['jenis_simpanan'] ?? 'sukarela';
    $allowedJenis = ['pokok', 'wajib', 'sukarela'];
    if (!in_array($jenis, $allowedJenis, true)) {
        $jenis = 'sukarela';
    }
    $nominal = (float)($_POST['nominal'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');

    if ($nominal <= 0) {
        $errors[] = 'Nominal harus lebih dari 0.';
    }

    $bukti = null;
    if (empty($errors)) {
        if (!isset($_FILES['bukti_transfer'])) {
            $errors[] = 'Upload bukti transfer wajib.';
        } else {
            $bukti = upload_file($_FILES['bukti_transfer'], __DIR__ . '/../uploads/bukti_simpanan', ['image/jpeg', 'image/png']);
            if (!$bukti) {
                $errors[] = 'Upload bukti transfer wajib dalam format JPG/PNG.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO simpanan (user_id, jenis_simpanan, nominal, bukti_transfer, status, keterangan, tanggal_transaksi, created_at) VALUES (?, ?, ?, ?, 'Menunggu konfirmasi', ?, CURDATE(), NOW())");
        $stmt->execute([$user['id'], $jenis, $nominal, $bukti, $catatan]);
        log_activity((int)$user['id'], 'Mengajukan simpanan ' . ucfirst($jenis) . ' sebesar ' . format_rupiah($nominal));
        notify_admins('Pengajuan simpanan baru', $user['nama'] . ' mengajukan simpanan ' . ucfirst($jenis) . ' sebesar ' . format_rupiah($nominal) . '.');
        set_flash('success', 'Pengajuan simpanan berhasil. Menunggu konfirmasi admin.');
        redirect('/user/simpanan.php');
    }
}

$page_title = 'Ajukan Simpanan';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="form-section">
    <h4 class="mb-4">Ajukan Simpanan Sukarela</h4>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= e($errors[0]); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Jenis Simpanan</label>
                <select name="jenis_simpanan" class="form-select">
                    <option value="sukarela">Simpanan Sukarela</option>
                    <option value="pokok">Simpanan Pokok</option>
                    <option value="wajib">Simpanan Wajib</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nominal</label>
                <input type="number" name="nominal" class="form-control" min="1000" required>
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
        <button class="btn btn-primary mt-4">Kirim Pengajuan</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
