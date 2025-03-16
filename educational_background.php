<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "mywebsite");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $qualification = $_POST['qualification'];
    $institute = $_POST['institute'];
    $study_duration = $_POST['study_duration'];

    // Use prepared statements for security
    $stmt = $conn->prepare("INSERT INTO education_details (email, qualification, institute, study_duration) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $qualification, $institute, $study_duration);

    if ($stmt->execute()) {
        echo "<script>alert('Education details saved successfully!');</script>";
    } else {
        echo "<script>alert('Error: " . $stmt->error . "');</script>";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Education Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f8ff;
        }
        form {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background-color: #f28252;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center; color: black;">Education Details</h2>
    <form method="POST" action="">

        <label for="email">Email Address:</label>
        <select name="email" id="email" required>
            <option value="">Select Email</option>
            <?php
            // Fetch email addresses from the users table
            $result = $conn->query("SELECT email FROM users");
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<option value='" . $row['email'] . "'>" . $row['email'] . "</option>";
                }
            } else {
                echo "<option value=''>No users found</option>";
            }
            ?>
        </select>

        <label for="qualification">Qualification:</label>
        <input type="text" name="qualification" id="qualification" required>

        <label for="institute">Institute:</label>
        <input type="text" name="institute" id="institute" required>

        <label for="study_duration">Study Duration:</label>
        <input type="text" name="study_duration" id="study_duration" required>

        
        <button type="button" class="button" onclick="window.location.href='education_details.php';">submit</button>

    </form>
</body>
</html>
