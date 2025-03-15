<?php
// Database configuration
$db_host = "localhost";  // or your database host
$db_user = "root";       // or your database username
$db_pass = "";           // your database password
$db_name = "mywebsite";  // your database name

// Create database connection
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if(!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>