<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();
$authUser = current_user();
sync_late_fines();

if (is_post()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_fine_paid') {
        $fineId = (int)($_POST['id'] ?? 0);
        if ($fineId > 0 && mark_fine_paid($fineId)) {
            log_activity((int)$authUser['id'], 'Menandai denda sebagai dibayar');
            set_flash('success', 'Denda ditandai sudah dibayar.');
        }
        redirect('/admin/angsuran.php');
    }

    if (in_array($action, ['confirm', 'reject'], true)) {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $action === 'confirm' ? 'Diterima' : 'Ditolak';

        $stmt = $pdo->prepare("UPDATE angsuran SET status = ? WHERE id_angsuran = ?");
        $stmt->execute([$status, $id]);

        // Ambil data untuk notifikasi dan cek pelunasan
        $row = $pdo->prepare("
            SELECT a.besar_angsuran AS nominal, a.id_anggota, a.angsuran_ke,
                   p.nama_pinjaman AS nomor_pinjaman, p.id_pinjaman AS pinjaman_id
            FROM angsuran a
            JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
            WHERE a.id_angsuran = ?
        ");
        $row->execute([$id]);
        $data = $row->fetch();

        if ($data) {
            if ($status === 'Diterima') {
                // Cek apakah semua angsuran sudah lunas
                $check = $pdo->prepare("SELECT COUNT(*) AS total FROM angsuran WHERE id_pinjaman = ? AND status != 'Diterima'");
                $check->execute([$data['pinjaman_id']]);
                $isLunas = ((int)$check->fetch()['total'] === 0);

                if ($isLunas) {
                    $pdo->prepare("UPDATE pinjaman SET status = 'Lunas', tgl_pelunasan = CURDATE() WHERE id_pinjaman = ?")
                        ->execute([$data['pinjaman_id']]);
                    create_notification(
                        (int)$data['id_anggota'],
                        'Pinjaman Lunas!',
                        'Selamat! Seluruh angsuran pinjaman ' . $data['nomor_pinjaman'] . ' telah terbayar lunas.'
                    );
                } else {
                    create_notification(
                        (int)$data['id_anggota'],
                        'Pembayaran angsuran dikonfirmasi',
                        'Pembayaran angsuran ke-' . $data['angsuran_ke'] . ' untuk pinjaman ' . $data['nomor_pinjaman'] . ' sebesar ' . format_rupiah($data['nominal']) . ' telah diterima.'
                    );
                }
            } else {
                // Status Ditolak – notifikasi anggota agar upload ulang
                create_notification(
                    (int)$data['id_anggota'],
                    'Pembayaran angsuran ditolak',
                    'Pembayaran angsuran ke-' . $data['angsuran_ke'] . ' untuk pinjaman ' . $data['nomor_pinjaman'] . ' ditolak. Silakan upload ulang bukti pembayaran yang valid.'
                );
            }

            log_activity(
                (int)$authUser['id'],
                ($status === 'Diterima' ? 'Mengonfirmasi' : 'Menolak') . ' angsuran ' . $data['nomor_pinjaman'] . ' ke-' . $data['angsuran_ke']
            );
        }
        set_flash('success', 'Status angsuran diperbarui.');
        redirect('/admin/angsuran.php');
    }
}

