<?php
$host = 'localhost';
$dbname = 'digital_signage';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    // Create media table with new columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        type ENUM('image', 'video', 'webpage') NOT NULL,
        path VARCHAR(255),
        url VARCHAR(255),
        duration INT DEFAULT 10,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if display_order column exists, if not add it
    $result = $pdo->query("SHOW COLUMNS FROM media LIKE 'display_order'");
    if (!$result->fetch()) {
        $pdo->exec("ALTER TABLE media ADD COLUMN display_order INT DEFAULT 0");
        // Initialize display_order for existing records
        $pdo->exec("SET @order := 0");
        $pdo->exec("UPDATE media SET display_order = @order := @order + 1 ORDER BY created_at ASC");
    }
    
    // Check if is_active column exists, if not add it
    $result = $pdo->query("SHOW COLUMNS FROM media LIKE 'is_active'");
    if (!$result->fetch()) {
        $pdo->exec("ALTER TABLE media ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 