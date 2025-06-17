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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
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
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
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
            margin: 25px 30px 0;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #bdc3c7;
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
            table-layout: fixed;
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
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
            transition: background-color 0.3s ease;
            word-wrap: break-word;
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
            min-width: 100px;
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
        }

        .price-cell {
            color: #27ae60;
            font-weight: bold;
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
        }

        .available-details {
            color: #27ae60;
            font-weight: 500;
        }

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
                    <colgroup>
    <col style="width: 100px"> <!-- Status -->
   
    <col> <!-- Item Name -->
    <col style="width: 120px"> <!-- Quantity -->
    <col style="width: 80px"> <!-- Unit -->
    <col style="width: 120px"> <!-- Price -->
    <col> <!-- Supplier -->
    <col> <!-- Details -->
</colgroup>
                    <thead>
    <tr>
        <th>Status</th>
    
        <th>Item Name</th>
        <th>Quantity</th>
        <th>Unit</th>
        <th>Price</th>
        <th>Supplier</th>
        <th>Details</th>
    </tr>
</thead>
                    <tbody>
                        <?php if (empty($availableItems)): ?>
                            <tr><td colspan="7" class="no-data">No available items found</td></tr>
                        <?php else: ?>
                            <?php foreach ($availableItems as $item): ?>
    <tr class="available-row">
        <td>
            <?php if ($item['status'] === 'available'): ?>
                <span class="status-badge status-available"><?= ucfirst($item['status']) ?></span>
            <?php else: ?>
                <span class="status-badge status-low"><?= ucfirst($item['status']) ?></span>
            <?php endif; ?>
        </td>
        
        <td><?= htmlspecialchars($item['item_name']) ?></td>
        <td class="quantity-cell"><?= number_format($item['current_quantity']) ?></td>
        <td><?= strtoupper($item['unit']) ?></td>
        <td class="price-cell">Rs. <?= number_format($item['price_per_unit'], 2) ?></td>
        <td><?= htmlspecialchars($item['supplier_name'] ?? 'N/A') ?></td>
        <td>
            <div class="available-details">
                <div>✔ Ready to sell</div>
                <div>Last updated: <?= date('M d, Y') ?></div>
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
                    <colgroup>
                        <col style="width: 120px"> <!-- Status -->
                        <col> <!-- Item Name -->
                        <col style="width: 120px"> <!-- Quantity -->
                        <col style="width: 80px"> <!-- Unit -->
                        <col style="width: 120px"> <!-- Price -->
                        <col> <!-- Supplier -->
                        <col> <!-- Details -->
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Price</th>
                            <th>Supplier</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($soldItems)): ?>
                            <tr><td colspan="7" class="no-data">No sold items found</td></tr>
                        <?php else: ?>
                            <?php foreach ($soldItems as $item): ?>
                                <tr class="sold-row">
                                    <td>
                                        <span class="status-badge status-sold">Sold</span>
                                    </td>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="quantity-cell"><?= number_format($item['sold_quantity']) ?></td>
                                    <td><?= strtoupper($item['unit']) ?></td>
                                    <td class="price-cell">Rs. <?= number_format($item['price_per_unit'], 2) ?></td>
                                    <td><?= htmlspecialchars($item['supplier_name'] ?? 'N/A') ?></td>
                                    <td>
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
</html>