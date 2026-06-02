<?php

function ensure_feature_tables(): void
{
    // No-op: all tables are predefined or file-based
}

function status_badge_class(?string $status = null): string
{
    $map = [
        'Menunggu Verifikasi' => 'badge-menunggu',
        'Menunggu konfirmasi' => 'badge-menunggu',
        'Menunggu review' => 'badge-menunggu',
        'Belum dibayar' => 'badge-menunggu',
        'Belum Dibayar' => 'badge-menunggu',
        'Disetujui' => 'badge-disetujui',
        'Diterima' => 'badge-diterima',
        'Dibayar' => 'badge-diterima',
        'Dicairkan' => 'badge-dicairkan',
        'Ditolak' => 'badge-ditolak',
        'Lunas' => 'badge-lunas',
        'Nonaktif' => 'badge-nonaktif',
    ];

    return $map[$status] ?? 'badge-nonaktif';
}

function log_activity(?int $userId, string $aktivitas): void
{
    if (!$userId || trim($aktivitas) === '') {
        return;
    }

    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $file = $dir . '/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] [User ID: {$userId}] {$aktivitas}\n";
    file_put_contents($file, $logLine, FILE_APPEND);
}

function get_notifications_file(int $userId): string
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . '/notifications_' . $userId . '.json';
}

