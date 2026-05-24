<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];

if (is_post()) {
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nama === '' || $alamat === '' || $no_hp === '' || $email === '') {
        $errors[] = 'Nama, alamat, no HP, dan email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user['id']]);
    if ($stmt->fetch()) {
        $errors[] = 'Email sudah digunakan.';
    }

    if (empty($errors)) {
        $params = [$nama, $alamat, $no_hp, $email, $pekerjaan, $user['id']];
        $sql = "UPDATE users SET nama = ?, alamat = ?, no_hp = ?, email = ?, pekerjaan = ?, updated_at = NOW()";
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params = [$nama, $alamat, $no_hp, $email, $pekerjaan, $hash, $user['id']];
        }
        $sql .= " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        log_activity((int)$user['id'], 'Memperbarui profil');
        set_flash('success', 'Profil berhasil diperbarui.');
        redirect('/user/profil.php');
    }
}

$page_title = 'Profil Anggota';
$role = 'user';
$flash = get_flash();
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="form-section">
    <h4 class="mb-4">Profil Anggota</h4>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= e($errors[0]); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nama</label>
                <input type="text" name="nama" class="form-control" value="<?= e($user['nama']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">No HP</label>
                <input type="text" name="no_hp" class="form-control" value="<?= e($user['no_hp']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Pekerjaan</label>
                <input type="text" name="pekerjaan" class="form-control" value="<?= e($user['pekerjaan']); ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Alamat</label>
                <textarea name="alamat" class="form-control" rows="2" required><?= e($user['alamat']); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password Baru (opsional)</label>
                <input type="password" name="password" class="form-control">
            </div>
        </div>
        <button class="btn btn-primary mt-4">Simpan</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
