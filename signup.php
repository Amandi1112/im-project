<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background: url(images/background60.jpg)">
    <div class="container">
        <div class="form-container">
            <h2>Create an Account</h2>
            <form action="signup_process.php" method="post">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group1">
                    <label for="position">Position</label>
                    <select id="position" name="position" required>
                        <option value="" disabled selected>Select your position</option>
                        <option value="accountant">Accountant</option>
                        <option value="clerk">Clerk</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="form-button">
                    <button type="submit" name="signup">Sign Up</button>
                </div>
                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add popup script -->
    <script src="popup.js"></script>
</body>
</html>