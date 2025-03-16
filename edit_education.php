<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "mywebsite");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the email from URL
$email = isset($_GET['email']) ? $_GET['email'] : null;

// Handle form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $qualification = $_POST['qualification'];
    $institute = $_POST['institute'];
    $study_duration = $_POST['study_duration'];

    // Update query now uses email as the identifier
    $stmt = $conn->prepare("UPDATE education_details SET qualification=?, institute=?, study_duration=? WHERE email=?");
    $stmt->bind_param("ssss", $qualification, $institute, $study_duration, $email);

    if ($stmt->execute()) {
        header("Location: education_details.php");
        exit();
    } else {
        echo "<script>alert('Error updating record: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Fetch existing record
if ($email) {
    $stmt = $conn->prepare("SELECT * FROM education_details WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Education Details</title>
    <style>
        body {
            background:url(images/background2.jpg);
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
    <h2 style="text-align: center; color: black;">Edit Education Details</h2>
    
    <form method="POST" action="">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($row['email']); ?>">
        
        <label for="email">Email Address:</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($row['email']); ?>" readonly required>
        <!-- Made readonly since email is the primary key and shouldn't be changed here -->

        <label for="qualification">Qualification:</label>
        <input type="text" name="qualification" id="qualification" value="<?php echo htmlspecialchars($row['qualification']); ?>" required>

        <label for="institute">Institute:</label>
        <input type="text" name="institute" id="institute" value="<?php echo htmlspecialchars($row['institute']); ?>" required>

        <label for="study_duration">Study Duration:</label>
        <input type="text" name="study_duration" id="study_duration" value="<?php echo htmlspecialchars($row['study_duration']); ?>" required>

        <button type="submit">Update</button>
    </form>
</body>
</html>

<?php
$conn->close();
?>