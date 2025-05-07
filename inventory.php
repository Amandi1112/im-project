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

// Function to determine stock status based on unit and quantity
function getStockStatus($quantity, $unit) {
    $unit = strtolower($unit);

    // Define thresholds based on units
    if ($unit == 'kg') {
        if ($quantity < 5) return 'Low';
        else if ($quantity < 20) return 'Medium';
        else return 'High';
    } else if ($unit == 'packet' || $unit == 'packets') {
        if ($quantity < 5) return 'Low';
        else if ($quantity < 10) return 'Medium';
        else return 'High';
    } else if ($unit == 'g' || $unit == 'gram' || $unit == 'grams') {
        if ($quantity < 100) return 'Low';
        else if ($quantity < 500) return 'Medium';
        else return 'High';
    } else if ($unit == 'bottle' || $unit == 'bottles') {
        if ($quantity < 5) return 'Low';
        else if ($quantity < 20) return 'Medium';
        else return 'High';
    } else {
        // Default case for other units
        if ($quantity < 5) return 'Low';
        else if ($quantity < 10) return 'Medium';
        else return 'High';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Inventory Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <style>
        :root {
            /* New modern color palette */
            --primary: #6366f1;        /* Indigo */
            --primary-dark:rgb(22, 17, 105);   /* Darker Indigo */
            --secondary: #10b981;      /* Emerald */
            --secondary-dark: #059669; /* Darker Emerald */
            --danger: #ef4444;         /* Red */
            --danger-dark: #dc2626;    /* Darker Red */
            --success: #22c55e;        /* Green */
            --success-dark: #16a34a;   /* Darker Green */
            --warning: #f59e0b;        /* Amber */
            --warning-dark: #d97706;   /* Darker Amber */
            --info: #0ea5e9;           /* Sky */
            --info-dark: #0284c7;      /* Darker Sky */
            --light: #f9fafb;          /* Gray 50 */
            --dark: #1e293b;           /* Slate 800 */
            --gray: #64748b;           /* Slate 500 */
            --gray-light: #e2e8f0;     /* Slate 200 */
            --bg-color: #f1f5f9;       /* Slate 100 */
            --card-bg: #ffffff;        /* White */
            --text-color: #334155;     /* Slate 700 */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding-bottom: 70px;
            font-size: 15px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        /* Inventory Distribution Panel */
        .inventory-distribution-card {
            background: var(--card-bg);
            border-left: 5px solid var(--info);
            height: 500px;
        }

        .inventory-distribution-card .card-header {
            background: white;
            color: var(--dark);
            border-bottom: 1px solid var(--gray-light);
            padding: 1.2rem 1.5rem;
            font-weight: 600;
        }

        /* Current Inventory Panel */
        .current-inventory-card {
            background: var(--card-bg);
            border-left: 5px solid var(--secondary);
        }

        .current-inventory-card .card-body {
            height: calc(100% - 60px);
            display: flex;
            flex-direction: column;
        }

        .current-inventory-card .table-responsive {
            flex: 1;
            overflow-y: auto;
        }

        .current-inventory-card .card-header {
            background: white;
            color: var(--dark);
            border-bottom: 1px solid var(--gray-light);
            padding: 1.2rem 1.5rem;
            font-weight: 600;
        }

        /* Stock Thresholds Card */
        .stock-thresholds-card {
            background: var(--card-bg);
            border-left: 5px solid var(--primary);
        }

        .stock-thresholds-card .card-header {
            background: white;
            color: var(--dark);
            border-bottom: 1px solid var(--gray-light);
            padding: 1.2rem 1.5rem;
            font-weight: 600;
        }

        .threshold-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .threshold-item:last-child {
            border-bottom: none;
        }

        .threshold-category {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .threshold-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .threshold-unit {
            background-color: rgba(241, 245, 249, 0.6);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Table styles */
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background-color: rgba(241, 245, 249, 0.6);
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid var(--gray-light);
            padding: 1rem 1.2rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1rem 1.2rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-light);
            font-size: 0.95rem;
        }

        .table tr:hover {
            background-color: rgba(241, 245, 249, 0.4);
        }

        /* Badge styles */
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .badge-low {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .badge-medium {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--warning-dark);
        }

        .badge-high {
            background-color: rgba(34, 197, 94, 0.15);
            color: var(--success-dark);
        }

        /* Chart container */
        .chart-container {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            height: 350px;
            box-shadow: inset 0 0 8px rgba(0,0,0,0.02);
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
            padding-left: 45px;
            border-radius: 10px;
            border: 1px solid var(--gray-light);
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            font-size: 0.9rem;
            padding-top: 0.7rem;
            padding-bottom: 0.7rem;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            border-color: var(--primary);
        }

        /* Button styling */
        .btn-light {
            background-color: white;
            border: 1px solid var(--gray-light);
            color: var(--primary);
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            transition: all 0.2s;
        }

        .btn-light:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Footer styling */
        .footer {
            background-color: var(--dark);
            color: rgba(255, 255, 255, 0.8);
            padding: 1.25rem 0;
            position: fixed;
            bottom: 0;
            width: 100%;
            z-index: 10;
        }

        /* Animation for low stock pulse */
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .pulse-warning {
            animation: pulse 2s infinite;
        }

        /* For the legend below chart */
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .chart-legend-item {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 500;
        }

        .chart-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            margin-right: 8px;
        }

        /* Header username and date badges */
        .header-badge {
            background-color: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            margin-left: 0.8rem;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .header-badge i {
            margin-right: 0.5rem;
        }

        /* Sidebar toggle button */
        .sidebar-toggler {
            background-color: transparent !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            color: white !important;
            border-radius: 8px;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin-right: 1rem;
        }

        .sidebar-toggler:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Status dot indicators */
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-dot-low {
            background-color: var(--danger);
        }

        .status-dot-medium {
            background-color: var(--warning);
        }

        .status-dot-high {
            background-color: var(--success);
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 d-flex align-items-center">

                    <h2 class="mb-0" style="color: white; font-weight: bold;"><i class="fas fa-boxes me-2"></i>Inventory Overview</h2>
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
            <div class="col-md-8">
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
                                    ORDER BY i.current_quantity ASC";
                                    $result = $conn->query($sql);

                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $quantity = intval($row['current_quantity']);
                                            $unit = $row['unit'];
                                            $status = getStockStatus($quantity, $unit);
                                            $statusClass = '';
                                            $dotClass = '';

                                            if ($status == 'Low') {
                                                $statusClass = 'badge-low pulse-warning';
                                                $dotClass = 'status-dot-low';
                                            } elseif ($status == 'Medium') {
                                                $statusClass = 'badge-medium';
                                                $dotClass = 'status-dot-medium';
                                            } else {
                                                $statusClass = 'badge-high';
                                                $dotClass = 'status-dot-high';
                                            }

                                            echo "<tr data-id='" . htmlspecialchars($row["item_id"]) . "'>";
                                            echo "<td>" . htmlspecialchars($row["item_name"]) . "</td>";
                                            echo "<td>Rs." . formatCurrency($row["price_per_unit"]) . "/" . htmlspecialchars($unit) . "</td>";
                                            echo "<td>" . $quantity . " " . htmlspecialchars($unit) . "</td>";
                                            echo "<td><span class='badge " . $statusClass . "'><span class='status-dot " . $dotClass . "'></span>" . $status . "</span></td>";
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
            <div class="col-md-4">
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
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card stock-thresholds-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Stock Level Thresholds</h5>
                    </div>
                    <div class="card-body">
                        <!-- High Stock Thresholds -->
                        <div class="threshold-item">
                            <div class="threshold-category">
                                <span class="status-dot status-dot-high me-2"></span>
                                High Stock
                            </div>
                            <div class="threshold-details">
                                <div class="threshold-unit">
                                    <i class="fas fa-weight me-1"></i> KG: ≥ 40kg
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-box me-1"></i> Packets: ≥ 20 packets
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-balance-scale me-1"></i> Gram: ≥ 1000g
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-wine-bottle me-1"></i> Bottle: ≥ 40 bottles
                                </div>
                            </div>
                        </div>

                        <!-- Medium Stock Thresholds -->
                        <div class="threshold-item">
                            <div class="threshold-category">
                                <span class="status-dot status-dot-medium me-2"></span>
                                Medium Stock
                            </div>
                            <div class="threshold-details">
                                <div class="threshold-unit">
                                    <i class="fas fa-weight me-1"></i> KG: 5-39kg
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-box me-1"></i> Packets: 5-19 packets
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-balance-scale me-1"></i> Gram: 100-999g
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-wine-bottle me-1"></i> Bottle: 5-39 bottles
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Thresholds -->
                        <div class="threshold-item">
                            <div class="threshold-category">
                                <span class="status-dot status-dot-low me-2"></span>
                                Low Stock (Need to Order)
                            </div>
                            <div class="threshold-details">
                                <div class="threshold-unit">
                                    <i class="fas fa-weight me-1"></i> KG: < 5kg
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-box me-1"></i> Packets: < 5 packets
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-balance-scale me-1"></i> Gram: < 100g
                                </div>
                                <div class="threshold-unit">
                                    <i class="fas fa-wine-bottle me-1"></i> Bottle: < 5 bottles
                                </div>
                            </div>
                        </div>

                        <!-- General Notes -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6 class="mb-2"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Notes</h6>
                            <ul class="mb-0 ps-3">
                                <li>Items in <span class="text-danger fw-bold">Low Stock</span> require immediate attention</li>
                                <li>Maintain <span class="text-success fw-bold">High Stock</span> levels for popular items</li>
                                <li>Check stock levels weekly to ensure adequate inventory</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                            // Custom query using the getStockStatus function logic in PHP
                            $lowCount = 0;
                            $mediumCount = 0;
                            $highCount = 0;

                            $sql = "SELECT current_quantity, unit FROM items";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    $status = getStockStatus($row['current_quantity'], $row['unit']);
                                    if ($status == 'Low') $lowCount++;
                                    else if ($status == 'Medium') $mediumCount++;
                                    else $highCount++;
                                }
                            }

                            echo "$lowCount, $mediumCount, $highCount";
                            ?>
                        ],
                        backgroundColor: [
                            '#ff6b6b',       // danger-color for Low Stock
                            '#ffbe0b',       // warning-color for Medium Stock
                            '#2ecc71'        // accent-color for High Stock
                        ],
                        borderWidth: 3,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.8)',
                            titleFont: {
                                size: 14,
                                family: 'Nunito'
                            },
                            bodyFont: {
                                size: 13,
                                family: 'Nunito'
                            },
                            padding: 12,
                            cornerRadius: 8,
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
                        animateRotate: true,
                        duration: 1500,
                        easing: 'easeOutQuart'
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
            // Make table rows clickable
            const rows = document.querySelectorAll('#itemsTable tbody tr');
            rows.forEach(row => {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-id');
                    if (itemId) {
                        showNotification('Item details would open in a complete version', 'info');
                    }
                });
            });
        }

        function checkLowStock() {
            // Check for low stock items
            const lowStockItems = <?php
                $sql = "SELECT item_name, current_quantity, unit FROM items WHERE current_quantity < 5";
                $result = $conn->query($sql);
                $items = [];
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $items[] = $row;
                    }
                }
                echo json_encode($items);
            ?>;

            if (lowStockItems.length > 0) {
                let message = 'The following items are at low stock levels and need immediate attention:<ul>';
                lowStockItems.forEach(item => {
                    message += `<li>${item.item_name} - ${item.current_quantity} ${item.unit}</li>`;
                });
                message += '</ul>';
                showNotification(message, 'warning');
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `position-fixed bottom-0 end-0 p-3`;
            notification.style.zIndex = '11';

            let bgColor, textColor;
            switch(type) {
                case 'warning':
                    bgColor = 'var(--warning)';
                    textColor = 'var(--dark)';
                    break;
                case 'error':
                    bgColor = 'var(--danger)';
                    textColor = 'white';
                    break;
                case 'success':
                    bgColor = 'var(--success)';
                    textColor = 'white';
                    break;
                default:
                    bgColor = 'var(--info)';
                    textColor = 'white';
            }

            const alert = document.createElement('div');
            alert.className = `alert alert-dismissible fade show`;
            alert.role = 'alert';
            alert.style.backgroundColor = bgColor;
            alert.style.color = textColor;
            alert.style.borderRadius = '10px';
            alert.style.boxShadow = '0 8px 16px rgba(0,0,0,0.15)';
            alert.style.padding = '14px 20px';
            alert.style.border = 'none';
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
