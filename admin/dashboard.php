<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
sync_late_fines();

$totalAnggota        = (int)$pdo->query("SELECT COUNT(*) AS total FROM users WHERE role = 'user'")->fetch()['total'];
$menungguVerif       = (int)$pdo->query("SELECT COUNT(*) AS total FROM users WHERE role = 'user' AND status_verifikasi = 'Menunggu Verifikasi'")->fetch()['total'];
$totalSimpanan       = (float)$pdo->query("SELECT SUM(nominal) AS total FROM simpanan WHERE status = 'Diterima'")->fetch()['total'];
$totalPinjamanAktif  = (float)$pdo->query("SELECT SUM(nominal) AS total FROM pinjaman WHERE status IN ('Disetujui', 'Dicairkan')")->fetch()['total'];
$totalPinjamanMenunggu = (int)$pdo->query("SELECT COUNT(*) AS total FROM pinjaman WHERE status = 'Menunggu review'")->fetch()['total'];
$totalAngsuranBulan  = (float)$pdo->query("SELECT SUM(nominal) AS total FROM angsuran WHERE status = 'Diterima' AND MONTH(tanggal_bayar) = MONTH(CURDATE()) AND YEAR(tanggal_bayar) = YEAR(CURDATE())")->fetch()['total'];
$pinjamanJatuhTempo  = (int)$pdo->query("SELECT COUNT(*) AS total FROM angsuran a JOIN pinjaman p ON a.pinjaman_id = p.id WHERE a.status != 'Diterima' AND a.jatuh_tempo < CURDATE() AND p.status IN ('Disetujui', 'Dicairkan')")->fetch()['total'];
$totalDendaBelumBayar = unpaid_fines_total();

// Chart data
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-{$i} months"));
}
$chartData = [];
foreach ($months as $month) {
    $s = $pdo->prepare("SELECT SUM(nominal) AS total FROM simpanan WHERE status = 'Diterima' AND DATE_FORMAT(tanggal_transaksi, '%Y-%m') = ?");
    $s->execute([$month]);
    $p = $pdo->prepare("SELECT SUM(nominal) AS total FROM pinjaman WHERE status IN ('Disetujui', 'Dicairkan') AND DATE_FORMAT(tanggal_disetujui, '%Y-%m') = ?");
    $p->execute([$month]);
    $a = $pdo->prepare("SELECT SUM(nominal) AS total FROM angsuran WHERE status = 'Diterima' AND DATE_FORMAT(tanggal_bayar, '%Y-%m') = ?");
    $a->execute([$month]);
    $chartData[] = [
        'month'    => $month,
        'simpanan' => (float)($s->fetch()['total'] ?? 0),
        'pinjaman' => (float)($p->fetch()['total'] ?? 0),
        'angsuran' => (float)($a->fetch()['total'] ?? 0),
    ];
}
$labels         = array_map(fn($m) => date('M Y', strtotime($m . '-01')), $months);
$simpananSeries = array_map(fn($d) => $d['simpanan'], $chartData);
$pinjamanSeries = array_map(fn($d) => $d['pinjaman'], $chartData);
$angsuranSeries = array_map(fn($d) => $d['angsuran'], $chartData);

