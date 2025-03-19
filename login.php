<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background: url(images/background60.jpg)">
    <div class="container">
        <div class="form-container">
             <!-- Add the logo here -->
             <img src="images/logo.jpeg" alt="Logo" class="form-logo">
            <h2>Login</h2>
            <form action="login_process.php" method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-button">
                    <button type="submit" name="login">Login</button>
                </div>
                <div class="form-footer">
                    <p>Don't have an account? <a href="signup.php">Sign up</a></p>
                </div>
                <div class="form-footer">
                    <p>Forgot your password? <a href="reset_password.php">Reset password</a></p>
            </form>
        </div>
    </div>
    
    <!-- Add popup script -->
    <script src="popup.js"></script>
</body>
</html>