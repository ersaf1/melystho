<?php

function e(mixed $value): string
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

function format_rupiah(mixed $angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function parse_currency(mixed $val): float
{
    if (empty($val)) {
        return 0.0;
    }
    $val = (string)$val;
    
    // Jika ada koma, diasumsikan sebagai pecahan desimal gaya Indonesia (contoh: 42.777,78 atau 42777,78)
    if (strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
        return (float)$val;
    }
    
    // Jika ada lebih dari satu titik, itu adalah pemisah ribuan (contoh: 1.000.000)
    if (substr_count($val, '.') > 1) {
        $val = str_replace('.', '', $val);
        return (float)$val;
    }
    
    // Jika ada satu titik
    if (substr_count($val, '.') === 1) {
        $parts = explode('.', $val);
        // Jika setelah titik ada tepat 3 digit, kemungkinan besar itu ribuan (contoh: 1.000)
        if (strlen($parts[1]) === 3) {
            $val = str_replace('.', '', $val);
        }
    }
    
    return (float)$val;
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

function set_setting(string $name, mixed $value): void
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
