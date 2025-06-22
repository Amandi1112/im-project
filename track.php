<?php
// config.php - Database Configuration
class Database {
    private $host = "localhost";
    private $db_name = "mywebsite";
    private $username = "root"; // Change as needed
    private $password = "";     // Change as needed
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Fetch available inventory items
function getAvailableItems($db) {
    $query = "SELECT 
                i.item_id,
                i.item_code,
                i.item_name,
                i.price_per_unit,
                i.current_quantity,
                i.unit,
                s.supplier_name,
                'available' AS status
              FROM items i 
              LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
              WHERE i.current_quantity > 0
              ORDER BY item_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch sold inventory items
function getSoldItems($db) {
    $query = "SELECT 
                i.item_id,
                i.item_code,
                i.item_name,
                p.price_per_unit,
                i.current_quantity,
                p.unit,
                s.supplier_name,
                'sold' AS status,
                p.purchase_id,
                p.member_id,
                p.full_name,
                p.purchase_date,
                p.quantity AS sold_quantity
              FROM purchases p
              JOIN items i ON p.item_id = i.item_id
              LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
              ORDER BY purchase_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get summary statistics
function getSummaryData($db) {
    // Total available items
    $availableQuery = "SELECT COUNT(*) as total_available, SUM(current_quantity) as total_stock 
                       FROM items WHERE current_quantity > 0";
    $stmt = $db->prepare($availableQuery);
    $stmt->execute();
    $available = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total sold items
    $soldQuery = "SELECT COUNT(*) as total_sold, SUM(quantity) as total_quantity_sold, 
                         SUM(total_price) as total_revenue
                  FROM purchases";
    $stmt = $db->prepare($soldQuery);
    $stmt->execute();
    $sold = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'available' => $available,
        'sold' => $sold
    ];
}

$availableItems = getAvailableItems($db);
$soldItems = getSoldItems($db);
$summary = getSummaryData($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Tabbed View</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: rgb(64, 86, 137);
            --primary-light: rgba(10, 36, 99, 0.1);
            --secondary-color: #3e92cc;
            --accent-color: #2ecc71;
            --danger-color: #ff6b6b;
            --warning-color: #ffbe0b;
            --text-main: rgb(0, 0, 0);
            --text-secondary: #6c757d;
            --background-light: rgba(255, 255, 255, 0.98);
            --shadow-light: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --border-radius: 12px;
            --gradient-primary: linear-gradient(135deg, var(--primary-color), #3a0ca3);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-main);
            background: linear-gradient(135deg, rgb(208, 212, 232) 0%, rgb(223, 245, 254) 100%);
            background-image: radial-gradient(circle at 10% 20%, rgba(234, 249, 249, 0.67) 0%, rgba(239, 249, 251, 0.63) 90%);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            padding: 20px 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }

        .summary-card {
           background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

                .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .summary-card:hover::before {
            transform: scaleX(1);
        }

        .summary-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .summary-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }

        .summary-card.available .number {
            color: #27ae60;
        }

        .summary-card.sold .number {
            color: #e74c3c;
        }

        .tabs {
            display: flex;
            background: #ecf0f1;
            border-bottom: 3px solid #bdc3c7;
            margin: 0 30px;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 20px;
            background: none;
            border: none;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #7f8c8d;
            text-align: center;
            position: relative;
        }

        .tab-button.active {
            background: white;
            color: #2c3e50;
        }

        .tab-button.active::after {
            content: "";
            position: absolute;
            bottom: -3px;
            left: 0;
            right: 0;
            height: 3px;
            background: #3498db;
        }

        .tab-button:hover {
            background: #d5dbdb;
        }

        .tab-button .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            margin-left: 8px;
        }

        .tab-button.available.active .badge {
            background: #27ae60;
            color: white;
        }

        .tab-button.sold.active .badge {
            background: #e74c3c;
            color: white;
        }

        .tab-content {
            display: none;
            padding: 0;
        }

        .tab-content.active {
            display: block;
        }

        .search-box {
              position: relative;
            max-width: 500px;
            margin: 30px auto;
            padding: 0 30px;
        }

       .search-box input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid transparent;
            border-radius: 50px;
            font-size: 1rem;
            background: white;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .search-icon {
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.2rem;
        }

        .table-container {
            margin: 0 30px 30px;
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
            transition: background-color 0.3s ease;
            vertical-align: top;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
            min-width: 80px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .status-available {
            background: #27ae60;
            color: white;
        }

        .status-low {
            background: #f39c12;
            color: white;
        }

        .status-sold {
            background: #e74c3c;
            color: white;
        }

        .quantity-cell {
            font-weight: bold;
            font-size: 1.1em;
            text-align: center;
        }

        .price-cell {
            color: #27ae60;
            font-weight: bold;
            text-align: right;
        }

        .unit-cell {
            text-align: center;
            font-weight: 500;
        }

        .no-data {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
            font-size: 1.2em;
        }

        .sold-row {
            background-color: #fff5f5;
            position: relative;
        }

        .sold-row::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background-color: #e74c3c;
        }

        .available-row {
            background-color: #f5fff5;
            position: relative;
        }

        .available-row::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background-color: #27ae60;
        }

        .sold-details {
            background-color: #ffeeee;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 0.9em;
        }

        .available-details {
            color: #27ae60;
            font-weight: 500;
            font-size: 0.9em;
        }

