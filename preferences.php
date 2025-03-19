<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection details (VERY IMPORTANT: Replace with your actual credentials)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Default values for preferences
$default_theme = 'light';
$default_language = 'en';

// Fetch user preferences from the database, if they exist
$sql = "SELECT theme, language FROM user_preferences WHERE user_id = '$user_id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $theme = htmlspecialchars($row["theme"]);
    $language = htmlspecialchars($row["language"]);
} else {
    // Use default values if no preferences are found
    $theme = $default_theme;
    $language = $default_language;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_theme = $conn->real_escape_string($_POST["theme"]);
    $new_language = $conn->real_escape_string($_POST["language"]);

    // Validate data (add more validation as needed)
    if (empty($new_theme) || empty($new_language)) {
        $error_message = "Please select all options.";
    } else {
        // Check if user preferences already exist
        $check_sql = "SELECT user_id FROM user_preferences WHERE user_id = '$user_id'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            // Update existing preferences
            $update_sql = "UPDATE user_preferences SET theme = '$new_theme', language = '$new_language' WHERE user_id = '$user_id'";
            if ($conn->query($update_sql) === TRUE) {
                $success_message = "Preferences updated successfully!";
                $theme = $new_theme;
                $language = $new_language;
            } else {
                $error_message = "Error updating preferences: " . $conn->error;
            }
        } else {
            // Insert new preferences
            $insert_sql = "INSERT INTO user_preferences (user_id, theme, language) VALUES ('$user_id', '$new_theme', '$new_language')";
            if ($conn->query($insert_sql) === TRUE) {
                $success_message = "Preferences saved successfully!";
                $theme = $new_theme;
                $language = $new_language;
            } else {
                $error_message = "Error saving preferences: " . $conn->error;
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Preferences</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h1>User Preferences</h1>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="mb-3">
                <label for="theme" class="form-label">Theme:</label>
                <select class="form-select" id="theme" name="theme">
                    <option value="light" <?php if ($theme == 'light') echo 'selected'; ?>>Light</option>
                    <option value="dark" <?php if ($theme == 'dark') echo 'selected'; ?>>Dark</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="language" class="form-label">Language:</label>
                <select class="form-select" id="language" name="language">
                    <option value="en" <?php if ($language == 'en') echo 'selected'; ?>>English</option>
                    <option value="fr" <?php if ($language == 'fr') echo 'selected'; ?>>French</option>
                    <!-- Add more languages as needed -->
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Save Preferences</button>
        </form>
    </div>
</body>
</html>
