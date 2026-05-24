<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();

if (is_post()) {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE users SET status_verifikasi = 'Disetujui', updated_at = NOW() WHERE id = ?")->execute([$id]);
            set_flash('success', 'Anggota berhasil disetujui.');
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE users SET status_verifikasi = 'Ditolak', updated_at = NOW() WHERE id = ?")->execute([$id]);
            set_flash('success', 'Pendaftaran anggota ditolak.');
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE users SET status_verifikasi = IF(status_verifikasi = 'Nonaktif', 'Disetujui', 'Nonaktif'), updated_at = NOW() WHERE id = ?")->execute([$id]);
            set_flash('success', 'Status anggota diperbarui.');
        }
    }
    redirect('/admin/anggota.php');
}

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

if ($q !== '') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'user' AND (nama LIKE ? OR nik LIKE ? OR email LIKE ?)");
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);
    $total = (int)$stmt->fetch()['total'];
    $stmt  = $pdo->prepare("SELECT * FROM users WHERE role = 'user' AND (nama LIKE ? OR nik LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, "%$q%"); $stmt->bindValue(2, "%$q%"); $stmt->bindValue(3, "%$q%");
    $stmt->bindValue(4, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(5, $offset,  PDO::PARAM_INT);
    $stmt->execute();
} else {
    $total = (int)$pdo->query("SELECT COUNT(*) AS total FROM users WHERE role = 'user'")->fetch()['total'];
    $stmt  = $pdo->prepare("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset,  PDO::PARAM_INT);
    $stmt->execute();
}
$anggota = $stmt->fetchAll();

$page_title = 'Manajemen Anggota';
$role       = 'admin';
$baseUrl    = '/admin/anggota.php?' . ($q !== '' ? 'q=' . urlencode($q) . '&' : '');

function badgeClass($status) {
    $map = ['Menunggu Verifikasi' => 'badge-menunggu', 'Disetujui' => 'badge-disetujui', 'Ditolak' => 'badge-ditolak', 'Nonaktif' => 'badge-nonaktif'];
    return $map[$status] ?? 'badge-nonaktif';
}
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manajemen Anggota</h1>
        <p class="page-sub">Total <?= $total; ?> anggota terdaftar<?= $q ? " — hasil pencarian: <strong>" . e($q) . "</strong>" : ""; ?></p>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form class="row g-2 align-items-end" method="get">
        <div class="col-md-6 col-lg-5">
            <label class="form-label" style="font-size:.78rem">Cari Anggota</label>
            <div class="search-input-wrap">
                <i class="bi bi-search search-icon"></i>
                <input type="text" name="q" class="form-control" placeholder="Nama, NIK, atau email…" value="<?= e($q); ?>" id="searchAnggota">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary px-4" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius);font-size:.875rem" id="searchBtn">
                <i class="bi bi-search me-1"></i>Cari
            </button>
        </div>
        <?php if ($q): ?>
        <div class="col-auto">
            <a href="/admin/anggota.php" class="btn btn-outline-secondary px-3" style="border-radius:var(--radius);font-size:.875rem">
                <i class="bi bi-x me-1"></i>Reset
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-people me-2" style="color:var(--primary)"></i>Daftar Anggota</span>
        <span style="font-size:.78rem;color:var(--text-muted)">
            Halaman <?= $page; ?> — Menampilkan <?= count($anggota); ?> dari <?= $total; ?> data
        </span>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Anggota</th>
                    <th>NIK</th>
                    <th>Kontak</th>
                    <th>Bergabung</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($anggota)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <p>Tidak ada data anggota<?= $q ? " dengan kata kunci '$q'" : ""; ?></p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($anggota as $i => $item): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:.78rem"><?= $offset + $i + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;flex-shrink:0">
                                        <?= strtoupper(substr($item['nama'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:.875rem"><?= e($item['nama']); ?></div>
                                        <div style="font-size:.72rem;color:var(--text-muted)">@<?= e($item['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-family:monospace;font-size:.8rem"><?= e($item['nik']); ?></td>
                            <td>
                                <div style="font-size:.82rem"><?= e($item['email']); ?></div>
                                <div style="font-size:.75rem;color:var(--text-muted)"><?= e($item['no_hp'] ?? '-'); ?></div>
                            </td>
                            <td style="font-size:.78rem;color:var(--text-muted)"><?= e(date('d/m/Y', strtotime($item['created_at']))); ?></td>
                            <td>
                                <span class="badge-status <?= badgeClass($item['status_verifikasi']); ?>">
                                    <?= e($item['status_verifikasi']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="/admin/detail-anggota.php?id=<?= e($item['id']); ?>" class="btn-action view" title="Lihat Detail"><i class="bi bi-eye"></i></a>
                                    <?php if ($item['status_verifikasi'] === 'Menunggu Verifikasi'): ?>
                                        <button class="btn-action approve"
                                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-action="/admin/anggota.php"
                                            data-message="Setujui pendaftaran anggota <?= e($item['nama']); ?>?"
                                                data-id="<?= e($item['id']); ?>"
                                                data-action-type="approve"
                                                title="Setujui">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn-action reject"
                                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-action="/admin/anggota.php"
                                            data-message="Tolak pendaftaran anggota <?= e($item['nama']); ?>?"
                                                data-id="<?= e($item['id']); ?>"
                                                data-action-type="reject"
                                                title="Tolak">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-action toggle"
                                            data-bs-toggle="modal" data-bs-target="#confirmModal"
                                            data-action="/admin/anggota.php"
                                            data-message="Ubah status aktif/nonaktif anggota <?= e($item['nama']); ?>?"
                                            data-id="<?= e($item['id']); ?>"
                                            data-action-type="toggle"
                                            title="Toggle Status">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="panel-body" style="border-top:1px solid var(--border-light);padding:.85rem 1.5rem">
        <?= build_pagination($total, $page, $perPage, $baseUrl); ?>
    </div>
    <?php endif; ?>
</div>

<!-- ─── Confirm Modal ─── -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="modal-icon warning mb-3">
                    <i class="bi bi-question-circle"></i>
                </div>
                <h6 class="fw-700 mb-2">Konfirmasi Aksi</h6>
                <p class="confirm-message mb-0" style="font-size:.875rem;color:var(--text-secondary)"></p>
                <form method="post" class="mt-4">
                    <input type="hidden" name="id" id="confirmId">
                    <input type="hidden" name="action" id="confirmAction">
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius:var(--radius);font-size:.875rem">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius);font-size:.875rem">Ya, Lanjutkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = "<script>
const modal = document.getElementById('confirmModal');
modal.addEventListener('show.bs.modal', event => {
    const btn = event.relatedTarget;
    document.getElementById('confirmId').value = btn.getAttribute('data-id');
    document.getElementById('confirmAction').value = btn.getAttribute('data-action-type');
    modal.querySelector('.confirm-message').innerHTML = btn.getAttribute('data-message');
    modal.querySelector('form').setAttribute('action', btn.getAttribute('data-action'));
    const typeMap = { approve: 'success', reject: 'danger', toggle: 'warning' };
    const iconMap = { approve: 'bi-check-circle', reject: 'bi-x-circle', toggle: 'bi-arrow-repeat' };
    const t = btn.getAttribute('data-action-type');
    const iconEl = modal.querySelector('.modal-icon');
    iconEl.className = 'modal-icon ' + (typeMap[t] || 'warning') + ' mb-3';
    iconEl.innerHTML = '<i class=\"bi ' + (iconMap[t] || 'bi-question-circle') + '\"></i>';
});
</script>";
?>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
