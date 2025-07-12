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
function getStockStatus($quantity, $unit, $type = '') {
    $unit = strtolower($unit);
    $type = strtolower($type);

    // Special rules for bags/bottles (kg), box/sachet/bars (g), bottle (l/ml)
    if ($unit == 'kg' && ($type == 'bags' || $type == 'bottles')) {
        if ($quantity <= 10) return 'Low';
        if ($quantity < 30) return 'Medium';
        return 'High';
    } else if ($unit == 'g' && ($type == 'box' || $type == 'sachet' || $type == 'bars' || $type == 'packets ')) {
        if ($quantity <= 10) return 'Low';
        if ($quantity > 10 && $quantity <= 30) return 'Medium';
        return 'High';
    } else if ($unit == 'g' || $unit == 'g' || $type == 'packets') {
        if ($quantity <= 10) return 'Low';
        if ($quantity > 10 && $quantity <= 30) return 'Medium';
        return 'High';
    } else if (($unit == 'l' || $unit == 'ml') && $type == 'bottle') {
        if ($quantity <= 10) return 'Low';
        if ($quantity < 30) return 'Medium';
        return 'High';
    }

    // General rules for other types/units
    if ($unit == 'kg') {
        if ($quantity < 5) return 'Low';
        if ($quantity < 20) return 'Medium';
        return 'High';
    } else if ($unit == 'packet' || $unit == 'packets' || $type == 'packets') {
        if ($quantity < 5) return 'Low';
        if ($quantity < 10) return 'Medium';
        return 'High';
    } else if ($unit == 'g' || $unit == 'gram' || $unit == 'grams') {
        if ($quantity < 100) return 'Low';
        if ($quantity < 500) return 'Medium';
        return 'High';
    } else if ($unit == 'bottle' || $unit == 'bottles' || $type == 'bottle') {
        if ($quantity < 5) return 'Low';
        if ($quantity < 20) return 'Medium';
        return 'High';
    } else if ($type == 'bags' || $type == 'box' || $type == 'sachet' || $type == 'bars' || $type == 'other') {
        if ($quantity < 5) return 'Low';
        if ($quantity < 10) return 'Medium';
        return 'High';
    } else {
        // Default case for other units/types
        if ($quantity < 5) return 'Low';
        if ($quantity < 10) return 'Medium';
        return 'High';
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
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 0 rgba(102,126,234,0.5), 0 5px 20px rgba(0,0,0,0.2);
            animation: glowPulse 2s infinite alternate;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
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

                    <div class="w-100 d-flex justify-content-center">
                        <div class="w-100 d-flex justify-content-center">
                            <h2 class="mb-0 text-center w-100" style="color: white; font-weight: bold; font-size: 38px;"><i class="fas fa-boxes me-2"></i>Inventory Overview</h2>
                        </div>
                    </div>
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
    <div class="container" style="max-width:1800px; padding:60px 40px; background:rgba(255,255,255,0.97); border-radius:18px; box-shadow:0 18px 40px rgba(0,0,0,0.22); margin:30px auto;">
        
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card current-inventory-card" style="background:rgba(255,255,255,0.97); border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,0.08);">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background:white; color:#1e293b; border-bottom:1px solid #e2e8f0; font-weight:600; font-size:1.3em;">
                        <span style="font-size: 30px;"><i class="fas fa-box-open me-2"></i>Current Inventory</span>
                        <a href="display_purchase_details.php" class="btn btn-primary" style="padding:12px 28px; border-radius:10px; font-size:1.1em;">View All</a>
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
                                        <th style="font-size:20px;">Item Name</th>
                                        <th style="font-size:20px;">Price/Unit</th>
                                        <th style="font-size:20px;">Qty (Unit)</th>
                                        <th style="font-size:20px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT i.item_id, i.item_code, i.item_name, i.price_per_unit, i.current_quantity, s.supplier_name, i.unit, i.type FROM items i LEFT JOIN supplier s ON i.supplier_id = s.supplier_id ORDER BY i.current_quantity ASC";
                                    $result = $conn->query($sql);
                                    if ($result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            $quantity = intval($row['current_quantity']);
                                            $unit = $row['unit'];
                                            $type = isset($row['type']) ? $row['type'] : '';
                                            $status = getStockStatus($quantity, $unit, $type);
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
                                            echo "<td style='font-size:20px;'>" . htmlspecialchars($row["item_name"]) . "</td>";
                                            echo "<td style='font-size:20px;'>Rs." . formatCurrency($row["price_per_unit"]) . "/unit" . "</td>";
                                            echo "<td style='font-size:20px;'>" . $quantity . (strtolower($unit) == 'kg' ? 'kg' : '') . (!empty($type) ? " <span class='text-muted' style='font-size:13px;'>(" . htmlspecialchars($type) . ")</span>" : "") . "</td>";
                                            echo "<td style='font-size:20px;'><span class='badge " . $statusClass . "'><span class='status-dot " . $dotClass . "'></span>" . $status . "</span></td>";
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
                <div class="card inventory-distribution-card" style="background:rgba(255,255,255,0.97); border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,0.08);">
                    <div class="card-header" style="background:white; color:#1e293b; border-bottom:1px solid #e2e8f0; font-weight:600; font-size:1.3em;">
                        <span style="font-size:30px;"><i class="fas fa-chart-pie me-2"></i>Inventory Distribution</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="chart-legend-item">
                                <span class="chart-legend-color" style="background-color: var(--danger);"></span>
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
                <div class="card stock-thresholds-card" style="background:rgba(255,255,255,0.97); border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,0.08);">
                    <div class="card-header" style="background:white; color:#1e293b; border-bottom:1px solid #e2e8f0; font-weight:600; font-size:1.3em;">
                        <span style="font-size: 30px;"><i class="fas fa-info-circle me-2"></i>Stock Level Thresholds</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <table class="table table-bordered table-sm mb-0" style="background: #f8fafc; font-size:1.15em;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="font-size: 20px;">Unit/Type</th>
                                        <th style="font-size: 20px;">Low Stock</th>
                                        <th style="font-size: 20px;">Medium Stock</th>
                                        <th style="font-size: 20px;">High Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="font-size: 20px;"><i class="fas fa-weight me-1"></i> KG</td>
                                        <td style="font-size: 20px;">&le; 10 units</td>
                                        <td style="font-size: 20px;">11 - 29 units</td>
                                        <td style="font-size: 20px;">&ge; 30 units</td>
                                    </tr>
                                    <tr>
                                        <td style="font-size: 20px;"><i class="fas fa-box me-1"></i> L or ml</td>
                                        <td style="font-size: 20px;">&le; 10 units</td>
                                        <td style="font-size: 20px;">11 - 29 units</td>
                                        <td style="font-size: 20px;">&ge; 30 units</td>
                                    </tr>
                                    <tr>
                                        <td style="font-size: 20px;"><i class="fas fa-weight-hanging me-1"></i> Grams (boxes/sachets/bars/packets)</td>
                                        <td style="font-size: 20px;">&le; 10 units</td>
                                        <td style="font-size: 20px;">11 - 29 units</td>
                                        <td style="font-size: 20px;">&ge; 30 units</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 p-3 bg-light rounded"><br>
                            <h6 class="mb-2" style="font-size:30px;"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Important Notes</h6>
                            <br>
                            <ul class="mb-0 ps-3" style="font-size:1.05em;">
                                <li style="font-size: 20px;">Items in <span class="text-danger fw-bold">Low Stock</span> require immediate attention</li>
                                <li style="font-size: 20px;">Maintain <span class="text-success fw-bold">High Stock</span> levels for popular items</li>
                                <li style="font-size: 20px;">Check stock levels weekly to ensure adequate inventory</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <a href="home.php" class="btn btn-primary floating-btn animate__animated animate__fadeInUp">
        <i class="fas fa-home"></i>
    </a>

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
                            // Use type in getStockStatus for accurate distribution
                            $lowCount = 0;
                            $mediumCount = 0;
                            $highCount = 0;
                            $sql = "SELECT current_quantity, unit, type FROM items";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    $type = isset($row['type']) ? $row['type'] : '';
                                    $status = getStockStatus($row['current_quantity'], $row['unit'], $type);
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
                    message += `<li>${item.item_name} - ${item.current_quantity}${item.unit.toLowerCase() == 'kg' ? 'kg' : ''}</li>`;
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