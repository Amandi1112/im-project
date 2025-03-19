<?php
// Include database connection
require_once 'db_connection.php';

// Check if the database connection is established
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        echo "<script>alert('All fields required.');</script>";
    } elseif ($new_password !== $confirm_password) {
        echo "<script>alert('Password do not match.');</script>";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update the password in the database
        $sql = "UPDATE users SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $hashed_password, $email);

        $execution_result = $stmt->execute();
        if ($execution_result) {
            echo "<script>alert('password reset successfully!.');</script>";
        } else {
            echo "<script>alert('Error resetting password.');</script>";
        }
        
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('images/background60.jpg');
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        

        .reset-password-container {
            background-color: #d5731846;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .reset-password-container h2 {
            margin-bottom: 20px;
            color:rgb(0, 0, 0);
            text-align: center;
        }

        .reset-password-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color:rgb(0, 0, 0);
        }

        .reset-password-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .reset-password-container button {
            width: 100%;
            padding: 10px;
            background-color: rgb(135, 74, 0);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .reset-password-container button:hover {
            background-color: #f28252;
        }

        .reset-password-container .back-to-login {
            margin-top: 10px;
        }

        .reset-password-container p {
            text-align: center;
            color: #000000;
            font-size: 14px;
        }
    </style>

    <div class="reset-password-container">
        <h2>Reset Password</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>

            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required>

            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <button type="submit">Reset Password</button>
            <button type="button" class="back-to-login">
                <a href="login.php" style="text-decoration: none; color: rgb(135, 74, 0); color:white">Back to login</a>
            </button>
        </form>
        <p>Enter your email and new password to reset your account.</p>
    </div>
</body>
</html>