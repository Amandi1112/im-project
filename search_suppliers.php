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

if (isset($_GET['term'])) {
    $term = $_GET['term'];
    $query = $conn->prepare("SELECT supplier_name as label, supplier_id as value FROM supplier WHERE supplier_name LIKE ?");
    $term = '%' . $term . '%';
    $query->bind_param("s", $term);
    $query->execute();
    $result = $query->get_result();
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    echo json_encode($suppliers);
} else {
    echo json_encode([]);
}

$conn->close();
?>
