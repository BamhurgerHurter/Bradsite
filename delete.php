<?php
$jsonFile = '/var/www/blog-data/posts.json';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if (file_exists($jsonFile)) {
        $posts = json_decode(file_get_contents($jsonFile), true);
        
        // Find media to delete from disk if it exists
        foreach ($posts as $p) {
            if ($p['id'] === $id && !empty($p['media'])) {
                $filePath = '/var/www/blog-data' . $p['media']['url'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
        
        $posts = array_values(array_filter($posts, function($p) use ($id) {
            return $p['id'] !== $id;
        }));
        
        file_put_contents($jsonFile, json_encode($posts, JSON_PRETTY_PRINT));
    }
}
header('Location: /admin');
exit;
?>
