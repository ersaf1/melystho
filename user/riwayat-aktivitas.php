<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$authUser = current_user();
ensure_feature_tables();

$q = trim($_GET['q'] ?? '');
$params = [$authUser['id']];
$where = 'WHERE l.user_id = ?';
if ($q !== '') {
    $where .= ' AND l.aktivitas LIKE ?';
    $params[] = "%$q%";
}

$pdo = db();
$stmt = $pdo->prepare("
    SELECT l.*
    FROM activity_logs l
    $where
    ORDER BY l.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$page_title = 'Riwayat Aktivitas';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Riwayat Aktivitas</h1>
        <p class="page-sub">Catatan aktivitas akun Anda di sistem koperasi.</p>
    </div>
</div>

<div class="filter-bar">
    <form method="get">
        <div class="search-input-wrap">
            <i class="bi bi-search search-icon"></i>
            <input type="search" name="q" class="form-control" value="<?= e($q); ?>" placeholder="Cari aktivitas..." data-table-search="#activityTable">
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-clock-history me-2 text-primary-custom"></i>Aktivitas Saya</span>
        <span style="font-size:.78rem;color:var(--text-muted)"><?= count($logs); ?> data</span>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table" id="activityTable">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Aktivitas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="2"><div class="empty-state"><i class="bi bi-clipboard-x"></i><p>Belum ada aktivitas.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem"><?= e(date('d/m/Y H:i', strtotime($log['created_at']))); ?></td>
                            <td><?= e($log['aktivitas']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
