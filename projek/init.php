<?php
// init.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/Database.php';
$database = new DatabaseConnection();
$GLOBALS['conn'] = $database->getConnection();

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>  