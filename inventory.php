<?php
// Database configuration - should be in a separate config file in production
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mywebsite');

// Error reporting - only for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("System error occurred. Please try again later.");
}

// Function to sanitize input data
function sanitizeInput($data) {
    global $conn;
    return htmlspecialchars(strip_tags($conn->real_escape_string(trim($data))));
}

// Function to format currency
function formatCurrency($amount) {
    return number_format(floatval($amount), 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Inventory Management Dashboard | MyWebsite</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .dashboard-header {
            background-color: var(--secondary-color);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0 !important;
            font-weight: 600;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table th {
            background-color: var(--light-gray);
            font-weight: 600;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-box i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 20px;
            border: 1px solid #dee2e6;
        }
        
        .badge-low {
            background-color: var(--accent-color);
        }
        
        .badge-medium {
            background-color: #f39c12;
        }
        
        .badge-high {
            background-color: #27ae60;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-active {
            background-color: #2ecc71;
        }
        
        .status-inactive {
            background-color: #e74c3c;
        }
        
        .footer {
            background-color: var(--dark-gray);
            color: white;
            padding: 1.5rem 0;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-boxes me-2"></i>Inventory Management</h1>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-user me-1"></i> 
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-calendar-alt me-1"></i> <?php echo date('F j, Y'); ?>
                    </span>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Items</h6>
                                <h3 class="mb-0">
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM items";
                                    $result = $conn->query($sql);
                                    echo $result->fetch_assoc()['total'];
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-box text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Suppliers</h6>
                                <h3 class="mb-0">
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM supplier";
                                    $result = $conn->query($sql);
                                    echo $result->fetch_assoc()['total'];
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-truck text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Recent Purchases</h6>
                                <h3 class="mb-0">
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM item_purchases WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                                    $result = $conn->query($sql);
                                    echo $result->fetch_assoc()['total'];
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-shopping-cart text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Inventory Sections -->
        <div class="row">
            <!-- Current Inventory -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Current Inventory</h5>
                        <a href="display_purchase_details.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchItems" class="form-control" placeholder="Search items...">
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch items with supplier name and quantity status
                                    $sql = "SELECT i.item_id, i.item_code, i.item_name, i.price_per_unit, 
                                            i.current_quantity, s.supplier_name 
                                            FROM items i
                                            LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
                                            ORDER BY i.current_quantity ASC
                                            LIMIT 8";
                                    $result = $conn->query($sql);

                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $quantity = intval($row['current_quantity']);
                                            $status = '';
                                            
                                            if ($quantity < 5) {
                                                $status = '<span class="badge badge-low">Low</span>';
                                            } elseif ($quantity < 10) {
                                                $status = '<span class="badge badge-medium">Medium</span>';
                                            } else {
                                                $status = '<span class="badge badge-high">High</span>';
                                            }
                                            
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["item_code"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["item_name"]) . "</td>";
                                            echo "<td>$" . formatCurrency($row["price_per_unit"]) . "</td>";
                                            echo "<td>" . $quantity . "</td>";
                                            echo "<td>" . $status . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>No items found in inventory</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Purchases -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Recent Purchases</h5>
                        <a href="supplier_purchases.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchPurchases" class="form-control" placeholder="Search purchases...">
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="purchasesTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch recent item purchases with item details
                                    $sql = "SELECT ip.purchase_id, ip.quantity, ip.price_per_unit, ip.total_price, 
                                            ip.purchase_date, i.item_name, s.supplier_name 
                                            FROM item_purchases ip
                                            JOIN items i ON ip.item_id = i.item_id
                                            LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
                                            ORDER BY ip.purchase_date DESC, ip.purchase_id DESC
                                            LIMIT 8";
                                    $result = $conn->query($sql);

                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["item_name"]) . "<br><small class='text-muted'>" . htmlspecialchars($row["supplier_name"]) . "</small></td>";
                                            echo "<td>" . intval($row["quantity"]) . "</td>";
                                            echo "<td>$" . formatCurrency($row["price_per_unit"]) . "</td>";
                                            echo "<td>$" . formatCurrency($row["total_price"]) . "</td>";
                                            echo "<td>" . date('M j, Y', strtotime($row["purchase_date"])) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>No recent purchases found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Sections -->
        <div class="row mt-4">
            <!-- Recent Activity Log -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php
                            // Fetch recent activity logs
                            $sql = "SELECT activity, timestamp FROM activity_log 
                                    ORDER BY timestamp DESC 
                                    LIMIT 5";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    $timeAgo = timeAgo($row['timestamp']);
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                    echo '<span>' . htmlspecialchars($row['activity']) . '</span>';
                                    echo '<small class="text-muted">' . $timeAgo . '</small>';
                                    echo '</li>';
                                }
                            } else {
                                echo '<li class="list-group-item text-center">No recent activity</li>';
                            }
                            
                            // Helper function for time ago
                            function timeAgo($datetime) {
                                $time = strtotime($datetime);
                                $time = time() - $time;
                                
                                $units = array (
                                    31536000 => 'year',
                                    2592000 => 'month',
                                    604800 => 'week',
                                    86400 => 'day',
                                    3600 => 'hour',
                                    60 => 'minute',
                                    1 => 'second'
                                );
                                
                                foreach ($units as $unit => $val) {
                                    if ($time < $unit) continue;
                                    $numberOfUnits = floor($time / $unit);
                                    return ($val == 'second') ? 'just now' : 
                                           (($numberOfUnits > 1) ? $numberOfUnits.' '.$val.'s ago' : $numberOfUnits.' '.$val.' ago');
                                }
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Top Suppliers -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Top Suppliers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Items</th>
                                        <th>Contact</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch top suppliers by item count
                                    $sql = "SELECT s.supplier_id, s.supplier_name, s.contact_number, 
                                            COUNT(i.item_id) as item_count
                                            FROM supplier s
                                            LEFT JOIN items i ON s.supplier_id = i.supplier_id
                                            GROUP BY s.supplier_id
                                            ORDER BY item_count DESC
                                            LIMIT 5";
                                    $result = $conn->query($sql);

                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["supplier_name"]) . "<br><small class='text-muted'>" . htmlspecialchars($row["supplier_id"]) . "</small></td>";
                                            echo "<td>" . intval($row["item_count"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["contact_number"]) . "</td>";
                                            
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center'>No suppliers found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> MyWebsite. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0">Inventory Management System v1.0</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Enhanced search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Items table search
            const searchItems = document.getElementById('searchItems');
            if (searchItems) {
                searchItems.addEventListener('keyup', function() {
                    searchTable('itemsTable', this.value);
                });
            }
            
            // Purchases table search
            const searchPurchases = document.getElementById('searchPurchases');
            if (searchPurchases) {
                searchPurchases.addEventListener('keyup', function() {
                    searchTable('purchasesTable', this.value);
                });
            }
        });
        
        function searchTable(tableId, searchText) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const rows = table.getElementsByTagName('tr');
            const text = searchText.toLowerCase();
            
            for (let i = 1; i < rows.length; i++) { // Skip header row
                const cells = rows[i].getElementsByTagName('td');
                let rowMatches = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j]) {
                        const cellText = cells[j].textContent || cells[j].innerText;
                        if (cellText.toLowerCase().indexOf(text) > -1) {
                            rowMatches = true;
                            break;
                        }
                    }
                }
                
                rows[i].style.display = rowMatches ? '' : 'none';
            }
        }
        
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 300000 ms = 5 minutes
    </script>
</body>
</html>
<?php
// Close the database connection
$conn->close();
?>