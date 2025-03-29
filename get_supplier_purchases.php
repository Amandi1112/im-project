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

    $purchase_query = $conn->prepare("
        SELECT ip.purchase_date, i.item_name, ip.quantity, ip.price_per_unit, ip.total_price
        FROM item_purchases ip
        JOIN items i ON ip.item_id = i.item_id
        WHERE i.supplier_id = ?
        ORDER BY ip.purchase_date
    ");
    $purchase_query->bind_param("s", $supplier_id);
    $purchase_query->execute();
    $result = $purchase_query->get_result();
    $purchases = [];
    while ($row = $result->fetch_assoc()) {
        $purchases[] = $row;
    }
    echo json_encode(['purchases' => $purchases]);
} else {
    echo json_encode(['error' => 'Invalid supplier ID']);
}

$conn->close();
?>
