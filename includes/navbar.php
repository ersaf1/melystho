<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? null;
$config = $config ?? (require __DIR__ . '/../config/config.php');
?>
<nav class="navbar navbar-public navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="<?= base_url('/') ?>">
            <span class="navbar-brand-icon"><i class="bi bi-bank2"></i></span>
            <span><?= e($config['app']['name']); ?></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false">
            <i class="bi bi-list fs-4" style="color:var(--primary)"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto gap-1">
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/') ?>">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/') ?>#syarat">Syarat Anggota</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/') ?>#produk">Produk</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/') ?>#keunggulan">Keunggulan</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/') ?>#kontak">Kontak</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $role === 'admin' ? base_url('/admin/dashboard.php') : base_url('/user/dashboard.php'); ?>"
                       class="btn btn-nav-login">
                        <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                    <a href="<?= base_url('/logout.php') ?>" class="btn btn-nav-register">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('/login.php') ?>" class="btn btn-nav-login" id="navLoginBtn">
                        <i class="bi bi-person me-1"></i>Login
                    </a>
                    <a href="<?= base_url('/register.php') ?>" class="btn btn-nav-register" id="navRegisterBtn">
                        <i class="bi bi-person-plus me-1"></i>Daftar Anggota
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
