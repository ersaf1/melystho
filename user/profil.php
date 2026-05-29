<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo  = db();
$errors = [];

// ══════════════════════════════════════════════════════════════════════════════
// Ambil data anggota lengkap dari database (dinamis, bukan hardcode)
// ══════════════════════════════════════════════════════════════════════════════
$stmtAnggota = $pdo->prepare("SELECT * FROM anggota WHERE id_anggota = ?");
$stmtAnggota->execute([$user['id']]);
$anggota = $stmtAnggota->fetch();

if (!$anggota) {
    set_flash('danger', 'Data anggota tidak ditemukan.');
    redirect('/user/dashboard.php');
}

// ── Nomor anggota (format ANG-XXXX) ──────────────────────────────────────────
$nomorAnggota = 'ANG-' . str_pad((string)$anggota['id_anggota'], 4, '0', STR_PAD_LEFT);

// ── Tanggal bergabung ─────────────────────────────────────────────────────────
$tglBergabung = !empty($anggota['created_at'])
    ? date('d F Y', strtotime($anggota['created_at']))
    : 'Tidak tersedia';

// ── Total simpanan yang sudah disetujui ───────────────────────────────────────
$stmtSimpanan = $pdo->prepare("SELECT COALESCE(SUM(besar_simpanan), 0) AS total FROM simpanan WHERE id_anggota = ? AND status = 'Diterima'");
$stmtSimpanan->execute([$user['id']]);
$totalSimpanan = (float)($stmtSimpanan->fetch()['total'] ?? 0);

// ── Total pinjaman aktif (Dicairkan) ─────────────────────────────────────────
$stmtPinjaman = $pdo->prepare("SELECT COALESCE(SUM(besar_pinjaman), 0) AS total, COUNT(*) AS jumlah FROM pinjaman WHERE id_anggota = ? AND status IN ('Dicairkan', 'Disetujui', 'Menunggu review')");
$stmtPinjaman->execute([$user['id']]);
$rowPinjaman  = $stmtPinjaman->fetch();
$totalPinjaman  = (float)($rowPinjaman['total'] ?? 0);
$jumlahPinjaman = (int)($rowPinjaman['jumlah'] ?? 0);

// ── Simpanan per jenis ────────────────────────────────────────────────────────
$stmtJenis = $pdo->prepare("SELECT nm_simpanan AS jenis, COALESCE(SUM(besar_simpanan), 0) AS total FROM simpanan WHERE id_anggota = ? AND status = 'Diterima' GROUP BY nm_simpanan");
$stmtJenis->execute([$user['id']]);
$simpananJenis = ['pokok' => 0, 'wajib' => 0, 'sukarela' => 0];
foreach ($stmtJenis->fetchAll() as $row) {
    $simpananJenis[$row['jenis']] = (float)$row['total'];
}

