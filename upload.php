<?php
// Handle POST request from admin panel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $fileData = null;

    // Handle file upload (images, audio, video)
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['media']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['media']['tmp_name'], $targetPath)) {
            $fileData = [
                'url' => '/' . $targetPath,
                'type' => $_FILES['media']['type']
            ];
        }
    }

    if ($title !== '' && $content !== '') {
        $jsonFile = 'posts.json';
        $posts = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
        
        // Prepend new post
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
