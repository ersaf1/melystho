<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo  = db();
$errors = [];

// ── Batas pinjaman aktif dari settings (default: 3) ──────────────────────────
$maxActiveLoans = (int)get_setting('max_active_loans', 3);

// ── Total simpanan yang sudah disetujui milik user ───────────────────────────
$stmt = $pdo->prepare("SELECT COALESCE(SUM(besar_simpanan), 0) AS total FROM simpanan WHERE id_anggota = ? AND status = 'Diterima'");
$stmt->execute([$user['id']]);
$totalSimpanan = (float)($stmt->fetch()['total'] ?? 0);
$maxLoanLimit  = 5 * $totalSimpanan; // Batas maksimal total pinjaman aktif = 5× simpanan

// ── Hitung total pinjaman aktif user saat ini ────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(besar_pinjaman), 0) AS sum_nominal FROM pinjaman WHERE id_anggota = ? AND status IN ('Menunggu review', 'Disetujui', 'Dicairkan')");
$stmt->execute([$user['id']]);
$rowAktif      = $stmt->fetch();
$activeLoans   = (int)$rowAktif['total'];
$sumPinjamanAktif = (float)$rowAktif['sum_nominal'];

// ── Dana koperasi yang tersedia ──────────────────────────────────────────────
$modalAwal = (float)get_setting('modal_awal_koperasi', 100000000);

$stmt = $pdo->query("SELECT COALESCE(SUM(besar_simpanan), 0) AS total FROM simpanan WHERE status = 'Diterima'");
$totalSimpananKoperasi = (float)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query("SELECT COALESCE(SUM(besar_pinjaman), 0) AS total FROM pinjaman WHERE status IN ('Disetujui', 'Dicairkan')");
$totalPinjamanAktifKoperasi = (float)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query("SELECT COALESCE(SUM(besar_angsuran), 0) AS total FROM angsuran WHERE status = 'Diterima'");
$totalAngsuranKoperasi = (float)($stmt->fetch()['total'] ?? 0);

$danaAvailable = $modalAwal + $totalSimpananKoperasi + $totalAngsuranKoperasi - $totalPinjamanAktifKoperasi;

// ── Validasi status anggota ───────────────────────────────────────────────────
$isVerified   = in_array($user['status'], ['Disetujui', 'Aktif'], true);
if (!$isVerified) {
    set_flash('danger', 'Akun Anda belum aktif/terverifikasi. Pengajuan pinjaman hanya diizinkan untuk akun yang sudah diverifikasi.');
    redirect('/user/dashboard.php');
}

// ── Cek batas pinjaman aktif ─────────────────────────────────────────────────
$limitReached = $maxActiveLoans > 0 && $activeLoans >= $maxActiveLoans;

