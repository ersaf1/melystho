<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $pdo = db();
    $role = $_SESSION['role'] ?? 'user';
    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT *, id_petugas AS id, 'Disetujui' AS status_verifikasi FROM petugas_koperasi WHERE id_petugas = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT *, id_anggota AS id, status AS status_verifikasi FROM anggota WHERE id_anggota = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user ?: null;
}

function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        redirect('/login.php');
    }
}

function require_admin(): void
{
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        redirect('/user/dashboard.php');
    }
}

function require_user(): void
{
    require_login();
    if ($_SESSION['role'] !== 'user') {
        redirect('/admin/dashboard.php');
    }
}

