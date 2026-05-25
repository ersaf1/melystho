<?php
require_once __DIR__ . '/../config/helpers.php';
$config = require __DIR__ . '/../config/config.php';
$page_title = $page_title ?? $config['app']['name'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($config['app']['name']); ?> — Kelola simpanan dan pinjaman koperasi dengan mudah, aman, dan transparan.">
    <title><?= e($page_title); ?> — <?= e($config['app']['name']); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= base_url('assets/css/styles.css'); ?>" rel="stylesheet">
</head>
<body>