function create_notification(int $userId, string $judul, string $pesan): void
{
    if ($userId <= 0 || trim($judul) === '' || trim($pesan) === '') {
        return;
    }

    $file = get_notifications_file($userId);
    $notifications = [];
    if (file_exists($file)) {
        $notifications = json_decode(file_get_contents($file), true) ?: [];
    }

    $newId = count($notifications) + 1;
    $notifications[] = [
        'id' => $newId,
        'judul' => $judul,
        'pesan' => $pesan,
        'is_read' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents($file, json_encode($notifications, JSON_PRETTY_PRINT));
}

function create_unique_notification(int $userId, string $judul, string $pesan): void
{
    $file = get_notifications_file($userId);
    $notifications = [];
    if (file_exists($file)) {
        $notifications = json_decode(file_get_contents($file), true) ?: [];
    }

    foreach ($notifications as $n) {
        if ($n['judul'] === $judul && $n['pesan'] === $pesan) {
            return;
        }
    }

    create_notification($userId, $judul, $pesan);
}

function notify_admins(string $judul, string $pesan): void
{
    $admins = db()->query("SELECT id_petugas FROM petugas_koperasi WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        create_notification((int)$admin['id_petugas'], $judul, $pesan);
    }
}

function unread_notification_count(int $userId): int
{
    $file = get_notifications_file($userId);
    if (!file_exists($file)) {
        return 0;
    }

    $notifications = json_decode(file_get_contents($file), true) ?: [];
    $count = 0;
    foreach ($notifications as $n) {
        if ($n['is_read'] == 0) {
            $count++;
        }
    }
    return $count;
}

function mark_notification_read(int $notificationId, int $userId): void
{
    $file = get_notifications_file($userId);
    if (!file_exists($file)) {
        return;
    }

    $notifications = json_decode(file_get_contents($file), true) ?: [];
    foreach ($notifications as &$n) {
        if ($n['id'] == $notificationId) {
            $n['is_read'] = 1;
        }
    }
    file_put_contents($file, json_encode($notifications, JSON_PRETTY_PRINT));
}

function mark_all_notifications_read(int $userId): void
{
    $file = get_notifications_file($userId);
    if (!file_exists($file)) {
        return;
    }

    $notifications = json_decode(file_get_contents($file), true) ?: [];
    foreach ($notifications as &$n) {
        $n['is_read'] = 1;
    }
    file_put_contents($file, json_encode($notifications, JSON_PRETTY_PRINT));
}

function sync_late_fines(?int $userId = null): void
{
    $tarif = (float)get_setting('denda_per_hari', 5000);
    if ($tarif <= 0) {
        return;
    }

    $params = [];
    $userFilter = '';
    if ($userId) {
        $userFilter = ' AND a.id_anggota = ?';
        $params[] = $userId;
    }

    $sql = "
        SELECT
            a.id_angsuran,
            a.angsuran_ke,
            da.tgl_jatuh_tempo,
            a.status AS status_angsuran,
            p.nama_pinjaman,
            a.id_anggota,
            DATEDIFF(
                CASE
                    WHEN a.status IN ('Menunggu konfirmasi', 'Diterima') AND a.tgl_pembayaran IS NOT NULL
                    THEN a.tgl_pembayaran
                    ELSE CURDATE()
                END,
                da.tgl_jatuh_tempo
            ) AS jumlah_hari
        FROM angsuran a
        JOIN detail_angsuran da ON da.id_angsuran = a.id_angsuran
        JOIN pinjaman p ON p.id_pinjaman = a.id_pinjaman
        WHERE da.tgl_jatuh_tempo IS NOT NULL
          $userFilter
        HAVING jumlah_hari > 0
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $hari = max(0, (int)$row['jumlah_hari']);
        $total = $hari * $tarif;

        $update = db()->prepare("
            UPDATE detail_angsuran
            SET jumlah_hari_terlambat = ?, denda_tarif_per_hari = ?, denda_total = ?, tanggal_denda = COALESCE(tanggal_denda, CURDATE()), updated_at = NOW()
            WHERE id_angsuran = ? AND status_denda != 'Dibayar'
        ");
        $update->execute([$hari, $tarif, $total, (int)$row['id_angsuran']]);

        create_unique_notification(
            (int)$row['id_anggota'],
            'Denda muncul',
            'Denda angsuran ' . $row['nama_pinjaman'] . ' ke-' . $row['angsuran_ke'] . ' sebesar ' . format_rupiah($total) . ' karena terlambat ' . $hari . ' hari.'
        );
    }
}

function sync_due_reminders(?int $userId = null): void
{
    $days = max(0, (int)get_setting('reminder_days_before_due', 3));
    $pdo = db();

    $sql = "SELECT id_pinjaman, nama_pinjaman, id_anggota, tgl_pinjaman, tenor, angsuran_per_bulan, total_bayar FROM pinjaman WHERE status = 'Dicairkan'";
    $params = [];
    if ($userId) {
        $sql .= " AND id_anggota = ?";
        $params[] = $userId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activeLoans = $stmt->fetchAll();

    foreach ($activeLoans as $p) {
        $stLatest = $pdo->prepare("SELECT angsuran_ke, status FROM angsuran WHERE id_pinjaman = ? ORDER BY angsuran_ke DESC LIMIT 1");
        $stLatest->execute([$p['id_pinjaman']]);
        $latest = $stLatest->fetch();

        $next_ke = 1;
        $can_remind = true;

        if ($latest) {
            if ($latest['status'] === 'Menunggu konfirmasi') {
                $can_remind = false;
            } elseif ($latest['status'] === 'Ditolak') {
                $next_ke = (int)$latest['angsuran_ke'];
            } elseif ($latest['status'] === 'Diterima') {
                if ((int)$latest['angsuran_ke'] < (int)$p['tenor']) {
                    $next_ke = (int)$latest['angsuran_ke'] + 1;
                } else {
                    $can_remind = false;
                }
            }
        }

        if ($can_remind) {
            $tenor = (int)$p['tenor'];
            $tglPinjam = $p['tgl_pinjaman'];
            $angsuranPerBulan = (float)$p['angsuran_per_bulan'];
            $totalBayar = (float)$p['total_bayar'];
            $angsuranTerakhir = round($totalBayar - ($angsuranPerBulan * ($tenor - 1)), 2);

            $nominal = ($next_ke === $tenor) ? $angsuranTerakhir : $angsuranPerBulan;
            $jatuhTempo = date('Y-m-d', strtotime("+{$next_ke} month", strtotime($tglPinjam)));

            $today = date('Y-m-d');
            $maxDate = date('Y-m-d', strtotime("+{$days} days"));

            if ($jatuhTempo >= $today && $jatuhTempo <= $maxDate) {
                create_unique_notification(
                    (int)$p['id_anggota'],
                    'Jatuh tempo angsuran',
                    'Angsuran ' . $p['nama_pinjaman'] . ' ke-' . $next_ke . ' jatuh tempo pada ' . date('d/m/Y', strtotime($jatuhTempo)) . ' sebesar ' . format_rupiah($nominal) . '.'
                );
            }
        }
    }
}

function unpaid_fines_total(?int $userId = null): float
{
    $pdo = db();
    if ($userId) {
        $stmt = $pdo->prepare("
            SELECT SUM(da.denda_total) AS total
            FROM detail_angsuran da
            JOIN angsuran a ON a.id_angsuran = da.id_angsuran
            WHERE da.status_denda = 'Belum Dibayar' AND a.id_anggota = ?
        ");
        $stmt->execute([$userId]);
        return (float)($stmt->fetch()['total'] ?? 0);
    }

    $row = $pdo->query("SELECT SUM(denda_total) AS total FROM detail_angsuran WHERE status_denda = 'Belum Dibayar'")->fetch();
    return (float)($row['total'] ?? 0);
}

function mark_fine_paid(int $angsuranId): bool
{
    $stmt = db()->prepare("UPDATE detail_angsuran SET status_denda = 'Dibayar', tanggal_bayar_denda = CURDATE(), updated_at = NOW() WHERE id_angsuran = ? AND status_denda = 'Belum Dibayar'");
    $stmt->execute([$angsuranId]);
    return $stmt->rowCount() > 0;
}

function database_backup_sql(): string
{
    $pdo = db();
    $tables = ['katagori_pinjaman', 'petugas_koperasi', 'anggota', 'simpanan', 'angsuran', 'detail_angsuran', 'pinjaman'];
    $sql = "-- Backup Koperasi Simpan Pinjam (7 CDM Tables)\n";
    $sql .= "-- Dibuat: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
        foreach ($rows as $row) {
            $columns = array_map(fn($col) => "`$col`", array_keys($row));
            $values = array_map(fn($value) => $value === null ? 'NULL' : $pdo->quote((string)$value), array_values($row));
            $sql .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }

    return $sql . "SET FOREIGN_KEY_CHECKS=1;\n";
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inString = false;
    $quote = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($inString && $char === $quote && $i + 1 < $length && $sql[$i + 1] === $quote) {
            $buffer .= $char . $sql[$i + 1];
            $i++;
            continue;
        }

        if (($char === "'" || $char === '"') && $prev !== '\\') {
            if (!$inString) {
                $inString = true;
                $quote = $char;
            } elseif ($quote === $char) {
                $inString = false;
                $quote = '';
            }
        }

        if ($char === ';' && !$inString) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function restore_database_sql(string $sql): int
{
    $pdo = db();
    $count = 0;
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (split_sql_statements($sql) as $statement) {
            if (preg_match('/^\s*--/m', $statement)) {
                $statement = preg_replace('/^\s*--.*$/m', '', $statement);
            }
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
            $count++;
        }
    } catch (Throwable $e) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        throw $e;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return $count;
}
