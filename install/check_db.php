<?php
/**
 * Database Check Utility
 * 
 * Include this file at the top of any script that requires database access
 * to automatically redirect to installation if needed.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config
require_once dirname(__FILE__) . '/../config.php';

/**
 * Check if database needs installation and redirect if necessary
 */
function checkDatabaseAndRedirect() {
    if (defined('DB_NEEDS_INSTALLATION') && DB_NEEDS_INSTALLATION) {
        header('Location: ' . BASE_URL . '/install/index.php');
        exit;
    }
    
    if (defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR) {
        die("Database connection error. Please check your database settings or run the <a href='" . BASE_URL . "/install/index.php'>installation wizard</a>.");
    }
    
    return true;
}

// Run the check if this script is included directly
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    checkDatabaseAndRedirect();
}
?> 