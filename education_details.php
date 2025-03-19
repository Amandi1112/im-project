<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "mywebsite");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete request
if (isset($_GET['delete'])) {
    $email = $_GET['delete']; // Changed from id to email
    $stmt = $conn->prepare("DELETE FROM education_details WHERE email = ?");
    $stmt->bind_param("s", $email); // String parameter instead of integer
    $stmt->execute();
    $stmt->close();
    header("Location: education_details.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Education Details Table</title>
    
    <style>
        body {
            background:url("images/background60.jpg");
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f8ff;
        }
        table {
            width: 80%;
            margin: 90px auto;
            border-collapse: collapse;
            
            background-color: #d5731846;
    /* Changed color */
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: rgb(135, 74, 0);
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .edit-btn {
            background-color: #4CAF50;
            color: white;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        .edit-btn:hover {
            background-color: #45a049;
        }
        .delete-btn:hover {
            background-color: #da190b;
        }
        .header-container {
            max-width: 1200px;
            margin: auto;
            position: relative; /* Added for positioning the home button */
            position: relative;
            margin-bottom: 30px;
        }
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
        .home-btn:hover {
            background-color: #f28252;
        }
        
    </style>
</head>
<body>
<h2 style="text-align: center; font-weight: bold; color: black; font-size: 2.5em; text-shadow: 2px 2px 5px lightblue; margin-top: 70px;">Education Details</h2>
<div class="header-container">
            <a href="home.php" class="home-btn">Home</a>
            <a href="educational_background.php" class="btn">Add details</a>
            
        </div>
    <table>
        <thead>
            <tr>
                <th>Email</th> <!-- Changed from ID to Email -->
                <th>Qualification</th>
                <th>Institute</th>
                <th>Study Duration</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Fetch records ordered by email (no id column)
            $result = $conn->query("SELECT email, qualification, institute, study_duration FROM education_details ORDER BY email ASC");
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>"; // Replaced id with email
                    echo "<td>" . htmlspecialchars($row['qualification']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['institute']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['study_duration']) . "</td>";
                    echo "<td>";
                    echo "<button class='edit-btn' onclick=\"location.href='edit_education.php?email=" . urlencode($row['email']) . "'\">Edit</button>";
                    echo "<button class='delete-btn' onclick=\"if(confirm('Are you sure you want to delete this record?')) location.href='education_details.php?delete=" . urlencode($row['email']) . "'\">Delete</button>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align: center;'>No records found</td></tr>"; // Adjusted colspan to 5
            }
            ?>
        </tbody>
    </table>

</body>
</html>

<?php
$conn->close();
?>