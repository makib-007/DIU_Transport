<?php
// Database configuration for XAMPP
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'diutransport';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");
?>
