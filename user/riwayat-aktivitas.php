<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$authUser = current_user();
$q = trim($_GET['q'] ?? '');

$logFile = __DIR__ . '/../logs/activity.log';
$logs = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^\[(.*?)\]\s+\[User\s+ID:\s+(\d+)\]\s+(.*)$/', $line, $matches)) {
            $created_at = $matches[1];
            $user_id = (int)$matches[2];
            $aktivitas = $matches[3];
            
            if ($user_id !== (int)$authUser['id']) {
                continue;
            }
            if ($q !== '' && stripos($aktivitas, $q) === false) {
                continue;
            }
            
            $logs[] = [
                'created_at' => $created_at,
                'aktivitas' => $aktivitas
            ];
        }
    }
}

usort($logs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$logs = array_slice($logs, 0, 200);

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
