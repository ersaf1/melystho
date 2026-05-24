<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo  = db();
sync_late_fines((int)$user['id']);
sync_due_reminders((int)$user['id']);

$stmt = $pdo->prepare("SELECT jenis_simpanan, SUM(nominal) AS total FROM simpanan WHERE user_id = ? AND status = 'Diterima' GROUP BY jenis_simpanan");
$stmt->execute([$user['id']]);
$simpananTotals = ['pokok' => 0, 'wajib' => 0, 'sukarela' => 0];
foreach ($stmt->fetchAll() as $row) {
    $simpananTotals[$row['jenis_simpanan']] = (float)$row['total'];
}
$totalSimpanan = array_sum($simpananTotals);

$stmt = $pdo->prepare("SELECT SUM(nominal) AS total FROM pinjaman WHERE user_id = ? AND status IN ('Menunggu review', 'Disetujui', 'Dicairkan')");
$stmt->execute([$user['id']]);
$totalPinjaman = (float)($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->prepare("SELECT SUM(a.nominal) AS total FROM angsuran a JOIN pinjaman p ON a.pinjaman_id = p.id WHERE p.user_id = ? AND a.status != 'Diterima'");
$stmt->execute([$user['id']]);
$sisaAngsuran = (float)($stmt->fetch()['total'] ?? 0);
$totalDenda = unpaid_fines_total((int)$user['id']);

$recentSimpanan = $pdo->prepare("SELECT * FROM simpanan WHERE user_id = ? ORDER BY tanggal_transaksi DESC LIMIT 5");
$recentSimpanan->execute([$user['id']]);
$recentSimpanan = $recentSimpanan->fetchAll();

$recentAngsuran = $pdo->prepare("SELECT a.*, p.nomor_pinjaman FROM angsuran a JOIN pinjaman p ON a.pinjaman_id = p.id WHERE p.user_id = ? ORDER BY a.created_at DESC LIMIT 5");
$recentAngsuran->execute([$user['id']]);
$recentAngsuran = $recentAngsuran->fetchAll();

$isVerified = $user['status_verifikasi'] === 'Disetujui';

$page_title = 'Dashboard';
$role = 'user';

/* Badge helper */
function badgeClass($status) {
    $map = [
        'Menunggu Verifikasi'  => 'badge-menunggu',
        'Menunggu konfirmasi'  => 'badge-menunggu',
        'Menunggu review'      => 'badge-menunggu',
        'Disetujui'            => 'badge-disetujui',
        'Diterima'             => 'badge-diterima',
        'Dicairkan'            => 'badge-dicairkan',
        'Ditolak'              => 'badge-ditolak',
        'Lunas'                => 'badge-lunas',
        'Nonaktif'             => 'badge-nonaktif',
    ];
    return $map[$status] ?? 'badge-nonaktif';
}
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Selamat datang, <?= e(explode(' ', $user['nama'])[0]); ?> 👋</h1>
        <p class="page-sub">Berikut ringkasan keuangan koperasi Anda hari ini.</p>
    </div>
    <?php if ($isVerified): ?>
        <a href="/user/ajukan-pinjaman.php" class="btn-primary-custom" id="ajukanPinjamanBtn">
            <i class="bi bi-plus-circle-fill"></i>Ajukan Pinjaman
        </a>
    <?php else: ?>
        <button class="btn-primary-custom" disabled style="opacity:.5;cursor:not-allowed" title="Akun belum terverifikasi">
            <i class="bi bi-lock-fill"></i>Ajukan Pinjaman
        </button>
    <?php endif; ?>
</div>

<!-- Alert jika belum terverifikasi -->
<?php if (!$isVerified): ?>
    <div class="alert alert-warning-custom mb-4">
        <i class="bi bi-clock-history"></i>
        <div>
            <strong>Akun Anda Sedang Menunggu Verifikasi</strong>
            <div style="font-size:.82rem;margin-top:2px">
                Status saat ini: <strong><?= e($user['status_verifikasi']); ?></strong>.
                Beberapa fitur seperti pengajuan pinjaman akan aktif setelah admin memverifikasi akun Anda (1–2 hari kerja).
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ─── STAT CARDS ─── -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-piggy-bank-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Simpanan</div>
                <div class="stat-value"><?= format_rupiah($totalSimpanan); ?></div>
                <div class="stat-trend">Pokok + Wajib + Sukarela</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-info">
                <div class="stat-label">Pinjaman Aktif</div>
                <div class="stat-value"><?= format_rupiah($totalPinjaman); ?></div>
                <div class="stat-trend">Dalam proses / cair</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-info">
                <div class="stat-label">Sisa Angsuran</div>
                <div class="stat-value"><?= format_rupiah($sisaAngsuran); ?></div>
                <div class="stat-trend">Total belum terbayar</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="bi bi-person-check-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Status Anggota</div>
                <div class="stat-value sm"><?= e($user['status_verifikasi']); ?></div>
                <div class="stat-trend">Keanggotaan koperasi</div>
            </div>
        </div>
    </div>
</div>

<?php if ($totalDenda > 0): ?>
    <div class="alert alert-danger-custom mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>Denda belum dibayar: <?= format_rupiah($totalDenda); ?></strong>
            <div style="font-size:.82rem;margin-top:2px">Detail denda dapat dilihat pada halaman angsuran dan detail pinjaman.</div>
        </div>
    </div>
<?php endif; ?>

<!-- ─── SIMPANAN BREAKDOWN ─── -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card green" style="flex-direction:column;align-items:flex-start;gap:.5rem">
            <div class="stat-label" style="display:flex;align-items:center;gap:.4rem"><i class="bi bi-wallet2"></i>Simpanan Pokok</div>
            <div class="stat-value sm"><?= format_rupiah($simpananTotals['pokok']); ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card green" style="flex-direction:column;align-items:flex-start;gap:.5rem">
            <div class="stat-label" style="display:flex;align-items:center;gap:.4rem"><i class="bi bi-piggy-bank"></i>Simpanan Wajib</div>
            <div class="stat-value sm"><?= format_rupiah($simpananTotals['wajib']); ?></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card green" style="flex-direction:column;align-items:flex-start;gap:.5rem">
            <div class="stat-label" style="display:flex;align-items:center;gap:.4rem"><i class="bi bi-safe2"></i>Simpanan Sukarela</div>
            <div class="stat-value sm"><?= format_rupiah($simpananTotals['sukarela']); ?></div>
        </div>
    </div>
</div>

<!-- ─── RECENT TABLES ─── -->
<div class="row g-4">
    <!-- Simpanan Terbaru -->
    <div class="col-lg-6">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-piggy-bank me-2 text-green"></i>Simpanan Terbaru</span>
                <a href="/user/simpanan.php" style="font-size:.78rem;color:var(--primary);font-weight:600;text-decoration:none">Lihat Semua →</a>
            </div>
            <div class="table-responsive panel-body p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSimpanan)): ?>
                            <tr><td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <p>Belum ada riwayat simpanan</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($recentSimpanan as $item): ?>
                                <tr>
                                    <td><?= e(date('d/m/Y', strtotime($item['tanggal_transaksi']))); ?></td>
                                    <td><span style="font-weight:600"><?= e(ucfirst($item['jenis_simpanan'])); ?></span></td>
                                    <td><?= format_rupiah($item['nominal']); ?></td>
                                    <td><span class="badge-status <?= badgeClass($item['status']); ?>"><?= e($item['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Angsuran Terbaru -->
    <div class="col-lg-6">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-calendar-check me-2" style="color:var(--accent-amber)"></i>Angsuran Terbaru</span>
                <a href="/user/bayar-angsuran.php" style="font-size:.78rem;color:var(--primary);font-weight:600;text-decoration:none">Lihat Semua →</a>
            </div>
            <div class="table-responsive panel-body p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Pinjaman</th>
                            <th>Ke-</th>
                            <th>Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAngsuran)): ?>
                            <tr><td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <p>Belum ada riwayat angsuran</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($recentAngsuran as $item): ?>
                                <tr>
                                    <td><code style="font-size:.78rem;background:var(--bg);padding:.15rem .4rem;border-radius:4px"><?= e($item['nomor_pinjaman']); ?></code></td>
                                    <td><?= e($item['angsuran_ke']); ?></td>
                                    <td><?= format_rupiah($item['nominal']); ?></td>
                                    <td><span class="badge-status <?= badgeClass($item['status']); ?>"><?= e($item['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="panel mt-4">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-lightning-fill me-2" style="color:var(--accent-amber)"></i>Aksi Cepat</span>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <a href="/user/ajukan-simpanan.php" class="d-flex flex-column align-items-center gap-2 p-3 text-decoration-none"
                   style="background:var(--accent-green-soft);border:1px solid var(--accent-green-light);border-radius:var(--radius);transition:var(--transition)" id="quickSimpananBtn">
                    <i class="bi bi-plus-circle-fill" style="font-size:1.5rem;color:var(--accent-green)"></i>
                    <span style="font-size:.78rem;font-weight:600;color:var(--accent-green)">Setor Simpanan</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= $isVerified ? '/user/ajukan-pinjaman.php' : '#'; ?>"
                   class="d-flex flex-column align-items-center gap-2 p-3 text-decoration-none <?= !$isVerified ? 'pe-none' : ''; ?>"
                   style="background:<?= $isVerified ? 'var(--accent-amber-light)' : 'var(--bg)'; ?>;border:1px solid <?= $isVerified ? 'var(--accent-amber-light)' : 'var(--border)'; ?>;border-radius:var(--radius);opacity:<?= $isVerified ? '1' : '.5'; ?>;transition:var(--transition)" id="quickPinjamanBtn">
                    <i class="bi bi-send-fill" style="font-size:1.5rem;color:<?= $isVerified ? 'var(--accent-amber)' : 'var(--text-muted)'; ?>"></i>
                    <span style="font-size:.78rem;font-weight:600;color:<?= $isVerified ? 'var(--accent-amber)' : 'var(--text-muted)'; ?>">Ajukan Pinjaman</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="/user/bayar-angsuran.php" class="d-flex flex-column align-items-center gap-2 p-3 text-decoration-none"
                   style="background:var(--accent-blue-light);border:1px solid var(--accent-blue-light);border-radius:var(--radius);transition:var(--transition)" id="quickAngsuranBtn">
                    <i class="bi bi-calendar-check-fill" style="font-size:1.5rem;color:var(--accent-blue)"></i>
                    <span style="font-size:.78rem;font-weight:600;color:var(--accent-blue)">Bayar Angsuran</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="/user/profil.php" class="d-flex flex-column align-items-center gap-2 p-3 text-decoration-none"
                   style="background:var(--primary-soft);border:1px solid var(--primary-soft);border-radius:var(--radius);transition:var(--transition)" id="quickProfilBtn">
                    <i class="bi bi-person-gear" style="font-size:1.5rem;color:var(--primary)"></i>
                    <span style="font-size:.78rem;font-weight:600;color:var(--primary)">Ubah Profil</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
