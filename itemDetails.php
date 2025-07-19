<?php
// Database connection configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'mywebsite';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get aggregated item quantities by name and unit size
function getAggregatedItemQuantities($pdo) {
    $query = "
        SELECT 
            MIN(item_name) as item_name,
            unit_size,
            SUM(current_quantity) as total_quantity,
            GROUP_CONCAT(DISTINCT unit ORDER BY unit ASC) as units,
            GROUP_CONCAT(DISTINCT type ORDER BY type ASC) as types,
            MIN(price_per_unit) as min_price,
            MAX(price_per_unit) as max_price,
            COUNT(DISTINCT CASE WHEN supplier_id IS NOT NULL THEN supplier_id END) as supplier_count,
            GROUP_CONCAT(DISTINCT CASE WHEN supplier_id IS NOT NULL THEN supplier_id END ORDER BY supplier_id ASC) as supplier_ids
        FROM items 
        GROUP BY LOWER(TRIM(item_name)), unit_size
        ORDER BY MIN(item_name) ASC, unit_size ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get supplier details for hover
function getSupplierDetails($pdo, $supplierIds) {
    if (empty($supplierIds)) return [];
    
    $placeholders = str_repeat('?,', count($supplierIds) - 1) . '?';
    $query = "SELECT supplier_id as id, supplier_name as name, contact_number as contact, nic FROM supplier WHERE supplier_id IN ($placeholders)";
    $stmt = $pdo->prepare($query);
    $stmt->execute($supplierIds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get the data
$items = getAggregatedItemQuantities($pdo);

// Get supplier details for each item
foreach ($items as &$item) {
    if (!empty($item['supplier_ids'])) {
        $supplierIds = explode(',', $item['supplier_ids']);
        $item['supplier_details'] = getSupplierDetails($pdo, $supplierIds);
    } else {
        $item['supplier_details'] = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Quantity Display</title>
     <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --primary-color:rgb(43, 70, 132);
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
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(to right, #0f2f8fff, #0f1429ff);
            color: white;
            padding: 40px 30px 30px 30px;
            text-align: center;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
        }
        .header h1 {
            font-size: 2.7em;
            font-weight: 600;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        .header p {
            font-size: 1.15em;
            opacity: 0.95;
            font-weight: 400;
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
            box-shadow: 0 0 0 0 rgba(20, 48, 170, 0.5), 0 5px 20px rgba(0,0,0,0.2);
            animation: glowPulse 2s infinite alternate;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        .search-container {
            padding: 25px 30px;
            background: #f8f9fa;
            border-bottom: 1.5px solid #e0e0e0;
        }
        .search-box {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }
        .search-input {
            width: 100%;
            padding: 15px 20px;
            font-size: 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 25px;
            outline: none;
            transition: border-color 0.3s;
            background: #fff;
            font-family: inherit;
        }
        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.3em;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            gap: 30px;
            padding: 25px 30px;
            background: #f8f9fa;
            border-bottom: 1.5px solid #e0e0e0;
        }
        .stat-card {
            text-align: center;
            padding: 18px 10px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.07);
            min-width: 150px;
            transition: box-shadow 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.13);
        }
        .stat-number {
            font-size: 2.1em;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 1em;
            margin-top: 5px;
            font-weight: 500;
        }
        .table-container {
            overflow-x: auto;
            padding: 0 30px 30px 30px;
        }
        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .items-table th,
        .items-table td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1.5px solid #e0e0e0;
            font-size: 20px; /* Increased base font size for all table cells */
            color: #333;
        }
        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #e0e0e0;
            font-size: 22px; /* Slightly larger for headers */
        }
        .items-table tr:hover {
            background-color: #f3f6ff;
            transition: background 0.2s;
        }
        .quantity-display {
            font-weight: 600;
            font-size: 24px; /* Increased from 15px */
            padding: 8px 12px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }
        .quantity-display.low {
            background: #fff5f5;
            border: 1px solid #fc8181;
            color: #c53030;
            font-weight: 700;
        }
        .quantity-display.out {
            background: #fff5f5;
            border: 1px solid #fc8181;
            color: #c53030;
            font-weight: 700;
        }
        .unit-item {
            background: #f8f9fa;
            color: #495057;
            padding: 6px 10px; /* Increased padding */
            border-radius: 4px;
            font-size: 16px; /* Increased from 13px */
            margin-right: 6px;
            margin-bottom: 3px;
            display: inline-block;
            border: 1px solid #dee2e6;
            font-weight: 500;
        }
        .type-item {
            background: #f8f9fa;
            color: #495057;
            padding: 5px 10px; /* Increased padding */
            border-radius: 4px;
            font-size: 15px; /* Increased from 12px */
            text-transform: uppercase;
            margin-right: 4px;
            margin-bottom: 2px;
            display: inline-block;
            border: 1px solid #dee2e6;
            font-weight: 500;
        }
        .price-range {
            font-size: 18px; /* Increased from 14px */
            color: #495057;
            font-weight: 500;
        }
        .supplier-info {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        .supplier-count {
            background: #17a2b8;
            color: white;
            padding: 6px 12px; /* Increased padding */
            border-radius: 12px;
            font-size: 16px; /* Increased from 13px */
            font-weight: 500;
            display: inline-block;
        }
        .supplier-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px; /* Increased from 13px */
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            margin-bottom: 5px;
            min-width: 200px;
            white-space: normal;
            width: max-content;
            max-width: 300px;
        }
        .supplier-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.9);
        }
        .supplier-info:hover .supplier-tooltip {
            opacity: 1;
            visibility: visible;
        }
        .supplier-item {
            margin-bottom: 8px;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .supplier-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .supplier-name {
            font-weight: 600;
            color: #fff;
            font-size: 14px; /* Increased font size */
        }
        .supplier-contact {
            font-size: 12px; /* Increased from 11px */
            color: #ccc;
            margin-top: 2px;
        }
        .no-items {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 1.2em; /* Increased from 1.1em */
        }
        .loading {
            text-align: center;
            padding: 50px;
            color: #999;
            font-size: 1.2em; /* Increased from 1.1em */
        }
        @media (max-width: 768px) {
            .stats {
                flex-direction: column;
                gap: 10px;
                padding: 15px 10px;
            }
            .table-container {
                padding: 0 10px 10px 10px;
            }
            .items-table th,
            .items-table td {
                padding: 10px 5px;
                font-size: 16px; /* Increased mobile font size from 14px */
            }
            .items-table th {
                font-size: 18px; /* Increased mobile header size from 16px */
            }
            .header h1 {
                font-size: 2em;
            }
            .supplier-tooltip {
                position: fixed;
                left: 10px !important;
                right: 10px;
                transform: none !important;
                width: auto !important;
                max-width: none !important;
            }
            .quantity-display {
                font-size: 20px; /* Adjusted for mobile */
            }
            .unit-item {
                font-size: 14px; /* Adjusted for mobile */
            }
            .type-item {
                font-size: 13px; /* Adjusted for mobile */
            }
            .price-range {
                font-size: 16px; /* Adjusted for mobile */
            }
            .supplier-count {
                font-size: 14px; /* Adjusted for mobile */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Item Inventory</h1>
            <p>Consolidated quantity display by item name and unit size</p>
        </div>

        <div class="search-container">
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Search items...">
                <span class="search-icon">🔍</span>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="totalItems"><?php echo count($items); ?></div>
                <div class="stat-label" style="font-size: 20px; color:black; font-weight:bold;">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="totalQuantity">
                    <?php echo array_sum(array_column($items, 'total_quantity')); ?>
                </div>
                <div class="stat-label" style="font-size: 20px; color:black; font-weight:bold;">Total Quantity</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="lowStockItems">
                    <?php echo count(array_filter($items, function($item) { return $item['total_quantity'] <= 10; })); ?>
                </div>
                <div class="stat-label" style="font-size: 20px; color:black; font-weight:bold;">Low Stock</div>
            </div>
        </div>
        <br>
        <div class="table-container">
            <table class="items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="font-size: 22px; color:black; font-weight:bold;">Item Name</th>
                        <th style="font-size: 22px; color:black; font-weight:bold;">Total Quantity</th>
                        <th style="font-size: 22px; color:black; font-weight:bold;">Units</th>
                        <th style="font-size: 22px; color:black; font-weight:bold;">Types</th>
                        <th style="font-size: 22px; color:black; font-weight:bold;">Price Range</th>
                        <th style="font-size: 22px; color:black; font-weight:bold;">Suppliers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" class="no-items">No items found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $quantity = $item['total_quantity'];
                                    $badgeClass = '';
                                    if ($quantity == 0) {
                                        $badgeClass = 'out';
                                    } elseif ($quantity <= 10) {
                                        $badgeClass = 'low';
                                    }
                                    ?>
                                    <span class="quantity-display <?php echo $badgeClass; ?>">
                                        <?php echo number_format($quantity); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // Display unit size first
                                    echo '<span class="unit-item">' . htmlspecialchars($item['unit_size']) . '</span>';
                                    
                                    // Then display units
                                    $units = explode(',', $item['units']);
                                    foreach ($units as $unit) {
                                        echo '<span class="unit-item">' . htmlspecialchars(trim($unit)) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $types = explode(',', $item['types']);
                                    foreach ($types as $type) {
                                        echo '<span class="type-item">' . htmlspecialchars(trim($type)) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td class="price-range">
                                    <?php if ($item['min_price'] == $item['max_price']): ?>
                                        Rs. <?php echo number_format($item['min_price'], 2); ?>
                                    <?php else: ?>
                                        Rs. <?php echo number_format($item['min_price'], 2); ?> - Rs. <?php echo number_format($item['max_price'], 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="supplier-info">
                                        <span class="supplier-count">
                                            <?php 
                                            $supplierCount = (int)$item['supplier_count'];
                                            if ($supplierCount == 0) {
                                                echo "No supplier";
                                            } else {
                                                echo $supplierCount . ' supplier' . ($supplierCount > 1 ? 's' : '');
                                            }
                                            ?>
                                        </span>
                                        <?php if (!empty($item['supplier_details'])): ?>
                                            <div class="supplier-tooltip">
                                                <?php foreach ($item['supplier_details'] as $supplier): ?>
                                                    <div class="supplier-item">
                                                        <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                                        <div class="supplier-contact">
                                                            <?php if (!empty($supplier['contact'])): ?>
                                                                📞 <?php echo htmlspecialchars($supplier['contact']); ?>
                                                            <?php endif; ?>
                                                            <?php if (!empty($supplier['nic'])): ?>
                                                                <br>🆔 <?php echo htmlspecialchars($supplier['nic']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <a href="home.php" class="btn btn-primary floating-btn animate__animated animate__fadeInUp">
        <i class="fas fa-home"></i>
    </a>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('itemsTable');
            const rows = table.getElementsByTagName('tr');
            
            let visibleRows = 0;
            
            for (let i = 1; i < rows.length; i++) { // Skip header row
                const row = rows[i];
                const itemName = row.cells[0].textContent.toLowerCase();
                const unitsInfo = row.cells[2].textContent.toLowerCase();
                
                if (itemName.includes(searchTerm) || unitsInfo.includes(searchTerm)) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            }
            
            // Update total items count
            document.getElementById('totalItems').textContent = visibleRows;
        });

        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Add loading state
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('itemsTable');
            if (table.rows.length === 1) { // Only header row
                const tbody = table.getElementsByTagName('tbody')[0];
                tbody.innerHTML = '<tr><td colspan="6" class="loading">Loading items...</td></tr>';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
</body>
</html>