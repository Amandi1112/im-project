<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['supplier_id'])) {
    $supplier_id = $_GET['supplier_id'];

    $balance_query = $conn->prepare("
    SELECT
        COALESCE(
            (SELECT SUM(ip.total_price)
             FROM items i
             JOIN item_purchases ip ON i.item_id = ip.item_id
             WHERE i.supplier_id = ?),
            0
        ) - COALESCE(
            (SELECT SUM(sp.amount)
             FROM supplier_payments sp
             WHERE sp.supplier_id = ?),
            0
        ) as balance
    ");
    $balance_query->bind_param("ss", $supplier_id, $supplier_id);
    $balance_query->execute();
    $result = $balance_query->get_result();
    $balance_row = $result->fetch_assoc();
    echo json_encode(['balance' => $balance_row['balance']]);
} else {
    echo json_encode(['error' => 'Invalid supplier ID']);
}

$conn->close();
?>
