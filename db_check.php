<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Check if the database needs installation
if (defined('DB_NEEDS_INSTALLATION') && DB_NEEDS_INSTALLATION) {
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

// Check if there's a database connection error
if (defined('DB_CONNECTION_ERROR') && DB_CONNECTION_ERROR) {
    die("Database connection error. Please check your database settings or run the <a href='" . BASE_URL . "/install/index.php'>installation wizard</a>.");
}
?> 