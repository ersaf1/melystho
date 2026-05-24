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
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
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
