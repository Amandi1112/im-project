<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "mywebsite");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete request
if(isset($_GET['delete'])) {
    $email = $_GET['delete'];
    // Use prepared statement to prevent SQL injection
    $delete_sql = "DELETE FROM personal_details WHERE email = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("s", $email);
    
    if($stmt->execute()) {
        // Set success message in session
        session_start();
        $_SESSION['message'] = "<div class='success-message'>Record deleted successfully!</div>";
        // Redirect to the same page to refresh the table
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        session_start();
        $_SESSION['message'] = "<div class='error-message'>Error deleting record: " . $conn->error . "</div>";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}

// Display message if exists
session_start();
if(isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    unset($_SESSION['message']);
}

// Check if there's a form submission to process
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];
    $marital_status = $_POST['marital_status'];
    $date_of_birth = $_POST['date_of_birth'];
    $address = isset($_POST['address']) ? $_POST['address'] : '';
    $religion = $_POST['religion'];
    $nic = $_POST['nic'];
    $contact_number = $_POST['contact_number'];
    $spouse_name = $_POST['spouse_name'];
    
    // Check for duplicate email
    $check_sql = "SELECT email FROM personal_details WHERE email = '$email'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        echo "<div class='error-message'>Error: Email already exists in the database!</div>";
    } else {
        // SQL to insert data
        $sql = "INSERT INTO personal_details (full_name, email, gender, age, marital_status, date_of_birth, address, religion, nic, contact_number, spouse_name)
                VALUES ('$full_name', '$email', '$gender', '$age', '$marital_status', '$date_of_birth', '$address', '$religion', '$nic', '$contact_number', '$spouse_name')";
        
        if ($conn->query($sql) === TRUE) {
            echo "<div class='success-message'>Employee data saved successfully!</div>";
        } else {
            echo "<div class='error-message'>Error: " . $conn->error . "</div>";
        }
    }
}

// Fetch all employee records
$sql = "SELECT * FROM personal_details ORDER BY full_name DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Records</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-image: url('images/background2.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        h2 {
            text-align: center;
            color: #fff;
            font-size: 2em;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.8), 0 0 20px rgba(255, 255, 255, 0.6);
        }
        .container {
            max-width: 1200px;
            margin: auto;
            position: relative; /* Added for positioning the home button */
        }
        td {
            color: black; /* Changed table data color to white */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 5px solid white;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: rgb(94, 94, 94);
            color: white; /* Changed table heading color to white */
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color:rgb(62, 62, 62);
        }
        .success-message {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .error-message {
            background-color: #f2dede;
            color: #a94442;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .btn {
            background-color:rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        .confirm-dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
            justify-content: center;
            align-items: center;
        }
        .confirm-box {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            max-width: 400px;
        }
        .confirm-buttons {
            margin-top: 15px;
        }
        .confirm-btn, .cancel-btn {
            padding: 8px 15px;
            margin: 0 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .confirm-btn {
            background-color: #f44336;
            color: white;
        }
        .cancel-btn {
            background-color: #ccc;
        }
        /* New styles for home button */
        .home-btn {
            background-color:rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            position: absolute;
            top: 0;
            right: 0;
            margin: 10px;
        }
        .home-btn:hover {
            background-color: #0056b3;
        }
        .header-container {
            position: relative;
            margin-bottom: 30px;
        }
    </style>
    <script>
        function confirmDelete(email) {
            if(confirm("Are you sure you want to delete this record?")) {
                window.location.href = "?delete=" + encodeURIComponent(email);
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h2>Employee Records</h2>
            <a href="home.php" class="home-btn">Home</a>
        </div>
        
        <div class="actions">
            <a href="employee.php" class="btn">Add New Employee</a>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Marital Status</th>
                        <th>Date of Birth</th>
                        <th>Address</th>
                        <th>Religion</th>
                        <th>NIC</th>
                        <th>Contact Number</th>
                        <th>Spouse Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row["full_name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["gender"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["age"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["marital_status"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["date_of_birth"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["address"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["religion"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["nic"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["contact_number"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["spouse_name"]) . "</td>";
                            echo "<td><button class='delete-btn' onclick='confirmDelete(\"" . htmlspecialchars($row["email"]) . "\")'>Delete</button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='12' style='text-align:center;'>No records found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>