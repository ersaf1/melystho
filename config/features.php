<?php

function ensure_feature_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS denda (
            id INT AUTO_INCREMENT PRIMARY KEY,
            angsuran_id INT NOT NULL UNIQUE,
            user_id INT NOT NULL,
            jumlah_hari INT NOT NULL DEFAULT 0,
            tarif_per_hari DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_denda DECIMAL(15,2) NOT NULL DEFAULT 0,
            status ENUM('Belum Dibayar', 'Dibayar') DEFAULT 'Belum Dibayar',
            tanggal_denda DATE NOT NULL,
            tanggal_bayar DATE NULL,
            created_at DATETIME,
            updated_at DATETIME,
            INDEX idx_denda_user_status (user_id, status),
            CONSTRAINT fk_denda_angsuran FOREIGN KEY (angsuran_id) REFERENCES angsuran(id) ON DELETE CASCADE,
            CONSTRAINT fk_denda_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            judul VARCHAR(150) NOT NULL,
            pesan TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME,
            INDEX idx_notifications_user_read (user_id, is_read),
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            aktivitas VARCHAR(255) NOT NULL,
            created_at DATETIME,
            INDEX idx_activity_logs_user_created (user_id, created_at),
            CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    set_setting('denda_per_hari', get_setting('denda_per_hari', 5000));
    set_setting('reminder_days_before_due', get_setting('reminder_days_before_due', 3));

    $done = true;
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

    ensure_feature_tables();
    $stmt = db()->prepare("INSERT INTO activity_logs (user_id, aktivitas, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$userId, $aktivitas]);
}

function create_notification(int $userId, string $judul, string $pesan): void
{
    if ($userId <= 0 || trim($judul) === '' || trim($pesan) === '') {
        return;
    }

    ensure_feature_tables();
    $stmt = db()->prepare("INSERT INTO notifications (user_id, judul, pesan, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->execute([$userId, $judul, $pesan]);
}

function create_unique_notification(int $userId, string $judul, string $pesan): void
{
    ensure_feature_tables();
    $stmt = db()->prepare("SELECT id FROM notifications WHERE user_id = ? AND judul = ? AND pesan = ? LIMIT 1");
    $stmt->execute([$userId, $judul, $pesan]);
    if (!$stmt->fetch()) {
        create_notification($userId, $judul, $pesan);
    }
}

function notify_admins(string $judul, string $pesan): void
{
    ensure_feature_tables();
    $admins = db()->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        create_notification((int)$admin['id'], $judul, $pesan);
    }
}

function unread_notification_count(int $userId): int
{
    ensure_feature_tables();
    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)($stmt->fetch()['total'] ?? 0);
}

function mark_notification_read(int $notificationId, int $userId): void
{
    ensure_feature_tables();
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
}

function mark_all_notifications_read(int $userId): void
{
    ensure_feature_tables();
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

function sync_late_fines(?int $userId = null): void
{
    ensure_feature_tables();

    $tarif = (float)get_setting('denda_per_hari', 5000);
    if ($tarif <= 0) {
        return;
    }

    $params = [];
    $userFilter = '';
    if ($userId) {
        $userFilter = ' AND p.user_id = ?';
        $params[] = $userId;
    }

    $sql = "
        SELECT
            a.id AS angsuran_id,
            a.angsuran_ke,
            a.jatuh_tempo,
            a.status AS status_angsuran,
            p.nomor_pinjaman,
            p.user_id,
            DATEDIFF(
                CASE
                    WHEN a.status IN ('Menunggu konfirmasi', 'Diterima') AND a.tanggal_bayar IS NOT NULL
                    THEN a.tanggal_bayar
                    ELSE CURDATE()
                END,
                a.jatuh_tempo
            ) AS jumlah_hari
        FROM angsuran a
        JOIN pinjaman p ON p.id = a.pinjaman_id
        WHERE a.jatuh_tempo IS NOT NULL
          AND p.status IN ('Disetujui', 'Dicairkan', 'Lunas')
          $userFilter
        HAVING jumlah_hari > 0
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $hari = max(0, (int)$row['jumlah_hari']);
        $total = $hari * $tarif;
        $existing = db()->prepare("SELECT id, status FROM denda WHERE angsuran_id = ? LIMIT 1");
        $existing->execute([(int)$row['angsuran_id']]);
        $fine = $existing->fetch();

        if ($fine) {
            if ($fine['status'] !== 'Dibayar') {
                $update = db()->prepare("
                    UPDATE denda
                    SET jumlah_hari = ?, tarif_per_hari = ?, total_denda = ?, tanggal_denda = CURDATE(), updated_at = NOW()
                    WHERE id = ?
                ");
                $update->execute([$hari, $tarif, $total, (int)$fine['id']]);
            }
            continue;
        }

        $insert = db()->prepare("
            INSERT INTO denda (angsuran_id, user_id, jumlah_hari, tarif_per_hari, total_denda, status, tanggal_denda, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'Belum Dibayar', CURDATE(), NOW(), NOW())
        ");
        $insert->execute([(int)$row['angsuran_id'], (int)$row['user_id'], $hari, $tarif, $total]);

        create_unique_notification(
            (int)$row['user_id'],
            'Denda muncul',
            'Denda angsuran ' . $row['nomor_pinjaman'] . ' ke-' . $row['angsuran_ke'] . ' sebesar ' . format_rupiah($total) . ' karena terlambat ' . $hari . ' hari.'
        );
    }
}

function sync_due_reminders(?int $userId = null): void
{
    ensure_feature_tables();

    $days = max(0, (int)get_setting('reminder_days_before_due', 3));
    $params = [$days];
    $userFilter = '';
    if ($userId) {
        $userFilter = ' AND p.user_id = ?';
        $params[] = $userId;
    }

    $sql = "
        SELECT a.id, a.angsuran_ke, a.jatuh_tempo, a.nominal, p.nomor_pinjaman, p.user_id
        FROM angsuran a
        JOIN pinjaman p ON p.id = a.pinjaman_id
        WHERE a.status IN ('Belum dibayar', 'Ditolak')
          AND p.status IN ('Disetujui', 'Dicairkan')
          AND a.jatuh_tempo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
          $userFilter
        ORDER BY a.jatuh_tempo ASC
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        create_unique_notification(
            (int)$row['user_id'],
            'Jatuh tempo angsuran',
            'Angsuran ' . $row['nomor_pinjaman'] . ' ke-' . $row['angsuran_ke'] . ' jatuh tempo pada ' . date('d/m/Y', strtotime($row['jatuh_tempo'])) . ' sebesar ' . format_rupiah($row['nominal']) . '.'
        );
    }
}

function unpaid_fines_total(?int $userId = null): float
{
    ensure_feature_tables();
    if ($userId) {
        $stmt = db()->prepare("SELECT SUM(total_denda) AS total FROM denda WHERE status = 'Belum Dibayar' AND user_id = ?");
        $stmt->execute([$userId]);
        return (float)($stmt->fetch()['total'] ?? 0);
    }

    $row = db()->query("SELECT SUM(total_denda) AS total FROM denda WHERE status = 'Belum Dibayar'")->fetch();
    return (float)($row['total'] ?? 0);
}

function mark_fine_paid(int $fineId): bool
{
    ensure_feature_tables();
    $stmt = db()->prepare("UPDATE denda SET status = 'Dibayar', tanggal_bayar = CURDATE(), updated_at = NOW() WHERE id = ? AND status = 'Belum Dibayar'");
    $stmt->execute([$fineId]);
    return $stmt->rowCount() > 0;
}

function database_backup_sql(): string
{
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- Backup Koperasi Simpan Pinjam\n";
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
