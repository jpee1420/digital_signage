<?php
$host = 'localhost';
$dbname = 'digital_signage';
$username = 'root';
$password = '';

define ('BASE_URL', '/digital_signage');

$tempConn = new mysqli($host, $username, $password);

if ($tempConn->connect_error) {
    define('DB_CONNECTION_ERROR', true);
} else {
    // Check if database exists
    $dbExists = $tempConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    
    if ($dbExists->num_rows == 0) {
        define('DB_NEEDS_INSTALLATION', true);
    } else {
        // Connect to the database to check for required tables
        $conn = new mysqli($host, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            define('DB_CONNECTION_ERROR', true);
        } else {
            // Check for required tables
            $requiredTables = ['media', 'ticker_messages'];
            $missingTables = [];
            
            foreach ($requiredTables as $table) {
                $tableExists = $conn->query("SHOW TABLES LIKE '$table'");
                if ($tableExists->num_rows == 0) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                define('DB_NEEDS_INSTALLATION', true);
                error_log("Missing required tables: " . implode(', ', $missingTables));
            } else {
                define('DB_NEEDS_INSTALLATION', false);
                define('DB_CONNECTION_ERROR', false);
            }
        }
    }
    
    $tempConn->close();
}

// Check if the installation is complete
if (!defined('DB_CONNECTION_ERROR')) {
    define('DB_CONNECTION_ERROR', false);
}