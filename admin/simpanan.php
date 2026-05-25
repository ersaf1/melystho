<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$authUser = current_user();

if (isset($_GET['print'])) {
    $id   = (int)$_GET['print'];
    $stmt = $pdo->prepare("SELECT s.*, s.id_simpanan AS id, s.nm_simpanan AS jenis_simpanan, s.besar_simpanan AS nominal, s.tgl_simpanan AS tanggal_transaksi, s.ket AS keterangan, u.nama, u.id_anggota AS user_id FROM simpanan s JOIN anggota u ON s.id_anggota = u.id_anggota WHERE s.id_simpanan = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if ($data) {
        echo '<!DOCTYPE html><html><head><title>Bukti Simpanan</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">';
        echo '<style>body{font-family:Inter,sans-serif;padding:2rem;background:#f8f9fa}.receipt{max-width:480px;margin:auto;background:#fff;border-radius:12px;padding:2rem;box-shadow:0 4px 24px rgba(0,0,0,.08)}.receipt-header{text-align:center;border-bottom:2px dashed #e2e8f0;padding-bottom:1rem;margin-bottom:1rem}.receipt-row{display:flex;justify-content:space-between;margin-bottom:.6rem;font-size:.9rem}.receipt-label{color:#64748b}.receipt-value{font-weight:600}</style>';
        echo '</head><body>';
        echo '<div class="receipt">';
        echo '<div class="receipt-header"><h5 class="mb-1" style="color:#1e3a5f">Bukti Transaksi Simpanan</h5><p class="mb-0 text-muted" style="font-size:.8rem">Koperasi Simpan Pinjam</p></div>';
        echo '<div class="receipt-row"><span class="receipt-label">Nama Anggota</span><span class="receipt-value">' . e($data['nama']) . '</span></div>';
        echo '<div class="receipt-row"><span class="receipt-label">Tanggal Transaksi</span><span class="receipt-value">' . e(date('d F Y', strtotime($data['tanggal_transaksi']))) . '</span></div>';
        echo '<div class="receipt-row"><span class="receipt-label">Jenis Simpanan</span><span class="receipt-value">' . e(ucfirst($data['jenis_simpanan'])) . '</span></div>';
        echo '<div class="receipt-row"><span class="receipt-label">Nominal</span><span class="receipt-value" style="color:#16a34a">' . format_rupiah($data['nominal']) . '</span></div>';
        echo '<div class="receipt-row"><span class="receipt-label">Status</span><span class="receipt-value">' . e($data['status']) . '</span></div>';
        echo '<hr><p class="text-center text-muted" style="font-size:.75rem;margin-top:.5rem">Dicetak pada ' . date('d/m/Y H:i') . '</p>';
        echo '</div>';
        echo '<script>window.print();</script></body></html>';
        exit;
    }
}

