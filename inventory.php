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
    return number_format(floatval($amount), 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #4a6fa5;
            --primary-dark: #345382;
            --secondary: #16db93;
            --secondary-dark: #12b378;
            --danger: #e63946;
            --danger-dark: #c1121f;
            --success: #06d6a0;
            --success-dark: #04a87e;
            --warning: #ffb703;
            --warning-dark: #fb8500;
            --info: #118ab2;
            --info-dark: #0d6986;
            --light: #f8f9fa;
            --dark: #1d3557;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --bg-color: #f0f3f5;
            --card-bg: #ffffff;
            --text-color: #2b2d42;
        }
    
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding-bottom: 70px;
            font-size: 15px;
        }
        
        .dashboard-header {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.25rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0,0,0,0.12);
        }
        
        /* Inventory Distribution Panel */
        .inventory-distribution-card {
            background: var(--card-bg);
            border-top: 5px solid var(--info);
            height: 500px;
        }
        
        .inventory-distribution-card .card-header {
            background: linear-gradient(to right, var(--info), var(--info-dark));
            color: white;
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        
        /* Current Inventory Panel */
        .current-inventory-card {
            background: var(--card-bg);
            border-top: 5px solid var(--success);
        }

        .current-inventory-card .card-body {
            height: calc(100% - 56px);
            display: flex;
            flex-direction: column;
        }

        .current-inventory-card .table-responsive {
            flex: 1;
            overflow-y: auto;
        }
        
        .current-inventory-card .card-header {
            background: linear-gradient(to right, var(--success), var(--success-dark));
            color: white;
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        
        /* Table styles */
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background-color: rgba(240, 240, 240, 0.5);
            color: var(--dark);
            font-weight: 600;
            border-bottom: 2px solid var(--gray-light);
            padding: 0.85rem 1rem;
        }
        
        .table td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .table tr:hover {
            background-color: rgba(240, 240, 240, 0.3);
        }
        
        /* Badge styles */
        .badge {
            padding: 0.4rem 0.65rem;
            font-weight: 500;
            border-radius: 30px;
        }
        
        .badge-low {
            background-color: var(--danger);
            color: white;
        }
        
        .badge-medium {
            background-color: var(--warning);
            color: var(--dark);
        }
        
        .badge-high {
            background-color: var(--success);
            color: white;
        }
        
        /* Chart container */
        .chart-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            height: 350px;
            box-shadow: inset 0 0 8px rgba(0,0,0,0.05);
        }
        
        /* Search box styling */
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-box i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 25px;
            border: 1px solid var(--gray-light);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            font-size: 0.9rem;
            padding-top: 0.6rem;
            padding-bottom: 0.6rem;
        }
        
        .search-box input:focus {
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
            border-color: var(--primary);
        }
        
        /* Button styling */
        .btn-light {
            background-color: white;
            border: 1px solid var(--gray-light);
            color: var(--dark);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.4rem 1rem;
            transition: all 0.2s;
        }
        
        .btn-light:hover {
            background-color: var(--gray-light);
            border-color: var(--gray);
        }
        
        /* Footer styling */
        .footer {
            background-color: var(--dark);
            color: white;
            padding: 1.25rem 0;
            position: fixed;
            bottom: 0;
            width: 100%;
            z-index: 10;
        }
        
        /* Animation for low stock pulse */
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(230, 57, 70, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(230, 57, 70, 0); }
            100% { box-shadow: 0 0 0 0 rgba(230, 57, 70, 0); }
        }
        
        .pulse-warning {
            animation: pulse 2s infinite;
        }
        
        /* For the legend below chart */
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .chart-legend-item {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .chart-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        /* Header username and date badges */
        .header-badge {
            background-color: rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: 0.4rem 1rem;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            margin-left: 0.5rem;
        }
        
        .header-badge i {
            margin-right: 0.4rem;
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 d-flex align-items-center">
                    <button class="sidebar-toggler btn btn-sm btn-light me-3" id="sidebarToggler">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="mb-0"><i class="fas fa-boxes me-2"></i>Inventory Management</h2>
                </div>
                <div class="col-md-6 text-end">
                    
                    <span class="header-badge">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                    </span>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="container">
        <!-- Inventory Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card inventory-distribution-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Inventory Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="chart-legend-item">
                                <span class="chart-legend-color" style="background-color: var(--danger);" ></span>
                                Low Stock
                            </div>
                            <div class="chart-legend-item">
                                <span class="chart-legend-color" style="background-color: var(--warning);"></span>
                                Medium Stock
                            </div>
                            <div class="chart-legend-item">
                                <span class="chart-legend-color" style="background-color: var(--success);"></span>
                                High Stock
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card current-inventory-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Current Inventory</h5>
                        <div>
                            
                            <a href="display_purchase_details.php" class="btn btn-light">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="search-box mb-3">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchItems" class="form-control" placeholder="Search items...">
                        </div>
                                
                        <div class="table-responsive">
                            <table class="table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Price/Unit</th>
                                        <th>Qty (Unit)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch items with supplier name and quantity status
                                    $sql = "SELECT i.item_id, i.item_code, i.item_name, i.price_per_unit,
                                    i.current_quantity, s.supplier_name, i.unit
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
                                            echo "<td>" . htmlspecialchars($row["item_name"]) . "</td>";
                                            echo "<td>Rs." . formatCurrency($row["price_per_unit"]) . "/" . htmlspecialchars($row["unit"]) . "</td>";
                                            echo "<td>" . $quantity . " " . htmlspecialchars($row["unit"]) . "</td>";
                                            echo "<td><span class='badge " . $statusClass . "'>" . $status . "</span></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center'>No items found in inventory</td></tr>";
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
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0"></p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts
            initCharts();
            
            // Initialize search functionality
            initSearch();
            
            // Initialize event listeners
            initEventListeners();
            
            // Check for low stock items
            checkLowStock();
        });
        
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
                            'var(--danger)',
                            'var(--warning)',
                            'var(--success)'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
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
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
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
            // Export inventory
            document.getElementById('exportInventory').addEventListener('click', function() {
                exportTableToCSV('itemsTable', 'inventory_export.csv');
            });
            
            // Sidebar toggler
            document.getElementById('sidebarToggler').addEventListener('click', function() {
                // You would implement sidebar toggle functionality here
                alert('Sidebar toggle functionality would go here in a full implementation');
            });
        }
        
        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
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
        
        function checkLowStock() {
            // Check for low stock items
            const lowStockCount = <?php
                $sql = "SELECT COUNT(*) as count FROM items WHERE current_quantity < 5";
                $result = $conn->query($sql);
                echo $result->fetch_assoc()['count'];
            ?>;
            
            if (lowStockCount > 0) {
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
    </script>
</body>
</html>
<?php
// Close the database connection
$conn->close();
?>