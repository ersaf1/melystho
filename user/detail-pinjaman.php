<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT *, id_pinjaman AS id, nama_pinjaman AS nomor_pinjaman, besar_pinjaman AS nominal, tgl_pengajuan_pinjaman AS tanggal_pengajuan, ket AS catatan, ket AS tujuan FROM pinjaman WHERE id_pinjaman = ? AND id_anggota = ?");
$stmt->execute([$id, $user['id']]);
$pinjaman = $stmt->fetch();
if (!$pinjaman) {
    set_flash('danger', 'Pinjaman tidak ditemukan.');
    redirect('/user/pinjaman.php');
}
sync_late_fines((int)$user['id']);

$stmtPaid = $pdo->prepare("
    SELECT 
        a.id_angsuran AS id,
        a.angsuran_ke,
        a.besar_angsuran AS nominal,
        a.tgl_pembayaran AS tanggal_bayar,
        a.status,
        da.tgl_jatuh_tempo AS jatuh_tempo,
        da.jumlah_hari_terlambat AS jumlah_hari,
        da.denda_total,
        da.status_denda AS status_denda
    FROM angsuran a
    LEFT JOIN detail_angsuran da ON da.id_angsuran = a.id_angsuran
    WHERE a.id_pinjaman = ?
");
$stmtPaid->execute([$id]);
$paidMap = [];
foreach ($stmtPaid->fetchAll() as $r) {
    $paidMap[(int)$r['angsuran_ke']] = $r;
}

$schedule = [];
if (in_array($pinjaman['status'], ['Dicairkan', 'Lunas'], true) && !empty($pinjaman['tgl_pinjaman'])) {
    $tenor = (int)$pinjaman['tenor'];
    $tglPinjam = $pinjaman['tgl_pinjaman'];
    $angsuranPerBulan = (float)$pinjaman['angsuran_per_bulan'];
    $totalBayar = (float)$pinjaman['total_bayar'];
    $angsuranTerakhir = round($totalBayar - ($angsuranPerBulan * ($tenor - 1)), 2);

    for ($i = 1; $i <= $tenor; $i++) {
        $jatuhTempo = date('Y-m-d', strtotime("+{$i} month", strtotime($tglPinjam)));
        $expectedNominal = ($i === $tenor) ? $angsuranTerakhir : $angsuranPerBulan;

        if (isset($paidMap[$i])) {
            $schedule[] = [
                'id' => $paidMap[$i]['id'],
                'angsuran_ke' => $i,
                'jatuh_tempo' => $paidMap[$i]['jatuh_tempo'] ?: $jatuhTempo,
                'nominal' => $paidMap[$i]['nominal'],
                'tanggal_bayar' => $paidMap[$i]['tanggal_bayar'],
                'status' => $paidMap[$i]['status'],
                'jumlah_hari' => $paidMap[$i]['jumlah_hari'],
                'total_denda' => $paidMap[$i]['denda_total'],
                'status_denda' => $paidMap[$i]['status_denda'],
            ];
        } else {
            $schedule[] = [
                'id' => null,
                'angsuran_ke' => $i,
                'jatuh_tempo' => $jatuhTempo,
                'nominal' => $expectedNominal,
                'tanggal_bayar' => null,
                'status' => 'Belum dibayar',
                'jumlah_hari' => 0,
                'total_denda' => 0.0,
                'status_denda' => 'Belum Dibayar',
            ];
        }
    }
}

$historyStmt = $pdo->prepare("
    SELECT 
        id_angsuran AS id,
        angsuran_ke,
        besar_angsuran AS nominal,
        tgl_pembayaran AS tanggal_bayar,
        status
    FROM angsuran
    WHERE id_pinjaman = ? AND tgl_pembayaran IS NOT NULL
    ORDER BY tgl_pembayaran DESC
");
$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

$page_title = 'Detail Pinjaman';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="card card-stat p-4 mb-4">
    <div class="row g-3">
        <div class="col-md-4"><strong>No Pinjaman:</strong> <?= e($pinjaman['nomor_pinjaman']); ?></div>
        <div class="col-md-4"><strong>Tanggal Pengajuan:</strong> <?= e($pinjaman['tanggal_pengajuan']); ?></div>
        <div class="col-md-4"><strong>Status:</strong> <span class="badge bg-secondary"><?= e($pinjaman['status']); ?></span></div>
        <div class="col-md-4"><strong>Nominal:</strong> <?= format_rupiah($pinjaman['nominal']); ?></div>
        <div class="col-md-4"><strong>Bunga:</strong> <?= e($pinjaman['bunga_persen']); ?>%</div>
        <div class="col-md-4"><strong>Tenor:</strong> <?= e($pinjaman['tenor']); ?> bulan</div>
        <div class="col-md-4"><strong>Angsuran/Bulan:</strong> <?= format_rupiah($pinjaman['angsuran_per_bulan']); ?></div>
        <div class="col-md-4"><strong>Total Bayar:</strong> <?= format_rupiah($pinjaman['total_bayar']); ?></div>
        <?php if (in_array($pinjaman['status'], ['Dicairkan', 'Lunas'], true) && !empty($pinjaman['tgl_pinjaman'])): ?>
            <div class="col-md-4"><strong>Tanggal Pinjam:</strong> <?= e(date('d/m/Y', strtotime($pinjaman['tgl_pinjaman']))); ?></div>
            <div class="col-md-4"><strong>Tanggal Pengembalian:</strong> <?= e(date('d/m/Y', strtotime("+{$pinjaman['tenor']} month", strtotime($pinjaman['tgl_pinjaman'])))); ?></div>
        <?php endif; ?>
        <div class="col-md-12"><strong>Tujuan:</strong> <?= e($pinjaman['tujuan']); ?></div>
        <?php if (!empty($pinjaman['catatan'])): ?>
            <div class="col-md-12"><strong>Catatan:</strong> <?= e($pinjaman['catatan']); ?></div>
        <?php endif; ?>
    </div>
</div>

<h5>Jadwal Angsuran</h5>
<div class="table-responsive mb-4">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Angsuran Ke</th>
                <th>Jatuh Tempo</th>
                <th>Nominal</th>
                <th>Status</th>
                <th>Denda</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedule as $row): ?>
                <tr>
                    <td><?= e($row['angsuran_ke']); ?></td>
                    <td><?= e($row['jatuh_tempo']); ?></td>
                    <td><?= format_rupiah($row['nominal']); ?></td>
                    <td><span class="badge-status <?= status_badge_class($row['status']); ?>"><?= e($row['status']); ?></span></td>
                    <td>
                        <?php if (!empty($row['total_denda'])): ?>
                            <strong style="color:var(--accent-red)"><?= format_rupiah($row['total_denda']); ?></strong>
                            <div style="font-size:.72rem;color:var(--text-muted)"><?= e($row['jumlah_hari']); ?> hari - <?= e($row['status_denda']); ?></div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($schedule)): ?>
                <tr><td colspan="5" class="text-muted">Jadwal belum dibuat.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h5>Riwayat Pembayaran</h5>
<div class="table-responsive mb-4">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Angsuran Ke</th>
                <th>Tanggal Bayar</th>
                <th>Nominal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= e($row['angsuran_ke']); ?></td>
                    <td><?= e($row['tanggal_bayar']); ?></td>
                    <td><?= format_rupiah($row['nominal']); ?></td>
                    <td><span class="badge bg-secondary"><?= e($row['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
                <tr><td colspan="4" class="text-muted">Belum ada pembayaran.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
