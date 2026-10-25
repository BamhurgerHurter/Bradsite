<?php
declare(strict_types=1);

const DATA_DIR = '/var/www/blog-data/';
const POSTS    = DATA_DIR . 'posts.json';
const UPLOADS  = DATA_DIR . 'uploads/';
const MAX_FILES = 20;

function fail(int $code, string $msg): never {
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
        header('Location: /admin?error=' . rawurlencode($msg), true, 303);
        exit;
    }
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($msg);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST only');
}

$site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin';
if (!in_array($site, ['same-origin', 'none'], true)) {
    fail(403, 'Cross-site request blocked');
}

// If the whole upload was over the server limit, PHP throws everything away.
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !$_POST && !$_FILES) {
    fail(413, 'Those files add up to too much. Try fewer photos at once.');
}

$title = trim((string)($_POST['title'] ?? ''));
$body  = trim((string)($_POST['content'] ?? ''));

if ($title === '' || $body === '') {
    fail(400, 'Please add a title and some text.');
}
if (mb_strlen($title) > 200 || mb_strlen($body) > 20000) {
    fail(400, 'That’s too long. Titles can be 200 characters and posts 20,000.');
}

// Collect the chosen files (the form sends media[] so several can be picked).
$files = [];
if (isset($_FILES['media']) && is_array($_FILES['media']['error'])) {
    foreach ($_FILES['media']['error'] as $i => $err) {
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = ['tmp' => $_FILES['media']['tmp_name'][$i], 'error' => $err];
    }
}
if (count($files) > MAX_FILES) {
    fail(400, 'Please pick ' . MAX_FILES . ' files or fewer per post.');
}

$types = [
    'image/jpeg' => 'jpg',  'image/png'  => 'png',  'image/gif'  => 'gif',  'image/webp' => 'webp',
    'audio/mpeg' => 'mp3',  'audio/ogg'  => 'ogg',  'audio/wav'  => 'wav',  'audio/x-wav' => 'wav',
    'audio/mp4'  => 'm4a',  'audio/x-m4a' => 'm4a', 'audio/webm' => 'weba',
    'video/mp4'  => 'mp4',  'video/webm' => 'webm', 'video/ogg'  => 'ogv',  'video/quicktime' => 'mov',
];

$media = [];
$saved = [];
$undo = function () use (&$saved): void {
    foreach ($saved as $path) {
        @unlink($path);
    }
};

foreach ($files as $f) {
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        $undo();
        fail(413, 'One of those files is too big. The limit is 50 MB each.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $undo();
        fail(400, 'The upload didn’t finish. Please try again.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp']);
    if (!is_string($mime) || !isset($types[$mime])) {
        $undo();
        fail(415, 'One of those files isn’t supported. Try photos, audio or video.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $types[$mime];
    if (!move_uploaded_file($f['tmp'], UPLOADS . $name)) {
        $undo();
        fail(500, 'Could not save a file. Please try again.');
    }
    $saved[] = UPLOADS . $name;
    $media[] = ['url' => '/uploads/' . $name, 'type' => $mime];
}

$ok = false;
$fp = fopen(POSTS, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $posts = json_decode(stream_get_contents($fp) ?: '[]', true);
    if (!is_array($posts)) {
        $posts = [];
    }
    array_unshift($posts, [
        'id'      => (int)(microtime(true) * 1000),
        'title'   => $title,
        'content' => $body,
        'media'   => $media,
        'date'    => date('Y-m-d H:i'),
    ]);
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
    $undo();
    fail(500, 'Could not save the post. Please try again.');
}

header('Location: /admin?posted=1', true, 303);
exit;
