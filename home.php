<?php
    session_start();
    if(!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="heading">T&C Co-op City Shop</div>
            <nav>
                <ul>
                    <li><a href="home.php" class="active">Home</a></li>
                    <li><a href="employee.php">Employee</a></li>
                    <li><a href="#">Services</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <div class="user-info">
                    Welcome!  :  <?php echo $_SESSION['user_name']; ?>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        
        <main>
            <div class="welcome-section">
                <h1>Welcome to the Homepage</h1>
                <p>You've successfully logged in. This is your personalized homepage.</p>
            </div>
            
            <div class="content-section">
                <div class="card">
                    <h3>Your Profile</h3>
                    <p>Email: <?php echo $_SESSION['user_email']; ?></p>
                    <a href="#" class="btn">Edit Profile</a>
                </div>
                
                <div class="card">
                    <h3>Recent Activity</h3>
                    <p>No recent activity to display.</p>
                </div>
                
                <div class="card">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#">My Settings</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2025 T&C Corporative City Shop. All rights reserved.</p>
        </footer>
    </div>
    
    <!-- Add popup script -->
    <script src="popup.js"></script>
</body>
</html>