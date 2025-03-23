<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['position'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection details (replace with your actual credentials)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Colombo'); // e.g., 'America/New_York'

// Get current date and time
$currentDate = date('F j, Y'); // Format: March 20, 2025
$currentTime = date('g:i A');  // Format: 3:45 PM

// Get the total number of members
$sql = "SELECT COUNT(*) AS total_members FROM members";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalMembers = $row["total_members"];
} else {
    $totalMembers = 0; // Default value if no members found
}

//Get the total number of suppliers
$sql = "SELECT COUNT(*) AS total_suppliers FROM supplier";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalSuppliers = $row["total_suppliers"];
} else {
    $totalSuppliers = 0;
}

// For demonstration purposes - Add some dummy data for dashboard metrics
$monthlySales = 24850;
$totalInventoryItems = 342;
$recentPurchases = 15;

// Get current date for dashboard
$currentDate = date("F j, Y");
$currentTime = date("h:i A");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T&C Co-op City Shop - Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* General Styles */
        :root {
            --primary-color: rgb(82, 31, 3);
            --primary-light: rgba(93, 11, 97, 0.1);
            --secondary-color: rgb(227, 121, 132);
            --accent-color:rgb(78, 154, 241);
            --text-main: #2c3e50;
            --text-secondary: #5d6778;
            --background-light: rgba(255, 255, 255, 0.95);
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 0;
            background: url('images/background60.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
            min-height: 100vh;
        }

        .container {
            padding: 20px;
            padding-bottom: 100px; /* Ensure content doesn't overlap footer */
        }

        /* Header Styles */
        .header {
            background-color: var(--background-light);
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        .header-logo {
            height: 50px;
            margin-right: 15px;
        }

        .user-menu {
            display: flex;
            align-items: center;
        }

        .user-info {
            margin-right: 20px;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .logout-btn {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background-color: #c82333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .date-display {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-align: right;
        }

        /* Navigation Bar */
        .navbar {
            background-color: var(--background-light);
            border-radius: 8px;
            box-shadow: var(--shadow-light);
            margin-bottom: 30px;
            padding: 0;
        }

        .navbar-nav .nav-link {
            color: var(--text-main);
            font-weight: 500;
            padding: 15px 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link:focus {
            color: var(--primary-color);
            background-color: var(--primary-light);
        }

        .navbar-nav .active {
            color: var(--primary-color);
        }
        
        .navbar-nav .active:after {
            content: '';
            position: absolute;
            width: 80%;
            height: 3px;
            background-color: var(--primary-color);
            bottom: 0;
            left: 10%;
            border-radius: 10px 10px 0 0;
        }

        .dropdown-menu {
            border: none;
            box-shadow: var(--shadow-medium);
            border-radius: 5px;
        }

        .dropdown-item {
            color: var(--text-main);
            padding: 12px 20px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        /* Dashboard Grid and Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .dashboard-full-width {
            grid-column: 1 / -1;
        }

        .card {
            background-color: var(--background-light);
            border: none;
            border-radius: 8px;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .card-header i {
            margin-right: 10px;
        }

        .card-body {
            padding: 25px;
        }

        .card-body p {
            color: var(--text-secondary);
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .card-body a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-block;
            margin-top: 5px;
        }

        .card-body a:hover {
            color: rgb(150, 30, 155);
            text-decoration: underline;
        }

        .card-body ul {
            padding-left: 20px;
            margin-bottom: 0;
        }

        .card-body li {
            margin-bottom: 10px;
        }

        /* Stats Display */
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .metrics-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .metric-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            flex: 1;
            min-width: 200px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            background-color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .metric-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--accent-color);
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }
        
        .metric-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        /* Overview Section */
        .welcome-banner {
            background: linear-gradient(45deg, var(--primary-color),rgb(244, 146, 66));
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-content {
            position: relative;
            z-index: 1;
        }
        
        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .welcome-text {
            font-size: 1rem;
            max-width: 800px;
            margin-bottom: 0;
        }
        
        .welcome-decoration {
            position: absolute;
            right: 30px;
            bottom: -20px;
            font-size: 8rem;
            opacity: 0.1;
            color: white;
        }
        
        /* Activity Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        a
        .timeline:before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-dot {
            position: absolute;
            left: -30px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: var(--accent-color);
            top: 2px;
        }
        
        .timeline-content {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .timeline-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .timeline-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .timeline-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0;
        }
        
        /* Quick Action Buttons */
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-btn {
            background-color: var(--background-light);
            color: var(--primary-color);
            border: 1px solid var(--primary-light);
            border-radius: 5px;
            padding: 8px 15px;
            display: inline-flex;
            align-items: center;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .action-btn i {
            margin-right: 5px;
        }
        
        .action-btn:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        /* Footer Styles */
        .footer {
            text-align: center;
            padding: 20px;
            background-color: var(--background-light);
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.08);
            color: var(--text-secondary);
            border-radius: 8px 8px 0 0;
            position: absolute;
            bottom: 0;
            width: calc(100% - 40px);
            margin: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-links {
            display: flex;
            gap: 15px;
        }
        
        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
        }

        /* Media Queries */
        @media (max-width: 992px) {
            .navbar-nav .nav-link {
                padding: 12px 15px;
            }
            
            .metrics-container {
                flex-direction: column;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .user-menu {
                margin-top: 15px;
            }
            
            .date-display {
                position: static;
                text-align: center;
                margin-top: 10px;
            }
            
            .footer {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="d-flex align-items-center">
                <div class="user-avatar d-flex align-items-center justify-content-center">
                    <i class="fas fa-store"></i>
                </div>
                <div class="header-title">T&C Co-op City Shop</div>
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div>Welcome, <?php echo $_SESSION['user_name']; ?></div>
                        <small class="text-muted">Administrator</small>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </header>

        <div class="welcome-banner">
            <div class="welcome-content">
                <h2 class="welcome-title">Welcome to the Admin Dashboard</h2>
                <p class="welcome-text">Manage your co-op shop operations efficiently. Check the latest statistics and access important features quickly.</p>
                <div class="mt-3">
                    <span class="badge bg-light text-dark me-2">
                        <i class="far fa-calendar me-1"></i><?php echo $currentDate; ?>
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="far fa-clock me-1"></i><?php echo $currentTime; ?>
                    </span>
                </div>
            </div>
            <div class="welcome-decoration">
                <i class="fas fa-chart-bar"></i>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavDropdown">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="home.php">
                                <i class="fas fa-home me-1"></i>Home
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-tie me-1"></i>Employee
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="personal_detail.php">Personal Details</a></li>
                                <li><a class="dropdown-item" href="educational_background.php">Educational Background</a></li>
                                <li><a class="dropdown-item" href="work_experience.php">Work Experience</a></li>
                            </ul>
                        </li>
                        

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-truck me-1"></i>Suppliers
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="supplier.php">Registration</a></li>
                                <li><a class="dropdown-item" href="supplier_purchases.php">Item Purchases</a></li>
                                <li><a class="dropdown-item" href="supplier_payments.php">Handling Bills</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-box-open me-1"></i>Inventory
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="manage_categories_items.php">Add New Categories</a></li>
                                <li><a class="dropdown-item" href="safety_stock.php">Safety Stock</a></li>
                                <li><a class="dropdown-item" href="inventory_mgt.php">Manage Inventory</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-users me-1"></i>Members
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="member.php">Bank Memberships</a></li>
                                <li><a class="dropdown-item" href="member_registration.php">Member Registration</a></li>
                                <li><a class="dropdown-item" href="customer_purchases.php">Member Purchases</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-chart-line me-1"></i>Reports
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="customer_credit.php">Credit Balance Reports</a></li>
                                <li><a class="dropdown-item" href="report2.php">Inventory Report</a></li>
                                <li><a class="dropdown-item" href="supplier_performance.php">Supplier Performance Report</a></li>
                                <li><a class="dropdown-item" href="supplier_payment_report.php">Supplier Payment Reports</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-pie me-2"></i>Business Overview
            </div>
            <div class="card-body">
                <div class="metrics-container">
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="metric-value"><?php echo $totalMembers; ?></div>
                        <div class="metric-label">Active Members</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="metric-value"><?php echo $totalSuppliers; ?></div>
                        <div class="metric-label">Active Suppliers</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <div class="metric-value">$<?php echo number_format($monthlySales); ?></div>
                        <div class="metric-label">Monthly Sales</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="metric-value"><?php echo $totalInventoryItems; ?></div>
                        <div class="metric-label">Inventory Items</div>
                    </div>
                </div>
                
                <div class="quick-actions mt-4">
                    <a href="report1.php" class="action-btn">
                        <i class="fas fa-file-alt"></i> View Sales Report
                    </a>
                    <a href="report2.php" class="action-btn">
                        <i class="fas fa-warehouse"></i> Check Inventory
                    </a>
                    <a href="member_registration.php" class="action-btn">
                        <i class="fas fa-user-plus"></i> Register Member
                    </a>
                    <a href="supplier.php" class="action-btn">
                        <i class="fas fa-truck-loading"></i> Add Supplier
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-shield me-2"></i>Administrator Profile
                </div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?php echo $_SESSION['user_email']; ?></p>
                    <p><strong>Security:</strong> <i class="fas fa-lock"></i> Password Protected</p>
                    <p><strong>Last Login:</strong>
                    <span>
                        <i class="far fa-clock me-1"></i><?php echo $currentTime; ?>
                    </span></p>
                    
                    <div class="d-grid gap-2 mt-3">
                        <a href="reset_password.php" class="btn btn-outline-primary">
                            <i class="fas fa-key me-1"></i> Change Password
                        </a>
                        <a href="reset_password.php" class="btn btn-outline-secondary">
                            <i class="fas fa-pen me-1"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tasks me-2"></i>Providing Benifits
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-time">...</div>
                                <div class="timeline-title">Best Prices & Offers</div>
                                <div class="timeline-text">Providing items with lowest prices to the members in credit basis</div>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-time">...</div>
                                <div class="timeline-title">Wide Assortment</div>
                                <div class="timeline-text"> Choose from a variety of products from branded and chilled. New products added monthly!</div>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="timeline-time">...</div>
                                <div class="timeline-title">Satisfying with fastest management</div>
                                <div class="timeline-text">Manage the selling goods faster make your customer happy!!</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-link me-2"></i>Quick Access
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="display_coop_number.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-users me-3 text-primary"></i>
                            <div>
                                <div class="fw-bold">Members Directory</div>
                                <small class="text-muted">View and manage co-op members</small>
                            </div>
                        </a>
                        <a href="display_registered_suppliers.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-truck me-3 text-success"></i>
                            <div>
                                <div class="fw-bold">Supplier Database</div>
                                <small class="text-muted">Manage supplier relationships</small>
                            </div>
                        </a>
                        <a href="manage_categories_items.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-tags me-3 text-warning"></i>
                            <div>
                                <div class="fw-bold">Categories & Items</div>
                                <small class="text-muted">Manage product inventory</small>
                            </div>
                        </a>
                        <a href="report1.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-chart-line me-3 text-danger"></i>
                            <div>
                                <div class="fw-bold">Sales Reports</div>
                                <small class="text-muted">View financial performance</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div>&copy; 2025 T&C Co-op City Shop. All rights reserved.</div>
        <div class="footer-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Help Center</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>