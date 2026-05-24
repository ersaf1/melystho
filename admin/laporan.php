<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$type = $_GET['type'] ?? 'anggota';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$status = $_GET['status'] ?? '';
$userId = (int)($_GET['user_id'] ?? 0);

$rows = [];
$columns = [];
$title = '';

function export_csv(array $columns, array $rows, string $filename): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fputcsv($output, $columns);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

if ($type === 'anggota') {
    $title = 'Laporan Anggota';
    $stmt = $pdo->query("SELECT nama, nik, email, status_verifikasi, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    $columns = ['Nama', 'NIK', 'Email', 'Status', 'Tanggal Daftar'];
} elseif ($type === 'simpanan') {
    $title = 'Laporan Simpanan';
    $conditions = [];
    $params = [];
    if ($start && $end) {
        $conditions[] = 'tanggal_transaksi BETWEEN ? AND ?';
        $params[] = $start;
        $params[] = $end;
    }
    if ($status !== '') {
        $conditions[] = 'status = ?';
        $params[] = $status;
    }
    if ($userId) {
        $conditions[] = 'user_id = ?';
        $params[] = $userId;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $stmt = $pdo->prepare("SELECT tanggal_transaksi, jenis_simpanan, nominal, status FROM simpanan $where ORDER BY tanggal_transaksi DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $columns = ['Tanggal', 'Jenis', 'Nominal', 'Status'];
} elseif ($type === 'pinjaman') {
    $title = 'Laporan Pinjaman';
    $conditions = [];
    $params = [];
    if ($start && $end) {
        $conditions[] = 'tanggal_pengajuan BETWEEN ? AND ?';
        $params[] = $start;
        $params[] = $end;
    }
    if ($status !== '') {
        $conditions[] = 'status = ?';
        $params[] = $status;
    }
    if ($userId) {
        $conditions[] = 'user_id = ?';
        $params[] = $userId;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $stmt = $pdo->prepare("SELECT nomor_pinjaman, nominal, tenor, status, tanggal_pengajuan FROM pinjaman $where ORDER BY tanggal_pengajuan DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $columns = ['No Pinjaman', 'Nominal', 'Tenor', 'Status', 'Tanggal Pengajuan'];
} elseif ($type === 'angsuran') {
    $title = 'Laporan Angsuran';
    $conditions = [];
    $params = [];
    if ($start && $end) {
        $conditions[] = 'tanggal_bayar BETWEEN ? AND ?';
        $params[] = $start;
        $params[] = $end;
    }
    if ($status !== '') {
        $conditions[] = 'status = ?';
        $params[] = $status;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $stmt = $pdo->prepare("SELECT pinjaman_id, angsuran_ke, nominal, status, tanggal_bayar FROM angsuran $where ORDER BY tanggal_bayar DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $columns = ['Pinjaman ID', 'Angsuran Ke', 'Nominal', 'Status', 'Tanggal Bayar'];
} elseif ($type === 'kas') {
    $title = 'Laporan Kas Koperasi';
    $conditions = [];
    $params = [];
    if ($start && $end) {
        $conditions[] = 'tanggal BETWEEN ? AND ?';
        $params[] = $start;
        $params[] = $end;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $stmt = $pdo->prepare("SELECT tipe, kategori, nominal, keterangan, tanggal FROM transaksi_kas $where ORDER BY tanggal DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $columns = ['Tipe', 'Kategori', 'Nominal', 'Keterangan', 'Tanggal'];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    export_csv($columns, array_map('array_values', $rows), $type . '.csv');
}

$users = $pdo->query("SELECT id, nama FROM users WHERE role = 'user' ORDER BY nama ASC")->fetchAll();

$page_title = 'Laporan';
$role = 'admin';
$exportQuery = $_GET;
$exportQuery['export'] = 'csv';
$exportUrl = '/admin/laporan.php?' . http_build_query($exportQuery);
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><?= e($title); ?></h4>
    <a href="<?= e($exportUrl); ?>" class="btn btn-outline-secondary">Export CSV</a>
</div>

<form class="row g-2 mb-4">
    <div class="col-md-3">
        <select name="type" class="form-select">
            <option value="anggota" <?= $type === 'anggota' ? 'selected' : ''; ?>>Anggota</option>
            <option value="simpanan" <?= $type === 'simpanan' ? 'selected' : ''; ?>>Simpanan</option>
            <option value="pinjaman" <?= $type === 'pinjaman' ? 'selected' : ''; ?>>Pinjaman</option>
            <option value="angsuran" <?= $type === 'angsuran' ? 'selected' : ''; ?>>Angsuran</option>
            <option value="kas" <?= $type === 'kas' ? 'selected' : ''; ?>>Kas</option>
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="start" class="form-control" value="<?= e($start); ?>">
    </div>
    <div class="col-md-2">
        <input type="date" name="end" class="form-control" value="<?= e($end); ?>">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <?php foreach (['Menunggu review', 'Disetujui', 'Ditolak', 'Dicairkan', 'Lunas', 'Menunggu konfirmasi', 'Diterima'] as $s): ?>
                <option value="<?= e($s); ?>" <?= $status === $s ? 'selected' : ''; ?>><?= e($s); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="user_id" class="form-select">
            <option value="">Semua Anggota</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= e($u['id']); ?>" <?= $userId === (int)$u['id'] ? 'selected' : ''; ?>><?= e($u['nama']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1 d-grid">
        <button class="btn btn-outline-primary">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?= e($col); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $key => $col): ?>
                        <td><?= e(array_values($row)[$key] ?? ''); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?= count($columns); ?>" class="text-muted">Data kosong.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
