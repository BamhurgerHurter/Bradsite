<?php
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $jsonFile = 'posts.json';
    
    if (file_exists($jsonFile)) {
        $posts = json_decode(file_get_contents($jsonFile), true);
        $posts = array_values(array_filter($posts, function($p) use ($id) {
            return $p['id'] !== $id;
        }));
        file_put_contents($jsonFile, json_encode($posts, JSON_PRETTY_PRINT));
    }
}
header('Location: /admin');
exit;
?>
