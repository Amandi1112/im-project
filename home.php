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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* General Styles */
        body {
            font-family: 'Roboto', sans-serif;

            color: #343a40;
            margin: 0;
            padding: 0;
        }

        .container {
            padding: 20px;
        }

        /* Header Styles */
        .header {
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: bold;
            color:rgb(0, 0, 0);
        }

        .user-menu {
            display: flex;
            align-items: center;
        }

        .user-info {
            margin-right: 20px;
            color:rgb(255, 255, 255);
            font-weight: 500;
        }

        .logout-btn {
            background-color:rgb(227, 121, 132);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 7px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #c82333;
            color:black;
        }

        /* Navigation Bar */
        .navbar {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .navbar-nav .nav-link {
            background-color: none;
            color: #495057;
            font-weight: 500;
            padding: 10px 15px;
        }

        .navbar-nav .nav-link:hover {
            color: #007bff;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .dropdown-item {
            color: #495057;
            padding: 10px 15px;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #007bff;
        }

        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background-color: #ffffff;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background-color:rgb(132, 144, 154);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        .card-body p {
            color: #6c757d;
            margin-bottom: 10px;
        }

        .card-body a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .card-body a:hover {
            color: #0056b3;
        }

        /* Footer Styles */
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            color: #6c757d;
        }

        /* Media Queries */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body style="background: url(images/background60.jpg);">
    <div class="container">
        <header class="header">
            <div class="header-title">T&C Co-op City Shop</div>
            <div class="user-menu">
                <div class="user-info">Welcome! <?php echo $_SESSION['user_name']; ?></div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavDropdown">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="home.php">Home </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color:rgb(0, 0, 0);"> 
                                Employee
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="personal_detail.php">Personal Details</a></li>
                                <li><a class="dropdown-item" href="educational_background.php">Educational Background</a></li>
                                <li><a class="dropdown-item" href="work_experience.php">Work Experience</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color:rgb(0, 0, 0);">
                                Suppliers
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="supplier.php">Registration</a></li>
                                <li><a class="dropdown-item" href="purchases.php">Item Purchases</a></li>
                                <li><a class="dropdown-item" href="Bill_Handling.php">Handling Bills</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color:rgb(0, 0, 0);">
                                Products
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="manage_categories_items.php">Categories & Items</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color:rgb(0, 0, 0);">
                                Members
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                                <li><a class="dropdown-item" href="member.php">Bank Memberships</a></li>
                                <li><a class="dropdown-item" href="member_registration.php">Member Registration</a></li>
                                <li><a class="dropdown-item" href="purchases.php">Purchases</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color:rgb(0, 0, 0);">
                                Reports
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
        <br><br><br>
        <di class="dashboard-grid">
            <div class="card">
                <div class="card-header" style="color:rgb(0, 0, 0);">Your Profile</div>
                <div class="card-body">
                    <p>Email : <?php echo $_SESSION['user_email']; ?></p>
                    <p>Password: ######</p>
                    <a href="reset_password.php">Edit Profile</a>
                </div>
            </div>

            <div class="card">
              <div class="card-header" style="color:rgb(0, 0, 0);">Total Members</div>
              <div class="card-body">
                <p>Active Members: <?php echo $totalMembers; ?></p>
                <p>Active Suppliers: <?php echo $totalSuppliers; ?></p>
                <p>Supplier Performance: </p>
                <p>Customer Satisfaction Rate: </p>
              </div>
            </div>


            <div class="card">
                <div class="card-header" style="color:rgb(0, 0, 0);">Quick Links</div>
                <div class="card-body">
                    <ul>
                        <li><a href="display_coop_number.php" style="font-size: 17px;">Members</a></li>
                        <li><a href="display_registered_suppliers.php" style="font-size: 17px;">Suppliers</a></li>
                        <li><a href="manage_categories_items.php" style="font-size: 17px";>Categories & Items</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <br><br><br><br><br><br><br><br><br>

        <footer class="footer">
            &copy; 2025 T&C Co-op City Shop. All rights reserved.
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
