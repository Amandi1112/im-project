<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['position'] != 'admin') {
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
    <style>
        /* CSS for dropdown menu */
        nav ul li {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
        
        .dropdown-menu a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
        }
        
        .dropdown-menu a:hover {
            background-color: rgb(229, 178, 154);
        }
        
        /* Show dropdown menu on hover */
        nav ul li:hover .dropdown-menu {
            display: block;
        }
        
        /* Override container styles to reduce margins */
        .container {
            max-width: 95%;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="heading">T&C Co-op City Shop</div>
            <nav>
                <ul>
                    <li><a href="home.php" class="active">Home</a></li>
                    <li>
                        <a href="#">Employee</a>
                        <div class="dropdown-menu">
                            <a href="personal_detail.php">Personal Details</a>
                            <a href="educational_background.php">Educational Background</a>
                            <a href="work_experience.php">Work Experience</a>
                        </div>
                    </li>
                    <li>
                        <a href="#">Members</a>
                        <div class="dropdown-menu">
                            <a href="member.php">Bank Memberships</a>
                            <a href="member_registration.php">Member Registration</a>
                            <a href="purchases.php">Purchases</a>
                        </div>
                    </li>
                    <li><a href="#">Sales Reports</a></li>
                    <li><a href="#">Inventory Reports</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <div class="user-info">
                    Welcome! : <?php echo $_SESSION['user_name']; ?>
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
            <p>© 2025 T&C Corporative City Shop. All rights reserved.</p>
        </footer>
    </div>
    
    <!-- Add popup script -->
    <script src="popup.js"></script>
</body>
</html>