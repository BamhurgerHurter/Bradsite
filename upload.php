<?php
declare(strict_types=1);

$dataDir   = '/var/www/blog-data/';
$jsonFile  = $dataDir . 'posts.json';
$uploadDir = $dataDir . 'uploads/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$title   = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$media   = null;

if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $allowed = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/webm' => 'weba',
        'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv', 'video/quicktime' => 'mov',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['media']['tmp_name']);
    if (!isset($allowed[$mime])) {
        http_response_code(415);
        exit('Unsupported file type: ' . htmlspecialchars((string)$mime));
    }
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $name)) {
        $media = ['url' => '/uploads/' . $name, 'type' => $mime];
    }
}

if ($title !== '' && $content !== '') {
    $fp = fopen($jsonFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $posts = json_decode(stream_get_contents($fp) ?: '[]', true);
        if (!is_array($posts)) { $posts = []; }
        array_unshift($posts, [
            'id'      => time(),
            'title'   => htmlspecialchars($title),
            'content' => htmlspecialchars($content),
            'media'   => $media,
            'date'    => date('Y-m-d H:i'),
        ]);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
header('Location: /admin');
exit;
