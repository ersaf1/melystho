<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$authUser = current_user();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
$stmt->execute([$id]);
$anggota = $stmt->fetch();
if (!$anggota) {
    set_flash('danger', 'Anggota tidak ditemukan.');
    redirect('/admin/anggota.php');
}

$errors = [];
if (is_post()) {
    $data = [
        'nama' => trim($_POST['nama'] ?? ''),
        'nik' => trim($_POST['nik'] ?? ''),
        'alamat' => trim($_POST['alamat'] ?? ''),
        'no_hp' => trim($_POST['no_hp'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'pekerjaan' => trim($_POST['pekerjaan'] ?? ''),
        'status_verifikasi' => trim($_POST['status_verifikasi'] ?? 'Menunggu Verifikasi'),
    ];

    if ($data['nama'] === '' || $data['nik'] === '' || $data['email'] === '') {
        $errors[] = 'Nama, NIK, dan email wajib diisi.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE (nik = ? OR email = ?) AND id != ? LIMIT 1");
        $check->execute([$data['nik'], $data['email'], $id]);
        if ($check->fetch()) {
            $errors[] = 'NIK atau email sudah digunakan anggota lain.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET nama = ?, nik = ?, alamat = ?, no_hp = ?, email = ?, pekerjaan = ?, status_verifikasi = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $data['nama'],
            $data['nik'],
            $data['alamat'],
            $data['no_hp'],
            $data['email'],
            $data['pekerjaan'],
            $data['status_verifikasi'],
            $id
        ]);
        log_activity((int)$authUser['id'], 'Mengedit data anggota ' . $data['nama']);
        set_flash('success', 'Data anggota diperbarui.');
        redirect('/admin/detail-anggota.php?id=' . $id);
    }
}

$page_title = 'Detail Anggota';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="form-section">
            <h4 class="mb-3">Data Anggota</h4>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?= e($errors[0]); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?= e($anggota['nama']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">NIK</label>
                        <input type="text" name="nik" class="form-control" value="<?= e($anggota['nik']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($anggota['email']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No HP</label>
                        <input type="text" name="no_hp" class="form-control" value="<?= e($anggota['no_hp']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pekerjaan</label>
                        <input type="text" name="pekerjaan" class="form-control" value="<?= e($anggota['pekerjaan']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status_verifikasi" class="form-select">
                            <?php foreach (['Menunggu Verifikasi', 'Disetujui', 'Ditolak', 'Nonaktif'] as $status): ?>
                                <option value="<?= e($status); ?>" <?= $anggota['status_verifikasi'] === $status ? 'selected' : ''; ?>><?= e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= e($anggota['alamat']); ?></textarea>
                    </div>
                </div>
                <button class="btn btn-primary mt-4">Simpan</button>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-stat p-3">
            <h5>Dokumen Anggota</h5>
            <div class="mb-3">
                <small>Foto KTP</small>
                <?php if (!empty($anggota['foto_ktp'])): ?>
                    <img src="/uploads/ktp/<?= e($anggota['foto_ktp']); ?>" class="img-fluid rounded">
                <?php else: ?>
                    <p class="text-muted">Tidak ada.</p>
                <?php endif; ?>
            </div>
            <div>
                <small>Foto Diri</small>
                <?php if (!empty($anggota['foto_diri'])): ?>
                    <img src="/uploads/diri/<?= e($anggota['foto_diri']); ?>" class="img-fluid rounded">
                <?php else: ?>
                    <p class="text-muted">Tidak ada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