// Recent members waiting
$pendingAnggota = $pdo->query("SELECT id, nama, email, created_at FROM users WHERE role='user' AND status_verifikasi='Menunggu Verifikasi' ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Recent pinjaman waiting
$pendingPinjaman = $pdo->query("SELECT p.*, u.nama FROM pinjaman p JOIN users u ON p.user_id = u.id WHERE p.status = 'Menunggu review' ORDER BY p.created_at DESC LIMIT 5")->fetchAll();

$page_title = 'Dashboard Admin';
$role = 'admin';

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

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Admin</h1>
        <p class="page-sub">Ringkasan operasional koperasi per <?= date('d F Y'); ?></p>
    </div>
    <a href="/admin/laporan.php" class="btn-primary-custom" id="laporanBtn">
        <i class="bi bi-file-earmark-bar-graph"></i>Lihat Laporan
    </a>
</div>

<!-- ─── STAT CARDS ROW 1 ─── -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Anggota</div>
                <div class="stat-value"><?= $totalAnggota; ?></div>
                <div class="stat-trend">Seluruh anggota terdaftar</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-person-exclamation"></i></div>
            <div class="stat-info">
                <div class="stat-label">Menunggu Verifikasi</div>
                <div class="stat-value"><?= $menungguVerif; ?></div>
                <div class="stat-trend"><a href="/admin/anggota.php" style="color:var(--accent-amber);font-size:.75rem">Proses sekarang →</a></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-piggy-bank-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Simpanan Masuk</div>
                <div class="stat-value sm"><?= format_rupiah($totalSimpanan); ?></div>
                <div class="stat-trend">Semua transaksi diterima</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-info">
                <div class="stat-label">Pinjaman Aktif</div>
                <div class="stat-value sm"><?= format_rupiah($totalPinjamanAktif); ?></div>
                <div class="stat-trend">Disetujui + dicairkan</div>
            </div>
        </div>
    </div>
</div>

<!-- ─── STAT CARDS ROW 2 ─── -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info">
                <div class="stat-label">Pinjaman Menunggu</div>
                <div class="stat-value"><?= $totalPinjamanMenunggu; ?></div>
                <div class="stat-trend"><a href="/admin/pinjaman.php" style="color:var(--accent-amber);font-size:.75rem">Tinjau sekarang →</a></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Angsuran Bulan Ini</div>
                <div class="stat-value sm"><?= format_rupiah($totalAngsuranBulan); ?></div>
                <div class="stat-trend">Pembayaran sudah diterima</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Jatuh Tempo</div>
                <div class="stat-value"><?= $pinjamanJatuhTempo; ?></div>
                <div class="stat-trend">Angsuran melewati batas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="stat-info">
                <div class="stat-label">Denda Belum Dibayar</div>
                <div class="stat-value sm"><?= format_rupiah($totalDendaBelumBayar); ?></div>
                <div class="stat-trend"><a href="/admin/angsuran.php#denda" style="color:var(--accent-red);font-size:.75rem">Lihat denda -></a></div>
            </div>
        </div>
    </div>
</div>

<!-- ─── CHART + PENDING TABLES ─── -->
<div class="row g-4 mb-4">
    <!-- Chart -->
    <div class="col-lg-7">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-bar-chart-line me-2" style="color:var(--primary)"></i>Grafik 6 Bulan Terakhir</span>
                <span style="font-size:.75rem;color:var(--text-muted)">Simpanan, pinjaman, dan angsuran</span>
            </div>
            <div class="panel-body chart-wrap">
                <canvas id="chartKoperasi" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Anggota Pending -->
    <div class="col-lg-5">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-person-check me-2" style="color:var(--accent-amber)"></i>Menunggu Verifikasi</span>
                <a href="/admin/anggota.php" style="font-size:.75rem;color:var(--primary);font-weight:600;text-decoration:none">Semua →</a>
            </div>
            <div class="table-responsive panel-body p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Daftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingAnggota)): ?>
                            <tr><td colspan="3">
                                <div class="empty-state">
                                    <i class="bi bi-check-circle"></i>
                                    <p>Semua anggota sudah terverifikasi</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingAnggota as $a): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600;font-size:.83rem"><?= e($a['nama']); ?></div>
                                        <div style="font-size:.72rem;color:var(--text-muted)"><?= e($a['email']); ?></div>
                                    </td>
                                    <td style="font-size:.78rem;color:var(--text-muted)"><?= e(date('d/m/Y', strtotime($a['created_at']))); ?></td>
                                    <td>
                                        <a href="/admin/detail-anggota.php?id=<?= $a['id']; ?>" class="btn-action view" title="Detail"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ─── PINJAMAN PENDING ─── -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-send-exclamation me-2" style="color:var(--accent-amber)"></i>Pengajuan Pinjaman Terbaru</span>
        <a href="/admin/pinjaman.php" style="font-size:.75rem;color:var(--primary);font-weight:600;text-decoration:none">Lihat Semua →</a>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No. Pinjaman</th>
                    <th>Anggota</th>
                    <th>Nominal</th>
                    <th>Tenor</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingPinjaman)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>Tidak ada pengajuan pinjaman menunggu</p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($pendingPinjaman as $p): ?>
                        <tr>
                            <td><code style="font-size:.75rem;background:var(--bg);padding:.15rem .4rem;border-radius:4px"><?= e($p['nomor_pinjaman']); ?></code></td>
                            <td style="font-weight:600"><?= e($p['nama']); ?></td>
                            <td><?= format_rupiah($p['nominal']); ?></td>
                            <td><?= e($p['tenor']); ?> bln</td>
                            <td><span class="badge-status <?= badgeClass($p['status']); ?>"><?= e($p['status']); ?></span></td>
                            <td style="font-size:.78rem;color:var(--text-muted)"><?= e(date('d/m/Y', strtotime($p['created_at']))); ?></td>
                            <td>
                                <a href="/admin/detail-pinjaman.php?id=<?= $p['id']; ?>" class="btn-action view" title="Detail"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
$extra_js .= '<script>
document.addEventListener("DOMContentLoaded", () => {
    const ctx = document.getElementById("chartKoperasi");
    if (!ctx) return;
    new Chart(ctx, {
        type: "bar",
        data: {
            labels: ' . json_encode($labels) . ',
            datasets: [
                {
                    label: "Simpanan",
                    data: ' . json_encode($simpananSeries) . ',
                    backgroundColor: "rgba(22,163,74,.75)",
                    borderColor: "#16a34a",
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
                {
                    label: "Pinjaman",
                    data: ' . json_encode($pinjamanSeries) . ',
                    backgroundColor: "rgba(217,119,6,.65)",
                    borderColor: "#d97706",
                    borderWidth: 1.5,
                    borderRadius: 6,
                    type: "line",
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: "#d97706",
                    pointRadius: 4,
                },
                {
                    label: "Angsuran",
                    data: ' . json_encode($angsuranSeries) . ',
                    backgroundColor: "rgba(37,99,235,.15)",
                    borderColor: "#2563eb",
                    borderWidth: 2,
                    type: "line",
                    fill: false,
                    tension: 0.35,
                    pointBackgroundColor: "#2563eb",
                    pointRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: "top", labels: { font: { family: "Inter", size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => " Rp " + ctx.parsed.y.toLocaleString("id-ID")
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (v) => "Rp " + (v >= 1e6 ? (v/1e6).toFixed(1) + "jt" : v.toLocaleString("id-ID")),
                        font: { family: "Inter", size: 11 }
                    },
                    grid: { color: "#f1f5f9" }
                },
                x: { ticks: { font: { family: "Inter", size: 11 } }, grid: { display: false } }
            }
        }
    });
});
</script>';
?>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
