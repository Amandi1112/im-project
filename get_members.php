<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

$conn = new mysqli($servername, $username, $password, $dbname);

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
$sql = "SELECT id, full_name, bank_membership_number 
        FROM members 
        WHERE id LIKE ? 
           OR full_name LIKE ? 
           OR bank_membership_number LIKE ?
        LIMIT 10";

$search_term = "%$query%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $search_term, $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

header('Content-Type: application/json');
echo json_encode($members);

$conn->close();
?>