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

date_default_timezone_set('Asia/Colombo');

// Get current date and time
$currentDate = date('F j, Y');
$currentTime = date('g:i A');

// Get the total number of members
$sql = "SELECT COUNT(*) AS total_members FROM members";
$result = $conn->query($sql);
$totalMembers = ($result->num_rows > 0) ? $result->fetch_assoc()["total_members"] : 0;

// Get the total number of suppliers
$sql = "SELECT COUNT(*) AS total_suppliers FROM supplier";
$result = $conn->query($sql);
$totalSuppliers = ($result->num_rows > 0) ? $result->fetch_assoc()["total_suppliers"] : 0;

// Calculate the total inventory level
$sql = "SELECT SUM(current_quantity) AS current_quantity FROM items";
$result = $conn->query($sql);
$totalInventoryLevel = ($result->num_rows > 0) ? $result->fetch_assoc()["current_quantity"] : 0;

// Get recent activity (example)
$recentActivities = [
    ['icon' => 'fas fa-user-plus', 'title' => 'New Member', 'description' => '5 new members registered today', 'time' => '2 hours ago'],
    ['icon' => 'fas fa-truck', 'title' => 'Supplier Delivery', 'description' => 'Fresh goods delivered from Perera & Sons', 'time' => '4 hours ago'],
    ['icon' => 'fas fa-shopping-cart', 'title' => 'Sales Peak', 'description' => 'Evening sales reached 150 transactions', 'time' => '6 hours ago']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T&C Co-op City Shop - Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color:rgb(64, 86, 137);
            --primary-light: rgba(10, 36, 99, 0.1);
            --secondary-color: #3e92cc;
            --accent-color: #2ecc71;
            --danger-color: #ff6b6b;
            --warning-color: #ffbe0b;
            --text-main:rgb(0, 0, 0);
            --text-secondary: #6c757d;
            --background-light: rgba(255, 255, 255, 0.98);
            --shadow-light: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --border-radius: 12px;
            --gradient-primary: linear-gradient(135deg, var(--primary-color), #3a0ca3);
        }

        body {
            
            font-family: 'Poppins', sans-serif;
            color: var(--text-main);
            background: linear-gradient(135deg,rgb(208, 212, 232) 0%,rgb(223, 245, 254) 100%);
            background-image: radial-gradient(circle at 10% 20%, rgba(234, 249, 249, 0.67) 0%, rgba(239, 249, 251, 0.63) 90%);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
        }

        .container {
            padding: 20px;
            padding-bottom: 100px;
            max-width: 1600px;
        }

        /* Header Styles */
        .header {
            background-color: var(--background-light);
            padding: 20px 30px;
            margin-bottom: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInDown 0.6s both;
        }

        .header-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-logo {
            height: 50px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            box-shadow: 0 4px 8px rgba(10, 36, 99, 0.2);
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .logout-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(10, 36, 99, 0.2);
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #3a0ca3, var(--primary-color));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(10, 36, 99, 0.3);
        }

        /* Welcome Banner */
        .welcome-banner {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s both 0.2s;
            box-shadow: 0 10px 30px rgba(10, 36, 99, 0.2);
        }

        .welcome-content {
            position: relative;
            z-index: 2;
            max-width: 70%;
        }

        .welcome-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-text {
            font-size: 1.05rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .welcome-decoration {
            position: absolute;
            right: 30px;
            bottom: -30px;
            font-size: 10rem;
            opacity: 0.08;
            color: white;
            z-index: 1;
        }

        .time-badge {
            background-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            margin-right: 10px;
            transition: var(--transition);
        }

        .time-badge:hover {
            background-color: rgba(255, 255, 255, 0.25);
        }

        /* Navigation Bar */
        .navbar {
    background-color: var(--background-light);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    margin-bottom: 30px;
    padding: 0;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    z-index: 1000;
    animation: fadeIn 0.8s both 0.3s;
}

.navbar-nav .nav-link {
    color: var(--text-main);
    font-weight: 500;
    padding: 15px 20px;
    transition: var(--transition);
    position: relative;
    border-radius: 8px;
    margin: 0 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link:focus {
            color: var(--primary-color);
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .navbar-nav .active {
            color: white;
            background: var(--gradient-primary);
            box-shadow: 0 4px 15px rgba(10, 36, 99, 0.2);
        }

        .dropdown-menu {
    border: none;
    box-shadow: var(--shadow-medium);
    border-radius: var(--border-radius);
    padding: 10px 0;
    border: 1px solid rgba(0,0,0,0.05);
    z-index: 1050;
    position: absolute;
}

.dropdown-item {
    color: var(--text-main);
    padding: 10px 20px;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 10px;
}

        .dropdown-item:hover {
            background-color: var(--primary-light);
            color: var(--primary-color);
            transform: translateX(5px);
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
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInUp 0.6s both;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .card-header {
            background: var(--gradient-primary);
            color: white;
            padding: 18px 25px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        /* Stats Display */
        .metrics-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .metric-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .metric-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .metric-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .metric-label {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Activity Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline:before {
            content: '';
            position: absolute;
            left: 11px;
            top: 0;
            height: 100%;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            opacity: 0.2;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -30px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--gradient-primary);
            top: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.6rem;
            box-shadow: 0 3px 10px rgba(10, 36, 99, 0.2);
        }

        .timeline-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border-left: 3px solid var(--primary-color);
        }

        .timeline-content:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .timeline-time {
            font-size: 0.8rem;
            color: var(--secondary-color);
            margin-bottom: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .timeline-title {
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
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
            gap: 12px;
            margin-top: 20px;
        }

        .action-btn {
            background-color: white;
            color: var(--primary-color);
            border: 1px solid rgba(10, 36, 99, 0.1);
            border-radius: 8px;
            padding: 10px 18px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .action-btn i {
            font-size: 0.9rem;
        }

        .action-btn:hover {
            background-color: var(--primary-light);
            color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(10, 36, 99, 0.1);
            border-color: rgba(10, 36, 99, 0.2);
        }

        /* Quick Links */
        .quick-link-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            transition: var(--transition);
            margin-bottom: 10px;
            background-color: white;
            box-shadow: var(--shadow-light);
            border-left: 4px solid var(--primary-color);
        }

        .quick-link-item:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .quick-link-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .quick-link-icon.members {
            background: linear-gradient(45deg, #4361ee, #3a0ca3);
        }

        .quick-link-icon.suppliers {
            background: linear-gradient(45deg, #f8961e, #f3722c);
        }

        .quick-link-icon.inventory {
            background: linear-gradient(45deg, #43aa8b, #4d908e);
        }

        .quick-link-icon.reports {
            background: linear-gradient(45deg, #f94144, #f3722c);
        }

        .quick-link-text {
            flex-grow: 1;
        }

        .quick-link-title {
            font-weight: 600;
            margin-bottom: 3px;
            color: var(--text-main);
        }

        .quick-link-desc {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Footer Styles */
        .footer {
            text-align: center;
            padding: 25px;
            background-color: var(--background-light);
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
            color: var(--text-secondary);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            position: absolute;
            bottom: 0;
            width: calc(100% - 40px);
            margin: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 0.8s both;
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .footer-links a:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .welcome-content {
                max-width: 100%;
            }
            
            .welcome-decoration {
                display: none;
            }
        }

        @media (max-width: 992px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .user-menu {
                width: 100%;
                justify-content: space-between;
            }
            
            .navbar-nav .nav-link {
                padding: 12px 15px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .metrics-container {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .footer {
                flex-direction: column;
                gap: 15px;
                padding: 20px;
            }
            
            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 15px;
                padding-bottom: 120px;
            }
            
            .header-title {
                font-size: 1.8rem;
            }
            
            .welcome-title {
                font-size: 1.5rem;
            }
            
            .card-header {
                padding: 15px 20px;
            }
            
            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body style="font-size: 20px;">
    <div class="container">
        <header class="header">
            <div class="d-flex align-items-center">
                <div class="user-avatar">
                    <i class="fas fa-store"></i>
                </div>
                <h1 class="header-title" style="color:black;">T&C Co-op City Shop</h1>
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <div style="font-weight: bold; color: black;">Welcome, <?php echo $_SESSION['user_name']; ?></div>
                        <small  style="font-weight: bold; color:black;">Administrator</small>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <div class="welcome-banner animate__animated animate__fadeIn">
            <div class="welcome-content">
                <h2 class="welcome-title">Welcome to the Admin Dashboard</h2>
                <p class="welcome-text" style="font-size: 20px;">Manage your co-op shop operations efficiently. Monitor real-time statistics, track inventory, and streamline member services with our comprehensive tools.</p>
                <div class="mt-3">
                    <span class="time-badge">
                        <i class="far fa-calendar me-1"></i><?php echo $currentDate; ?>
                    </span>
                    <span class="time-badge">
                        <i class="far fa-clock me-1"></i><span id="currentTime"><?php echo $currentTime; ?></span>
                    </span>
                </div>
            </div>
            <div class="welcome-decoration">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavDropdown">
                    <ul class="navbar-nav">
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="employeeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-tie"></i> Employee
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="employeeDropdown">
                                <li><a class="dropdown-item" href="current_employee.php" style="font-size: 17px; color:black;"><i class="fas fa-id-card"></i> User Details Update</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="suppliersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-truck"></i> Suppliers
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="suppliersDropdown">
                                <li><a class="dropdown-item" href="supplier.php" style="font-size: 17px; color:black;"><i class="fas fa-user-plus"></i> Registration</a></li>
                                <li><a class="dropdown-item" href="purchase2.php" style="font-size: 17px; color:black;"><i class="fas fa-shopping-basket"></i> Item Purchases</a></li>
                                <li><a class="dropdown-item" href="supplier_payments.php" style="font-size: 17px; color:black;"><i class="fas fa-file-invoice-dollar"></i> Purchase Amount Details</a></li>
                                <li><a class="dropdown-item" href="actual_payment_supplier.php" style="font-size: 17px; color:black;"><i class="fas fa-money-bill-wave"></i> Payment Handling</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="inventoryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-box-open"></i> Inventory
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="inventoryDropdown">
                                <li><a class="dropdown-item" href="display_purchase_details.php" style="font-size: 17px; color:black;"><i class="fas fa-boxes"></i> Current Stock</a></li>
                                <li><a class="dropdown-item" href="inventory.php" style="font-size: 17px; color:black;"><i class="fas fa-exchange-alt"></i> Safety Stock</a></li>
                                <li><a class="dropdown-item" href="track.php" style="font-size:17px; color:black;"><i class="fas fa-truck-moving"></i> Track Items</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="membersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-users"></i> Members
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="membersDropdown">
                                <li><a class="dropdown-item" href="member.php" style="font-size: 17px; color:black;"><i class="fas fa-user-plus"></i> Member Registration</a></li>
                                <li><a class="dropdown-item" href="view_registered_members.php" style="font-size: 17px; color:black;"><i class="fas fa-id-badge"></i> Membership Numbers</a></li>
                                <li><a class="dropdown-item" href="purchase.php" style="font-size: 17px; color:black;"><i class="fas fa-shopping-cart"></i> Member Purchases</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-chart-line"></i> Reports
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="reportsDropdown">
                                <li><a class="dropdown-item" href="member_credit_report.php" style="font-size: 17px; color:black;"><i class="fas fa-credit-card"></i> Credit Balance Reports</a></li>
                                <li><a class="dropdown-item" href="member_purchase_history.php" style="font-size: 17px; color:black;"><i class="fas fa-star"></i>Purchase history of members</a></li>
                                <li><a class="dropdown-item" href="display_purchase_details.php" style="font-size: 17px; color:black;"><i class="fas fa-file-alt"></i> Purchase Details Report</a></li>
                                <li><a class="dropdown-item" href="supplier_performance_report.php" style="font-size: 17px; color:black;"><i class="fas fa-star"></i> Supplier Performance Report</a></li>
                                <li><a class="dropdown-item" href="profit.php" style="font-size: 17px; color:black;"><i class="fas fa-star"></i> Sales vs Purchases</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="card mb-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="card-header" style="font-size: 20px;">
                <i class="fas fa-chart-pie"></i> Business Overview
            </div>
            <div class="card-body">
                <div class="metrics-container">
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="metric-value" id="totalMembers"><?php echo $totalMembers; ?></div>
                        <div class="metric-label" style="font-size: 20px; font-weight: bold; color:black;">Active Members</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="metric-value" id="totalSuppliers"><?php echo $totalSuppliers; ?></div>
                        <div class="metric-label" style="font-size: 20px; font-weight: bold; color:black;">Active Suppliers</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="metric-value" id="totalInventoryLevel"><?php echo $totalInventoryLevel; ?></div>
                        <div class="metric-label" style="font-size: 20px; font-weight: bold; color:black;">Inventory Items</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="metric-value">+0%</div>
                        <div class="metric-label" style="font-size: 20px; font-weight: bold; color:black;">Monthly Growth</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <footer class="footer" style="text-align: center;">
        <div>&copy; 2025 T&C Co-op City Shop. All rights reserved.</div>
        <div class="footer-links">
            
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Update current time every second
        function updateCurrentTime() {
            const now = new Date();
            const options = { hour: 'numeric', minute: 'numeric', hour12: true };
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', options);
        }
        
        setInterval(updateCurrentTime, 1000);
        updateCurrentTime();

        // Animate elements when they come into view
        const animateOnScroll = () => {
            const elements = document.querySelectorAll('.card, .metric-card');
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if (elementPosition < windowHeight - 100) {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }
            });
        };

        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);

        // Sample chart for demonstration (would need actual data in production)
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Monthly Sales',
                            data: [12000, 19000, 15000, 22000, 20000, 25000],
                            borderColor: '#3e92cc',
                            backgroundColor: 'rgba(62, 146, 204, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    drawBorder: false
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>