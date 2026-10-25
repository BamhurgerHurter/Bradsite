<?php
declare(strict_types=1);

$jsonFile  = '/var/www/blog-data/posts.json';
$uploadDir = '/var/www/blog-data/uploads/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) { http_response_code(405); exit; }
$id = (int)$_POST['id'];

$fp = fopen($jsonFile, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $posts = json_decode(stream_get_contents($fp) ?: '[]', true);
    if (!is_array($posts)) { $posts = []; }
    foreach ($posts as $p) {
        if (($p['id'] ?? null) === $id && !empty($p['media']['url'])) {
            $f = $uploadDir . basename($p['media']['url']);
            if (is_file($f)) { unlink($f); }
        }
    }
    $posts = array_values(array_filter($posts, fn($p) => ($p['id'] ?? null) !== $id));
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
header('Location: /admin');
exit;
