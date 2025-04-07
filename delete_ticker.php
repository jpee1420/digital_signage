<?php
require_once 'config.php';

// If installation is needed, redirect to the installation wizard
if (defined('DB_NEEDS_INSTALLATION') && DB_NEEDS_INSTALLATION === true) {
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

// If there's a database connection error, redirect to the installation wizard
if (defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR === true) {
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

// Initialize PDO connection using the variables from config.php
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("PDO connection error: " . $e->getMessage());
    // Redirect to installation wizard
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

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