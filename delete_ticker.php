<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Check if it's a default message that shouldn't be deleted
    $stmt = $pdo->prepare("SELECT type FROM ticker_messages WHERE id = ?");
    $stmt->execute([$id]);
    $type = $stmt->fetch(PDO::FETCH_COLUMN);
    
    // Only allow deletion of custom type messages
    if ($type === 'custom') {
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM ticker_messages WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: index.php?success=delete_ticker');
        exit;
    } else {
        // Redirect with error message for default or schedule messages
        header('Location: index.php?error=cannot_delete_system');
        exit;
    }
}

// If we get here, no valid ID was provided
header('Location: index.php');
exit; 