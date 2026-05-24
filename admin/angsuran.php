<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_admin();

$pdo = db();

if (is_post()) {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['confirm', 'reject'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $status = $action === 'confirm' ? 'Diterima' : 'Ditolak';
        $stmt = $pdo->prepare("UPDATE angsuran SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        $row = $pdo->prepare("SELECT a.nominal, a.pinjaman_id FROM angsuran a WHERE a.id = ?");
        $row->execute([$id]);
        $data = $row->fetch();
        if ($data && $status === 'Diterima') {
            $pdo->prepare("INSERT INTO transaksi_kas (tipe, kategori, nominal, keterangan, tanggal, created_at) VALUES ('masuk', 'angsuran', ?, 'Pembayaran angsuran', CURDATE(), NOW())")
                ->execute([$data['nominal']]);

            $check = $pdo->prepare("SELECT COUNT(*) AS total FROM angsuran WHERE pinjaman_id = ? AND status != 'Diterima'");
            $check->execute([$data['pinjaman_id']]);
            if ((int)$check->fetch()['total'] === 0) {
                $pdo->prepare("UPDATE pinjaman SET status = 'Lunas' WHERE id = ?")->execute([$data['pinjaman_id']]);
            }
        }
        set_flash('success', 'Status angsuran diperbarui.');
        redirect('/admin/angsuran.php');
    }

    if ($action === 'manual') {
        $pinjamanId = (int)($_POST['pinjaman_id'] ?? 0);
        $nominal = (float)($_POST['nominal'] ?? 0);
        $tanggal = $_POST['tanggal_bayar'] ?? '';
        
        if ($pinjamanId && $nominal > 0) {
            // Cari angsuran belum dibayar yang paling awal (terkecil)
            $cekBelum = $pdo->prepare("SELECT angsuran_ke FROM angsuran WHERE pinjaman_id = ? AND status != 'Diterima' ORDER BY angsuran_ke ASC LIMIT 1");
            $cekBelum->execute([$pinjamanId]);
            $nextAngsuran = $cekBelum->fetch();

            if ($nextAngsuran) {
                $angsuranKe = $nextAngsuran['angsuran_ke'];
                $stmt = $pdo->prepare("UPDATE angsuran SET status = 'Diterima', nominal = ?, tanggal_bayar = ? WHERE pinjaman_id = ? AND angsuran_ke = ?");
                $stmt->execute([$nominal, $tanggal, $pinjamanId, $angsuranKe]);
                
                $pdo->prepare("INSERT INTO transaksi_kas (tipe, kategori, nominal, keterangan, tanggal, created_at) VALUES ('masuk', 'angsuran', ?, 'Pembayaran manual', ?, NOW())")
                    ->execute([$nominal, $tanggal]);

                $check = $pdo->prepare("SELECT COUNT(*) AS total FROM angsuran WHERE pinjaman_id = ? AND status != 'Diterima'");
                $check->execute([$pinjamanId]);
                if ((int)$check->fetch()['total'] === 0) {
                    $pdo->prepare("UPDATE pinjaman SET status = 'Lunas' WHERE id = ?")->execute([$pinjamanId]);
                }
                set_flash('success', "Pembayaran manual untuk angsuran ke-$angsuranKe tersimpan.");
            } else {
                set_flash('danger', 'Pinjaman ini sudah lunas atau tidak ada angsuran tertunda.');
            }
        } else {
            set_flash('danger', 'Formulir tidak lengkap.');
        }
        redirect('/admin/angsuran.php');
    }
}

$stmt = $pdo->query("SELECT a.*, p.nomor_pinjaman, u.nama FROM angsuran a JOIN pinjaman p ON a.pinjaman_id = p.id JOIN users u ON p.user_id = u.id ORDER BY a.created_at DESC");
$angsuran = $stmt->fetchAll();

$pinjamanList = $pdo->query("SELECT id, nomor_pinjaman FROM pinjaman WHERE status IN ('Disetujui', 'Dicairkan')")->fetchAll();

$page_title = 'Manajemen Angsuran';
$role = 'admin';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Pinjaman</th>
                        <th>Anggota</th>
                        <th>Angsuran Ke</th>
                        <th>Nominal</th>
                        <th>Tanggal Bayar</th>
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
                            <td><?= e($row['angsuran_ke']); ?></td>
                            <td><?= format_rupiah($row['nominal']); ?></td>
                            <td><?= e($row['tanggal_bayar'] ?: '-'); ?></td>
                            <td><span class="badge bg-secondary"><?= e($row['status']); ?></span></td>
                            <td>
                                <?php if (!empty($row['bukti_transfer'])): ?>
                                    <a href="/uploads/bukti_angsuran/<?= e($row['bukti_transfer']); ?>" target="_blank">Lihat</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="d-flex gap-2">
                                <?php if ($row['status'] === 'Menunggu konfirmasi'): ?>
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?= e($row['id']); ?>">
                                        <button class="btn btn-sm btn-success" name="action" value="confirm">Konfirmasi</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?= e($row['id']); ?>">
                                        <button class="btn btn-sm btn-danger" name="action" value="reject">Tolak</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($angsuran)): ?>
                        <tr><td colspan="8" class="text-muted">Belum ada data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="form-section">
            <h5 class="mb-3">Input Pembayaran Manual</h5>
            <form method="post">
                <input type="hidden" name="action" value="manual">
                <div class="mb-2">
                    <label class="form-label">Pinjaman</label>
                    <select name="pinjaman_id" class="form-select" required>
                        <option value="">Pilih Pinjaman</option>
                        <?php foreach ($pinjamanList as $p): ?>
                            <option value="<?= e($p['id']); ?>"><?= e($p['nomor_pinjaman']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Sistem akan otomatis memilih angsuran terkecil yang belum dibayar.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nominal</label>
                    <input type="number" name="nominal" class="form-control" min="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar</label>
                    <input type="date" name="tanggal_bayar" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Simpan</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
