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
        body {
            font-family: 'Poppins', sans-serif;
            color: #2c3e50;
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
            background-color: rgba(255, 255, 255, 0.95);
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: rgb(93, 11, 97);
            letter-spacing: 0.5px;
        }

        .user-menu {
            display: flex;
            align-items: center;
        }

        .user-info {
            margin-right: 20px;
            color: #555;
            font-weight: 500;
        }

        .logout-btn {
            background-color: rgb(227, 121, 132);
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

        /* Navigation Bar */
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            padding: 0;
        }

        .navbar-nav .nav-link {
            color: #2c3e50;
            font-weight: 500;
            padding: 15px 20px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link:focus {
            color: rgb(93, 11, 97);
            background-color: rgba(93, 11, 97, 0.05);
        }

        .navbar-nav .active {
            color: rgb(93, 11, 97);
            border-bottom: 2px solid rgb(93, 11, 97);
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }

        .dropdown-item {
            color: #2c3e50;
            padding: 12px 20px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(93, 11, 97, 0.05);
            color: rgb(93, 11, 97);
        }

        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
        }

        .card {
            background-color: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background-color: rgb(93, 11, 97);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
        }

        .card-body {
            padding: 25px;
        }

        .card-body p {
            color: #5d6778;
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .card-body a {
            color: rgb(93, 11, 97);
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
            color: rgb(93, 11, 97);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #5d6778;
            font-weight: 500;
        }

        /* Footer Styles */
        .footer {
            text-align: center;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.08);
            color: #5d6778;
            border-radius: 8px 8px 0 0;
            position: absolute;
            bottom: 0;
            width: calc(100% - 40px);
            margin: 0 20px;
        }

        /* Media Queries */
        @media (max-width: 992px) {
            .navbar-nav .nav-link {
                padding: 12px 15px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-title">T&C Co-op City Shop</div>
            <div class="user-menu">
                <div class="user-info">
                    <i class="fas fa-user-circle me-2"></i>Welcome, <?php echo $_SESSION['user_name']; ?>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </header>

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
                                <li><a class="dropdown-item" href="purchases.php">Item Purchases</a></li>
                                <li><a class="dropdown-item" href="Bill_Handling.php">Handling Bills</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-box-open me-1"></i>Products
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="manage_categories_items.php">Categories & Items</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-users me-1"></i>Members
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="member.php">Bank Memberships</a></li>
                                <li><a class="dropdown-item" href="member_registration.php">Member Registration</a></li>
                                <li><a class="dropdown-item" href="purchases.php">Purchases</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-chart-line me-1"></i>Reports
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="report1.php">Sales Report</a></li>
                                <li><a class="dropdown-item" href="report2.php">Inventory Report</a></li>
                                <li><a class="dropdown-item" href="report3.php">Supplier Performance Report</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-shield me-2"></i>Administrator Profile
                </div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?php echo $_SESSION['user_email']; ?></p>
                    <p><strong>Security:</strong> <i class="fas fa-lock"></i> Password Protected</p>
                    <a href="reset_password.php">
                        <i class="fas fa-pen me-1"></i>Update Profile Settings
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2"></i>System Overview
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col">
                            <div class="stat-value"><?php echo $totalMembers; ?></div>
                            <div class="stat-label"><i class="fas fa-user-check me-1"></i>Active Members</div>
                        </div>
                    </div>
                    <div class="row">  
                        <div class="col">
                            <div class="stat-value"><?php echo $totalSuppliers; ?></div>
                            <div class="stat-label"><i class="fas fa-handshake me-1"></i>Active Suppliers</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-link me-2"></i>Quick Access
                </div>
                <div class="card-body">
                    <ul>
                        <li><a href="display_coop_number.php"><i class="fas fa-users me-2"></i>Members Directory</a></li>
                        <li><a href="display_registered_suppliers.php"><i class="fas fa-truck me-2"></i>Supplier Database</a></li>
                        <li><a href="manage_categories_items.php"><i class="fas fa-tags me-2"></i>Categories & Items</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        &copy; 2025 T&C Co-op City Shop. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>