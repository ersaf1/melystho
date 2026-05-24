<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
if (is_post()) {
    $data = [
        'nama'          => trim($_POST['nama'] ?? ''),
        'nik'           => trim($_POST['nik'] ?? ''),
        'tempat_lahir'  => trim($_POST['tempat_lahir'] ?? ''),
        'tanggal_lahir' => trim($_POST['tanggal_lahir'] ?? ''),
        'jenis_kelamin' => trim($_POST['jenis_kelamin'] ?? ''),
        'alamat'        => trim($_POST['alamat'] ?? ''),
        'no_hp'         => trim($_POST['no_hp'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'pekerjaan'     => trim($_POST['pekerjaan'] ?? ''),
        'penghasilan'   => trim($_POST['penghasilan'] ?? ''),
        'username'      => trim($_POST['username'] ?? ''),
        'password'      => $_POST['password'] ?? '',
        'setuju'        => isset($_POST['setuju']),
    ];

    foreach (['nama', 'nik', 'tanggal_lahir', 'alamat', 'no_hp', 'email', 'username', 'password'] as $field) {
        if ($data[$field] === '') {
            $errors[] = 'Lengkapi semua field wajib.';
            break;
        }
    }

    if (!$data['setuju']) {
        $errors[] = 'Anda harus menyetujui syarat dan ketentuan.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    $pdo  = db();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR nik = ?");
    $stmt->execute([$data['username'], $data['email'], $data['nik']]);
    if ($stmt->fetch()) {
        $errors[] = 'Username, email, atau NIK sudah terdaftar.';
    }

    $ktpFile   = null;
    $selfieFile = null;
    if (empty($errors)) {
        if (!isset($_FILES['foto_ktp'], $_FILES['foto_diri'])) {
            $errors[] = 'Upload foto KTP dan foto diri wajib.';
        } else {
            $ktpFile    = upload_file($_FILES['foto_ktp'],   __DIR__ . '/uploads/ktp',  ['image/jpeg', 'image/png']);
            $selfieFile = upload_file($_FILES['foto_diri'], __DIR__ . '/uploads/diri', ['image/jpeg', 'image/png']);
            if (!$ktpFile || !$selfieFile) {
                $errors[] = 'Upload foto KTP dan foto diri wajib dalam format JPG/PNG.';
            }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nama, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, no_hp, email, pekerjaan, penghasilan, username, password, role, status_verifikasi, foto_ktp, foto_diri, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 'Menunggu Verifikasi', ?, ?, NOW(), NOW())");
        $stmt->execute([
            $data['nama'], $data['nik'], $data['tempat_lahir'], $data['tanggal_lahir'],
            $data['jenis_kelamin'], $data['alamat'], $data['no_hp'], $data['email'],
            $data['pekerjaan'], $data['penghasilan'], $data['username'], $hash,
            $ktpFile, $selfieFile,
        ]);
        $newUserId = (int)$pdo->lastInsertId();
        log_activity($newUserId, 'Registrasi anggota baru');
        notify_admins('Pendaftaran anggota baru', 'Anggota ' . $data['nama'] . ' menunggu verifikasi.');

        set_flash('success', 'Pendaftaran berhasil! Akun Anda sedang menunggu verifikasi admin. Silakan login setelah disetujui.');
        redirect('/login.php');
    }
}

$page_title = 'Daftar Anggota';
$flash      = get_flash();
$config     = require __DIR__ . '/config/config.php';
$d          = $_POST ?? [];  // repopulate form
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Anggota — <?= e($config['app']['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body style="background:var(--bg)">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-5" style="max-width:760px">

    <!-- Header -->
    <div class="text-center mb-4">
        <div class="section-label mx-auto mb-3"><i class="bi bi-person-plus"></i>Pendaftaran Anggota</div>
        <h1 class="section-title">Formulir Pendaftaran</h1>
        <p class="section-sub mx-auto">Isi semua data dengan benar dan lengkap. Proses verifikasi 1–2 hari kerja.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger-custom mb-4">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Terjadi kesalahan:</strong>
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/flash.php'; ?>

    <!-- Progress Bar -->
    <div class="mb-3" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem 1.5rem">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span style="font-size:.78rem;font-weight:600;color:var(--text-secondary)">Kemajuan Pendaftaran</span>
            <span id="progressLabel" style="font-size:.78rem;font-weight:700;color:var(--primary)">Langkah 1 dari 5</span>
        </div>
        <div class="progress">
            <div class="progress-bar" id="stepProgress" role="progressbar" style="width:20%;background:linear-gradient(90deg,var(--primary),var(--primary-light))" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>

    <!-- Step Indicators -->
    <div class="step-indicator mb-4">
        <?php
        $stepLabels = ['Data Pribadi', 'Kontak', 'Akun', 'Dokumen', 'Konfirmasi'];
        foreach ($stepLabels as $i => $label):
        ?>
        <div class="step-ind-item <?= $i === 0 ? 'active' : ''; ?>">
            <div class="step-ind-circle"><?= $i === 0 ? '<i class="bi bi-person"></i>' : ($i + 1); ?></div>
            <div class="step-ind-label"><?= $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Form -->
    <div class="form-card">
        <form method="post" enctype="multipart/form-data" id="stepForm">

            <!-- ─── STEP 1: Data Pribadi ─── -->
            <div class="form-step active" id="step1">
                <h5 class="fw-700 mb-1" style="color:var(--primary)"><i class="bi bi-person-vcard me-2"></i>Data Pribadi</h5>
                <p class="mb-4" style="font-size:.83rem;color:var(--text-muted)">Informasi identitas diri Anda sesuai KTP.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nama">Nama Lengkap <span class="required">*</span></label>
                        <input type="text" id="nama" name="nama" class="form-control" placeholder="Sesuai KTP" required value="<?= e($d['nama'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nik">NIK (16 digit) <span class="required">*</span></label>
                        <input type="text" id="nik" name="nik" class="form-control" placeholder="1234567890123456" maxlength="16" required value="<?= e($d['nik'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tempat_lahir">Tempat Lahir</label>
                        <input type="text" id="tempat_lahir" name="tempat_lahir" class="form-control" placeholder="Kota kelahiran" value="<?= e($d['tempat_lahir'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tanggal_lahir">Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control" required value="<?= e($d['tanggal_lahir'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="jenis_kelamin">Jenis Kelamin</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" class="form-select">
                            <option value="">— Pilih —</option>
                            <option value="Laki-laki" <?= ($d['jenis_kelamin'] ?? '') === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?= ($d['jenis_kelamin'] ?? '') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="alamat">Alamat Lengkap <span class="required">*</span></label>
                        <textarea id="alamat" name="alamat" class="form-control" rows="3" placeholder="Jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota, kode pos" required><?= e($d['alamat'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn btn-primary px-4 py-2 fw-600" data-step="next"
                            style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius)">
                        Lanjut <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- ─── STEP 2: Kontak & Pekerjaan ─── -->
            <div class="form-step" id="step2">
                <h5 class="fw-700 mb-1" style="color:var(--primary)"><i class="bi bi-telephone me-2"></i>Kontak &amp; Pekerjaan</h5>
                <p class="mb-4" style="font-size:.83rem;color:var(--text-muted)">Informasi kontak dan data pekerjaan Anda.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="no_hp">No. HP / WhatsApp <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                            <input type="tel" id="no_hp" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= e($d['no_hp'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Alamat Email <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" required value="<?= e($d['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pekerjaan">Pekerjaan</label>
                        <input type="text" id="pekerjaan" name="pekerjaan" class="form-control" placeholder="Contoh: PNS, Wiraswasta, Karyawan" value="<?= e($d['pekerjaan'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="penghasilan">Penghasilan per Bulan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="penghasilan" name="penghasilan" class="form-control" placeholder="0" min="0" value="<?= e($d['penghasilan'] ?? ''); ?>">
                        </div>
                        <div class="form-text">Opsional — digunakan untuk perhitungan batas pinjaman.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 fw-600" data-step="prev" style="border-radius:var(--radius)">
                        <i class="bi bi-arrow-left me-1"></i>Sebelumnya
                    </button>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-600" data-step="next"
                            style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius)">
                        Lanjut <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- ─── STEP 3: Akun Login ─── -->
            <div class="form-step" id="step3">
                <h5 class="fw-700 mb-1" style="color:var(--primary)"><i class="bi bi-key me-2"></i>Akun Login</h5>
                <p class="mb-4" style="font-size:.83rem;color:var(--text-muted)">Buat username dan password untuk masuk ke sistem.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="username">Username <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-at input-icon"></i>
                            <input type="text" id="username" name="username" class="form-control" placeholder="username unik Anda" required value="<?= e($d['username'] ?? ''); ?>">
                        </div>
                        <div class="form-text">Huruf kecil, angka, dan underscore saja. Min. 5 karakter.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 8 karakter" required>
                            <i class="bi bi-eye input-icon-right" data-toggle-pw="password"></i>
                        </div>
                        <div class="form-text">Min. 8 karakter, kombinasikan huruf dan angka.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 fw-600" data-step="prev" style="border-radius:var(--radius)">
                        <i class="bi bi-arrow-left me-1"></i>Sebelumnya
                    </button>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-600" data-step="next"
                            style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius)">
                        Lanjut <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- ─── STEP 4: Upload Dokumen ─── -->
            <div class="form-step" id="step4">
                <h5 class="fw-700 mb-1" style="color:var(--primary)"><i class="bi bi-file-earmark-arrow-up me-2"></i>Upload Dokumen</h5>
                <p class="mb-4" style="font-size:.83rem;color:var(--text-muted)">Upload foto KTP dan selfie Anda dalam format JPG atau PNG, maks 2MB.</p>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Foto KTP <span class="required">*</span></label>
                        <div class="file-upload-area" id="ktpArea">
                            <i class="bi bi-cloud-arrow-up d-block mb-2" style="font-size:2rem;color:var(--text-muted)"></i>
                            <div class="upload-text">Klik atau seret file ke sini</div>
                            <div class="file-name text-truncate" style="display:none"></div>
                            <small class="d-block mt-1" style="color:var(--text-muted);font-size:.72rem">JPG / PNG • Maks 2MB</small>
                            <input type="file" name="foto_ktp" id="foto_ktp" accept="image/jpeg,image/png" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Foto Diri (Selfie) <span class="required">*</span></label>
                        <div class="file-upload-area" id="selfieArea">
                            <i class="bi bi-camera d-block mb-2" style="font-size:2rem;color:var(--text-muted)"></i>
                            <div class="upload-text">Klik atau seret file ke sini</div>
                            <div class="file-name text-truncate" style="display:none"></div>
                            <small class="d-block mt-1" style="color:var(--text-muted);font-size:.72rem">JPG / PNG • Maks 2MB</small>
                            <input type="file" name="foto_diri" id="foto_diri" accept="image/jpeg,image/png" required>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info-custom mt-3">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Pastikan foto KTP terlihat jelas dan semua teks terbaca. Foto selfie Anda digunakan untuk verifikasi identitas.</span>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 fw-600" data-step="prev" style="border-radius:var(--radius)">
                        <i class="bi bi-arrow-left me-1"></i>Sebelumnya
                    </button>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-600" data-step="next"
                            style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius)">
                        Lanjut <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- ─── STEP 5: Konfirmasi ─── -->
            <div class="form-step" id="step5">
                <h5 class="fw-700 mb-1" style="color:var(--primary)"><i class="bi bi-clipboard2-check me-2"></i>Konfirmasi &amp; Kirim</h5>
                <p class="mb-4" style="font-size:.83rem;color:var(--text-muted)">Periksa kembali data Anda sebelum mengirim formulir.</p>

                <div class="alert alert-warning-custom mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Pastikan semua data yang Anda isi <strong>sudah benar</strong>. Data yang sudah dikirim tidak bisa diubah tanpa menghubungi admin.</span>
                </div>

                <div class="mb-4">
                    <div class="form-check" style="padding:.75rem;background:var(--bg);border-radius:var(--radius);border:1.5px solid var(--border)">
                        <input class="form-check-input" type="checkbox" name="setuju" id="setuju" required>
                        <label class="form-check-label" for="setuju" style="font-size:.875rem;color:var(--text-primary)">
                            Saya menyatakan bahwa semua data yang saya isi adalah <strong>benar dan dapat dipertanggungjawabkan</strong>.
                            Saya menyetujui <a href="#" style="color:var(--primary)">syarat &amp; ketentuan</a> dan
                            <a href="#" style="color:var(--primary)">aturan koperasi</a>.
                        </label>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 fw-600" data-step="prev" style="border-radius:var(--radius)">
                        <i class="bi bi-arrow-left me-1"></i>Sebelumnya
                    </button>
                    <button type="submit" class="btn btn-success px-5 py-2 fw-700" id="submitRegBtn"
                            style="background:linear-gradient(135deg,var(--accent-green),#15803d);border:none;border-radius:var(--radius);box-shadow:0 4px 16px rgba(22,163,74,.3)">
                        <i class="bi bi-send-fill me-2"></i>Kirim Pendaftaran
                    </button>
                </div>
            </div>

        </form>
    </div>

    <p class="text-center mt-3" style="font-size:.82rem;color:var(--text-muted)">
        Sudah punya akun? <a href="/login.php" style="color:var(--primary);font-weight:600">Login di sini</a>
    </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
// Update step progress label
const origShowStep = window.__showStep;
const progressLabel = document.getElementById('progressLabel');
document.addEventListener('DOMContentLoaded', () => {
    const obs = new MutationObserver(() => {
        const active = document.querySelector('.step-ind-item.active');
        const items  = document.querySelectorAll('.step-ind-item');
        if (active && progressLabel) {
            const idx = Array.from(items).indexOf(active);
            progressLabel.textContent = `Langkah ${idx + 1} dari ${items.length}`;
        }
    });
    obs.observe(document.querySelector('.step-indicator'), { attributes: true, subtree: true, attributeFilter: ['class'] });
});
</script>
