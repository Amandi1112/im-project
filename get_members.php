<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite"; // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Get search query
$query = isset($_GET['q']) ? $_GET['q'] : '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

// Search in members table
$sql = "SELECT id, full_name, coop_number, bank_membership_number 
        FROM members 
        WHERE id LIKE ? 
           OR full_name LIKE ? 
           OR coop_number LIKE ? 
           OR bank_membership_number LIKE ?
        LIMIT 10";

$search_term = "%$query%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

echo json_encode($members);
$conn->close();
?>