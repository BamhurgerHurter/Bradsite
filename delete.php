<?php
declare(strict_types=1);

const DATA_DIR = '/var/www/blog-data/';
const POSTS    = DATA_DIR . 'posts.json';
const UPLOADS  = DATA_DIR . 'uploads/';

function fail(int $code, string $msg): never {
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
        header('Location: /admin?error=' . rawurlencode($msg), true, 303);
        exit;
    }
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($msg);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    fail(405, 'POST only');
}

$site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin';
if (!in_array($site, ['same-origin', 'none'], true)) {
    fail(403, 'Cross-site request blocked');
}

$id = (int)$_POST['id'];

$ok = false;
$fp = fopen(POSTS, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $posts = json_decode(stream_get_contents($fp) ?: '[]', true);
    if (!is_array($posts)) {
        $posts = [];
    }
    foreach ($posts as $p) {
        if (($p['id'] ?? null) === $id && !empty($p['media']['url'])) {
            $path = UPLOADS . basename((string)$p['media']['url']);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
    $posts = array_values(array_filter($posts, fn($p) => ($p['id'] ?? null) !== $id));
    ftruncate($fp, 0);
    rewind($fp);
    $ok = fwrite($fp, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
}
if ($fp) {
    fclose($fp);
}

if (!$ok) {
    fail(500, 'Could not delete the post. Please try again.');
}

header('Location: /admin?deleted=1', true, 303);
exit;
