<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['position'] != 'client') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="heading">T&C Co-op City Shop</div>
            <nav>
                <ul>
                    <li><a href="client_dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="orders.php">My Orders</a></li>
                    <li><a href="products.php">Products</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <div class="user-info">
                    Welcome, Client: <?php echo $_SESSION['user_name']; ?>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        
        <main>
            <div class="welcome-section">
                <h1>Client Dashboard</h1>
                <p>Welcome to your client dashboard. Here you can manage your orders and view products.</p>
            </div>
            
            <div class="content-section">
                <!-- Client-specific content here -->
            </div>
        </main>
        
        <footer>
            <p>&copy; 2025 T&C Corporative City Shop. All rights reserved.</p>
        </footer>
    </div>
    
    <script src="popup.js"></script>
</body>
</html>