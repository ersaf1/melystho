<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$authUser = current_user();

if (isset($_GET['backup']) && $_GET['backup'] === 'database') {
    log_activity((int)$authUser['id'], 'Melakukan backup database');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="backup-koperasi-' . date('Ymd-His') . '.sql"');
    echo database_backup_sql();
    exit;
}

if (is_post()) {
    $action = $_POST['action'] ?? 'settings';
    if ($action === 'restore') {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            set_flash('danger', 'File backup SQL wajib diupload.');
        } else {
            $ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                set_flash('danger', 'Format file restore harus .sql.');
            } else {
                $sql = file_get_contents($_FILES['backup_file']['tmp_name']);
                $total = restore_database_sql($sql);
                log_activity((int)$authUser['id'], 'Melakukan restore database');
                set_flash('success', 'Restore database selesai. Total statement: ' . $total . '.');
            }
        }
    } else {
        $defaultBunga = (float)($_POST['default_bunga_persen'] ?? 2);
        $maxActive = (int)($_POST['max_active_loans'] ?? 1);
        $maxLoanAmount = max(0, (float)($_POST['max_loan_amount'] ?? 10000000));
        $dendaPerHari = max(0, (float)($_POST['denda_per_hari'] ?? 5000));
        $reminderDays = max(0, (int)($_POST['reminder_days_before_due'] ?? 3));
        set_setting('default_bunga_persen', $defaultBunga);
        set_setting('max_active_loans', $maxActive);
        set_setting('max_loan_amount', $maxLoanAmount);
        set_setting('denda_per_hari', $dendaPerHari);
        set_setting('reminder_days_before_due', $reminderDays);
        log_activity((int)$authUser['id'], 'Memperbarui pengaturan koperasi');
        set_flash('success', 'Pengaturan disimpan.');
    }
    redirect('/admin/pengaturan.php');
}

$defaultBunga = get_setting('default_bunga_persen', 2);
$maxActive = get_setting('max_active_loans', 1);
$maxLoanAmount = get_setting('max_loan_amount', 10000000);
$dendaPerHari = get_setting('denda_per_hari', 5000);
$reminderDays = get_setting('reminder_days_before_due', 3);

$page_title = 'Pengaturan';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Pengaturan</h1>
        <p class="page-sub">Kelola parameter pinjaman, denda, reminder, backup, dan restore database.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-gear me-2 text-primary-custom"></i>Pengaturan Koperasi</span>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="action" value="settings">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Default Bunga Pinjaman (%)</label>
                            <input type="number" step="0.1" name="default_bunga_persen" class="form-control" value="<?= e($defaultBunga); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maksimal Pinjaman Aktif</label>
                            <input type="number" name="max_active_loans" class="form-control" value="<?= e($maxActive); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maksimal Nominal Pinjaman</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" name="max_loan_amount" data-type="currency" class="form-control" value="<?= e($maxLoanAmount); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Denda per Hari</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" name="denda_per_hari" data-type="currency" class="form-control" value="<?= e($dendaPerHari); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reminder Jatuh Tempo (hari)</label>
                            <input type="number" name="reminder_days_before_due" class="form-control" min="0" value="<?= e($reminderDays); ?>">
                        </div>
                    </div>
                    <button class="btn-primary-custom mt-4" type="submit"><i class="bi bi-save"></i>Simpan Pengaturan</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel mb-4">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-download me-2 text-green"></i>Backup Database</span>
            </div>
            <div class="panel-body">
                <p style="font-size:.85rem;color:var(--text-secondary)">Unduh seluruh struktur dan data database dalam format SQL.</p>
                <a href="<?= base_url('/admin/pengaturan.php?backup=database') ?>" class="btn btn-outline-success w-100"><i class="bi bi-database-down me-1"></i>Download Backup SQL</a>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-upload me-2 text-red"></i>Restore Database</span>
            </div>
            <div class="panel-body">
                <div class="alert alert-warning-custom">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Restore akan menjalankan isi file SQL. Gunakan hanya file backup yang valid.</span>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="restore">
                    <label class="form-label">File Backup (.sql)</label>
                    <input type="file" name="backup_file" class="form-control" accept=".sql" required>
                    <button class="btn btn-outline-danger w-100 mt-3" type="submit" onclick="return confirm('Restore database dari file ini?')"><i class="bi bi-database-up me-1"></i>Restore Database</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