// ══════════════════════════════════════════════════════════════════════════════
// POST Handler – Update Profil
// ══════════════════════════════════════════════════════════════════════════════
if (is_post()) {
    $nama      = trim($_POST['nama']      ?? '');
    $alamat    = trim($_POST['alamat']    ?? '');
    $no_hp     = trim($_POST['no_hp']     ?? '');
    $email     = trim($_POST['email']     ?? '');
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($nama === '' || $alamat === '' || $no_hp === '' || $email === '') {
        $errors[] = 'Nama, alamat, no HP, dan email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if (empty($errors)) {
        $stmtCek = $pdo->prepare("SELECT id_anggota FROM anggota WHERE email = ? AND id_anggota != ?");
        $stmtCek->execute([$email, $user['id']]);
        if ($stmtCek->fetch()) {
            $errors[] = 'Email sudah digunakan oleh anggota lain.';
        }
    }

    if (empty($errors)) {
        $sql    = "UPDATE anggota SET nama = ?, alamat = ?, no_tlp = ?, email = ?, ket = ?, updated_at = NOW()";
        $params = [$nama, $alamat, $no_hp, $email, $pekerjaan];

        if ($password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Password baru minimal 6 karakter.';
            } else {
                $sql   .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        if (empty($errors)) {
            $sql   .= " WHERE id_anggota = ?";
            $params[] = $user['id'];
            $pdo->prepare($sql)->execute($params);
            log_activity((int)$user['id'], 'Memperbarui profil');
            set_flash('success', 'Profil berhasil diperbarui.');
            redirect('/user/profil.php');
        }
    }
}

$page_title = 'Profil Anggota';
$role = 'user';
$flash = get_flash();
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<!-- ══════════════════════════════════════════════════
     RINGKASAN KEANGGOTAAN (Data Dinamis dari DB)
══════════════════════════════════════════════════ -->
<div class="panel mb-4">
    <div class="panel-header">
        <span class="panel-title">
            <i class="bi bi-person-badge-fill me-2 text-primary-custom"></i>Ringkasan Keanggotaan
        </span>
        <span class="badge-status <?= status_badge_class($anggota['status']); ?>">
            <?= e($anggota['status']); ?>
        </span>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <!-- Identitas Anggota -->
            <div class="col-md-6">
                <div class="p-3 rounded border h-100" style="background:var(--bg,#f8f9fa)">
                    <h6 class="fw-bold mb-3" style="color:var(--primary)">
                        <i class="bi bi-person-fill me-1"></i>Identitas Anggota
                    </h6>
                    <table class="w-100" style="font-size:.88rem;border-collapse:separate;border-spacing:0 .4rem">
                        <tr>
                            <td style="color:var(--text-muted);width:140px">Nama Lengkap</td>
                            <td><strong><?= e($anggota['nama']); ?></strong></td>
                        </tr>
                        <tr>
                            <td style="color:var(--text-muted)">Email</td>
                            <td><?= e($anggota['email']); ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--text-muted)">No. HP</td>
                            <td><?= e($anggota['no_tlp'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--text-muted)">Nomor Anggota</td>
                            <td>
                                <code style="background:var(--primary-soft,#e8f4ff);color:var(--primary);padding:.15rem .4rem;border-radius:4px;font-weight:700">
                                    <?= e($nomorAnggota); ?>
                                </code>
                            </td>
                        </tr>
                        <tr>
                            <td style="color:var(--text-muted)">Bergabung</td>
                            <td><?= e($tglBergabung); ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--text-muted)">NIK</td>
                            <td><?= e($anggota['nik'] ?: '-'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Ringkasan Keuangan -->
            <div class="col-md-6">
                <div class="p-3 rounded border h-100" style="background:var(--bg,#f8f9fa)">
                    <h6 class="fw-bold mb-3" style="color:var(--accent-green,#22c55e)">
                        <i class="bi bi-graph-up-arrow me-1"></i>Ringkasan Keuangan
                    </h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center p-2 rounded" style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2)">
                                <div style="font-size:.72rem;color:var(--text-muted)">Total Simpanan</div>
                                <div style="font-size:.9rem;font-weight:700;color:var(--accent-green)"><?= format_rupiah($totalSimpanan); ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 rounded" style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2)">
                                <div style="font-size:.72rem;color:var(--text-muted)">Total Pinjaman Aktif</div>
                                <div style="font-size:.9rem;font-weight:700;color:var(--accent-amber)"><?= format_rupiah($totalPinjaman); ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 rounded" style="background:var(--primary-soft,#e8f4ff);border:1px solid rgba(59,130,246,.15)">
                                <div style="font-size:.68rem;color:var(--text-muted)">Simpanan Pokok</div>
                                <div style="font-size:.8rem;font-weight:600;color:var(--primary)"><?= format_rupiah($simpananJenis['pokok']); ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 rounded" style="background:var(--primary-soft,#e8f4ff);border:1px solid rgba(59,130,246,.15)">
                                <div style="font-size:.68rem;color:var(--text-muted)">Simpanan Wajib</div>
                                <div style="font-size:.8rem;font-weight:600;color:var(--primary)"><?= format_rupiah($simpananJenis['wajib']); ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 rounded" style="background:var(--primary-soft,#e8f4ff);border:1px solid rgba(59,130,246,.15)">
                                <div style="font-size:.68rem;color:var(--text-muted)">Simpanan Sukarela</div>
                                <div style="font-size:.8rem;font-weight:600;color:var(--primary)"><?= format_rupiah($simpananJenis['sukarela']); ?></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-2 rounded"
                                 style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);font-size:.82rem">
                                <span style="color:var(--text-muted)">Pinjaman Aktif / Pengajuan</span>
                                <strong><?= $jumlahPinjaman; ?> pinjaman</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     FORM EDIT PROFIL
══════════════════════════════════════════════════ -->
<div class="form-section">
    <h4 class="mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Profil</h4>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= e($errors[0]); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                <input type="text" name="nama" class="form-control"
                       value="<?= e($anggota['nama']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control"
                       value="<?= e($anggota['email']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">No HP <span class="required">*</span></label>
                <input type="text" name="no_hp" class="form-control"
                       value="<?= e($anggota['no_tlp']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Pekerjaan</label>
                <input type="text" name="pekerjaan" class="form-control"
                       value="<?= e($anggota['ket']); ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Alamat <span class="required">*</span></label>
                <textarea name="alamat" class="form-control" rows="2" required><?= e($anggota['alamat']); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password Baru
                    <small class="text-muted">(kosongkan jika tidak ingin ubah)</small>
                </label>
                <input type="password" name="password" class="form-control" minlength="6"
                       placeholder="Min. 6 karakter">
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary px-4">
                <i class="bi bi-save me-1"></i>Simpan Perubahan
            </button>
            <a href="<?= base_url('/user/dashboard.php') ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
