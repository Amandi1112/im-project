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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        .card-animate {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        
        .progress-thin {
            height: 6px;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.7rem;
            padding: 3px 6px;
        }
        
        .sidebar-toggler {
            display: none;
            cursor: pointer;
        }
        
        @media (max-width: 992px) {
            .sidebar-toggler {
                display: inline-block;
            }
        }
        
        /* Pulse animation for important items */
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
        
        .pulse-warning {
            animation: pulse 2s infinite;
        }
        
        /* Dark mode toggle */
        .dark-mode-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dark-mode {
            background-color: #1a1a2e;
            color: #f8f9fa;
        }
        
        .dark-mode .card {
            background-color: #16213e;
            color: #f8f9fa;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        
        .dark-mode .table {
            color: #f8f9fa;
        }
        
        .dark-mode .table th {
            background-color: #0f3460;
            color: #f8f9fa;
        }
        
        .dark-mode .dashboard-header {
            background-color: #0f3460;
        }
        
    </style>
</head>
<body>
    <!-- Dashboard Header with Dark Mode Toggle -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 d-flex align-items-center">
                    <button class="sidebar-toggler btn btn-sm btn-light me-3" id="sidebarToggler">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><i class="fas fa-boxes me-2"></i>Inventory Management</h1>
                </div>
                <div class="col-md-6 text-end">
                    <span class="dark-mode-toggle badge bg-light text-dark me-2" id="darkModeToggle" title="Toggle Dark Mode">
                        <i class="fas fa-moon me-1"></i> Dark Mode
                    </span>
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-user me-1"></i> 
                        <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?>
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
        <!-- Summary Cards with Animation -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Items</h6>
                                <h3 class="mb-0" id="totalItems">
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM items";
                                    $result = $conn->query($sql);
                                    echo $result->fetch_assoc()['total'];
                                    ?>
                                </h3>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-primary" id="itemsProgress" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded position-relative">
                                <i class="fas fa-box text-primary fs-4"></i>
                                <span class="notification-badge badge bg-danger" id="lowStockBadge" style="display: none;">!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Suppliers</h6>
                                <h3 class="mb-0" id="totalSuppliers">
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM supplier";
                                    $result = $conn->query($sql);
                                    echo $result->fetch_assoc()['total'];
                                    ?>
                                </h3>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-success" id="suppliersProgress" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-truck text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Recent Purchases</h6>
                                <h3 class="mb-0" id="recentPurchases">
                                    <?php
                                    $sql = "SELECT COUNT(*) as total FROM item_purchases WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                                    $result = $conn->query($sql);
                                    echo $result->fetch_assoc()['total'];
                                    ?>
                                </h3>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-warning" id="purchasesProgress" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-shopping-cart text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Inventory Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Inventory Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Purchases</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="purchasesChart"></canvas>
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
                        <div>
                            <button class="btn btn-sm btn-outline-secondary me-2" id="exportInventory">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                            <a href="display_purchase_details.php" class="btn btn-sm btn-light">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="search-box mb-3">
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
                                        <th>Actions</th>
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
                                            $statusClass = '';
                                            
                                            if ($quantity < 5) {
                                                $status = 'Low';
                                                $statusClass = 'badge-low pulse-warning';
                                            } elseif ($quantity < 10) {
                                                $status = 'Medium';
                                                $statusClass = 'badge-medium';
                                            } else {
                                                $status = 'High';
                                                $statusClass = 'badge-high';
                                            }
                                            
                                            echo "<tr data-id='" . htmlspecialchars($row["item_id"]) . "'>";
                                            echo "<td>" . htmlspecialchars($row["item_code"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["item_name"]) . "</td>";
                                            echo "<td>$" . formatCurrency($row["price_per_unit"]) . "</td>";
                                            echo "<td>" . $quantity . "</td>";
                                            echo "<td><span class='badge " . $statusClass . "'>" . $status . "</span></td>";
                                            echo "<td>
                                                    <button class='btn btn-sm btn-outline-primary edit-item' data-id='" . htmlspecialchars($row["item_id"]) . "'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-outline-info view-item' data-id='" . htmlspecialchars($row["item_id"]) . "'>
                                                        <i class='fas fa-eye'></i>
                                                    </button>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center'>No items found in inventory</td></tr>";
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
                        <div>
                            <button class="btn btn-sm btn-outline-secondary me-2" id="exportPurchases">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                            <a href="supplier_purchases.php" class="btn btn-sm btn-light">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="search-box mb-3">
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
                                    // [Keep your existing PHP code for purchases table]
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        <button class="btn btn-sm btn-outline-secondary" id="refreshActivity">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body" id="activityLogContainer">
                        <ul class="list-group list-group-flush" id="activityLog">
                            <?php
                            // [Keep your existing PHP code for activity log]
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
                            <table class="table table-hover" id="suppliersTable">
                                <thead>
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Items</th>
                                        <th>Contact</th>
                                        <th>Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // [Keep your existing PHP code for top suppliers]
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
    
    <!-- Modal for Item Details -->
    <div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalLabel">Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="itemModalBody">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize animations
            initAnimations();
            
            // Initialize charts
            initCharts();
            
            // Initialize search functionality
            initSearch();
            
            // Initialize event listeners
            initEventListeners();
            
            // Initialize progress bars
            initProgressBars();
            
            // Initialize dark mode
            initDarkMode();
            
            // Check for low stock items
            checkLowStock();
        });
        
        function initAnimations() {
            // Add animation to cards on scroll
            const animateOnScroll = () => {
                const cards = document.querySelectorAll('.card');
                cards.forEach(card => {
                    const cardPosition = card.getBoundingClientRect().top;
                    const screenPosition = window.innerHeight / 1.3;
                    
                    if (cardPosition < screenPosition) {
                        card.classList.add('card-animate');
                    }
                });
            };
            
            window.addEventListener('scroll', animateOnScroll);
            animateOnScroll(); // Run once on load
        }
        
        function initCharts() {
            // Inventory Distribution Pie Chart
            const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
            const inventoryChart = new Chart(inventoryCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Low Stock', 'Medium Stock', 'High Stock'],
                    datasets: [{
                        data: [
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM items WHERE current_quantity < 5";
                            $result = $conn->query($sql);
                            echo $result->fetch_assoc()['count'] . ',';
                            
                            $sql = "SELECT COUNT(*) as count FROM items WHERE current_quantity >= 5 AND current_quantity < 10";
                            $result = $conn->query($sql);
                            echo $result->fetch_assoc()['count'] . ',';
                            
                            $sql = "SELECT COUNT(*) as count FROM items WHERE current_quantity >= 10";
                            $result = $conn->query($sql);
                            echo $result->fetch_assoc()['count'];
                            ?>
                        ],
                        backgroundColor: [
                            '#e74c3c',
                            '#f39c12',
                            '#27ae60'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Monthly Purchases Line Chart
            const purchasesCtx = document.getElementById('purchasesChart').getContext('2d');
            const purchasesChart = new Chart(purchasesCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Purchases',
                        data: [
                            <?php
                            // This is simplified - in a real app you'd query actual monthly data
                            for ($i = 1; $i <= 12; $i++) {
                                $rand = rand(5, 20);
                                echo $rand;
                                if ($i < 12) echo ', ';
                            }
                            ?>
                        ],
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function initSearch() {
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
            
            // Suppliers table search
            const searchSuppliers = document.getElementById('searchSuppliers');
            if (searchSuppliers) {
                searchSuppliers.addEventListener('keyup', function() {
                    searchTable('suppliersTable', this.value);
                });
            }
        }
        
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
        
        function initEventListeners() {
            // View item details
            document.querySelectorAll('.view-item').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    viewItemDetails(itemId);
                });
            });
            
            // Edit item
            document.querySelectorAll('.edit-item').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    editItem(itemId);
                });
            });
            
            // Export inventory
            document.getElementById('exportInventory').addEventListener('click', function() {
                exportTableToCSV('itemsTable', 'inventory_export.csv');
            });
            
            // Export purchases
            document.getElementById('exportPurchases').addEventListener('click', function() {
                exportTableToCSV('purchasesTable', 'purchases_export.csv');
            });
            
            // Refresh activity log
            document.getElementById('refreshActivity').addEventListener('click', function() {
                refreshActivityLog();
            });
            
            // Dark mode toggle
            document.getElementById('darkModeToggle').addEventListener('click', function() {
                toggleDarkMode();
            });
            
            // Sidebar toggler
            document.getElementById('sidebarToggler').addEventListener('click', function() {
                // You would implement sidebar toggle functionality here
                alert('Sidebar toggle functionality would go here in a full implementation');
            });
        }
        
        function viewItemDetails(itemId) {
            // In a real application, this would fetch data via AJAX
            const modal = new bootstrap.Modal(document.getElementById('itemModal'));
            document.getElementById('itemModalLabel').textContent = 'Item Details - ID: ' + itemId;
            
            // Simulate AJAX loading
            setTimeout(() => {
                document.getElementById('itemModalBody').innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <img src="https://via.placeholder.com/300" class="img-fluid rounded mb-3" alt="Item Image">
                        </div>
                        <div class="col-md-8">
                            <h4>Item Details</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Item Code:</th>
                                    <td>ITEM-${itemId}</td>
                                </tr>
                                <tr>
                                    <th>Item Name:</th>
                                    <td>Sample Item ${itemId}</td>
                                </tr>
                                <tr>
                                    <th>Price:</th>
                                    <td>$19.99</td>
                                </tr>
                                <tr>
                                    <th>Current Quantity:</th>
                                    <td>15</td>
                                </tr>
                                <tr>
                                    <th>Supplier:</th>
                                    <td>Sample Supplier</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                `;
            }, 500);
            
            modal.show();
        }
        
        function editItem(itemId) {
            // In a real application, this would open an edit form
            alert('Edit functionality for item ID: ' + itemId + ' would go here');
        }
        
        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Skip action columns
                    if (cols[j].querySelector('.edit-item') || cols[j].querySelector('.view-item')) continue;
                    
                    // Clean inner text
                    let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '');
                    text = text.replace(/(\s\s)/gm, ' ');
                    text = text.replace(/"/g, '""');
                    row.push('"' + text + '"');
                }
                
                csv.push(row.join(','));
            }
            
            // Download CSV file
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (navigator.msSaveBlob) { // IE 10+
                navigator.msSaveBlob(blob, filename);
            } else {
                link.href = URL.createObjectURL(blob);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        function refreshActivityLog() {
            const refreshBtn = document.getElementById('refreshActivity');
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing';
            
            // Simulate AJAX refresh
            setTimeout(() => {
                // In a real app, this would fetch new data from the server
                const activityLog = document.getElementById('activityLog');
                const newActivity = document.createElement('li');
                newActivity.className = 'list-group-item d-flex justify-content-between align-items-center';
                newActivity.innerHTML = `
                    <span>Manual refresh performed</span>
                    <small class="text-muted">just now</small>
                `;
                activityLog.insertBefore(newActivity, activityLog.firstChild);
                
                // Remove oldest item if more than 5
                if (activityLog.children.length > 5) {
                    activityLog.removeChild(activityLog.lastChild);
                }
                
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                
                // Show notification
                showNotification('Activity log refreshed successfully', 'success');
            }, 1000);
        }
        
        function initProgressBars() {
            // Animate progress bars on load
            setTimeout(() => {
                document.getElementById('itemsProgress').style.width = '75%';
                document.getElementById('suppliersProgress').style.width = '60%';
                document.getElementById('purchasesProgress').style.width = '45%';
            }, 500);
        }
        
        function initDarkMode() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const body = document.body;
            
            // Check for saved user preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-sun me-1"></i> Light Mode';
            }
            
            // Toggle dark mode
            darkModeToggle.addEventListener('click', () => {
                body.classList.toggle('dark-mode');
                
                if (body.classList.contains('dark-mode')) {
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.innerHTML = '<i class="fas fa-sun me-1"></i> Light Mode';
                } else {
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.innerHTML = '<i class="fas fa-moon me-1"></i> Dark Mode';
                }
            });
        }
        
        function checkLowStock() {
            // In a real app, this would check actual low stock items
            const lowStockCount = <?php
                $sql = "SELECT COUNT(*) as count FROM items WHERE current_quantity < 5";
                $result = $conn->query($sql);
                echo $result->fetch_assoc()['count'];
            ?>;
            
            if (lowStockCount > 0) {
                document.getElementById('lowStockBadge').style.display = 'block';
                document.getElementById('lowStockBadge').textContent = lowStockCount;
                
                // Show notification
                showNotification(`Warning: ${lowStockCount} item(s) are low in stock`, 'warning');
            }
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `position-fixed bottom-0 end-0 p-3`;
            notification.style.zIndex = '11';
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.role = 'alert';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            notification.appendChild(alert);
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 150);
            }, 5000);
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
