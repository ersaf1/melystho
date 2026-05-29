<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
if (is_post()) {
    $pdo = db();
    $data = [
        'nama'             => trim($_POST['nama'] ?? ''),
        'no_hp'            => trim($_POST['no_hp'] ?? ''),
        'email'            => trim($_POST['email'] ?? ''),
        'alamat'           => trim($_POST['alamat'] ?? ''),
        'password'         => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
    ];

    // Basic Validations
    foreach (['nama', 'no_hp', 'email', 'alamat', 'password', 'confirm_password'] as $field) {
        if ($data[$field] === '') {
            $errors[] = 'Semua field wajib diisi.';
            break;
        }
    }

    if (empty($errors)) {
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
        if (strlen($data['password']) < 6) {
            $errors[] = 'Kata sandi minimal 6 karakter.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
    }

    if (empty($errors)) {
        
        // Check if email already registered in anggota or petugas_koperasi
        $stmt = $pdo->prepare("SELECT id_anggota FROM anggota WHERE email = ? UNION SELECT id_petugas FROM petugas_koperasi WHERE email = ?");
        $stmt->execute([$data['email'], $data['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Alamat email sudah terdaftar.';
        }
    }

    if (empty($errors)) {
        // 1. Auto-generate Username from Email prefix
        $emailParts = explode('@', $data['email']);
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $emailParts[0]);
        $baseUsername = strtolower($baseUsername);
        if (strlen($baseUsername) < 5) {
            $baseUsername = str_pad($baseUsername, 5, '0');
        }
        
        $username = $baseUsername;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT id_anggota FROM anggota WHERE username = ? UNION SELECT id_petugas FROM petugas_koperasi WHERE username = ?");
            $stmt->execute([$username, $username]);
            if (!$stmt->fetch()) {
                break;
            }
            $username = $baseUsername . $counter;
            $counter++;
        }

        // 2. Auto-generate unique 16-digit NIK
        $nik = '3273' . str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        while (true) {
            $stmt = $pdo->prepare("SELECT id_anggota FROM anggota WHERE nik = ?");
            $stmt->execute([$nik]);
            if (!$stmt->fetch()) {
                break;
            }
            $nik = '3273' . str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        }

        // 3. Hash password and insert user into anggota (Auto-approved 'Disetujui' for instant login)
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO anggota (nama, nik, tmp_lhr, tgl_lhr, j_kel, alamat, no_tlp, email, username, password, role, status, foto_ktp, foto_diri, created_at, updated_at) VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, 'user', 'Disetujui', NULL, NULL, NOW(), NOW())");
        
        try {
            $stmt->execute([
                $data['nama'], 
                $nik,
                'Jakarta', // default tempat lahir
                $data['alamat'], 
                $data['no_hp'], 
                $data['email'], 
                $username, 
                $hash
            ]);
            
            $newUserId = (int)$pdo->lastInsertId();
            log_activity($newUserId, 'Registrasi anggota baru (Otomatis Aktif)');
            
            set_flash('success', 'Pendaftaran berhasil! Akun Anda sudah aktif secara otomatis. Silakan masuk.');
            redirect('/login.php');
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan data ke database: ' . $e->getMessage();
        }
    }
}

$page_title = 'Daftar Anggota';
$flash      = get_flash();
$config     = require __DIR__ . '/config/config.php';
$d          = $_POST ?? [];
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
    <link href="<?= base_url('/assets/css/styles.css') ?>" rel="stylesheet">
</head>
<body style="background:var(--bg)">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-5" style="max-width:650px">

    <!-- Header -->
    <div class="text-center mb-4">
        <div class="section-label mx-auto mb-3"><i class="bi bi-person-plus"></i>Pendaftaran Anggota</div>
        <h1 class="section-title">Formulir Pendaftaran</h1>
        <p class="section-sub mx-auto">Isi data diri Anda di bawah ini untuk menjadi anggota koperasi secara instan.</p>
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

    <!-- Form -->
    <div class="form-card">
        <form method="post" id="regForm">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nama">Nama Lengkap <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person input-icon"></i>
                        <input type="text" id="nama" name="nama" class="form-control" placeholder="Nama lengkap sesuai identitas" required value="<?= e($d['nama'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label" for="no_hp">No. HP / WhatsApp <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                        <input type="tel" id="no_hp" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= e($d['no_hp'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="email">Alamat Email <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" required value="<?= e($d['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="alamat">Alamat Lengkap <span class="required">*</span></label>
                    <textarea id="alamat" name="alamat" class="form-control" rows="3" placeholder="Jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota" required><?= e($d['alamat'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="password">Kata Sandi <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="confirm_password">Ulangi Kata Sandi <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Ulangi kata sandi Anda" required>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <a href="<?= base_url('/login.php') ?>" class="text-decoration-none" style="font-size:.875rem;color:var(--primary);font-weight:600">
                    <i class="bi bi-arrow-left me-1"></i>Sudah punya akun? Masuk
                </a>
                <button type="submit" class="btn btn-success px-5 py-2 fw-700"
                        style="background:linear-gradient(135deg,var(--accent-green),#15803d);border:none;border-radius:var(--radius);box-shadow:0 4px 16px rgba(22,163,74,.3)">
                    <i class="bi bi-person-plus-fill me-2"></i>Daftar Sekarang
                </button>
            </div>

        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
