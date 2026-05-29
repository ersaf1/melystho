<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo  = db();
$errors = [];

// Minimal simpanan wajib dari pengaturan (default: Rp 50.000)
$minSimpananWajib = (float)get_setting('simpanan_wajib_minimal', 50000);

if (is_post()) {
    $jenis  = $_POST['jenis_simpanan'] ?? 'sukarela';
    $allowedJenis = ['pokok', 'wajib', 'sukarela'];
    if (!in_array($jenis, $allowedJenis, true)) {
        $jenis = 'sukarela';
    }

    $nominal = parse_currency($_POST['nominal'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');

    // Validasi nominal umum
    if ($nominal <= 0) {
        $errors[] = 'Nominal harus lebih dari 0.';
    }

    // Validasi simpanan wajib — minimal Rp50.000
    if ($jenis === 'wajib' && $nominal < $minSimpananWajib) {
        $errors[] = 'Simpanan Wajib minimal ' . format_rupiah($minSimpananWajib) . ' per bulan.';
    }

    $bukti = null;
    if (empty($errors)) {
        if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload bukti transfer wajib.';
        } else {
            $bukti = upload_file($_FILES['bukti_transfer'], __DIR__ . '/../uploads/bukti_simpanan', ['image/jpeg', 'image/png']);
            if (!$bukti) {
                $errors[] = 'Upload bukti transfer wajib dalam format JPG/PNG (maks. 5 MB).';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO simpanan (id_anggota, nm_simpanan, besar_simpanan, bukti_transfer, status, ket, tgl_simpanan, created_at) VALUES (?, ?, ?, ?, 'Menunggu konfirmasi', ?, CURDATE(), NOW())");
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
    <h4 class="mb-2"><i class="bi bi-piggy-bank-fill me-2 text-primary-custom"></i>Ajukan Simpanan</h4>
    <p class="text-muted mb-4">Setorkan simpanan Anda. Bukti transfer wajib dilampirkan dan menunggu konfirmasi admin.</p>

    <!-- Info aturan simpanan -->
    <div class="alert alert-info mb-4" style="font-size:.85rem">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Ketentuan Simpanan:</strong>
        Simpanan Wajib minimal <strong><?= format_rupiah($minSimpananWajib); ?>/bulan</strong>.
        Simpanan Sukarela bebas nominal.
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill me-2"></i><?= e($errors[0]); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="simpananForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Jenis Simpanan <span class="required">*</span></label>
                <select name="jenis_simpanan" id="jenisSimpanan" class="form-select" required>
                    <option value="sukarela">Simpanan Sukarela</option>
                    <option value="pokok">Simpanan Pokok</option>
                    <option value="wajib">Simpanan Wajib</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nominal <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" name="nominal" id="nominalSimpanan" data-type="currency"
                           class="form-control" required placeholder="0">
                </div>
                <div class="form-text" id="nominalHelp">
                    Simpanan Sukarela: bebas nominal.
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Upload Bukti Transfer <span class="required">*</span></label>
                <input type="file" name="bukti_transfer" class="form-control" accept="image/*" required>
                <div class="form-text">Format JPG/PNG, maks. 5 MB.</div>
            </div>
            <div class="col-md-12">
                <label class="form-label">Catatan</label>
                <textarea name="catatan" class="form-control" rows="2"
                          placeholder="Catatan tambahan (opsional)"></textarea>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary px-4" type="submit">
                <i class="bi bi-send-fill me-1"></i>Kirim Pengajuan
            </button>
            <a href="<?= base_url('/user/simpanan.php') ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>

<?php
$minWajibJS = (float)$minSimpananWajib;
$extra_js = "<script>
document.addEventListener('DOMContentLoaded', () => {
    const jenis   = document.getElementById('jenisSimpanan');
    const help    = document.getElementById('nominalHelp');
    const minWajib = {$minWajibJS};

    function formatRp(v) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(v);
    }

    function updateHelp() {
        if (jenis.value === 'wajib') {
            help.innerHTML = '<span class=\"text-danger fw-bold\">Minimal ' + formatRp(minWajib) + '/bulan untuk Simpanan Wajib.</span>';
        } else if (jenis.value === 'pokok') {
            help.textContent = 'Simpanan Pokok — dibayar sekali saat pertama bergabung.';
        } else {
            help.textContent = 'Simpanan Sukarela: bebas nominal.';
        }
    }

    jenis.addEventListener('change', updateHelp);
    updateHelp();
});
</script>";
?>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
