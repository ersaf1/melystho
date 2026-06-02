<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_user();

$user = current_user();
$pdo = db();
$errors = [];
sync_late_fines((int)$user['id']);
sync_due_reminders((int)$user['id']);

$stmt = $pdo->prepare("SELECT id_pinjaman AS id, nama_pinjaman AS nomor_pinjaman, tgl_pinjaman, tenor, besar_pinjaman, total_bayar, angsuran_per_bulan FROM pinjaman WHERE id_anggota = ? AND status = 'Dicairkan'");
$stmt->execute([$user['id']]);
$pinjamanList = $stmt->fetchAll();

$angsuranList = [];
$dendaPerHari = (float)get_setting('denda_per_hari', 5000);

foreach ($pinjamanList as $p) {
    // Ambil semua angsuran yang sudah tercatat di database untuk pinjaman ini
    $stAng = $pdo->prepare("SELECT id_angsuran AS id, angsuran_ke, besar_angsuran AS nominal, status FROM angsuran WHERE id_pinjaman = ?");
    $stAng->execute([$p['id']]);
    $dbAngsurans = [];
    foreach ($stAng->fetchAll() as $a) {
        $dbAngsurans[(int)$a['angsuran_ke']] = $a;
    }

    $next_ke = 1;
    $repay_id = null;
    $status = 'Belum dibayar';
    $can_pay = true;

    for ($i = 1; $i <= (int)$p['tenor']; $i++) {
        if (isset($dbAngsurans[$i])) {
            $item = $dbAngsurans[$i];
            if ($item['status'] === 'Diterima') {
                continue;
            } else {
                $next_ke = $i;
                $repay_id = (int)$item['id'];
                $status = $item['status'];
                if ($item['status'] === 'Menunggu konfirmasi') {
                    $can_pay = false;
                }
                break;
            }
        } else {
            $next_ke = $i;
            $repay_id = null;
            $status = 'Belum dibayar';
            break;
        }
    }

    if ($can_pay) {
        $tenor = (int)$p['tenor'];
        $tglPinjam = $p['tgl_pinjaman'];
        $angsuranPerBulan = (float)$p['angsuran_per_bulan'];
        $totalBayar = (float)$p['total_bayar'];
        $angsuranTerakhir = round($totalBayar - ($angsuranPerBulan * ($tenor - 1)), 2);

        $nominal = ($next_ke === $tenor) ? $angsuranTerakhir : $angsuranPerBulan;
        $jatuhTempo = date('Y-m-d', strtotime("+{$next_ke} month", strtotime($tglPinjam)));

        // Hitung denda dinamis jika sudah lewat jatuh tempo
        $denda = 0.0;
        $today = date('Y-m-d');
        if ($today > $jatuhTempo) {
            $diff = (strtotime($today) - strtotime($jatuhTempo)) / 86400;
            $days = max(0, (int)floor($diff));
            $denda = $days * $dendaPerHari;
        }

        // Cari tahu apakah ada denda yang tercatat di detail_angsuran
        // (Misalnya jika angsuran ditolak, tapi denda sudah terlanjur terhitung di DB)
        if ($repay_id) {
            $stDenda = $pdo->prepare("SELECT denda_total FROM detail_angsuran WHERE id_angsuran = ?");
            $stDenda->execute([$repay_id]);
            $dbDenda = $stDenda->fetch();
            if ($dbDenda) {
                $denda = (float)$dbDenda['denda_total'];
            }
        }

        // Format value option: jika baru = "new_{id_pinjaman}_{next_ke}", jika exist (ditolak) = "existing_{repay_id}"
        $optValue = $repay_id ? "existing_{$repay_id}" : "new_{$p['id']}_{$next_ke}";

        $angsuranList[] = [
            'id' => $optValue, // di-mapping ke value dropdown
            'angsuran_ke' => $next_ke,
            'nominal' => $nominal,
            'jatuh_tempo' => $jatuhTempo,
            'nomor_pinjaman' => $p['nomor_pinjaman'],
            'total_denda' => $denda,
            'status_denda' => 'Belum Dibayar',
            'status' => $status
        ];
    }
}

