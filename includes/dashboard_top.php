<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
$config   = require __DIR__ . '/../config/config.php';
$flash    = get_flash();
$authUser = current_user();
$initials = strtoupper(substr($authUser['nama'] ?? 'U', 0, 1));

/* Map status_verifikasi → CSS class and label */
$statusMap = [
    'Disetujui'           => ['class' => 'verified',  'label' => 'Terverifikasi'],
    'Menunggu Verifikasi' => ['class' => 'pending',   'label' => 'Menunggu'],
    'Ditolak'             => ['class' => 'rejected',  'label' => 'Ditolak'],
    'Nonaktif'            => ['class' => 'nonactive', 'label' => 'Nonaktif'],
];
$verifStatus  = $authUser['status_verifikasi'] ?? 'Menunggu Verifikasi';
$statusInfo   = $statusMap[$verifStatus] ?? ['class' => 'pending', 'label' => $verifStatus];
?>
<?php require __DIR__ . '/header.php'; ?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-layout">
  <!-- ─── SIDEBAR ─── -->
  <aside class="app-sidebar" id="appSidebar">
    <!-- Brand -->
    <a href="/" class="sidebar-brand">
      <span class="sidebar-brand-icon"><i class="bi bi-bank2"></i></span>
      <span class="sidebar-brand-text">
        <span class="sidebar-brand-name"><?= e($config['app']['name']); ?></span>
        <span class="sidebar-brand-tagline">Portal Anggota</span>
      </span>
    </a>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <?php if ($role === 'admin'): ?>
        <div class="sidebar-section"><span class="sidebar-section-label">Menu Utama</span></div>
        <a href="/admin/dashboard.php" class="sidebar-link" id="sl-dashboard">
            <i class="bi bi-grid-1x2"></i>Dashboard
        </a>
        <a href="/admin/anggota.php" class="sidebar-link" id="sl-anggota">
            <i class="bi bi-people"></i>Anggota
        </a>

        <div class="sidebar-section mt-2"><span class="sidebar-section-label">Transaksi</span></div>
        <a href="/admin/simpanan.php" class="sidebar-link" id="sl-simpanan">
            <i class="bi bi-piggy-bank"></i>Simpanan
        </a>
        <a href="/admin/pinjaman.php" class="sidebar-link" id="sl-pinjaman">
            <i class="bi bi-cash-stack"></i>Pinjaman
        </a>
        <a href="/admin/angsuran.php" class="sidebar-link" id="sl-angsuran">
            <i class="bi bi-calendar-check"></i>Angsuran
        </a>

        <div class="sidebar-section mt-2"><span class="sidebar-section-label">Lainnya</span></div>
        <a href="/admin/laporan.php" class="sidebar-link" id="sl-laporan">
            <i class="bi bi-file-earmark-bar-graph"></i>Laporan
        </a>
        <a href="/admin/pengaturan.php" class="sidebar-link" id="sl-pengaturan">
            <i class="bi bi-gear"></i>Pengaturan
        </a>
      <?php else: ?>
        <div class="sidebar-section"><span class="sidebar-section-label">Menu</span></div>
        <a href="/user/dashboard.php" class="sidebar-link" id="sl-dashboard">
            <i class="bi bi-grid-1x2"></i>Dashboard
        </a>
        <a href="/user/profil.php" class="sidebar-link" id="sl-profil">
            <i class="bi bi-person-circle"></i>Profil Saya
        </a>

        <div class="sidebar-section mt-2"><span class="sidebar-section-label">Keuangan</span></div>
        <a href="/user/simpanan.php" class="sidebar-link" id="sl-simpanan">
            <i class="bi bi-piggy-bank"></i>Simpanan
        </a>
        <a href="/user/pinjaman.php" class="sidebar-link" id="sl-pinjaman">
            <i class="bi bi-cash-stack"></i>Pinjaman
        </a>
        <a href="/user/bayar-angsuran.php" class="sidebar-link" id="sl-angsuran">
            <i class="bi bi-calendar-check"></i>Angsuran
        </a>
      <?php endif; ?>
    </nav>

    <!-- User + Logout -->
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= $initials; ?></div>
        <div>
          <div class="sidebar-user-name"><?= e(explode(' ', $authUser['nama'])[0] ?? $authUser['nama']); ?></div>
          <div class="sidebar-user-role"><?= $role === 'admin' ? 'Administrator' : 'Anggota'; ?></div>
        </div>
        <a href="/logout.php" class="sidebar-logout ms-auto" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </aside>

  <!-- ─── MAIN AREA ─── -->
  <div class="app-main">
    <!-- Topbar -->
    <header class="app-topbar">
      <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
      </button>
      <div class="topbar-breadcrumb">
        <span class="topbar-page-title"><?= e($page_title ?? 'Dashboard'); ?></span>
      </div>
      <div class="topbar-right">
        <span class="status-pill <?= $statusInfo['class']; ?>"><?= $statusInfo['label']; ?></span>
        <div class="topbar-user">
          <div class="topbar-avatar"><?= $initials; ?></div>
          <div class="d-none d-md-block">
            <div class="topbar-user-name"><?= e($authUser['nama']); ?></div>
            <div class="topbar-user-role"><?= $role === 'admin' ? 'Administrator' : 'Anggota'; ?></div>
          </div>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="app-content">
        <?php require __DIR__ . '/flash.php'; ?>
