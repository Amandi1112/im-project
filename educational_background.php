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

    try {
        if ($stmt->execute()) {
            // Redirect to education_details.php after successful submission
            header("Location: education_details.php");
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        // Check if the error is due to duplicate entry (error code 1062)
        if ($e->getCode() == 1062) {
            echo "<script>alert('This email is already in use. Please use a different email.');</script>";
        } else {
            echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
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
            background: url("images/background60.jpg");
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f8ff;
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto 30px auto; /* Centered with bottom margin */
            text-align: center; /* Center the h2 */
        }
        .header-container {
            max-width: 1200px;
            margin: auto;
            position: relative; /* Added for positioning the home button */
            position: relative;
            margin-bottom: 30px;
        }
        form {
            max-width: 500px;
            margin: 0 auto 40px auto; /* Adjusted margin to space above buttons */
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
        .submit-btn-container {
            text-align: center; /* Center the submit button */
            margin-bottom: 20px; /* Space before additional buttons */
        }
        .submit-btn {
            background-color: rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 15px;
            max-width: 200px; /* Optional: Limit width for better appearance */
        }
        .submit-btn:hover {
            background-color: #f28252;
        }
        .nav-btn-container {
            text-align: center; /* Center the navigation buttons */
        }
        .home-btn, .current-emp-btn {
            background-color: rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            margin: 0 5px; /* Space between buttons */
        }
        .home-btn:hover, .current-emp-btn:hover {
            background-color: #f28252;
        }
    </style>
</head>
<body>
    <div class="header-container">
    <h2 style="text-align: center; font-weight: bold; color: black; font-size: 2.5em; text-shadow: 2px 2px 5px lightblue; margin-top: 70px;">Education Details</h2>
    </div>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="email-form">
        <label for="email">Email Address:</label>
        <select name="email" id="email" required>
            <option value="">Select Email</option>
            <?php
            // Fetch email addresses from the users table
            $result = $conn->query("SELECT email FROM users");
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<option value='" . htmlspecialchars($row['email']) . "'>" . htmlspecialchars($row['email']) . "</option>";
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
        
        <div class="submit-btn-container">
            <button type="submit" class="submit-btn">Submit</button>
        </div>
    </form>
    
    <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Home</a>
        <a href="education_details.php" class="current-emp-btn">Current Employee</a>
    </div>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>