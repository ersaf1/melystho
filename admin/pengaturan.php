<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();

if (is_post()) {
    $defaultBunga = (float)($_POST['default_bunga_persen'] ?? 2);
    $maxActive = (int)($_POST['max_active_loans'] ?? 1);
    set_setting('default_bunga_persen', $defaultBunga);
    set_setting('max_active_loans', $maxActive);
    set_flash('success', 'Pengaturan disimpan.');
    redirect('/admin/pengaturan.php');
}

$defaultBunga = get_setting('default_bunga_persen', 2);
$maxActive = get_setting('max_active_loans', 1);

$page_title = 'Pengaturan';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="form-section">
    <h4 class="mb-4">Pengaturan Koperasi</h4>
    <form method="post">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Default Bunga Pinjaman (%)</label>
                <input type="number" step="0.1" name="default_bunga_persen" class="form-control" value="<?= e($defaultBunga); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Maksimal Pinjaman Aktif</label>
                <input type="number" name="max_active_loans" class="form-control" value="<?= e($maxActive); ?>">
            </div>
        </div>
        <button class="btn btn-primary mt-4">Simpan</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