if (is_post()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'manual') {
        $userId    = (int)($_POST['user_id'] ?? 0);
        $jenis     = $_POST['jenis_simpanan'] ?? 'sukarela';
        if (!in_array($jenis, ['pokok', 'wajib', 'sukarela'], true)) {
            $jenis = 'sukarela';
        }
        $nominal   = (float)($_POST['nominal'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');
        if ($userId && $nominal > 0) {
            $pdo->prepare("INSERT INTO simpanan (id_anggota, nm_simpanan, besar_simpanan, bukti_transfer, status, ket, tgl_simpanan, created_at) VALUES (?, ?, ?, '', 'Diterima', ?, CURDATE(), NOW())")->execute([$userId, $jenis, $nominal, $keterangan]);
            log_activity((int)$authUser['id'], 'Menambah simpanan manual sebesar ' . format_rupiah($nominal));
            set_flash('success', 'Transaksi simpanan berhasil ditambahkan.');
        }
    } elseif (in_array($action, ['approve', 'reject'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $status = $action === 'approve' ? 'Diterima' : 'Ditolak';
            $pdo->prepare("UPDATE simpanan SET status = ? WHERE id_simpanan = ?")->execute([$status, $id]);
            $row = $pdo->prepare("SELECT s.besar_simpanan AS nominal, s.id_anggota AS user_id, u.nama FROM simpanan s JOIN anggota u ON u.id_anggota = s.id_anggota WHERE s.id_simpanan = ?");
            $row->execute([$id]);
            $simpananRow = $row->fetch();
            log_activity((int)$authUser['id'], ($status === 'Diterima' ? 'Menyetujui' : 'Menolak') . ' simpanan ' . ($simpananRow['nama'] ?? 'anggota'));
            set_flash('success', 'Status simpanan diperbarui.');
        }
    }
    redirect('/admin/simpanan.php');
}

$filterUser   = (int)($_GET['user_id'] ?? 0);
$filterJenis  = $_GET['jenis'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';

$conditions = [];
$params = [];
if ($filterUser)     { $conditions[] = 's.id_anggota = ?';           $params[] = $filterUser; }
if ($filterJenis)    { $conditions[] = 's.nm_simpanan = ?';          $params[] = $filterJenis; }
if ($filterStatus)   { $conditions[] = 's.status = ?';            $params[] = $filterStatus; }
if ($filterTanggal)  { $conditions[] = 's.tgl_simpanan = ?';      $params[] = $filterTanggal; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare("SELECT s.*, s.id_simpanan AS id, s.nm_simpanan AS jenis_simpanan, s.besar_simpanan AS nominal, s.tgl_simpanan AS tanggal_transaksi, s.ket AS keterangan, u.nama, u.id_anggota AS user_id FROM simpanan s JOIN anggota u ON s.id_anggota = u.id_anggota $where ORDER BY s.created_at DESC");
$stmt->execute($params);
$simpanan = $stmt->fetchAll();

$users = $pdo->query("SELECT id_anggota AS id, nama FROM anggota WHERE role = 'user' ORDER BY nama ASC")->fetchAll();

$page_title = 'Manajemen Simpanan';
$role       = 'admin';

function badgeClass($status) {
    $map = ['Menunggu konfirmasi' => 'badge-menunggu', 'Diterima' => 'badge-diterima', 'Ditolak' => 'badge-ditolak'];
    return $map[$status] ?? 'badge-nonaktif';
}
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manajemen Simpanan</h1>
        <p class="page-sub">Kelola transaksi simpanan seluruh anggota koperasi.</p>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT: Filter + Table -->
    <div class="col-lg-8">
        <!-- Filter Card -->
        <div class="panel mb-4">
            <div class="panel-header py-3 px-4" style="border-bottom: 1px solid var(--border-light)">
                <span class="panel-title" style="font-size:.9rem"><i class="bi bi-funnel me-2 text-primary-custom"></i>Filter Transaksi</span>
            </div>
            <div class="panel-body p-4">
                <form class="row g-3" method="get">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:var(--text-secondary)">Anggota</label>
                        <select name="user_id" class="form-select form-select-sm" style="border-radius:var(--radius-sm)">
                            <option value="">Semua Anggota</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= e($u['id']); ?>" <?= $filterUser === (int)$u['id'] ? 'selected' : ''; ?>><?= e($u['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:var(--text-secondary)">Jenis Simpanan</label>
                        <select name="jenis" class="form-select form-select-sm" style="border-radius:var(--radius-sm)">
                            <option value="">Semua</option>
                            <option value="pokok"    <?= $filterJenis === 'pokok' ? 'selected' : ''; ?>>Pokok</option>
                            <option value="wajib"    <?= $filterJenis === 'wajib' ? 'selected' : ''; ?>>Wajib</option>
                            <option value="sukarela" <?= $filterJenis === 'sukarela' ? 'selected' : ''; ?>>Sukarela</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:var(--text-secondary)">Status</label>
                        <select name="status" class="form-select form-select-sm" style="border-radius:var(--radius-sm)">
                            <option value="">Semua</option>
                            <option value="Menunggu konfirmasi" <?= $filterStatus === 'Menunggu konfirmasi' ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="Diterima" <?= $filterStatus === 'Diterima' ? 'selected' : ''; ?>>Diterima</option>
                            <option value="Ditolak"  <?= $filterStatus === 'Ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label" style="font-size:.78rem;font-weight:600;color:var(--text-secondary)">Tanggal Transaksi</label>
                        <input type="date" name="tanggal" class="form-control form-control-sm" style="border-radius:var(--radius-sm)" value="<?= e($filterTanggal); ?>">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-2" style="border-top: 1px dashed var(--border-light)">
                        <a href="<?= base_url('/admin/simpanan.php') ?>" class="btn btn-light btn-sm px-4" style="border:1px solid var(--border);border-radius:var(--radius-sm);font-weight:500;font-size:.8rem"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                        <button type="submit" class="btn btn-primary btn-sm px-4" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border:none;border-radius:var(--radius-sm);font-weight:600;font-size:.8rem"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table Panel -->
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="border-bottom: 1px solid var(--border-light)">
                <div class="d-flex align-items-center gap-2">
                    <span class="panel-title" style="font-size:.9rem"><i class="bi bi-piggy-bank me-2 text-green"></i>Data Simpanan</span>
                    <span class="badge bg-light text-secondary border" style="font-size:.7rem;font-weight:600;padding:.25rem .55rem"><?= count($simpanan); ?> transaksi</span>
                </div>
                <div class="search-input-wrap" style="width: 240px;margin: 0">
                    <i class="bi bi-search search-icon"></i>
                    <input type="search" class="form-control form-control-sm" placeholder="Cari simpanan..." data-table-search="#simpananTable" style="border-radius:var(--radius-sm)">
                </div>
            </div>
            <div class="table-responsive panel-body p-0">
                <table class="data-table" id="simpananTable">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Anggota</th>
                            <th>Jenis</th>
                            <th>Nominal</th>
                            <th>Bukti</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($simpanan)): ?>
                            <tr><td colspan="7">
                                <div class="empty-state py-5">
                                    <i class="bi bi-folder-x" style="font-size: 2.2rem; color: var(--text-muted)"></i>
                                    <p class="mt-2 text-secondary" style="font-size:.85rem">Tidak ada data simpanan ditemukan</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($simpanan as $row): ?>
                                <tr>
                                    <td style="font-size:.8rem"><?= e(date('d/m/Y', strtotime($row['tanggal_transaksi']))); ?></td>
                                    <td style="font-weight:600;font-size:.85rem"><?= e($row['nama']); ?></td>
                                    <td>
                                        <span style="font-size:.72rem;font-weight:700;padding:.2rem .55rem;border-radius:var(--radius-full);background:var(--accent-green-light);color:var(--accent-green)">
                                            <?= e(ucfirst($row['jenis_simpanan'])); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:700;color:var(--accent-green)"><?= format_rupiah($row['nominal']); ?></td>
                                    <td>
                                        <?php if (!empty($row['bukti_transfer'])): ?>
                                            <a href="<?= base_url('/uploads/bukti_simpanan/' . e($row['bukti_transfer'])) ?>" target="_blank" class="btn-action view" title="Lihat Bukti"><i class="bi bi-file-image"></i></a>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:.78rem">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge-status <?= badgeClass($row['status']); ?>"><?= e($row['status']); ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if ($row['status'] === 'Menunggu konfirmasi'): ?>
                                                <form method="post" style="display:inline">
                                                    <input type="hidden" name="id" value="<?= e($row['id']); ?>">
                                                    <button type="submit" name="action" value="approve" class="btn-action approve" title="Setujui" onclick="return confirm('Setujui simpanan ini?')"><i class="bi bi-check-lg"></i></button>
                                                </form>
                                                <form method="post" style="display:inline">
                                                    <input type="hidden" name="id" value="<?= e($row['id']); ?>">
                                                    <button type="submit" name="action" value="reject" class="btn-action reject" title="Tolak" onclick="return confirm('Tolak simpanan ini?')"><i class="bi bi-x-lg"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="<?= base_url('/admin/simpanan.php?print=' . e($row['id'])) ?>" class="btn-action print" title="Cetak" target="_blank"><i class="bi bi-printer"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: Manual Add Form -->
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-plus-circle me-2" style="color:var(--accent-green)"></i>Tambah Simpanan Manual</span>
            </div>
            <div class="panel-body">
                <div class="alert alert-info-custom mb-4" style="font-size:.78rem">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Simpanan yang ditambahkan secara manual akan langsung berstatus <strong>Diterima</strong>.</span>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="manual">
                    <div class="mb-3">
                        <label class="form-label">Anggota <span class="required">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">— Pilih Anggota —</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= e($u['id']); ?>"><?= e($u['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Simpanan <span class="required">*</span></label>
                        <select name="jenis_simpanan" class="form-select">
                            <option value="pokok">Pokok</option>
                            <option value="wajib">Wajib</option>
                            <option value="sukarela">Sukarela</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nominal <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="nominal" data-type="currency" class="form-control" required placeholder="0">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Opsional"></textarea>
                    </div>
                    <button type="submit" class="btn w-100 py-2 fw-700" style="background:linear-gradient(135deg,var(--accent-green),#15803d);color:#fff;border:none;border-radius:var(--radius);font-size:.9rem">
                        <i class="bi bi-plus-circle-fill me-2"></i>Tambah Simpanan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
