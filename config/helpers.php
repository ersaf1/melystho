<?php

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    static $base;
    if ($base === null) {
        $config = require __DIR__ . '/config.php';
        $base = $config['app']['base_url'];
        if ($base === '') {
            $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
            $projectRoot = str_replace('\\', '/', dirname(__DIR__));
            if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
                $sub = substr($projectRoot, strlen($docRoot));
                $base = ($sub === '') ? '' : '/' . ltrim($sub, '/');
            } else {
                $base = '';
            }
        }
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    if (strpos($url, 'http') === 0) {
        header("Location: {$url}");
    } else {
        header("Location: " . base_url($url));
    }
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function format_rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function upload_file(array $file, string $targetDir, array $allowedMime, int $maxSize = 5242880): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > $maxSize) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        return null;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('upload_', true) . '.' . strtolower($ext);
    $targetDir = rtrim($targetDir, '/\\');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    return $filename;
}

function get_setting(string $name, $default = null)
{
    static $settings;
    if ($settings === null) {
        $file = __DIR__ . '/settings.php';
        if (file_exists($file)) {
            $settings = require $file;
        } else {
            $settings = [];
        }
    }
    return isset($settings[$name]) ? $settings[$name] : $default;
}

function set_setting(string $name, $value): void
{
    $file = __DIR__ . '/settings.php';
    $settings = [];
    if (file_exists($file)) {
        $settings = require $file;
    }
    $settings[$name] = $value;
    
    $content = "<?php\n\nreturn " . var_export($settings, true) . ";\n";
    file_put_contents($file, $content);
}

function generate_loan_number(): string
{
    $prefix = 'PJ-' . date('Ym') . '-';
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM pinjaman WHERE nama_pinjaman LIKE ?");
    $stmt->execute([$prefix . '%']);
    $total = (int)$stmt->fetch()['total'] + 1;
    return $prefix . str_pad((string)$total, 4, '0', STR_PAD_LEFT);
}

function build_pagination(int $total, int $page, int $perPage, string $baseUrl): string
{
    $totalPages = (int)ceil($total / $perPage);
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav><ul class="pagination">';
    $prev = max(1, $page - 1);
    $next = min($totalPages, $page + 1);
    $html .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . $baseUrl . 'page=' . $prev . '">Prev</a></li>';
    for ($i = 1; $i <= $totalPages; $i++) {
        $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($page >= $totalPages ? ' disabled' : '') . '"><a class="page-link" href="' . $baseUrl . 'page=' . $next . '">Next</a></li>';
    $html .= '</ul></nav>';

    return $html;
}

require_once __DIR__ . '/features.php';
