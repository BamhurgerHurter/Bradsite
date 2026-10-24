<?php
$dataDir = '/var/www/blog-data/';
$jsonFile = $dataDir . 'posts.json';
$uploadDir = $dataDir . 'uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $fileData = null;

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . basename($_FILES['media']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['media']['tmp_name'], $targetPath)) {
            // Public URL maps through our symlink
            $fileData = [
                'url' => '/uploads/' . $fileName,
                'type' => $_FILES['media']['type']
            ];
        }
    }

    if ($title !== '' && $content !== '') {
        $posts = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
        
        array_unshift($posts, [
            'id' => time(),
            'title' => htmlspecialchars($title),
            'content' => htmlspecialchars($content),
            'media' => $fileData,
            'date' => date('Y-m-d H:i')
        ]);
        
        file_put_contents($jsonFile, json_encode($posts, JSON_PRETTY_PRINT));
    }
}
header('Location: /admin');
exit;
?>
