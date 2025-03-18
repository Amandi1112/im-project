<?php
// Database connection details
$servername = "localhost"; // Replace with your server name
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "mywebsite"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Delete Record
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Use a prepared statement to safely delete the record
    $stmt = $conn->prepare("DELETE FROM membership_numbers WHERE membership_number = ?");
    $stmt->bind_param("s", $id); // Bind the parameter

    if ($stmt->execute()) {
        $success_message = "Record deleted successfully!";
    } else {
        $error_message = "Error deleting record: " . $stmt->error;
    }

    $stmt->close(); // Close the prepared statement
}

// Initialize variables
$search_term = "";
$where_clause = "";

// Handle Search Functionality
if (isset($_GET['search']) && !empty($_GET['nic_search'])) {
    $search_term = $_GET['nic_search'];
    $where_clause = " WHERE nic_number LIKE ?";
}

// Prepare the SQL query
$sql = "SELECT * FROM membership_numbers" . $where_clause;
$stmt = $conn->prepare($sql);

// If searching, bind the parameter
if ($where_clause) {
    $search_param = "%" . $search_term . "%";
    $stmt->bind_param("s", $search_param);
}

// Execute the query
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Numbers</title>
    <style>
        body {
            background: url(images/background2.jpg);
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #ddd;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        /* Professional Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            animation: slideIn 0.3s forwards;
        }
        .notification-success {
            background-color: #e3f8e5;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .notification-error {
            background-color: #fae3e5;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .notification-icon {
            margin-right: 15px;
            font-size: 20px;
        }
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .home-btn {
            background-color:rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            top: 0;
            right: 0;
        }
        .home-btn:hover {
            background-color: #f28252;
        }
        /* Search Form Styles */
        .search-container {
            margin: 20px 0;
            display: flex;
            justify-content: center;
        }
        .search-form {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 600px;
        }
        .search-input {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-btn:hover {
            background-color: #45a049;
        }
        .reset-btn {
            background-color: #f0ad4e;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .reset-btn:hover {
            background-color: #ec971f;
        }
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1 style="text-align: center; font-weight: bold; color: black; font-size: 2.5em; text-shadow: 2px 2px 5px lightblue; margin-top: 70px;">Membership Details</h1>
    
    <div class="header-container">
        <a href="home.php" class="home-btn">Home</a>
    </div>
    
    <!-- Search Form -->
    <div class="search-container">
        <form class="search-form" method="GET" action="">
            <input type="text" name="nic_search" class="search-input" placeholder="Search by NIC Number..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" name="search" class="search-btn">Search</button>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="reset-btn">Reset</a>
        </form>
    </div>
    
    <!-- Display Success or Error Messages -->
    <?php
    if (isset($success_message)) {
        echo '<div class="notification notification-success" id="notification">
                <div class="notification-icon">✓</div>
                <div class="notification-content">' . $success_message . '</div>
              </div>';
    }
    if (isset($error_message)) {
        echo '<div class="notification notification-error" id="notification">
                <div class="notification-icon">✕</div>
                <div class="notification-content">' . $error_message . '</div>
              </div>';
    }
    ?>

    <!-- Display Records -->
    <table>
        <tr>
            <th>Membership Number</th>
            <th>NIC Number</th>
            <th>Created At</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>" . $row["membership_number"] . "</td>
                        <td>" . $row["nic_number"] . "</td>
                        <td>" . $row["created_at"] . "</td>
                        <td>
                            <a href='?delete=" . $row["membership_number"] . "' class='delete-btn'>Delete</a>
                        </td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No records found.</td></tr>";
        }
        ?>
    </table>

    <!-- JavaScript to Auto-Remove Notification -->
    <script>
        // Function to remove the notification after a delay
        function removeNotification() {
            const notification = document.getElementById('notification');
            if (notification) {
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 3000); // 3 seconds delay
            }
        }

        // Call the function when the page loads
        window.onload = removeNotification;
    </script>
</body>
</html>

<?php
// Close the connection
$stmt->close();
$conn->close();
?>