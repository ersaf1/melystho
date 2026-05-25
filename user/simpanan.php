<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();

// Fetch transaction history
$stmt = $pdo->prepare("SELECT *, id_simpanan AS id, nm_simpanan AS jenis_simpanan, besar_simpanan AS nominal, tgl_simpanan AS tanggal_transaksi, ket AS keterangan FROM simpanan WHERE id_anggota = ? ORDER BY tgl_simpanan DESC");
$stmt->execute([$user['id']]);
$simpanan = $stmt->fetchAll();

// Fetch savings totals
$stmt = $pdo->prepare("SELECT nm_simpanan AS jenis_simpanan, SUM(besar_simpanan) AS total FROM simpanan WHERE id_anggota = ? AND status = 'Diterima' GROUP BY nm_simpanan");
$stmt->execute([$user['id']]);
$totals = ['pokok' => 0, 'wajib' => 0, 'sukarela' => 0];
foreach ($stmt->fetchAll() as $row) {
    $totals[$row['jenis_simpanan']] = (float)$row['total'];
}
$totalSaldo = array_sum($totals);

$page_title = 'Simpanan';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Tabungan Saya</h1>
        <p class="page-sub">Pantau saldo akumulasi dan ajukan simpanan secara mandiri.</p>
    </div>
    <a href="<?= base_url('/user/ajukan-simpanan.php') ?>" class="btn-primary-custom" id="ajukanSimpananBtn">
        <i class="bi bi-piggy-bank-fill me-1"></i>Ajukan Simpanan
    </a>
</div>

<!-- ─── STAT CARDS ─── -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="bi bi-wallet2"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Saldo Simpanan</div>
                <div class="stat-value sm"><?= format_rupiah($totalSaldo); ?></div>
                <div class="stat-trend">Akumulasi seluruh tabungan</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-bank"></i></div>
            <div class="stat-info">
                <div class="stat-label">Simpanan Pokok</div>
                <div class="stat-value sm"><?= format_rupiah($totals['pokok']); ?></div>
                <div class="stat-trend">Setoran awal masuk anggota</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-info">
                <div class="stat-label">Simpanan Wajib</div>
                <div class="stat-value sm"><?= format_rupiah($totals['wajib']); ?></div>
                <div class="stat-trend">Setoran wajib bulanan</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-piggy-bank"></i></div>
            <div class="stat-info">
                <div class="stat-label">Simpanan Sukarela</div>
                <div class="stat-value sm"><?= format_rupiah($totals['sukarela']); ?></div>
                <div class="stat-trend">Tabungan fleksibel/sukarela</div>
            </div>
        </div>
    </div>
</div>

<!-- Table Panel -->
<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="border-bottom: 1px solid var(--border-light)">
        <div class="d-flex align-items-center gap-2">
            <span class="panel-title" style="font-size:.9rem"><i class="bi bi-clock-history me-2 text-primary-custom"></i>Riwayat Transaksi Simpanan</span>
            <span class="badge bg-light text-secondary border" style="font-size:.7rem;font-weight:600;padding:.25rem .55rem"><?= count($simpanan); ?> transaksi</span>
        </div>
        <div class="search-input-wrap" style="width: 240px;margin: 0">
            <i class="bi bi-search search-icon"></i>
            <input type="search" class="form-control form-control-sm" placeholder="Cari transaksi..." data-table-search="#simpananUserTable" style="border-radius:var(--radius-sm)">
        </div>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table" id="simpananUserTable">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Simpanan</th>
                    <th>Nominal</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($simpanan)): ?>
                    <tr><td colspan="5">
                        <div class="empty-state py-5">
                            <i class="bi bi-folder-x" style="font-size: 2.2rem; color: var(--text-muted)"></i>
                            <p class="mt-2 text-secondary" style="font-size:.85rem">Belum ada riwayat transaksi simpanan</p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($simpanan as $item): ?>
                        <tr>
                            <td style="font-size:.8rem"><?= e(date('d/m/Y', strtotime($item['tanggal_transaksi']))); ?></td>
                            <td>
                                <span style="font-size:.72rem;font-weight:700;padding:.2rem .55rem;border-radius:var(--radius-full);background:var(--accent-green-light);color:var(--accent-green)">
                                    <?= e(ucfirst($item['jenis_simpanan'])); ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:var(--accent-green)"><?= format_rupiah($item['nominal']); ?></td>
                            <td><span class="badge-status <?= status_badge_class($item['status']); ?>"><?= e($item['status']); ?></span></td>
                            <td style="font-size:.8rem;color:var(--text-secondary)"><?= e($item['keterangan'] !== '' ? $item['keterangan'] : '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