// Cari denda belum dibayar dari detail_angsuran
$dendaStmt = $pdo->prepare("
    SELECT 
        da.id_angsuran AS id,
        da.jumlah_hari_terlambat AS jumlah_hari,
        da.denda_total,
        da.status_denda AS status,
        da.created_at,
        a.angsuran_ke,
        p.nama_pinjaman AS nomor_pinjaman
    FROM detail_angsuran da
    JOIN angsuran a ON da.id_angsuran = da.id_angsuran
    JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
    WHERE a.id_anggota = ? AND da.status_denda = 'Belum Dibayar' AND da.jumlah_hari_terlambat > 0
    ORDER BY da.created_at DESC
");
$dendaStmt->execute([$user['id']]);
$dendaList = $dendaStmt->fetchAll();

if (is_post()) {
    $angsuranTarget = $_POST['angsuran_id'] ?? '';
    $nominal = parse_currency($_POST['nominal'] ?? 0);
    $tanggal = $_POST['tanggal_bayar'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');

    if ($angsuranTarget === '' || $nominal <= 0 || $tanggal === '') {
        $errors[] = 'Lengkapi data pembayaran.';
    }

    $bukti = null;
    if (empty($errors)) {
        if (!isset($_FILES['bukti_transfer'])) {
            $errors[] = 'Upload bukti transfer wajib.';
        } else {
            $bukti = upload_file($_FILES['bukti_transfer'], __DIR__ . '/../uploads/bukti_angsuran', ['image/jpeg', 'image/png']);
            if (!$bukti) {
                $errors[] = 'Upload bukti transfer wajib dalam format JPG/PNG.';
            }
        }
    }

    if (empty($errors)) {
        if (strpos($angsuranTarget, 'new_') === 0) {
            // Installment baru
            $parts = explode('_', $angsuranTarget);
            $pinjamanId = (int)$parts[1];
            $angsuranKe = (int)$parts[2];

            // Ambil data pinjaman untuk validasi
            $stPinj = $pdo->prepare("SELECT * FROM pinjaman WHERE id_pinjaman = ? AND id_anggota = ? AND status = 'Dicairkan'");
            $stPinj->execute([$pinjamanId, $user['id']]);
            $pinj = $stPinj->fetch();

            if (!$pinj) {
                $errors[] = 'Pinjaman tidak valid.';
            } else {
                $tenor = (int)$pinj['tenor'];
                $angsuranPerBulan = (float)$pinj['angsuran_per_bulan'];
                $totalBayar = (float)$pinj['total_bayar'];
                $angsuranTerakhir = round($totalBayar - ($angsuranPerBulan * ($tenor - 1)), 2);
                $expectedNominal = ($angsuranKe === $tenor) ? $angsuranTerakhir : $angsuranPerBulan;

                // Hitung denda dinamis
                $jatuhTempo = date('Y-m-d', strtotime("+{$angsuranKe} month", strtotime($pinj['tgl_pinjaman'])));
                $denda = 0.0;
                if ($tanggal > $jatuhTempo) {
                    $diff = (strtotime($tanggal) - strtotime($jatuhTempo)) / 86400;
                    $days = max(0, (int)floor($diff));
                    $denda = $days * $dendaPerHari;
                }

                $minRequired = $expectedNominal + $denda;
                if ($nominal < $minRequired) {
                    $errors[] = 'Nominal pembayaran Anda (' . format_rupiah($nominal) . ') kurang dari jumlah minimal yang harus dibayar yaitu ' . format_rupiah($minRequired) . '.';
                } else {
                    // Simpan ke angsuran dengan status Menunggu konfirmasi
                    $stmt = $pdo->prepare("INSERT INTO angsuran (id_katagori, id_anggota, id_pinjaman, tgl_pembayaran, angsuran_ke, besar_angsuran, ket, bukti_transfer, status, created_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, 'Menunggu konfirmasi', NOW())");
                    $stmt->execute([$user['id'], $pinjamanId, $tanggal, $angsuranKe, $nominal, $catatan, $bukti]);
                    $angId = (int)$pdo->lastInsertId();

                    // Simpan detail_angsuran dengan status_denda 'Belum Dibayar'
                    $stmt = $pdo->prepare("INSERT INTO detail_angsuran (id_angsuran, tgl_jatuh_tempo, besar_angsuran, ket, jumlah_hari_terlambat, denda_tarif_per_hari, denda_total, status_denda, tanggal_denda, tanggal_bayar_denda, created_at, updated_at) VALUES (?, ?, ?, '', ?, ?, ?, 'Belum Dibayar', NULL, NULL, NOW(), NOW())");
                    $daysLate = 0;
                    if ($tanggal > $jatuhTempo) {
                        $diff = (strtotime($tanggal) - strtotime($jatuhTempo)) / 86400;
                        $daysLate = max(0, (int)floor($diff));
                    }
                    $stmt->execute([$angId, $jatuhTempo, $expectedNominal, $daysLate, $dendaPerHari, $denda]);

                    log_activity((int)$user['id'], 'Membayar angsuran ke-' . $angsuranKe . ' sebesar ' . format_rupiah($nominal));
                    notify_admins('Angsuran baru', $user['nama'] . ' mengunggah pembayaran angsuran sebesar ' . format_rupiah($nominal) . '.');
                    set_flash('success', 'Pembayaran berhasil diupload. Menunggu konfirmasi admin.');
                    redirect('/user/bayar-angsuran.php');
                }
            }
        } elseif (strpos($angsuranTarget, 'existing_') === 0) {
            // Update angsuran ditolak sebelumnya
            $parts = explode('_', $angsuranTarget);
            $angsuranId = (int)$parts[1];

            $stmt = $pdo->prepare("
                SELECT a.id_angsuran AS id, a.angsuran_ke, a.besar_angsuran AS nominal, p.id_pinjaman, p.tgl_pinjaman, p.tenor, p.angsuran_per_bulan, p.total_bayar
                FROM angsuran a
                JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
                WHERE a.id_angsuran = ? AND a.id_anggota = ? AND a.status = 'Ditolak'
            ");
            $stmt->execute([$angsuranId, $user['id']]);
            $row = $stmt->fetch();

            if (!$row) {
                $errors[] = 'Angsuran tidak valid.';
            } else {
                $tenor = (int)$row['tenor'];
                $angsuranKe = (int)$row['angsuran_ke'];
                $angsuranPerBulan = (float)$row['angsuran_per_bulan'];
                $totalBayar = (float)$row['total_bayar'];
                $angsuranTerakhir = round($totalBayar - ($angsuranPerBulan * ($tenor - 1)), 2);
                $expectedNominal = ($angsuranKe === $tenor) ? $angsuranTerakhir : $angsuranPerBulan;

                // Hitung denda dinamis
                $jatuhTempo = date('Y-m-d', strtotime("+{$angsuranKe} month", strtotime($row['tgl_pinjaman'])));
                $denda = 0.0;
                if ($tanggal > $jatuhTempo) {
                    $diff = (strtotime($tanggal) - strtotime($jatuhTempo)) / 86400;
                    $days = max(0, (int)floor($diff));
                    $denda = $days * $dendaPerHari;
                }

                $minRequired = $expectedNominal + $denda;
                if ($nominal < $minRequired) {
                    $errors[] = 'Nominal pembayaran Anda (' . format_rupiah($nominal) . ') kurang dari jumlah minimal yang harus dibayar yaitu ' . format_rupiah($minRequired) . '.';
                } else {
                    $stmt = $pdo->prepare("UPDATE angsuran SET tgl_pembayaran = ?, bukti_transfer = ?, besar_angsuran = ?, status = 'Menunggu konfirmasi', ket = ? WHERE id_angsuran = ?");
                    $stmt->execute([$tanggal, $bukti, $nominal, $catatan, $angsuranId]);

                    // Update detail_angsuran
                    $stmt = $pdo->prepare("UPDATE detail_angsuran SET tgl_jatuh_tempo = ?, besar_angsuran = ?, jumlah_hari_terlambat = ?, denda_tarif_per_hari = ?, denda_total = ?, updated_at = NOW() WHERE id_angsuran = ?");
                    $daysLate = 0;
                    if ($tanggal > $jatuhTempo) {
                        $diff = (strtotime($tanggal) - strtotime($jatuhTempo)) / 86400;
                        $daysLate = max(0, (int)floor($diff));
                    }
                    $stmt->execute([$jatuhTempo, $expectedNominal, $daysLate, $dendaPerHari, $denda, $angsuranId]);

                    log_activity((int)$user['id'], 'Membayar angsuran ke-' . $angsuranKe . ' sebesar ' . format_rupiah($nominal));
                    notify_admins('Angsuran baru', $user['nama'] . ' mengunggah pembayaran angsuran sebesar ' . format_rupiah($nominal) . '.');
                    set_flash('success', 'Pembayaran berhasil diupload. Menunggu konfirmasi admin.');
                    redirect('/user/bayar-angsuran.php');
                }
            }
        } else {
            $errors[] = 'Pilihan angsuran tidak valid.';
        }
    }
}

$page_title = 'Pembayaran Angsuran';
$role = 'user';
?>
<?php require __DIR__ . '/../includes/dashboard_top.php'; ?>

<?php if (!empty($dendaList)): ?>
    <div class="panel mb-4">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-receipt-cutoff me-2 text-red"></i>Denda Belum Dibayar</span>
            <span style="font-size:.78rem;color:var(--text-muted)">Total <?= format_rupiah(array_sum(array_column($dendaList, 'denda_total'))); ?></span>
        </div>
        <div class="table-responsive panel-body p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pinjaman</th>
                        <th>Angsuran</th>
                        <th>Terlambat</th>
                        <th>Denda</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dendaList as $fine): ?>
                        <tr>
                            <td><?= e($fine['nomor_pinjaman']); ?></td>
                            <td>Ke-<?= e($fine['angsuran_ke']); ?></td>
                            <td><?= e($fine['jumlah_hari']); ?> hari</td>
                            <td style="font-weight:700;color:var(--accent-red)"><?= format_rupiah($fine['denda_total']); ?></td>
                            <td><span class="badge-status <?= status_badge_class($fine['status']); ?>"><?= e($fine['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="form-section">
    <h4 class="mb-4">Upload Bukti Pembayaran</h4>
    <?php if (empty($angsuranList)): ?>
        <div class="alert alert-warning">Tidak ada angsuran yang dapat dibayar saat ini.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= e($errors[0]); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Pilih Angsuran</label>
                <select name="angsuran_id" class="form-select" required>
                    <option value="">Pilih angsuran</option>
                    <?php foreach ($angsuranList as $row): ?>
                        <?php 
                        $total = (float)$row['nominal'] + (float)$row['total_denda'];
                        ?>
                        <option value="<?= e($row['id']); ?>" 
                                data-nominal="<?= (float)$row['nominal']; ?>" 
                                data-denda="<?= (float)$row['total_denda']; ?>" 
                                data-total="<?= $total; ?>">
                            <?= e($row['nomor_pinjaman']); ?> - Angsuran <?= e($row['angsuran_ke']); ?> (<?= format_rupiah($row['nominal']); ?>)<?= !empty($row['total_denda']) ? ' + denda ' . format_rupiah($row['total_denda']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Info block for minimal payment -->
                <div id="minPayInfo" class="mt-3 p-3 rounded border bg-light d-none" style="border-left: 4px solid var(--primary) !important;">
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.9rem; color: var(--text-muted);">
                        <span>Angsuran Pokok:</span>
                        <strong id="infoNominal">Rp 0</strong>
                    </div>
                    <div id="infoDendaRow" class="d-flex justify-content-between mb-1 d-none" style="font-size: 0.9rem; color: var(--accent-red);">
                        <span>Denda Keterlambatan:</span>
                        <strong id="infoDenda">Rp 0</strong>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <strong style="color: var(--primary);">Minimal Pembayaran:</strong>
                        <strong id="infoTotal" style="color: var(--primary); font-size: 1.1rem;">Rp 0</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nominal Bayar</label>
                <input type="text" name="nominal" id="nominal_bayar" data-type="currency" class="form-control" required>
                <div class="form-text" id="nominalHelp">Prefill otomatis dengan nilai minimal bayar saat angsuran dipilih.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="tanggal_bayar" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Upload Bukti Transfer</label>
                <input type="file" name="bukti_transfer" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-12">
                <label class="form-label">Catatan</label>
                <textarea name="catatan" class="form-control" rows="2"></textarea>
            </div>
        </div>
        <button class="btn btn-primary mt-4">Kirim</button>
    </form>
</div>

<?php
$extra_js = "<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectEl = document.querySelector('select[name=\"angsuran_id\"]');
    const minPayInfo = document.getElementById('minPayInfo');
    const infoNominal = document.getElementById('infoNominal');
    const infoDendaRow = document.getElementById('infoDendaRow');
    const infoDenda = document.getElementById('infoDenda');
    const infoTotal = document.getElementById('infoTotal');
    const nominalInput = document.getElementById('nominal_bayar');

    function formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', { 
            style: 'currency', 
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value || 0);
    }

    if (selectEl) {
        selectEl.addEventListener('change', () => {
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (!selectedOpt || selectedOpt.value === '') {
                minPayInfo.classList.add('d-none');
                nominalInput.value = '';
                return;
            }

            const nominal = parseFloat(selectedOpt.getAttribute('data-nominal') || 0);
            const denda = parseFloat(selectedOpt.getAttribute('data-denda') || 0);
            const total = parseFloat(selectedOpt.getAttribute('data-total') || 0);

            infoNominal.textContent = formatRupiah(nominal);
            if (denda > 0) {
                infoDenda.textContent = formatRupiah(denda);
                infoDendaRow.classList.remove('d-none');
            } else {
                infoDendaRow.classList.add('d-none');
            }
            infoTotal.textContent = formatRupiah(total);
            minPayInfo.classList.remove('d-none');

            // Prefill with the formatted total amount (currency parser handles dots)
            nominalInput.value = new Intl.NumberFormat('id-ID').format(total);
        });
    }
});
</script>";
?>

<?php require __DIR__ . '/../includes/dashboard_bottom.php'; ?>
