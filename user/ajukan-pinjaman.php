<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];
$maxActiveLoans = (int)get_setting('max_active_loans', 1);

// Fetch total approved savings of the user
$stmt = $pdo->prepare("SELECT SUM(besar_simpanan) AS total FROM simpanan WHERE id_anggota = ? AND status = 'Diterima'");
$stmt->execute([$user['id']]);
$totalSimpanan = (float)($stmt->fetch()['total'] ?? 0);
$maxLoanLimit = 5 * $totalSimpanan;

// Count active/pending loans for this user
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM pinjaman WHERE id_anggota = ? AND status IN ('Menunggu review', 'Disetujui', 'Dicairkan')");
$stmt->execute([$user['id']]);
$activeLoans = (int)$stmt->fetch()['total'];

$isVerified = in_array($user['status'], ['Disetujui', 'Aktif'], true);
if (!$isVerified) {
    set_flash('danger', 'Akun Anda belum aktif/terverifikasi. Pengajuan pinjaman hanya diizinkan untuk akun yang sudah diverifikasi.');
    redirect('/user/dashboard.php');
}
$limitReached = $maxActiveLoans > 0 && $activeLoans >= $maxActiveLoans;

if (is_post()) {
    if ($limitReached) {
        $errors[] = 'Batas pinjaman aktif Anda sudah tercapai.';
    } else {
        $nominal = (float)($_POST['nominal'] ?? 0);
        $tenor = (int)($_POST['tenor'] ?? 0);
        $bunga = 1.5; // Fixed flat interest rate of 1.5% per month
        $tujuan = 'Pinjaman Anggota'; // Auto-filled default value
        $penghasilan = 0;             // Auto-filled default value
        $dokumen = null;               // Auto-filled default value

        if ($nominal <= 0) {
            $errors[] = 'Nominal pinjaman harus lebih dari 0.';
        } elseif ($nominal > $maxLoanLimit) {
            $errors[] = 'Nominal pinjaman melebihi batas maksimal yang diperbolehkan (5x total simpanan Anda) yaitu ' . format_rupiah($maxLoanLimit) . '.';
        }
        if (!in_array($tenor, [6, 12, 24, 36], true)) {
            $errors[] = 'Tenor pilihan tidak valid. Pilih antara 6, 12, 24, atau 36 bulan.';
        }

        if (empty($errors)) {
            $totalBunga = $nominal * ($bunga / 100) * $tenor;
            $totalBayar = $nominal + $totalBunga;
            $angsuran = $totalBayar / $tenor;
            $nomorPinjaman = generate_loan_number();

            $stmt = $pdo->prepare("INSERT INTO pinjaman (id_anggota, nama_pinjaman, besar_pinjaman, bunga_persen, tenor, total_bayar, angsuran_per_bulan, ket, penghasilan, dokumen_pendukung, status, tgl_pengajuan_pinjaman, created_at, alasan_penolakan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu review', CURDATE(), NOW(), '')");
            
            try {
                $stmt->execute([
                    $user['id'],
                    $nomorPinjaman,
                    $nominal,
                    $bunga,
                    $tenor,
                    $totalBayar,
                    $angsuran,
                    $tujuan,
                    $penghasilan,
                    $dokumen
                ]);

                log_activity((int)$user['id'], 'Mengajukan pinjaman ' . $nomorPinjaman . ' sebesar ' . format_rupiah($nominal));
                notify_admins('Pengajuan pinjaman baru', $user['nama'] . ' mengajukan pinjaman ' . $nomorPinjaman . ' sebesar ' . format_rupiah($nominal) . '.');
                
                set_flash('success', 'Pengajuan pinjaman berhasil dikirim. Menunggu persetujuan admin.');
                redirect('/user/pinjaman.php');
            } catch (PDOException $e) {
                $errors[] = 'Gagal menyimpan pengajuan pinjaman: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Ajukan Pinjaman';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<?php if (!$isVerified): ?>
    <div class="alert alert-warning mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Akun Anda belum aktif/terverifikasi. Silakan hubungi pengelola koperasi.
    </div>
<?php elseif ($limitReached): ?>
    <div class="alert alert-warning mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Batas pinjaman aktif Anda sudah tercapai. Selesaikan pinjaman Anda saat ini terlebih dahulu.
    </div>
<?php endif; ?>

<div class="form-section">
    <h4 class="mb-2"><i class="bi bi-cash-coin me-2 text-primary-custom"></i>Form Pengajuan Pinjaman</h4>
    <p class="text-muted mb-4">Ajukan pinjaman baru dengan bunga ringan flat 1.5% per bulan dan tenor yang fleksibel.</p>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($errors[0]); ?>
        </div>
    <?php endif; ?>

    <form method="post" id="loanApplyForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="nominal">Jumlah Pinjaman (Rp) <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" name="nominal" id="nominal" data-type="currency" class="form-control" placeholder="Contoh: 5.000.000" required <?= (!$isVerified || $limitReached) ? 'disabled' : ''; ?>>
                </div>
                <div class="form-text">Min. pengajuan Rp 100.000, Maks. <?= format_rupiah($maxLoanLimit); ?> (5x total simpanan Anda: <?= format_rupiah($totalSimpanan); ?>)</div>
            </div>
            
            <div class="col-md-6">
                <label class="form-label" for="tenor">Tenor (Bulan) <span class="required">*</span></label>
                <select name="tenor" id="tenor" class="form-select" required <?= (!$isVerified || $limitReached) ? 'disabled' : ''; ?>>
                    <option value="">— Pilih Tenor —</option>
                    <option value="6">6 Bulan</option>
                    <option value="12">12 Bulan</option>
                    <option value="24">24 Bulan</option>
                    <option value="36">36 Bulan</option>
                </select>
                <div class="form-text">Pilih jangka waktu cicilan bulanan</div>
            </div>

            <div class="col-md-12">
                <label class="form-label" for="bunga">Suku Bunga Koperasi</label>
                <div class="input-group">
                    <input type="text" id="bunga" class="form-control" value="1.5" readonly disabled>
                    <span class="input-group-text">% per bulan (Flat)</span>
                </div>
            </div>
        </div>

        <hr class="my-4">
        
        <h5 class="mb-3 text-secondary"><i class="bi bi-calculator me-1"></i>Estimasi Angsuran Pinjaman</h5>
        <div class="row g-3 p-3 rounded bg-light border mb-4">
            <div class="col-md-4">
                <label class="form-label text-muted">Estimasi Bunga Total</label>
                <input type="text" id="total_bunga" class="form-control bg-white font-monospace fw-bold" readonly disabled placeholder="Rp 0">
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted">Total Pengembalian</label>
                <input type="text" id="total_bayar" class="form-control bg-white font-monospace fw-bold" readonly disabled placeholder="Rp 0">
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted text-primary-custom fw-bold">Angsuran / Bulan</label>
                <input type="text" id="angsuran" class="form-control bg-white font-monospace text-primary-custom fw-bold" readonly disabled placeholder="Rp 0">
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= base_url('/user/pinjaman.php') ?>" class="btn btn-outline-secondary px-4 py-2">Batal</a>
            <button type="submit" class="btn btn-primary px-5 py-2 fw-700" <?= (!$isVerified || $limitReached) ? 'disabled' : ''; ?>>
                <i class="bi bi-send-fill me-2"></i>Ajukan Sekarang
            </button>
        </div>
    </form>
</div>

<?php
$extra_js = "<script>
document.addEventListener('DOMContentLoaded', () => {
    const nominal = document.getElementById('nominal');
    const tenor = document.getElementById('tenor');
    const bungaVal = 1.5; // Fixed 1.5% flat per month
    const totalBunga = document.getElementById('total_bunga');
    const totalBayar = document.getElementById('total_bayar');
    const angsuran = document.getElementById('angsuran');

    function formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', { 
            style: 'currency', 
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value || 0);
    }

    function hitung() {
        const n = parseFloat(nominal.value.replace(/\./g, '') || 0);
        const t = parseInt(tenor.value || 0);
        
        if (n <= 0 || t <= 0) {
            totalBunga.value = '';
            totalBayar.value = '';
            angsuran.value = '';
            return;
        }

        const bungaTotal = n * (bungaVal / 100) * t;
        const total = n + bungaTotal;
        const angs = total / t;

        totalBunga.value = formatRupiah(bungaTotal);
        totalBayar.value = formatRupiah(total);
        angsuran.value = formatRupiah(angs);
    }

    [nominal, tenor].forEach(el => {
        el.addEventListener('input', hitung);
        el.addEventListener('change', hitung);
    });
});
</script>";
?>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
