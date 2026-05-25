<?php
require_once __DIR__ . '/config/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();
header('Location: ' . base_url('/login.php'));
exit;
