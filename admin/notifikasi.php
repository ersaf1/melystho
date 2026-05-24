<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$authUser = current_user();
ensure_feature_tables();

if (is_post()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'read') {
        mark_notification_read((int)($_POST['id'] ?? 0), (int)$authUser['id']);
        set_flash('success', 'Notifikasi ditandai sudah dibaca.');
    } elseif ($action === 'read_all') {
        mark_all_notifications_read((int)$authUser['id']);
        set_flash('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
    redirect('/admin/notifikasi.php');
}

$pdo = db();
$notificationsStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC");
$notificationsStmt->execute([$authUser['id']]);
$notifications = $notificationsStmt->fetchAll();

$page_title = 'Notifikasi';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notifikasi</h1>
        <p class="page-sub">Pantau pengajuan dan aktivitas terbaru yang membutuhkan perhatian admin.</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <form method="post">
            <input type="hidden" name="action" value="read_all">
            <button class="btn-primary-custom" type="submit"><i class="bi bi-check2-all"></i>Tandai Semua Dibaca</button>
        </form>
    <?php endif; ?>
</div>

<div class="filter-bar">
    <div class="search-input-wrap">
        <i class="bi bi-search search-icon"></i>
        <input type="search" class="form-control" placeholder="Cari notifikasi..." data-table-search="#notificationsTable">
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-bell me-2 text-primary-custom"></i>Daftar Notifikasi</span>
        <span class="text-muted" style="font-size:.78rem"><?= count($notifications); ?> notifikasi</span>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table" id="notificationsTable">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Judul</th>
                    <th>Pesan</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notifications)): ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-bell-slash"></i><p>Belum ada notifikasi.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($notifications as $item): ?>
                        <tr>
                            <td>
                                <span class="badge-status <?= $item['is_read'] ? 'badge-diterima' : 'badge-menunggu'; ?>">
                                    <?= $item['is_read'] ? 'Dibaca' : 'Baru'; ?>
                                </span>
                            </td>
                            <td style="font-weight:700"><?= e($item['judul']); ?></td>
                            <td><?= e($item['pesan']); ?></td>
                            <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem"><?= e(date('d/m/Y H:i', strtotime($item['created_at']))); ?></td>
                            <td>
                                <?php if (!$item['is_read']): ?>
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?= e($item['id']); ?>">
                                        <button name="action" value="read" class="btn-action approve" title="Tandai dibaca"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
