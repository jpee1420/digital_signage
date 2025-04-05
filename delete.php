<?php
require_once 'config.php';

$deletion_status = 'success';
$error_message = '';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Get media info first
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($media) {
        // Track file deletion status
        $file_deleted = true;
        
        // Delete file if it exists
        if ($media['type'] !== 'webpage' && !empty($media['path'])) {
            $file_deleted = false;
            
            if (file_exists($media['path'])) {
                // Attempt to delete the file
                if (unlink($media['path'])) {
                    // Log successful file deletion
                    error_log("File successfully deleted: " . $media['path']);
                    $file_deleted = true;
                } else {
                    // Log error if file couldn't be deleted
                    $error = error_get_last();
                    $error_message = "Error deleting file: " . $media['path'];
                    if ($error) {
                        $error_message .= " - " . $error['message'];
                    }
                    error_log($error_message);
                    $deletion_status = 'error_file';
                }
            } else {
                // File not found at the specified path
                error_log("File not found for deletion: " . $media['path']);
                
                // Check if the file exists in the uploads folder directly
                $filename = basename($media['path']);
                $alt_path = 'uploads/' . $filename;
                
                if (file_exists($alt_path)) {
                    if (unlink($alt_path)) {
                        error_log("File successfully deleted from alternate path: " . $alt_path);
                        $file_deleted = true;
                    } else {
                        error_log("Error deleting file from alternate path: " . $alt_path);
                        $deletion_status = 'error_file';
                    }
                } else {
                    $deletion_status = 'file_not_found';
                    $error_message = "File not found for deletion: " . $media['path'];
                }
            }
        }
        
        // Delete from database
        try {
            $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                $deletion_status = 'error_db';
                $error_message = "Failed to delete record from database.";
            }
        } catch (PDOException $e) {
            $deletion_status = 'error_db';
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $deletion_status = 'not_found';
        $error_message = "Item not found in database.";
    }
}

// Redirect with status
if ($deletion_status === 'success') {
    header('Location: index.php?success=delete');
} else {
    header('Location: index.php?error=' . $deletion_status . '&message=' . urlencode($error_message));
}
exit; 