$stmt = $pdo->query("
    SELECT 
        a.id_angsuran AS id,
        a.angsuran_ke,
        da.tgl_jatuh_tempo AS jatuh_tempo,
        a.besar_angsuran AS nominal,
        a.tgl_pembayaran AS tanggal_bayar,
        a.status,
        a.bukti_transfer,
        p.nama_pinjaman AS nomor_pinjaman,
        u.nama 
    FROM angsuran a 
    JOIN detail_angsuran da ON a.id_angsuran = da.id_angsuran
    JOIN anggota u ON a.id_anggota = u.id_anggota 
    JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
    WHERE a.status != 'Belum dibayar'
    ORDER BY a.created_at DESC
");
$angsuran = $stmt->fetchAll();

$dendaRows = $pdo->query("
    SELECT 
        da.id_angsuran AS id,
        a.angsuran_ke,
        da.tgl_jatuh_tempo AS jatuh_tempo,
        da.jumlah_hari_terlambat AS jumlah_hari,
        da.denda_total,
        da.status_denda AS status,
        da.tanggal_bayar_denda AS tanggal_bayar,
        da.created_at,
        p.nama_pinjaman AS nomor_pinjaman,
        u.nama
    FROM detail_angsuran da
    JOIN angsuran a ON da.id_angsuran = a.id_angsuran
    JOIN anggota u ON a.id_anggota = u.id_anggota
    JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
    WHERE da.jumlah_hari_terlambat > 0
    ORDER BY da.status_denda ASC, da.created_at DESC
")->fetchAll();

$totalDendaBelumBayar = unpaid_fines_total();

$page_title = 'Manajemen Angsuran';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1 class="page-title">Manajemen Angsuran</h1>
        <p class="page-sub">Konfirmasi pembayaran, pantau jatuh tempo, dan kelola denda keterlambatan.</p>
    </div>
    <div>
        <a href="<?= base_url('/admin/bayar-manual.php') ?>" class="btn btn-primary" style="background:linear-gradient(135deg,var(--primary),var(--primary-light)); border:none;">
            <i class="bi bi-cash-coin me-1"></i>Input Bayar Manual
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Angsuran</div>
                <div class="stat-value"><?= count($angsuran); ?></div>
                <div class="stat-trend">Semua jadwal tercatat</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info">
                <div class="stat-label">Menunggu Konfirmasi</div>
                <div class="stat-value"><?= count(array_filter($angsuran, fn($row) => $row['status'] === 'Menunggu konfirmasi')); ?></div>
                <div class="stat-trend">Perlu validasi admin</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total Denda</div>
                <div class="stat-value sm"><?= format_rupiah($totalDendaBelumBayar); ?></div>
                <div class="stat-trend">Status belum dibayar</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="bi bi-receipt"></i></div>
            <div class="stat-info">
                <div class="stat-label">Data Denda</div>
                <div class="stat-value"><?= count($dendaRows); ?></div>
                <div class="stat-trend">Riwayat denda angsuran</div>
            </div>
        </div>
    </div>
</div>

<div class="filter-bar">
    <div class="search-input-wrap">
        <i class="bi bi-search search-icon"></i>
        <input type="search" class="form-control" placeholder="Cari angsuran atau denda..." data-table-search="#angsuranTable,#dendaTable">
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-12">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-calendar-check me-2 text-primary-custom"></i>Data Angsuran</span>
            </div>
            <div class="table-responsive panel-body p-0">
            <table class="data-table" id="angsuranTable">
                <thead>
                    <tr>
                        <th>Pinjaman</th>
                        <th>Anggota</th>
                        <th>Angsuran</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th>Bukti</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($angsuran as $row): ?>
                        <tr>
                            <td><?= e($row['nomor_pinjaman']); ?></td>
                            <td><?= e($row['nama']); ?></td>
                            <td>
                                Ke-<?= e($row['angsuran_ke']); ?>
                                <div style="font-size: 0.72rem; color: var(--text-muted); white-space: nowrap;">
                                    J.T: <?= $row['jatuh_tempo'] ? e(date('d/m/Y', strtotime($row['jatuh_tempo']))) : '-'; ?>
                                </div>
                            </td>
                            <td class="fw-bold"><?= format_rupiah($row['nominal']); ?></td>
                            <td>
                                <span class="badge-status <?= status_badge_class($row['status']); ?>"><?= e($row['status']); ?></span>
                                <?php if ($row['tanggal_bayar']): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; margin-top: 2px;">
                                        Bayar: <?= e(date('d/m/Y', strtotime($row['tanggal_bayar']))); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['bukti_transfer'])): ?>
                                    <a href="<?= base_url('/uploads/bukti_angsuran/' . e($row['bukti_transfer'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="padding: 2px 6px; font-size: 0.75rem;">Lihat</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($row['status'] === 'Menunggu konfirmasi'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= e($row['id']); ?>">
                                            <button class="btn btn-sm btn-success" name="action" value="confirm" style="padding: 2px 8px; font-size: 0.75rem;">Setuju</button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= e($row['id']); ?>">
                                            <button class="btn btn-sm btn-danger" name="action" value="reject" style="padding: 2px 8px; font-size: 0.75rem;">Tolak</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($angsuran)): ?>
                        <tr><td colspan="7" class="text-muted">Belum ada data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<div class="panel mt-4" id="denda">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-receipt-cutoff me-2 text-red"></i>Denda Keterlambatan</span>
        <span style="font-size:.78rem;color:var(--text-muted)">Tarif: <?= format_rupiah((float)get_setting('denda_per_hari', 5000)); ?> / hari</span>
    </div>
    <div class="table-responsive panel-body p-0">
        <table class="data-table" id="dendaTable">
            <thead>
                <tr>
                    <th>Pinjaman</th>
                    <th>Anggota</th>
                    <th>Angsuran</th>
                    <th>Terlambat</th>
                    <th>Total Denda</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dendaRows)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-check-circle"></i><p>Belum ada denda keterlambatan.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($dendaRows as $fine): ?>
                        <tr>
                            <td><code style="font-size:.78rem;background:var(--bg);padding:.15rem .4rem;border-radius:4px"><?= e($fine['nomor_pinjaman']); ?></code></td>
                            <td style="font-weight:600"><?= e($fine['nama']); ?></td>
                            <td>Ke-<?= e($fine['angsuran_ke']); ?><br><span style="font-size:.72rem;color:var(--text-muted)">Jatuh tempo <?= e(date('d/m/Y', strtotime($fine['jatuh_tempo']))); ?></span></td>
                            <td><?= e($fine['jumlah_hari']); ?> hari</td>
                            <td style="font-weight:700;color:var(--accent-red)"><?= format_rupiah($fine['total_denda']); ?></td>
                            <td><span class="badge-status <?= status_badge_class($fine['status']); ?>"><?= e($fine['status']); ?></span></td>
                            <td>
                                <?php if ($fine['status'] === 'Belum Dibayar'): ?>
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?= e($fine['id']); ?>">
                                        <button name="action" value="mark_fine_paid" class="btn-action approve" title="Tandai dibayar" onclick="return confirm('Tandai denda ini sudah dibayar?')"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:.78rem;color:var(--text-muted)">Dibayar <?= e($fine['tanggal_bayar'] ? date('d/m/Y', strtotime($fine['tanggal_bayar'])) : '-'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
