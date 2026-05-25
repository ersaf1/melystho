<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();

$q = trim($_GET['q'] ?? '');
$userId = (int)($_GET['user_id'] ?? 0);
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$logFile = __DIR__ . '/../logs/activity.log';
$logs = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^\[(.*?)\]\s+\[User\s+ID:\s+(\d+)\]\s+(.*)$/', $line, $matches)) {
            $created_at = $matches[1];
            $user_id = (int)$matches[2];
            $aktivitas = $matches[3];
            
            static $userCache = [];
            if (!isset($userCache[$user_id])) {
                $stmt = $pdo->prepare("SELECT nama, username, role FROM petugas_koperasi WHERE id_petugas = ? UNION SELECT nama, username, role FROM anggota WHERE id_anggota = ?");
                $stmt->execute([$user_id, $user_id]);
                $u = $stmt->fetch();
                $userCache[$user_id] = $u ?: ['nama' => 'User ID ' . $user_id, 'username' => 'user_' . $user_id, 'role' => 'user'];
            }
            
            $u = $userCache[$user_id];
            
            if ($q !== '' && stripos($aktivitas, $q) === false && stripos($u['nama'], $q) === false && stripos($u['username'], $q) === false) {
                continue;
            }
            if ($userId > 0 && $user_id !== $userId) {
                continue;
            }
            if ($start !== '' && $end !== '') {
                $dateOnly = date('Y-m-d', strtotime($created_at));
                if ($dateOnly < $start || $dateOnly > $end) {
                    continue;
                }
            }
            
            $logs[] = [
                'created_at' => $created_at,
                'user_id' => $user_id,
                'nama' => $u['nama'],
                'username' => $u['username'],
                'role' => $u['role'],
                'aktivitas' => $aktivitas
            ];
        }
    }
}

usort($logs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$logs = array_slice($logs, 0, 300);

$users = $pdo->query("SELECT id_petugas AS id, nama FROM petugas_koperasi UNION SELECT id_anggota AS id, nama FROM anggota ORDER BY nama ASC")->fetchAll();

$page_title = 'Audit Log';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Audit Log</h1>
        <p class="page-sub">Catatan aktivitas admin dan anggota untuk kebutuhan audit operasional.</p>
    </div>
</div>

<div class="filter-bar">
    <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4">
            <label class="form-label">Cari Aktivitas</label>
            <div class="search-input-wrap">
                <i class="bi bi-search search-icon"></i>
                <input type="search" name="q" class="form-control" value="<?= e($q); ?>" placeholder="Nama, username, atau aktivitas..." data-table-search="#auditLogTable">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Pengguna</label>
            <select name="user_id" class="form-select">
                <option value="">Semua Pengguna</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= e($u['id']); ?>" <?= $userId === (int)$u['id'] ? 'selected' : ''; ?>><?= e($u['nama']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Mulai</label>
            <input type="date" name="start" class="form-control" value="<?= e($start); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Selesai</label>
            <input type="date" name="end" class="form-control" value="<?= e($end); ?>">
        </div>
        <div class="col-md-1 d-grid">
            <button class="btn btn-outline-primary">Filter</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-shield-check me-2 text-primary-custom"></i>Riwayat Aktivitas</span>
        <span style="font-size:.78rem;color:var(--text-muted)">Maksimal 300 data terbaru</span>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table" id="auditLogTable">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Role</th>
                    <th>Aktivitas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="4"><div class="empty-state"><i class="bi bi-clipboard-x"></i><p>Belum ada aktivitas.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem"><?= e(date('d/m/Y H:i', strtotime($log['created_at']))); ?></td>
                            <td>
                                <div style="font-weight:700"><?= e($log['nama']); ?></div>
                                <div style="font-size:.72rem;color:var(--text-muted)">@<?= e($log['username']); ?></div>
                            </td>
                            <td><span class="badge-status <?= $log['role'] === 'admin' ? 'badge-disetujui' : 'badge-dicairkan'; ?>"><?= e($log['role']); ?></span></td>
                            <td><?= e($log['aktivitas']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
