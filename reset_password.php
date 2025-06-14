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
 <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg,rgb(214, 217, 231) 0%,rgb(215, 177, 254) 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .form-logo {
            
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            margin-left: 110px;
            border: 3px solid rgba(102, 126, 234, 0.3);
        }

        .reset-password-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
         background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            text-align: left;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .reset-password-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .reset-password-container label {
            margin-bottom: 20px;
            text-align: left;
             display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        .reset-password-container:hover{
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }

        .reset-password-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .reset-password-container input:focus{
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .reset-password-container button {
            
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .reset-password-container button:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.6);
        }
        .reset-password-container button:active{
            transform: translateY(0);
        }

        .reset-password-container .back-to-login {
            margin-top: 10px;
        }

        .reset-password-container p {
            text-align: center;
            color: #000000;
            font-size: 14px;
        }
        
        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ff4757;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: translateX(150%);
            transition: transform 0.4s ease;
            z-index: 1000;
        }
        
        .floating-alert.show {
            transform: translateX(0);
        }
    </style>
    </head>

    <div class="reset-password-container">
        <img src="images/logo.jpeg" alt="Logo" class="form-logo" id="logo" style="text-align: center;">
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
            <br>
        </form>
        <br>
        <p>Enter your email and new password to reset your account.</p>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // Add animation to logo on hover
        logo.addEventListener('mouseenter', function() {
                this.style.transform = 'rotate(10deg) scale(1.1)';
                this.style.transition = 'all 0.3s ease';
            });
            
            logo.addEventListener('mouseleave', function() {
                this.style.transform = 'rotate(0) scale(1)';
            });
        // Function to show notifications
        function showNotification(message, type) {
            // Check if alert element exists, if not create one
            let alertBox = document.getElementById('alert');
            if (!alertBox) {
                alertBox = document.createElement('div');
                alertBox.id = 'alert';
                alertBox.className = 'floating-alert';
                document.body.appendChild(alertBox);
                
                // Add CSS if not already present
                if (!document.querySelector('style#alert-style')) {
                    const style = document.createElement('style');
                    style.id = 'alert-style';
                    style.textContent = `
                        .floating-alert {
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background: #ff4757;
                            color: white;
                            padding: 15px 25px;
                            border-radius: 8px;
                            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                            transform: translateX(150%);
                            transition: transform 0.4s ease;
                            z-index: 1000;
                        }
                        
                        .floating-alert.show {
                            transform: translateX(0);
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
            
            alertBox.textContent = message;
            
            // Set color based on type
            switch(type) {
                case 'error':
                    alertBox.style.background = '#ff4757';
                    break;
                case 'success':
                    alertBox.style.background = '#2ed573';
                    break;
                case 'info':
                    alertBox.style.background = '#1e90ff';
                    break;
            }
            
            alertBox.classList.add('show');
            
            // Hide after 3 seconds
            setTimeout(() => {
                alertBox.classList.remove('show');
            }, 3000);
        }
        
        // Check URL parameters for notifications
        const params = new URLSearchParams(window.location.search);
        
        if (params.has('error')) {
            showNotification(params.get('error'), 'error');
        }
        
        if (params.has('success')) {
            showNotification(params.get('success'), 'success');
        }
    });
</script>
</body>
</html>