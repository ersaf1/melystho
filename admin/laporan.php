<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$pdo = db();
$type = $_GET['type'] ?? 'anggota';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$month = $_GET['month'] ?? '';
$year = $_GET['year'] ?? '';
$status = $_GET['status'] ?? '';
$userId = (int)($_GET['user_id'] ?? 0);
$allowedTypes = ['anggota', 'simpanan', 'pinjaman', 'angsuran'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'anggota';
}

function report_period_filter(string $dateColumn, array &$conditions, array &$params, string $start, string $end, string $month, string $year): void
{
    if ($month !== '') {
        $conditions[] = "DATE_FORMAT($dateColumn, '%Y-%m') = ?";
        $params[] = $month;
        return;
    }

    if ($year !== '') {
        $conditions[] = "YEAR($dateColumn) = ?";
        $params[] = $year;
        return;
    }

    if ($start !== '' && $end !== '') {
        $conditions[] = "$dateColumn BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    }
}

function load_report(PDO $pdo, string $type, string $start, string $end, string $month, string $year, string $status, int $userId): array
{
    $conditions = [];
    $params = [];

    if ($type === 'anggota') {
        report_period_filter('DATE(created_at)', $conditions, $params, $start, $end, $month, $year);
        if ($status !== '') {
            $conditions[] = 'status_verifikasi = ?';
            $params[] = $status;
        }
        $where = $conditions ? 'WHERE role = ? AND ' . implode(' AND ', $conditions) : 'WHERE role = ?';
        array_unshift($params, 'user');
        $stmt = $pdo->prepare("SELECT nama, nik, email, no_hp, status_verifikasi, created_at FROM users $where ORDER BY created_at DESC");
        $stmt->execute($params);
        return [
            'title' => 'Laporan Anggota',
            'columns' => ['Nama', 'NIK', 'Email', 'No HP', 'Status', 'Tanggal Daftar'],
            'rows' => array_map(fn($row) => [
                $row['nama'],
                $row['nik'],
                $row['email'],
                $row['no_hp'],
                $row['status_verifikasi'],
                date('d/m/Y', strtotime($row['created_at'])),
            ], $stmt->fetchAll()),
        ];
    }

    if ($type === 'simpanan') {
        report_period_filter('s.tanggal_transaksi', $conditions, $params, $start, $end, $month, $year);
        if ($status !== '') {
            $conditions[] = 's.status = ?';
            $params[] = $status;
        }
        if ($userId > 0) {
            $conditions[] = 's.user_id = ?';
            $params[] = $userId;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $pdo->prepare("
            SELECT s.tanggal_transaksi, u.nama, s.jenis_simpanan, s.nominal, s.status
            FROM simpanan s
            JOIN users u ON u.id = s.user_id
            $where
            ORDER BY s.tanggal_transaksi DESC
        ");
        $stmt->execute($params);
        return [
            'title' => 'Laporan Simpanan',
            'columns' => ['Tanggal', 'Anggota', 'Jenis', 'Nominal', 'Status'],
            'rows' => array_map(fn($row) => [
                date('d/m/Y', strtotime($row['tanggal_transaksi'])),
                $row['nama'],
                ucfirst($row['jenis_simpanan']),
                format_rupiah($row['nominal']),
                $row['status'],
            ], $stmt->fetchAll()),
        ];
    }

    if ($type === 'pinjaman') {
        report_period_filter('p.tanggal_pengajuan', $conditions, $params, $start, $end, $month, $year);
        if ($status !== '') {
            $conditions[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($userId > 0) {
            $conditions[] = 'p.user_id = ?';
            $params[] = $userId;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $pdo->prepare("
            SELECT p.nomor_pinjaman, u.nama, p.nominal, p.tenor, p.status, p.tanggal_pengajuan
            FROM pinjaman p
            JOIN users u ON u.id = p.user_id
            $where
            ORDER BY p.tanggal_pengajuan DESC
        ");
        $stmt->execute($params);
        return [
            'title' => 'Laporan Pinjaman',
            'columns' => ['No Pinjaman', 'Anggota', 'Nominal', 'Tenor', 'Status', 'Tanggal Pengajuan'],
            'rows' => array_map(fn($row) => [
                $row['nomor_pinjaman'],
                $row['nama'],
                format_rupiah($row['nominal']),
                $row['tenor'] . ' bulan',
                $row['status'],
                date('d/m/Y', strtotime($row['tanggal_pengajuan'])),
            ], $stmt->fetchAll()),
        ];
    }

    report_period_filter('COALESCE(a.tanggal_bayar, a.jatuh_tempo)', $conditions, $params, $start, $end, $month, $year);
    if ($status !== '') {
        $conditions[] = 'a.status = ?';
        $params[] = $status;
    }
    if ($userId > 0) {
        $conditions[] = 'p.user_id = ?';
        $params[] = $userId;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $stmt = $pdo->prepare("
        SELECT p.nomor_pinjaman, u.nama, a.angsuran_ke, a.jatuh_tempo, a.tanggal_bayar, a.nominal, a.status
        FROM angsuran a
        JOIN pinjaman p ON p.id = a.pinjaman_id
        JOIN users u ON u.id = p.user_id
        $where
        ORDER BY COALESCE(a.tanggal_bayar, a.jatuh_tempo) DESC
    ");
    $stmt->execute($params);
    return [
        'title' => 'Laporan Angsuran',
        'columns' => ['No Pinjaman', 'Anggota', 'Ke', 'Jatuh Tempo', 'Tanggal Bayar', 'Nominal', 'Status'],
        'rows' => array_map(fn($row) => [
            $row['nomor_pinjaman'],
            $row['nama'],
            $row['angsuran_ke'],
            $row['jatuh_tempo'] ? date('d/m/Y', strtotime($row['jatuh_tempo'])) : '-',
            $row['tanggal_bayar'] ? date('d/m/Y', strtotime($row['tanggal_bayar'])) : '-',
            format_rupiah($row['nominal']),
            $row['status'],
        ], $stmt->fetchAll()),
    ];
}

function report_html(string $title, array $columns, array $rows): string
{
    $html = '<html><head><style>
        body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#111827}
        h2{margin:0 0 4px;color:#1e3a5f}
        p{margin:0 0 14px;color:#64748b}
        table{width:100%;border-collapse:collapse}
        th{background:#1e3a5f;color:#fff;text-align:left;padding:7px;border:1px solid #1e3a5f}
        td{padding:6px;border:1px solid #e5e7eb}
        tr:nth-child(even) td{background:#f8fafc}
    </style></head><body>';
    $html .= '<h2>' . e($title) . '</h2><p>Dicetak pada ' . date('d/m/Y H:i') . '</p><table><thead><tr>';
    foreach ($columns as $column) {
        $html .= '<th>' . e($column) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    if (empty($rows)) {
        $html .= '<tr><td colspan="' . count($columns) . '">Data kosong.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . e($cell) . '</td>';
            }
            $html .= '</tr>';
        }
    }
    return $html . '</tbody></table></body></html>';
}

$report = load_report($pdo, $type, $start, $end, $month, $year, $status, $userId);

if (isset($_GET['export'])) {
    $filename = strtolower(str_replace(' ', '-', $report['title'])) . '-' . date('Ymd-His');
    if ($_GET['export'] === 'pdf') {
        if (!class_exists('\Dompdf\Dompdf')) {
            set_flash('danger', 'DomPDF belum tersedia. Jalankan composer install.');
            redirect('/admin/laporan.php?' . http_build_query(array_diff_key($_GET, ['export' => true])));
        }
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml(report_html($report['title'], $report['columns'], $report['rows']));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
        exit;
    }

    if ($_GET['export'] === 'excel') {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            set_flash('danger', 'PhpSpreadsheet belum tersedia. Jalankan composer install.');
            redirect('/admin/laporan.php?' . http_build_query(array_diff_key($_GET, ['export' => true])));
        }
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($report['title'], 0, 31));
        $sheet->fromArray($report['columns'], null, 'A1');
        $sheet->fromArray($report['rows'], null, 'A2');
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

$users = $pdo->query("SELECT id, nama FROM users WHERE role = 'user' ORDER BY nama ASC")->fetchAll();
$currentYear = (int)date('Y');
$exportBase = $_GET;
$exportBase['type'] = $type;
$pdfQuery = $exportBase;
$pdfQuery['export'] = 'pdf';
$excelQuery = $exportBase;
$excelQuery['export'] = 'excel';

$page_title = 'Laporan';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($report['title']); ?></h1>
        <p class="page-sub">Filter laporan dan export ke PDF atau Excel.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/admin/laporan.php?<?= e(http_build_query($pdfQuery)); ?>" class="btn btn-outline-danger"><i class="bi bi-filetype-pdf me-1"></i>Export PDF</a>
        <a href="/admin/laporan.php?<?= e(http_build_query($excelQuery)); ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
</div>

<div class="filter-bar">
    <form class="row g-2 align-items-end" method="get">
        <div class="col-md-3 col-xl-2">
            <label class="form-label">Jenis Laporan</label>
            <select name="type" class="form-select">
                <option value="anggota" <?= $type === 'anggota' ? 'selected' : ''; ?>>Anggota</option>
                <option value="simpanan" <?= $type === 'simpanan' ? 'selected' : ''; ?>>Simpanan</option>
                <option value="pinjaman" <?= $type === 'pinjaman' ? 'selected' : ''; ?>>Pinjaman</option>
                <option value="angsuran" <?= $type === 'angsuran' ? 'selected' : ''; ?>>Angsuran</option>
            </select>
        </div>
        <div class="col-md-3 col-xl-2">
            <label class="form-label">Bulan</label>
            <input type="month" name="month" class="form-control" value="<?= e($month); ?>">
        </div>
        <div class="col-md-3 col-xl-2">
            <label class="form-label">Tahun</label>
            <select name="year" class="form-select">
                <option value="">Semua Tahun</option>
                <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                    <option value="<?= $y; ?>" <?= $year === (string)$y ? 'selected' : ''; ?>><?= $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3 col-xl-2">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="start" class="form-control" value="<?= e($start); ?>">
        </div>
        <div class="col-md-3 col-xl-2">
            <label class="form-label">Tanggal Selesai</label>
            <input type="date" name="end" class="form-control" value="<?= e($end); ?>">
        </div>
        <div class="col-md-3 col-xl-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <?php foreach (['Menunggu Verifikasi', 'Disetujui', 'Ditolak', 'Nonaktif', 'Menunggu review', 'Dicairkan', 'Lunas', 'Menunggu konfirmasi', 'Diterima', 'Belum dibayar'] as $s): ?>
                    <option value="<?= e($s); ?>" <?= $status === $s ? 'selected' : ''; ?>><?= e($s); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 col-xl-3">
            <label class="form-label">Anggota</label>
            <select name="user_id" class="form-select">
                <option value="">Semua Anggota</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= e($u['id']); ?>" <?= $userId === (int)$u['id'] ? 'selected' : ''; ?>><?= e($u['nama']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 col-xl-3">
            <label class="form-label">Pencarian Real-time</label>
            <div class="search-input-wrap">
                <i class="bi bi-search search-icon"></i>
                <input type="search" class="form-control" placeholder="Cari isi tabel..." data-table-search="#reportTable">
            </div>
        </div>
        <div class="col-md-4 col-xl-2 d-grid">
            <button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        </div>
        <div class="col-md-4 col-xl-2 d-grid">
            <a href="/admin/laporan.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-table me-2 text-primary-custom"></i>Data Laporan</span>
        <span style="font-size:.78rem;color:var(--text-muted)"><?= count($report['rows']); ?> data</span>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table" id="reportTable">
            <thead>
                <tr>
                    <?php foreach ($report['columns'] as $col): ?>
                        <th><?= e($col); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($report['rows'])): ?>
                    <tr><td colspan="<?= count($report['columns']); ?>"><div class="empty-state"><i class="bi bi-inbox"></i><p>Data kosong.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($report['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= e($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
