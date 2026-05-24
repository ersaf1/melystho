<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];

$defaultBunga = (float)get_setting('default_bunga_persen', 2);
$maxActiveLoans = (int)get_setting('max_active_loans', 1);

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM pinjaman WHERE user_id = ? AND status IN ('Menunggu review', 'Disetujui', 'Dicairkan')");
$stmt->execute([$user['id']]);
$activeLoans = (int)$stmt->fetch()['total'];

$isVerified = in_array($user['status_verifikasi'], ['Disetujui', 'Aktif'], true);
$limitReached = $maxActiveLoans > 0 && $activeLoans >= $maxActiveLoans;

if (is_post()) {
    if (!$isVerified) {
        $errors[] = 'Akun belum diverifikasi.';
    } elseif ($limitReached) {
        $errors[] = 'Jumlah pinjaman aktif Anda sudah mencapai batas.';
    } else {
        $nominal = (float)($_POST['nominal'] ?? 0);
        $tenor = (int)($_POST['tenor'] ?? 0);
        $tujuan = trim($_POST['tujuan'] ?? '');
        $penghasilan = (float)($_POST['penghasilan'] ?? 0);
        $catatan = trim($_POST['catatan'] ?? '');
        $bunga = (float)($_POST['bunga_persen'] ?? $defaultBunga);

        if ($nominal <= 0 || $tenor <= 0 || $tujuan === '') {
            $errors[] = 'Lengkapi data pinjaman.';
        }

        $dokumen = null;
        if (empty($errors) && !empty($_FILES['dokumen']['name'])) {
            $dokumen = upload_file($_FILES['dokumen'], __DIR__ . '/../uploads/dokumen_pinjaman', ['image/jpeg', 'image/png', 'application/pdf'], 5242880);
            if (!$dokumen) {
                $errors[] = 'Format dokumen harus JPG/PNG/PDF.';
            }
        }

        if (empty($errors)) {
            $totalBunga = $nominal * ($bunga / 100) * $tenor;
            $totalBayar = $nominal + $totalBunga;
            $angsuran = $totalBayar / $tenor;

            $stmt = $pdo->prepare("INSERT INTO pinjaman (user_id, nomor_pinjaman, nominal, bunga_persen, tenor, total_bayar, angsuran_per_bulan, tujuan, penghasilan, catatan, dokumen_pendukung, status, tanggal_pengajuan, created_at, alasan_penolakan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu review', CURDATE(), NOW(), '')");
            $stmt->execute([
                $user['id'],
                generate_loan_number(),
                $nominal,
                $bunga,
                $tenor,
                $totalBayar,
                $angsuran,
                $tujuan,
                $penghasilan,
                $catatan,
                $dokumen
            ]);

            set_flash('success', 'Pengajuan pinjaman berhasil dikirim.');
            redirect('/user/pinjaman.php');
        }
    }
}

$page_title = 'Ajukan Pinjaman';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<?php if (!$isVerified): ?>
    <div class="alert alert-warning">Akun Anda belum diverifikasi.</div>
<?php elseif ($limitReached): ?>
    <div class="alert alert-warning">Batas pinjaman aktif Anda sudah tercapai.</div>
<?php endif; ?>

<div class="form-section">
    <h4 class="mb-4">Form Pengajuan Pinjaman</h4>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= e($errors[0]); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nominal Pinjaman</label>
                <input type="number" name="nominal" id="nominal" class="form-control" min="100000" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tenor (bulan)</label>
                <input type="number" name="tenor" id="tenor" class="form-control" min="1" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tujuan Pinjaman</label>
                <input type="text" name="tujuan" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Penghasilan per Bulan</label>
                <input type="number" name="penghasilan" class="form-control" min="0">
            </div>
            <div class="col-md-6">
                <label class="form-label">Bunga (%) per bulan</label>
                <input type="number" name="bunga_persen" id="bunga" class="form-control" step="0.1" value="<?= e($defaultBunga); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Upload Dokumen Pendukung</label>
                <input type="file" name="dokumen" class="form-control" accept="image/*,.pdf">
            </div>
            <div class="col-md-12">
                <label class="form-label">Catatan</label>
                <textarea name="catatan" class="form-control" rows="2"></textarea>
            </div>
        </div>
        <hr class="my-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Total Bunga</label>
                <input type="text" id="total_bunga" class="form-control" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label">Total Bayar</label>
                <input type="text" id="total_bayar" class="form-control" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label">Angsuran/Bulan</label>
                <input type="text" id="angsuran" class="form-control" readonly>
            </div>
        </div>
        <button class="btn btn-primary mt-4" <?= (!$isVerified || $limitReached) ? 'disabled' : ''; ?>>Ajukan</button>
    </form>
</div>

<?php
$extra_js = "<script>
    const nominal = document.getElementById('nominal');
    const tenor = document.getElementById('tenor');
    const bunga = document.getElementById('bunga');
    const totalBunga = document.getElementById('total_bunga');
    const totalBayar = document.getElementById('total_bayar');
    const angsuran = document.getElementById('angsuran');

    function formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value || 0);
    }

    function hitung() {
        const n = parseFloat(nominal.value || 0);
        const t = parseInt(tenor.value || 0);
        const b = parseFloat(bunga.value || 0);
        const bungaTotal = n * (b / 100) * t;
        const total = n + bungaTotal;
        const angs = t > 0 ? total / t : 0;
        totalBunga.value = formatRupiah(bungaTotal);
        totalBayar.value = formatRupiah(total);
        angsuran.value = formatRupiah(angs);
    }

    [nominal, tenor, bunga].forEach(el => el.addEventListener('input', hitung));
</script>";
?>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