        /* Fixed column widths for better alignment */
        .col-status { width: 120px; }
        .col-name { width: auto; min-width: 200px; }
        .col-quantity { width: 100px; }
        .col-unit { width: 80px; }
        .col-price { width: 120px; }
        .col-supplier { width: 180px; }
        .col-details { width: 220px; }

        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            
            .search-box, .table-container {
                margin-left: 20px;
                margin-right: 20px;
            }
            
            table {
                font-size: 0.9em;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Inventory Management System</h1>
            <p>Tabbed View of Available and Sold Items</p>
        </div>

        <div class="summary-cards">
            <div class="summary-card available">
                <h3>Available Items</h3>
                <div class="number"><?= $summary['available']['total_available'] ?></div>
                <p>Items in Stock</p>
            </div>
            <div class="summary-card available">
                <h3>Total Stock Quantity</h3>
                <div class="number"><?= number_format($summary['available']['total_stock']) ?></div>
                <p>Units Available</p>
            </div>
            <div class="summary-card sold">
                <h3>Total Sales</h3>
                <div class="number"><?= $summary['sold']['total_sold'] ?></div>
                <p>Transactions</p>
            </div>
            <div class="summary-card sold">
                <h3>Total Revenue</h3>
                <div class="number">Rs. <?= number_format($summary['sold']['total_revenue'], 2) ?></div>
                <p>Sales Value</p>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button available active" onclick="showTab('available')">
                Available Items
                <span class="badge"><?= count($availableItems) ?></span>
            </button>
            <button class="tab-button sold" onclick="showTab('sold')">
                Sold Items
                <span class="badge"><?= count($soldItems) ?></span>
            </button>
        </div>

        <!-- Available Items Tab -->
        <div id="available" class="tab-content active">
            <div class="search-box">
                <input type="text" id="searchAvailable" placeholder="Search available items by name, code, or supplier..." onkeyup="searchTable('availableTable', this.value)">
                <span class="search-icon">🔍</span>
            </div>
            
            <div class="table-container">
                <table id="availableTable">
                    <thead>
                        <tr>
                            <th class="col-status" style="width:1px;"></th>
                            <th class="col-name">Status</th>
                            <th class="col-quantity">Item Name</th>
                            <th class="col-unit">Quantity</th>
                            <th class="col-price">Unit</th>
                            <th class="col-supplier">Price</th>
                            <th class="col-details">Supplier</th>
                            <th class="col-code">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($availableItems)): ?>
                            <tr><td colspan="7" class="no-data">No available items found</td></tr>
                        <?php else: ?>
                            <?php foreach ($availableItems as $item): ?>
                                <tr class="available-row">
                                    <td class="col-status">
                                        <span class="status-badge status-available">Available</span>
                                    </td>
                                    <td class="col-name"><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="col-quantity quantity-cell"><?= number_format($item['current_quantity']) ?></td>
                                    <td class="col-unit unit-cell"><?= strtoupper($item['unit']) ?></td>
                                    <td class="col-price price-cell">Rs. <?= number_format($item['price_per_unit'], 2) ?></td>
                                    <td class="col-supplier"><?= htmlspecialchars($item['supplier_name'] ?? 'N/A') ?></td>
                                    <td class="col-details">
                                        <div class="available-details">
                                            <div>✔ Ready to sell</div>
                                            <div>Code: <?= htmlspecialchars($item['item_code']) ?></div>
                                            <div>Updated: <?= date('M d, Y') ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sold Items Tab -->
        <div id="sold" class="tab-content">
            <div class="search-box">
                <input type="text" id="searchSold" placeholder="Search sold items by name, code, customer, or purchase ID..." onkeyup="searchTable('soldTable', this.value)">
                <span class="search-icon">🔍</span>
            </div>
            
            <div class="table-container">
                <table id="soldTable">
                    <thead>
                        <tr>
                            <th class="col-status" style="width:1px;"></th>
                            <th class="col-name">Status</th>
                            <th class="col-quantity">Item Name</th>
                            <th class="col-unit">Quantity</th>
                            <th class="col-price">Unit</th>
                            <th class="col-supplier">Price</th>
                            <th class="col-details">Supplier</th>
                            <th class="col-code">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($soldItems)): ?>
                            <tr><td colspan="7" class="no-data">No sold items found</td></tr>
                        <?php else: ?>
                            <?php foreach ($soldItems as $item): ?>
                                <tr class="sold-row">
                                    <td class="col-status">
                                        <span class="status-badge status-sold">Sold</span>
                                    </td>
                                    <td class="col-name"><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="col-quantity quantity-cell"><?= number_format($item['sold_quantity']) ?></td>
                                    <td class="col-unit unit-cell"><?= strtoupper($item['unit']) ?></td>
                                    <td class="col-price price-cell">Rs. <?= number_format($item['price_per_unit'], 2) ?></td>
                                    <td class="col-supplier"><?= htmlspecialchars($item['supplier_name'] ?? 'N/A') ?></td>
                                    <td class="col-details">
                                        <div class="sold-details">
                                            <div><strong>Purchase #<?= $item['purchase_id'] ?></strong></div>
                                            <div>Customer: <?= htmlspecialchars($item['full_name']) ?></div>
                                            <div>Date: <?= date('M d, Y', strtotime($item['purchase_date'])) ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked button
            event.currentTarget.classList.add('active');
        }

        function searchTable(tableId, searchTerm) {
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            searchTerm = searchTerm.toLowerCase();
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                // Skip "no data" rows
                if (cells.length === 1 && cells[0].classList.contains('no-data')) {
                    continue;
                }
                
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    if (cellText.toLowerCase().indexOf(searchTerm) > -1) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        }

        // Add loading animation and smooth transitions
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.summary-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>