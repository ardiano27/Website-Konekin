<?php
include "config/Database.php";

$database = new DatabaseConnection();
$conn = $database->getConnection();

if ($conn) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed!";
}