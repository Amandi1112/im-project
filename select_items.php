<?php
session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get supplier ID from query parameter
$supplierId = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : null;
$returnUrl = isset($_GET['return_url']) ? $_GET['return_url'] : 'purchase_items.php';

// Debug information (remove after testing) - MOVED AFTER VARIABLE DEFINITIONS
error_log("select_items.php - Supplier ID: " . $supplierId);
error_log("select_items.php - Supplier Name from URL: " . (isset($_GET['supplier_name']) ? $_GET['supplier_name'] : 'Not provided'));
error_log("select_items.php - Return URL: " . $returnUrl);

if (!$supplierId) {
    die("Supplier ID is required");
}

// Get supplier name - first try from URL parameter, then from database
$supplierName = isset($_GET['supplier_name']) ? $_GET['supplier_name'] : '';

if (empty($supplierName)) {
    $stmt = $conn->prepare("SELECT supplier_name FROM supplier WHERE supplier_id = ?");
    $stmt->bind_param("s", $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $supplierName = $row['supplier_name'];
    }
}
// Get items for this supplier
$items = [];
$stmt = $conn->prepare("SELECT item_id, item_name, item_code, price_per_unit, current_quantity, unit 
                       FROM items 
                       WHERE supplier_id = ? 
                       ORDER BY item_name");
$stmt->bind_param("s", $supplierId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selected_items'])) {
    $_SESSION['selected_items'] = [
        'supplier_id' => $supplierId,
        'supplier_name' => $supplierName,
        'items' => []
    ];
    
    // Get details for selected items
    $selectedIds = $_POST['selected_items'];
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $sql = "SELECT item_id, item_name, price_per_unit, unit FROM items WHERE item_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($selectedIds));
    $stmt->bind_param($types, ...$selectedIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $_SESSION['selected_items']['items'][] = $row;
    }
    
    // Redirect back to the original page
    header("Location: $returnUrl");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Items to Purchase</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, rgb(208, 212, 232) 0%, rgb(223, 245, 254) 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        h1 {
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
            position: relative;
            padding-bottom: 10px;
        }
        
        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: #667eea;
            border-radius: 3px;
        }
        
        .supplier-info {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .items-list {
            margin-top: 20px;
        }
        
        .item-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .item-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .item-checkbox {
            margin-right: 15px;
        }
        
        .item-details {
            flex-grow: 1;
        }
        
        .item-name {
            font-weight: 500;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .action-buttons {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(to right, #6c757d, #495057);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(108, 117, 125, 0.4);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .item-card {
                flex-direction: column;
                align-items: flex-start;
            }
        
            .item-checkbox {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Select Items to Purchase</h1>
        
        <div class="supplier-info">
    <h2><?php echo !empty($supplierName) ? htmlspecialchars($supplierName) : 'Supplier ID: ' . htmlspecialchars($supplierId); ?></h2>
    <p>Supplier ID: <?php echo htmlspecialchars($supplierId); ?></p>
    <?php if (empty($supplierName)): ?>
        <p><em>Note: Supplier name could not be retrieved from database</em></p>
    <?php endif; ?>
</div>
        
        <?php if (empty($items)): ?>
            <div class="alert alert-info">
                No items found for this supplier.
            </div>
        <?php else: ?>
            <form method="post" action="">
                <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplierId); ?>">
                <input type="hidden" name="supplier_name" value="<?php echo htmlspecialchars($supplierName); ?>">
                
                <div class="items-list">
                    <?php foreach ($items as $item): ?>
                        <div class="item-card">
                            <label class="item-checkbox">
                                <input type="checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($item['item_id']); ?>">
                            </label>
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="item-meta">
                                    <span>Code: <?php echo htmlspecialchars($item['item_code']); ?></span>
                                    <span>Current Price: Rs.<?php echo number_format($item['price_per_unit'], 2); ?></span>
                                    <span>Current Qty: <?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-secondary" onclick="preserveSupplierState()">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Add Selected Items
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <script>
function preserveSupplierState() {
    // This function can be used if you need to preserve any additional state
    // when going back without selecting items
    console.log('Preserving supplier state...');
}
</script>
    
</body>
</html>