// ── Helper: tenor yang valid untuk nominal tertentu ──────────────────────────
function get_valid_tenors(float $nominal): array
{
    if ($nominal <= 1_000_000) {
        return [3, 6];
    } elseif ($nominal <= 3_000_000) {
        return [6, 12];
    } else {
        return [12, 24];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// POST Handler
// ══════════════════════════════════════════════════════════════════════════════
if (is_post()) {
    if ($limitReached) {
        $errors[] = 'Tidak dapat mengajukan pinjaman baru karena telah mencapai batas maksimal pinjaman aktif (' . $maxActiveLoans . ' pinjaman).';
    } else {
        $nominal  = parse_currency($_POST['nominal'] ?? 0);
        $tenor    = (int)($_POST['tenor'] ?? 0);
        $bunga    = (float)get_setting('default_bunga_persen', 1.5);

        // Validasi nominal
        if ($nominal < 100_000) {
            $errors[] = 'Nominal pinjaman minimal Rp 100.000.';
        }

        // Validasi tenor sesuai nominal (backend)
        $validTenors = get_valid_tenors($nominal);
        if (!in_array($tenor, $validTenors, true)) {
            $errors[] = 'Tenor tidak valid untuk nominal tersebut. Pilihan tenor: ' . implode(' atau ', $validTenors) . ' bulan.';
        }

        // Validasi total pinjaman aktif user ≤ 5× simpanan
        if (($sumPinjamanAktif + $nominal) > $maxLoanLimit) {
            $sisa = max(0, $maxLoanLimit - $sumPinjamanAktif);
            $errors[] = 'Nominal pinjaman melebihi batas maksimal yang diperbolehkan (5× total simpanan Anda = ' . format_rupiah($maxLoanLimit) . '). Sisa kapasitas pinjaman Anda: ' . format_rupiah($sisa) . '.';
        }

        // Validasi dana koperasi mencukupi
        if ($nominal > $danaAvailable) {
            $errors[] = 'Tidak dapat memproses pinjaman karena dana koperasi tidak mencukupi. Dana tersedia: ' . format_rupiah(max(0, $danaAvailable)) . '.';
        }

        if (empty($errors)) {
            // ── Hitung angsuran dengan pembulatan benar ────────────────────────────
            // Gunakan integer cent (×100) untuk hindari floating-point error
            $totalBunga    = round($nominal * ($bunga / 100) * $tenor, 2);
            $totalBayar    = round($nominal + $totalBunga, 2);

            // Angsuran per bulan dibulatkan ke 2 desimal
            $angsuranBulat = round($totalBayar / $tenor, 2);

            // Angsuran terakhir menyerap sisa selisih pembulatan
            // (disimpan di keterangan; penerapannya saat approve oleh admin)
            $nomorPinjaman = generate_loan_number();

            $stmt = $pdo->prepare("
                INSERT INTO pinjaman 
                    (id_anggota, nama_pinjaman, besar_pinjaman, bunga_persen, tenor,
                     total_bayar, angsuran_per_bulan, ket, penghasilan, dokumen_pendukung,
                     status, tgl_pengajuan_pinjaman, created_at, alasan_penolakan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu review', CURDATE(), NOW(), '')
            ");

            try {
                $stmt->execute([
                    $user['id'],
                    $nomorPinjaman,
                    $nominal,
                    $bunga,
                    $tenor,
                    $totalBayar,
                    $angsuranBulat,
                    'Pinjaman Anggota',
                    0,
                    null,
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

<?php if ($limitReached): ?>
    <div class="alert alert-warning mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Anda telah mencapai batas maksimal <strong><?= $maxActiveLoans ?> pinjaman aktif</strong>.
        Selesaikan pinjaman yang ada terlebih dahulu sebelum mengajukan yang baru.
    </div>
<?php endif; ?>

<div class="form-section">
    <h4 class="mb-2"><i class="bi bi-cash-coin me-2 text-primary-custom"></i>Form Pengajuan Pinjaman</h4>
    <p class="text-muted mb-4">
        Ajukan pinjaman baru. Tenor tersedia menyesuaikan otomatis berdasarkan nominal yang diinput.
        Bunga flat <strong><?= e(get_setting('default_bunga_persen', 1.5)) ?>%</strong> per bulan.
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($errors[0]); ?>
        </div>
    <?php endif; ?>

    <!-- Info Dana Koperasi -->
    <div class="alert alert-info mb-4" style="font-size:.85rem">
        <i class="bi bi-info-circle-fill me-2"></i>
        Dana koperasi tersedia: <strong><?= format_rupiah(max(0, $danaAvailable)); ?></strong> &nbsp;|&nbsp;
        Kapasitas pinjaman Anda: <strong><?= format_rupiah(max(0, $maxLoanLimit - $sumPinjamanAktif)); ?></strong>
        (5× simpanan Anda: <?= format_rupiah($totalSimpanan); ?>)
    </div>

    <form method="post" id="loanApplyForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="nominal">Jumlah Pinjaman (Rp) <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" name="nominal" id="nominal" data-type="currency" class="form-control"
                           placeholder="Contoh: 1.000.000" required
                           <?= $limitReached ? 'disabled' : ''; ?>>
                </div>
                <div class="form-text" id="nominalHelp">
                    Min. Rp 100.000 · Maks. <?= format_rupiah(max(0, $maxLoanLimit - $sumPinjamanAktif)); ?>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="tenor">Tenor (Bulan) <span class="required">*</span></label>
                <select name="tenor" id="tenor" class="form-select" required
                        <?= $limitReached ? 'disabled' : ''; ?>>
                    <option value="">— Masukkan nominal dulu —</option>
                </select>
                <div class="form-text" id="tenorHelp">Pilihan tenor muncul setelah nominal diisi.</div>
            </div>

            <div class="col-md-12">
                <label class="form-label">Suku Bunga Koperasi</label>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?= e(get_setting('default_bunga_persen', 1.5)); ?>" readonly disabled>
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

        <!-- Tabel aturan tenor -->
        <div class="mb-4 p-3 rounded border" style="background:var(--bg,#f8f9fa);font-size:.82rem;">
            <strong><i class="bi bi-table me-1"></i>Tabel Ketentuan Tenor:</strong>
            <table class="table table-sm table-bordered mt-2 mb-0" style="font-size:.82rem;">
                <thead class="table-light"><tr><th>Nominal Pinjaman</th><th>Tenor Tersedia</th></tr></thead>
                <tbody>
                    <tr><td>≤ Rp 1.000.000</td><td>3 atau 6 bulan</td></tr>
                    <tr><td>Rp 1.000.001 – Rp 3.000.000</td><td>6 atau 12 bulan</td></tr>
                    <tr><td>> Rp 3.000.000</td><td>12 atau 24 bulan</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= base_url('/user/pinjaman.php') ?>" class="btn btn-outline-secondary px-4 py-2">Batal</a>
            <button type="submit" class="btn btn-primary px-5 py-2 fw-700"
                    id="submitBtn" <?= $limitReached ? 'disabled' : ''; ?>>
                <i class="bi bi-send-fill me-2"></i>Ajukan Sekarang
            </button>
        </div>
    </form>
</div>

<?php
$bungaJS = (float)get_setting('default_bunga_persen', 1.5);
$extra_js = "<script>
document.addEventListener('DOMContentLoaded', () => {
    const nominalEl    = document.getElementById('nominal');
    const tenorEl      = document.getElementById('tenor');
    const bungaVal     = {$bungaJS};
    const totalBungaEl = document.getElementById('total_bunga');
    const totalBayarEl = document.getElementById('total_bayar');
    const angsuranEl   = document.getElementById('angsuran');

    const TENOR_RULES = [
        { maxNominal: 1_000_000, tenors: [3, 6]   },
        { maxNominal: 3_000_000, tenors: [6, 12]  },
        { maxNominal: Infinity,  tenors: [12, 24] },
    ];

    function formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR',
            minimumFractionDigits: 0, maximumFractionDigits: 0
        }).format(value || 0);
    }

    function getNominalValue() {
        return parseInt((nominalEl.value || '').replace(/\D/g, ''), 10) || 0;
    }

    function updateTenorOptions(nominal) {
        tenorEl.innerHTML = '';
        if (nominal <= 0) {
            tenorEl.innerHTML = '<option value=\"\">— Masukkan nominal dulu —</option>';
            return;
        }
        const rule   = TENOR_RULES.find(r => nominal <= r.maxNominal);
        const tenors = rule ? rule.tenors : [12, 24];
        tenorEl.innerHTML = '<option value=\"\">— Pilih Tenor —</option>';
        tenors.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t;
            opt.textContent = t + ' Bulan';
            tenorEl.appendChild(opt);
        });
    }

    function hitung() {
        const n = getNominalValue();
        const t = parseInt(tenorEl.value || 0);
        if (n <= 0 || t <= 0) {
            totalBungaEl.value = '';
            totalBayarEl.value = '';
            angsuranEl.value   = '';
            return;
        }
        const bungaTotal = Math.round(n * (bungaVal / 100) * t * 100) / 100;
        const total      = Math.round((n + bungaTotal) * 100) / 100;
        const angs       = Math.round((total / t) * 100) / 100;
        totalBungaEl.value = formatRupiah(bungaTotal);
        totalBayarEl.value = formatRupiah(total);
        angsuranEl.value   = formatRupiah(angs);
    }

    // Currency formatting is handled globally by main.js.
    // Here we only update tenor options and recalculate on every input.
    nominalEl.addEventListener('input', function () {
        updateTenorOptions(getNominalValue());
        hitung();
    });

    tenorEl.addEventListener('change', hitung);
});
</script>";
?>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
