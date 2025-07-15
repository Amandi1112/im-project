<?php
session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Define low stock threshold (adjust as needed)
define('LOW_STOCK_THRESHOLD', 10);
// Query to get all items with supplier names
$sql = "SELECT i.*, s.supplier_name 
        FROM items i
        LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
        ORDER BY i.item_name, i.price_per_unit";

$result = $conn->query($sql);

$items = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Add low_stock flag to each item
        $row['low_stock'] = ($row['current_quantity'] <= LOW_STOCK_THRESHOLD);
        $items[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($items);

$conn->close();
?>