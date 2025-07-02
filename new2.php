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

// Handle returned items from selection page
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selected_items'])) {
    $supplierId = $_POST['supplier_id'];
    $supplierName = $_POST['supplier_name'];
    $selectedItems = $_POST['selected_items'];
    
    // Get item details for selected items
    $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
    $sql = "SELECT item_id, item_name, price_per_unit, unit FROM items WHERE item_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($selectedItems));
    $stmt->bind_param($types, ...$selectedItems);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    
    // Store items in session to add to the form
    $_SESSION['selected_items'] = [
        'supplier_id' => $supplierId,
        'supplier_name' => $supplierName,
        'items' => $items
    ];
    
    // Redirect back to the form
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/**
 * Generates a unique item code based on item name, supplier ID, and date.
 */
function generateItemCode($itemName, $supplierId, $conn) {
    // Create item name prefix (first 3 letters, uppercase)
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $itemName), 0, 3));
    
    // Create supplier ID prefix (first 2 characters, uppercase)
    $supplierPrefix = strtoupper(substr($supplierId, 0, 2));
    
    // Get current date in YYMM format
    $datePart = date('ym');
    
    // Find the latest item with similar prefix to determine the next sequence number
    $sql = "SELECT item_code FROM items WHERE item_code LIKE '{$prefix}{$supplierPrefix}{$datePart}%' ORDER BY item_code DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastCode = $row['item_code'];
        // Extract the numerical sequence part
        $sequence = intval(substr($lastCode, -4)) + 1;
    } else {
        $sequence = 1;
    }
    
    // Format the sequence with leading zeros
    $sequencePart = str_pad($sequence, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $supplierPrefix . $datePart . $sequencePart;
}

/**
 * Retrieves item details based on item name and supplier ID.
 */
function getItemDetails($itemName, $supplierId, $conn) {
    $sql = "SELECT item_id, item_code, price_per_unit, unit_size, current_quantity, unit 
            FROM items 
            WHERE item_name = ? AND supplier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $itemName, $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}

/**
 * Adds a new item to the database.
 */
function addNewItem($itemName, $pricePerUnit, $unitSize, $supplierId, $unit, $conn) {
    $itemCode = generateItemCode($itemName, $supplierId, $conn);
    
    $sql = "INSERT INTO items (item_code, item_name, price_per_unit, unit_size, current_quantity, supplier_id, unit) 
            VALUES (?, ?, ?, ?, 0, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssddss", $itemCode, $itemName, $pricePerUnit, $unitSize, $supplierId, $unit);
    
    if ($stmt->execute()) {
        return [
            'item_id' => $conn->insert_id,
            'item_code' => $itemCode,
            'price_per_unit' => $pricePerUnit,
            'unit_size' => $unitSize,
            'current_quantity' => 0,
            'unit' => $unit
        ];
    } else {
        return null;
    }
}


/**
 * Adds a purchase record and updates the item quantity in the database.
 */
function addPurchase($itemId, $quantity, $pricePerUnit, $unitSize, $purchaseDate, $expireDate, $supplierId, $unit, $conn) {
    $totalPrice = $quantity * $pricePerUnit;
    $totalUnits = $quantity * $unitSize; // Calculate total units
    
    // Start transaction to ensure atomicity
    $conn->begin_transaction();
    
    try {
        // Insert purchase record
        $sql = "INSERT INTO item_purchases (item_id, quantity, price_per_unit, unit_size, total_units, total_price, purchase_date, expire_date, supplier_id, unit) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idddddssss", $itemId, $quantity, $pricePerUnit, $unitSize, $totalUnits, $totalPrice, $purchaseDate, $expireDate, $supplierId, $unit);
        $stmt->execute();
        
        // Update item quantity, price and unit_size in the items table
        $sql = "UPDATE items 
                SET current_quantity = current_quantity + ?, 
                    price_per_unit = ?,
                    unit_size = ?,
                    unit = ?
                WHERE item_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dddsi", $totalUnits, $pricePerUnit, $unitSize, $unit, $itemId);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback transaction in case of any error
        $conn->rollback();
        return false;
    }
}

