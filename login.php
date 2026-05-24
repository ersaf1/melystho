<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
if (is_post()) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Username/email dan password wajib diisi.';
    } else {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            redirect($user['role'] === 'admin' ? '/admin/dashboard.php' : '/user/dashboard.php');
        }
        $errors[] = 'Username/email atau password salah.';
    }
}

$page_title = 'Login';
$flash = get_flash();
$config = require __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= e($config['app']['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<body style="background:var(--bg)">

<div class="login-page">
    <!-- Left Panel -->
    <div class="login-left d-none d-lg-flex">
        <div class="login-left-content">
            <div class="login-brand">
                <div class="login-brand-icon"><i class="bi bi-bank2"></i></div>
                <span class="login-brand-name"><?= e($config['app']['name']); ?></span>
            </div>
            <h1 class="login-headline">Selamat Datang <span>Kembali</span></h1>
            <p class="login-sub">Masuk ke akun Anda untuk memantau simpanan, mengajukan pinjaman, dan mengelola keuangan koperasi Anda.</p>

            <div class="login-feature">
                <div class="login-feature-icon"><i class="bi bi-piggy-bank-fill"></i></div>
                <span class="login-feature-text">Pantau saldo simpanan Anda secara real-time</span>
            </div>
            <div class="login-feature">
                <div class="login-feature-icon"><i class="bi bi-send-check-fill"></i></div>
                <span class="login-feature-text">Ajukan pinjaman kapan saja, proses cepat</span>
            </div>
            <div class="login-feature">
                <div class="login-feature-icon"><i class="bi bi-calendar-check-fill"></i></div>
                <span class="login-feature-text">Riwayat angsuran lengkap dan terstruktur</span>
            </div>
            <div class="login-feature">
                <div class="login-feature-icon"><i class="bi bi-shield-fill-check"></i></div>
                <span class="login-feature-text">Data Anda aman dan terenkripsi</span>
            </div>
        </div>
    </div>

    <!-- Right: Form -->
    <div class="login-right">
        <div class="login-form-wrap">
            <!-- Mobile brand -->
            <div class="d-flex d-lg-none align-items-center gap-2 mb-4">
                <div class="navbar-brand-icon"><i class="bi bi-bank2"></i></div>
                <span style="font-size:1rem;font-weight:800;color:var(--primary)"><?= e($config['app']['name']); ?></span>
            </div>

            <div class="login-form-card">
                <h2 class="login-form-title">Masuk ke Akun</h2>
                <p class="login-form-sub">Masukkan username/email dan password Anda</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger-custom mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= e($errors[0]); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($flash)): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger'; ?>-custom mb-4" role="alert">
                        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'; ?>"></i>
                        <span><?= e($flash['message']); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" id="loginForm" data-loading>
                    <div class="mb-3">
                        <label class="form-label" for="loginUsername">Username atau Email <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" id="loginUsername" name="username" class="form-control"
                                   placeholder="Masukkan username atau email" required
                                   value="<?= e($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="loginPassword">Password <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" id="loginPassword" name="password" class="form-control"
                                   placeholder="Masukkan password" required>
                            <i class="bi bi-eye input-icon-right" data-toggle-pw="loginPassword" id="togglePw"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-700" id="loginSubmitBtn"
                            style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius);font-size:.9rem;box-shadow:0 4px 16px rgba(30,58,95,.25)">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                    </button>
                </form>

                <div class="divider"></div>
                <p class="text-center mb-0" style="font-size:.85rem;color:var(--text-secondary)">
                    Belum punya akun?
                    <a href="/register.php" style="color:var(--primary);font-weight:600;text-decoration:none" id="goRegister">Daftar Sekarang</a>
                </p>
            </div>

            <p class="text-center mt-3" style="font-size:.75rem;color:var(--text-muted)">
                <i class="bi bi-shield-check me-1"></i>Koneksi aman &amp; terenkripsi
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
