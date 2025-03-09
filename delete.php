<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Get media info first
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($media) {
        // Delete file if it exists
        if ($media['type'] !== 'webpage' && !empty($media['path'])) {
            if (file_exists($media['path'])) {
                unlink($media['path']);
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
        $stmt->execute([$id]);
    }
}

header('Location: index.php');
exit; 