/**
 * Retrieves all suppliers from the database, ordered by name.
 */
function getSuppliers($conn) {
    $sql = "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name";
    $result = $conn->query($sql);
    $suppliers = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
    }
    
    return $suppliers;
}

/**
 * Get all items for a specific supplier
 */
function getSupplierItems($supplierId, $conn) {
    $sql = "SELECT item_id, item_name, item_code, price_per_unit, unit_size, current_quantity 
            FROM items 
            WHERE supplier_id = ?
            ORDER BY item_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Handle AJAX request to get supplier items
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_supplier_items'])) {
    $supplierId = $_POST['supplier_id'];
    $items = getSupplierItems($supplierId, $conn);
    
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

// Handle AJAX request for item details
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['item_name']) && isset($_POST['supplier_id']) && !isset($_POST['submit_purchases'])) {
    $itemName = trim($_POST['item_name']);
    $supplierId = trim($_POST['supplier_id']);
    
    $item = getItemDetails($itemName, $supplierId, $conn);
    
    $response = ['exists' => false];
    
    if ($item) {
    $response = [
        'exists' => true,
        'item_id' => $item['item_id'],
        'price_per_unit' => $item['price_per_unit'],
        'unit_size' => $item['unit_size'],
        'current_quantity' => $item['current_quantity']
    ];
}
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX supplier search for autocomplete
if (isset($_GET['search_supplier'])) {
    $term = $_GET['search_supplier'];
    $sql = "SELECT supplier_id, supplier_name FROM supplier WHERE supplier_name LIKE ? ORDER BY supplier_name LIMIT 10";
    $stmt = $conn->prepare($sql);
    $likeTerm = "%$term%";
    $stmt->bind_param("s", $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($suppliers);
    exit;
}

/**
 * Process form submission for adding purchases
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_purchases'])) {
    $successCount = 0;
    $errorCount = 0;
    
    // Loop through each supplier section
    foreach ($_POST['supplier'] as $supplierId => $supplierData) {
        // Skip suppliers with no items
        if (!isset($supplierData['items']) || empty($supplierData['items'])) {
            continue;
        }
        
        // Process each item for this supplier
        foreach ($supplierData['items'] as $itemData) {
    $itemName = isset($itemData['item_name']) ? trim($itemData['item_name']) : '';
    $quantity = isset($itemData['quantity']) ? floatval($itemData['quantity']) : 0;
    $pricePerUnit = isset($itemData['price_per_unit']) ? floatval($itemData['price_per_unit']) : 0;
    $unitSize = isset($itemData['unit_size']) ? floatval($itemData['unit_size']) : 1.0;
    $purchaseDate = isset($itemData['purchase_date']) ? $itemData['purchase_date'] : date('Y-m-d');
    $expireDate = isset($itemData['expire_date']) && !empty($itemData['expire_date']) ? $itemData['expire_date'] : null;
    $unit = isset($itemData['unit']) ? $itemData['unit'] : 'other';
    
    // Skip invalid entries
    if (empty($itemName) || $quantity <= 0 || $pricePerUnit <= 0 || $unitSize <= 0) {
        continue;
    }
            // Check if item exists or create new
            $existingItem = getItemDetails($itemName, $supplierId, $conn);
            
            if ($existingItem) {
    $itemId = $existingItem['item_id'];
} else {
    $newItem = addNewItem($itemName, $pricePerUnit, $unitSize, $supplierId, $unit, $conn);
    if ($newItem) {
        $itemId = $newItem['item_id'];
    } else {
        $errorCount++;
        continue;
    }
}

// Add purchase record
if (addPurchase($itemId, $quantity, $pricePerUnit, $unitSize, $purchaseDate, $expireDate, $supplierId, $unit, $conn)) {
    $successCount++;
} else {
    $errorCount++;
}
        }
    }
    
    // Construct message
    $message = "$successCount items added successfully. ";
    if ($errorCount > 0) {
        $message .= "$errorCount items failed to add.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message));
    exit();
}

// Fetch all suppliers for the dropdown list
$suppliers = getSuppliers($conn);

// Get message from redirect if exists
$message = isset($_GET['message']) ? $_GET['message'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Item Purchase</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg,rgb(208, 212, 232) 0%,rgb(223, 245, 254) 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .main-container {
            width: 100%;
            max-width: 1200px;
            padding: 20px;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .form-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }
        
        h2, h3 {
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
            position: relative;
            padding-bottom: 10px;
        }
        
        h2::after, h3::after {
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
        
        h3::after {
            width: 60px;
        }
        
        .supplier-section {
            margin-bottom: 30px;
            padding: 25px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background-color: #f8f9fa;
            position: relative;
        }
        
        .supplier-header {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .supplier-header h3 {
            margin: 0;
            color: white;
            padding: 0;
        }
        
        .supplier-header h3::after {
            display: none;
        }
        
        .item-row {
            margin-bottom: 15px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .item-row:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
            font-size: 14px;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
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
        
        .btn-danger {
            background: linear-gradient(to right, #dc3545, #c82333);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-light {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            color: #495057;
        }
        
        .btn-light:hover {
            background: #e9ecef;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .supplier-actions {
            display: flex;
            gap: 10px;
        }
        
        .alert {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: translateX(150%);
            transition: transform 0.4s ease;
            z-index: 1000;
            color: white;
            font-weight: 500;
        }
        
        .floating-alert.show {
            transform: translateX(0);
        }
        
        .alert-error {
            background: #ff4757;
        }
        
        .alert-success {
            background: #2ed573;
        }
        
        .alert-info {
            background: #70a1ff;
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
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .items-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .items-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .items-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .items-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .items-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .supplier-badge {
            background: linear-gradient(to right, #6c757d, #495057);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-6 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
        }
        
        .col-md-1 { flex: 0 0 8.333333%; max-width: 8.333333%; }
        .col-md-2 { flex: 0 0 16.666667%; max-width: 16.666667%; }
        .col-md-3 { flex: 0 0 25%; max-width: 25%; }
        .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
        .col-md-6 { flex: 0 0 50%; max-width: 50%; }
        
        .mb-3 { margin-bottom: 1rem; }
        
        .remove-supplier {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .remove-supplier:hover {
            background: rgba(220, 53, 69, 0.2);
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }
            
            .supplier-section {
                padding: 15px;
            }
            
            .supplier-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .row [class*="col-md-"] {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
            }
            
            .action-buttons, .supplier-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="form-container">
            <h2>Supplier Item Purchase</h2>
            
            <!-- Display message if there are any success or error messages -->
            <?php if(!empty($message)): ?>
            <div class="alert">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Form for adding items by supplier -->
            <form method="post" action="" id="purchase-form">
                <!-- Supplier selection textbox with autocomplete -->
                <div class="mb-3">
                    <label class="form-label" style="font-size: 17px; font-weight: bold;">Select Supplier</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="supplier-name-input" class="form-control" placeholder="Start typing supplier name..." autocomplete="off" style="flex: 1;">
                        <input type="hidden" id="supplier-id-input">
                        <button type="button" id="add-supplier" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> Add Supplier
                        </button>
                    </div>
                </div>
                
                <!-- Container for supplier sections -->
                <div id="suppliers-container">
                    <!-- Supplier sections will be added here dynamically -->
                </div>
                
                <!-- Submit Button -->
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary" name="submit_purchases" style="font-size: 17px;">
                        <i class="fas fa-save"></i> Submit All Purchases
                    </button>
                    <a href="home.php" class="btn btn-light" style="font-size: 17px;">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                    <a href="display_purchase_details.php" class="btn btn-light" style="font-size: 17px;">
                        <i class="fas fa-eye"></i> View Purchases
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Alert -->
    <div class="floating-alert" id="alert"></div>
    
    <!-- Supplier Section Template (Hidden) -->
    <template id="supplier-template">
        <div class="supplier-section" data-supplier-id="{SUPPLIER_ID}">
            <div class="remove-supplier"><i class="fas fa-times"></i></div>
            <div class="supplier-header">
                <h3>{SUPPLIER_NAME}</h3>
                <div class="supplier-actions">
                    <button type="button" class="btn btn-light add-existing-item">
                        <i class="fas fa-list"></i> Add Existing Item
                    </button>
                    <button type="button" class="btn btn-light add-new-item">
                        <i class="fas fa-plus"></i> Add New Item
                    </button>
                </div>
            </div>
            <div class="items-container">
                <!-- Items for this supplier will be added here -->
            </div>
        </div>
    </template>
    
    <!-- New Item Row Template (Hidden) -->
    <template id="new-item-template">
        <div class="item-row">
            <div class="row">
                <!-- Item Name -->
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Item Name</label>
                        <input type="text" class="form-control item-name" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][item_name]" required>
                    </div>
                </div>
                <!-- Quantity -->
                <!-- Unit Size -->
<div class="col-md-2">
    <div class="mb-3">
        <label class="form-label" style="font-size: 16px; font-weight: bold;">Unit Size</label>
        <input type="number" step="0.01" class="form-control unit-size" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][unit_size]" min="0.01" value="1" required>
    </div>
</div>
                <div class="col-md-1-5">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Unit</label>
                        <select class="form-select unit-select" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][unit]" required>
                            <option value="g">Grams (g)</option>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="packets">Packets</option>
                            <option value="bottles">Bottles</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <!-- Price per Unit -->
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Price/Unit</label>
                        <input type="number" step="0.01" class="form-control price-per-unit" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][price_per_unit]" required>
                    </div>
                </div>
                <!-- Purchase Date -->
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Purchase Date</label>
                        <input type="date" class="form-control" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][purchase_date]" value="{CURRENT_DATE}" required>
                    </div>
                </div>
                <!-- Expire Date -->
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Expire Date</label>
                        <input type="date" class="form-control" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][expire_date]">
                    </div>
                </div>
                <!-- Remove Item Button -->
                <div class="col-md-1">
                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger form-control remove-item"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </template>
    
    <!-- Existing Item Selection Template (Hidden) -->
    <template id="existing-item-template">
        <div class="item-row">
            <div class="row">
                <!-- Item Selection -->
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Select Item</label>
                        <select class="form-select existing-item-select" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][item_name]" required>
                            <option value="">-- Select Item --</option>
                            {ITEM_OPTIONS}
                        </select>
                    </div>
                </div>
                <!-- Quantity -->
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Quantity</label>
                        <input type="number" class="form-control" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][quantity]" min="1" required>
                    </div>
                </div>
                <div class="col-md-1-5">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Unit</label>
                        <select class="form-select unit-select" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][unit]" required>
                            <option value="g">Grams (g)</option>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="packets">Packets</option>
                            <option value="bottles">Bottles</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <!-- Price per Unit -->
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Price/Unit</label>
                        <input type="number" step="0.01" class="form-control price-per-unit" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][price_per_unit]" required>
                    </div>
                </div>
                <!-- Purchase Date -->
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Purchase Date</label>
                        <input type="date" class="form-control" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][purchase_date]" value="{CURRENT_DATE}" required>
                    </div>
                </div>
                <!-- Expire Date -->
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 16px; font-weight: bold;">Expire Date</label>
                        <input type="date" class="form-control" name="supplier[{SUPPLIER_ID}][items][{ITEM_INDEX}][expire_date]">
                    </div>
                </div>
                <!-- Remove Item Button -->
                <div class="col-md-1">
                    <div class="mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger form-control remove-item"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    
<a href="home.php" class="btn btn-primary floating-btn animate__animated animate__fadeInUp">
        <i class="fas fa-home"></i>
    </a>
    <!-- jQuery -->
     <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script>
        $(document).ready(function() {
            let supplierCounts = {}; // Track item count per supplier
            const currentDate = new Date().toISOString().split('T')[0]; // Today's date in YYYY-MM-DD format
            const supplierNames = {}; // Store supplier names
            
            // Initialize supplier names
            <?php foreach ($suppliers as $supplier): ?>
            supplierNames['<?php echo $supplier['supplier_id']; ?>'] = '<?php echo $supplier['supplier_name']; ?>';
            <?php endforeach; ?>

            // Restore suppliers state if returning from select_items.php
function restoreSupplierState() {
    try {
        const savedSuppliers = sessionStorage.getItem('currentSuppliers');
        const savedCounts = sessionStorage.getItem('supplierCounts');
        
        if (savedSuppliers && savedCounts) {
            const suppliers = JSON.parse(savedSuppliers);
            supplierCounts = JSON.parse(savedCounts);
            
            suppliers.forEach(supplier => {
                // Check if supplier section already exists
                if ($('.supplier-section[data-supplier-id="' + supplier.id + '"]').length === 0) {
                    // Add supplier section
                    const template = document.getElementById('supplier-template').innerHTML;
                    let supplierSection = template
                        .replace(/{SUPPLIER_ID}/g, supplier.id)
                        .replace(/{SUPPLIER_NAME}/g, supplier.name);
                    
                    $('#suppliers-container').append(supplierSection);
                }
                
                // Restore items for this supplier
                const supplierSection = $('.supplier-section[data-supplier-id="' + supplier.id + '"]');
                const itemsContainer = supplierSection.find('.items-container');
                
                supplier.items.forEach(item => {
                    let template, newItemRow;
                    
                    if (item.is_existing) {
                        template = document.getElementById('existing-item-template').innerHTML;
                        // You'll need to fetch and populate the item options here
                        newItemRow = template
                            .replace(/{SUPPLIER_ID}/g, supplier.id)
                            .replace(/{ITEM_INDEX}/g, supplierCounts[supplier.id] || 0)
                            .replace(/{CURRENT_DATE}/g, currentDate)
                            .replace(/{ITEM_OPTIONS}/g, ''); // Will be populated by fetchSupplierItems
                    } else {
                        template = document.getElementById('new-item-template').innerHTML;
                        newItemRow = template
                            .replace(/{SUPPLIER_ID}/g, supplier.id)
                            .replace(/{ITEM_INDEX}/g, supplierCounts[supplier.id] || 0)
                            .replace(/{CURRENT_DATE}/g, currentDate);
                    }
                    
                    itemsContainer.append(newItemRow);
                    
                    // Set values for the restored item
                    // Set values for the restored item
const newRow = itemsContainer.find('.item-row').last();
if (item.is_existing) {
    newRow.find('.existing-item-select').val(item.item_name);
} else {
    newRow.find('.item-name').val(item.item_name);
}
newRow.find('input[name*="[quantity]"]').val(item.quantity);
newRow.find('.unit-size').val(item.unit_size || 1); // Add this line
newRow.find('.price-per-unit').val(item.price_per_unit);
newRow.find('.unit-select').val(item.unit);
newRow.find('input[name*="[purchase_date]"]').val(item.purchase_date);
newRow.find('input[name*="[expire_date]"]').val(item.expire_date);
                    
                    // Increment item count
                    if (!supplierCounts[supplier.id]) {
                        supplierCounts[supplier.id] = 0;
                    }
                    supplierCounts[supplier.id]++;
                });
                
                // Fetch existing items for this supplier (for existing item dropdowns)
                fetchSupplierItems(supplier.id);
            });
            
            // Clear the stored state
            sessionStorage.removeItem('currentSuppliers');
            sessionStorage.removeItem('supplierCounts');
        }
    } catch(e) {
        console.log('Error restoring supplier state:', e);
    }
}

// Call restore function on page load
restoreSupplierState();
            
            // Autocomplete for supplier name
            $("#supplier-name-input").autocomplete({
                source: function(request, response) {
                    $.get(window.location.pathname, {
                        search_supplier: request.term
                    }, function(data) {
                        response($.map(data, function(item) {
                            return {
                                label: item.supplier_name + ' (ID: ' + item.supplier_id + ')',
                                value: item.supplier_name,
                                id: item.supplier_id
                            };
                        }));
                    }, 'json');
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#supplier-id-input").val(ui.item.id);
                    $("#supplier-name-input").val(ui.item.value);
                    return false;
                },
                focus: function(event, ui) {
                    $("#supplier-name-input").val(ui.item.label);
                    return false;
                }
            });
            
            // Add supplier section when button is clicked
            $('#add-supplier').click(function() {
                const supplierId = $('#supplier-id-input').val();
                const supplierName = $('#supplier-name-input').val();
                if (supplierId === '' || supplierName === '') {
                    showAlert('Please select a supplier from the suggestions', 'error');
                    return;
                }
                // Check if supplier already added
                if ($('.supplier-section[data-supplier-id="' + supplierId + '"]').length > 0) {
                    showAlert('This supplier is already added', 'error');
                    return;
                }
                // Initialize item count for this supplier
                if (!supplierCounts[supplierId]) {
                    supplierCounts[supplierId] = 0;
                }
                // Clone supplier template
                const template = document.getElementById('supplier-template').innerHTML;
                let supplierSection = template
                    .replace(/{SUPPLIER_ID}/g, supplierId)
                    .replace(/{SUPPLIER_NAME}/g, supplierName);
                // Add to container
               // Add to container
$('#suppliers-container').append(supplierSection);

// Ensure the data attribute is properly set
$('.supplier-section[data-supplier-id="' + supplierId + '"]').attr('data-supplier-id', supplierId);
                // Reset input fields
                $('#supplier-name-input').val('');
                $('#supplier-id-input').val('');
                // Fetch existing items for this supplier
                fetchSupplierItems(supplierId);
            });
            
            // Fetch supplier items for existing items dropdown
            function fetchSupplierItems(supplierId) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { 
                        get_supplier_items: true,
                        supplier_id: supplierId
                    },
                    dataType: 'json',
                    success: function(items) {
                        // Store items in data attribute for this supplier section
                        const supplierSection = $('.supplier-section[data-supplier-id="' + supplierId + '"]');
                        supplierSection.data('items', items);
                    }
                });
            }
            
            // Add new item to supplier
            $(document).on('click', '.add-new-item', function() {
                const supplierSection = $(this).closest('.supplier-section');
                const supplierId = supplierSection.data('supplier-id');
                const itemsContainer = supplierSection.find('.items-container');
                
                // Get item index for this supplier
                if (!supplierCounts[supplierId]) {
                    supplierCounts[supplierId] = 0;
                }
                
                // Clone new item template
                const template = document.getElementById('new-item-template').innerHTML;
                let newItemRow = template
                    .replace(/{SUPPLIER_ID}/g, supplierId)
                    .replace(/{ITEM_INDEX}/g, supplierCounts[supplierId])
                    .replace(/{CURRENT_DATE}/g, currentDate);
                
                // Add to container
                itemsContainer.append(newItemRow);
                
                // Increment item count
                supplierCounts[supplierId]++;
            });

            // Handle existing item selection change - update to include unit_size
$(document).on('change', '.existing-item-select', function() {
    const selectedOption = $(this).find('option:selected');
    const pricePerUnit = selectedOption.data('price');
    const unitSize = selectedOption.data('unit-size');
    const currentQuantity = selectedOption.data('quantity');
    const unit = selectedOption.data('unit');
    
    // Set price per unit
    const priceInput = $(this).closest('.row').find('.price-per-unit');
    priceInput.val(pricePerUnit);
    
    // Set unit size
    const unitSizeInput = $(this).closest('.row').find('.unit-size');
    unitSizeInput.val(unitSize || 1);
    
    // Set unit if available
    if (unit) {
        const unitSelect = $(this).closest('.row').find('.unit-select');
        unitSelect.val(unit);
    }
    
    // Show current quantity tooltip
    if (currentQuantity) {
        showAlert(`Current quantity in stock: ${currentQuantity}`, 'info');
    }
});

// Check item existence when new item name changes - update to include unit_size
$(document).on('blur', '.item-name', function() {
    const itemNameInput = $(this);
    const itemName = itemNameInput.val().trim();
    
    if (!itemName) return;
    
    const supplierSection = itemNameInput.closest('.supplier-section');
    const supplierId = supplierSection.data('supplier-id');
    
    // Check if item exists
    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: { 
            item_name: itemName,
            supplier_id: supplierId
        },
        dataType: 'json',
        success: function(response) {
            if (response.exists) {
                const row = itemNameInput.closest('.row');
                const priceInput = row.find('.price-per-unit');
                const unitSizeInput = row.find('.unit-size');
                
                priceInput.val(response.price_per_unit);
                unitSizeInput.val(response.unit_size || 1);
                
                showAlert(`Item "${itemName}" already exists with current quantity: ${response.current_quantity}`, 'info');
            }
        }
    });
});
            
            // Add existing item to supplier - redirect to selection page
            // Add existing item to supplier - redirect to selection page
$(document).on('click', '.add-existing-item', function() {
    const supplierSection = $(this).closest('.supplier-section');
    const supplierId = supplierSection.data('supplier-id');
    const supplierName = supplierSection.find('.supplier-header h3').text();
    
    // Store current suppliers state in session storage (fallback to localStorage for persistence)
    const currentSuppliers = [];
    $('.supplier-section').each(function() {
        const section = $(this);
        const id = section.data('supplier-id');
        const name = section.find('.supplier-header h3').text();
        const items = [];
        
        // Collect existing items in this supplier section
        section.find('.item-row').each(function() {
            const itemRow = $(this);
            const itemData = {
                item_name: itemRow.find('.item-name, .existing-item-select').val(),
                quantity: itemRow.find('input[name*="[quantity]"]').val(),
                price_per_unit: itemRow.find('.price-per-unit').val(),
                unit: itemRow.find('.unit-select').val(),
                purchase_date: itemRow.find('input[name*="[purchase_date]"]').val(),
                expire_date: itemRow.find('input[name*="[expire_date]"]').val(),
                is_existing: itemRow.find('.existing-item-select').length > 0
            };
            if (itemData.item_name) {
                items.push(itemData);
            }
        });
        
        currentSuppliers.push({
            id: id,
            name: name,
            items: items
        });
    });
    
    // Store in sessionStorage
    try {
        sessionStorage.setItem('currentSuppliers', JSON.stringify(currentSuppliers));
        sessionStorage.setItem('supplierCounts', JSON.stringify(supplierCounts));
    } catch(e) {
        console.log('SessionStorage not available, state may not persist');
    }

    // Redirect to select items page with supplier name
    window.location.href = `select_items.php?supplier_id=${supplierId}&supplier_name=${encodeURIComponent(supplierName)}&return_url=${encodeURIComponent(window.location.href)}`;
});
            
            // Check for returned items from selection
            <?php if (isset($_SESSION['selected_items'])): ?>
                const selectedItems = <?php echo json_encode($_SESSION['selected_items']); ?>;
                
                // Check if supplier section exists, if not create it
                let supplierSection = $(`.supplier-section[data-supplier-id="${selectedItems.supplier_id}"]`);
                
                if (supplierSection.length === 0) {
                    // Add supplier section
                    if (!supplierCounts[selectedItems.supplier_id]) {
                        supplierCounts[selectedItems.supplier_id] = 0;
                    }
                    
                    const template = document.getElementById('supplier-template').innerHTML;
                    let newSupplierSection = template
                        .replace(/{SUPPLIER_ID}/g, selectedItems.supplier_id)
                        .replace(/{SUPPLIER_NAME}/g, selectedItems.supplier_name);
                    
                    $('#suppliers-container').append(newSupplierSection);
                    supplierSection = $(`.supplier-section[data-supplier-id="${selectedItems.supplier_id}"]`);
                }
            
                // Add each selected item
                const itemsContainer = supplierSection.find('.items-container');
                
                selectedItems.items.forEach(item => {
                    const template = document.getElementById('new-item-template').innerHTML;
                    let newItemRow = template
                        .replace(/{SUPPLIER_ID}/g, selectedItems.supplier_id)
                        .replace(/{ITEM_INDEX}/g, supplierCounts[selectedItems.supplier_id])
                        .replace(/{CURRENT_DATE}/g, currentDate);
                    
                    // Add to container
                    itemsContainer.append(newItemRow);
                    
                    // Set values for the new item
                    const newRow = itemsContainer.find('.item-row').last();
                    newRow.find('.item-name').val(item.item_name);
                    newRow.find('.price-per-unit').val(item.price_per_unit);
                    newRow.find('.unit-select').val(item.unit);
                    
                    // Increment item count
                    supplierCounts[selectedItems.supplier_id]++;
                });
                
                // Clear the session
                <?php unset($_SESSION['selected_items']); ?>
            <?php endif; ?>
            
            // Handle existing item selection change
            $(document).on('change', '.existing-item-select', function() {
                const selectedOption = $(this).find('option:selected');
                const pricePerUnit = selectedOption.data('price');
                const currentQuantity = selectedOption.data('quantity');
                const unit = selectedOption.data('unit');
                
                // Set price per unit
                const priceInput = $(this).closest('.row').find('.price-per-unit');
                priceInput.val(pricePerUnit);
                
                // Set unit if available
                if (unit) {
                    const unitSelect = $(this).closest('.row').find('.unit-select');
                    unitSelect.val(unit);
                }
                
                // Show current quantity tooltip
                if (currentQuantity) {
                    showAlert(`Current quantity in stock: ${currentQuantity}`, 'info');
                }
            });
            
            // Check item existence when new item name changes
            $(document).on('blur', '.item-name', function() {
                const itemNameInput = $(this);
                const itemName = itemNameInput.val().trim();
                
                if (!itemName) return;
                
                const supplierSection = itemNameInput.closest('.supplier-section');
                const supplierId = supplierSection.data('supplier-id');
                
                // Check if item exists
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { 
                        item_name: itemName,
                        supplier_id: supplierId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.exists) {
                            const priceInput = itemNameInput.closest('.row').find('.price-per-unit');
                            priceInput.val(response.price_per_unit);
                            showAlert(`Item "${itemName}" already exists with current quantity: ${response.current_quantity}`, 'info');
                        }
                    }
                });
            });
            
            // Remove item row
            $(document).on('click', '.remove-item', function() {
                $(this).closest('.item-row').remove();
            });
            
            // Remove supplier section
            $(document).on('click', '.remove-supplier', function() {
                $(this).closest('.supplier-section').remove();
            });
            
            // Form submission validation
            $('#purchase-form').on('submit', function(e) {
                // Check if at least one supplier is added
                if ($('.supplier-section').length === 0) {
                    e.preventDefault();
                    showAlert('Please add at least one supplier', 'error');
                    return false;
                }
                
                // Check if each supplier has at least one item
                let valid = true;
                $('.supplier-section').each(function() {
                    const supplierId = $(this).data('supplier-id');
                    const supplierName = supplierNames[supplierId];
                    
                    if ($(this).find('.item-row').length === 0) {
                        showAlert(`Please add at least one item for supplier: ${supplierName}`, 'error');
                        valid = false;
                        return false; // Break the loop
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Show alert function
            function showAlert(message, type) {
                const alertBox = $('#alert');
                alertBox.text(message);
                
                // Remove any existing classes
                alertBox.removeClass('alert-error alert-success alert-info');
                
                // Set color based on type
                if (type === 'error') {
                    alertBox.addClass('alert-error');
                } else if (type === 'success') {
                    alertBox.addClass('alert-success');
                } else {
                    alertBox.addClass('alert-info');
                }
                
                // Show the alert
                alertBox.addClass('show');
                
                // Hide after 5 seconds
                setTimeout(() => {
                    alertBox.removeClass('show');
                }, 5000);
            }
            
            // Show any PHP messages as floating alerts
            <?php if(!empty($message)): ?>
                showAlert('<?php echo $message; ?>', 'info');
            <?php endif; ?>
        });
    </script>
</body>
</html>