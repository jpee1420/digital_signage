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
    
    // Create ticker messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticker_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        type ENUM('default', 'schedule', 'custom') NOT NULL DEFAULT 'default',
        is_active BOOLEAN DEFAULT TRUE,
        target_url VARCHAR(255) DEFAULT NULL,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default ticker message if none exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM ticker_messages WHERE type = 'default'");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO ticker_messages (message, type) 
                   VALUES ('Welcome to University of Luzon College of Computer Studies. For any inquiries, please head to the CS Office.', 'default')");
    }
    
    // Insert schedule message if none exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM ticker_messages WHERE type = 'schedule'");
    if ($stmt->fetchColumn() == 0) {
        $schedule_message = "If you spot any errors in the current schedule, feel free to report them to the CS Lab Admin.";
        $stmt = $pdo->prepare("INSERT INTO ticker_messages (message, type, target_url) VALUES (?, 'schedule', 'http://localhost/smart_schedule/view_schedules.php')");
        $stmt->execute([$schedule_message]);
    }
    
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
    
    // Check if display_order column exists for ticker_messages, if not add it
    $result = $pdo->query("SHOW COLUMNS FROM ticker_messages LIKE 'display_order'");
    if (!$result->fetch()) {
        $pdo->exec("ALTER TABLE ticker_messages ADD COLUMN display_order INT DEFAULT 0");
        // Initialize display_order for existing records
        $pdo->exec("SET @order := 0");
        $pdo->exec("UPDATE ticker_messages SET display_order = @order := @order + 1 ORDER BY created_at ASC");
    }
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 