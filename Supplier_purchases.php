<?php
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

// Handle AJAX request for item details based on item_name and supplier_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['item_name']) && isset($_POST['supplier_id']) && !isset($_POST['submit_items'])) {
    $itemName = trim($_POST['item_name']);
    $supplierId = trim($_POST['supplier_id']);
    
    // Prepare SQL query to fetch item details
    $sql = "SELECT i.item_id, i.price_per_unit, i.current_quantity 
            FROM items i
            WHERE i.item_name = ? AND i.supplier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $itemName, $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response = ['exists' => false];
    
    // If item exists, populate the response array
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        $response = [
            'exists' => true,
            'item_id' => $item['item_id'],
            'price_per_unit' => $item['price_per_unit'],
            'current_quantity' => $item['current_quantity']
        ];
    }
    
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Generates a unique item code based on item name, supplier ID, and date.
 *
 * @param string $itemName The name of the item.
 * @param string $supplierId The ID of the supplier.
 * @param mysqli $conn The database connection object.
 *
 * @return string A unique item code.
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
 *
 * @param string $itemName The name of the item.
 * @param string $supplierId The ID of the supplier.
 * @param mysqli $conn The database connection object.
 *
 * @return array|null Item details if found, null otherwise.
 */
function getItemDetails($itemName, $supplierId, $conn) {
    $sql = "SELECT item_id, item_code, price_per_unit, current_quantity 
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
 *
 * @param string $itemName The name of the item.
 * @param float $pricePerUnit The price per unit of the item.
 * @param string $supplierId The ID of the supplier.
 * @param mysqli $conn The database connection object.
 *
 * @return array|null The new item's details if added successfully, null otherwise.
 */
function addNewItem($itemName, $pricePerUnit, $supplierId, $conn) {
    $itemCode = generateItemCode($itemName, $supplierId, $conn);
    
    $sql = "INSERT INTO items (item_code, item_name, price_per_unit, current_quantity, supplier_id) 
            VALUES (?, ?, ?, 0, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssds", $itemCode, $itemName, $pricePerUnit, $supplierId);
    
    if ($stmt->execute()) {
        return [
            'item_id' => $conn->insert_id,
            'item_code' => $itemCode,
            'price_per_unit' => $pricePerUnit,
            'current_quantity' => 0
        ];
    } else {
        return null;
    }
}

/**
 * Adds a purchase record and updates the item quantity in the database.
 *
 * @param int $itemId The ID of the item.
 * @param int $quantity The quantity purchased.
 * @param float $pricePerUnit The price per unit at the time of purchase.
 * @param string $purchaseDate The date of purchase.
 * @param string|null $expireDate The expiration date of the item, if applicable.
 * @param mysqli $conn The database connection object.
 *
 * @return bool True if the purchase was added successfully, false otherwise.
 */
function addPurchase($itemId, $quantity, $pricePerUnit, $purchaseDate, $expireDate, $conn) {
    $totalPrice = $quantity * $pricePerUnit;
    
    // Start transaction to ensure atomicity
    $conn->begin_transaction();
    
    try {
        // Insert purchase record
        $sql = "INSERT INTO item_purchases (item_id, quantity, price_per_unit, total_price, purchase_date, expire_date) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idddss", $itemId, $quantity, $pricePerUnit, $totalPrice, $purchaseDate, $expireDate);
        $stmt->execute();
        
        // Update item quantity and price in the items table
        $sql = "UPDATE items 
                SET current_quantity = current_quantity + ?, 
                    price_per_unit = ? 
                WHERE item_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddi", $quantity, $pricePerUnit, $itemId);
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
 *
 * @param mysqli $conn The database connection object.
 *
 * @return array An array of suppliers.
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

// Process form submission for adding multiple items
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_items'])) {
    $itemsData = $_POST['items'];
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($itemsData as $item) {
        $itemName = trim($item['item_name']);
        $quantity = intval($item['quantity']);
        $pricePerUnit = floatval($item['price_per_unit']);
        $purchaseDate = $item['purchase_date'];
        $expireDate = !empty($item['expire_date']) ? $item['expire_date'] : null;
        $supplierId = $item['supplier_id']; // Get supplier ID for each item
        
        if (!empty($itemName) && $quantity > 0 && $pricePerUnit > 0 && !empty($supplierId)) {
            // Check if the item already exists with this supplier
            $existingItem = getItemDetails($itemName, $supplierId, $conn);
            
            if ($existingItem) {
                $itemId = $existingItem['item_id'];
            } else {
                // If the item doesn't exist, add it as a new item
                $newItem = addNewItem($itemName, $pricePerUnit, $supplierId, $conn);
                if ($newItem) {
                    $itemId = $newItem['item_id'];
                } else {
                    $errorCount++;
                    continue;
                }
            }
            
            // Add purchase record
            if (addPurchase($itemId, $quantity, $pricePerUnit, $purchaseDate, $expireDate, $conn)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
    }
    
    // Construct a message based on the number of successful and failed item additions
    $message = "$successCount items added successfully. ";
    if ($errorCount > 0) {
        $message .= "$errorCount items failed to add.";
    }
    // Redirect to prevent form resubmission on refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all suppliers for the dropdown list
$suppliers = getSuppliers($conn);
?>
<?php
// [Previous PHP code remains exactly the same]
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Item Addition | Beautiful Interface</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        h2 {
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
            position: relative;
            padding-bottom: 10px;
        }
        
        h2::after {
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
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .supplier-info strong {
            color: #333;
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
        }
        
        .form-control, .form-select {
            padding: 10px 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            height: auto;
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
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            border: none;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(to right, #6c757d, #495057);
            border: none;
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(108, 117, 125, 0.4);
        }
        
        .btn-light {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            color: #495057;
        }
        
        .btn-light:hover {
            background: #e9ecef;
        }
        
        .btn-danger {
            background: linear-gradient(to right, #dc3545, #c82333);
            border: none;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(220, 53, 69, 0.4);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .alert {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
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
            color: black;
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
        
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }
            
            .item-row .col-md-3,
            .item-row .col-md-2 {
                margin-bottom: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body style="font-size: 20px; font-weight:bold;">
    <div class="main-container">
        <div class="form-container">
            <h2>Item Purchase</h2>
            
            <!-- Display message if there are any success or error messages -->
            <?php if(isset($message)): ?>
            <div class="alert alert-info">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Form for adding items -->
            <form method="post" action="" style="color:black;">
                <!-- Supplier information note -->
                <div class="supplier-info" style="font-size: 15px; font-weight: bold;">
                    <strong style="font-size: 15px;">Note:</strong> Items are linked to specific suppliers. You can now select different suppliers for each item.
                </div>
                
                <!-- Container for dynamically added item rows -->
                <div id="items-container" style="font-size: 20px;">
                    <!-- Initial item row -->
                    <div class="item-row">
                        <div class="row">
                            <!-- Item Name -->
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" style="font-size: 17px; font-weight:bold; color:black;">Item Name</label>
                                    <input type="text" class="form-control item-name" name="items[0][item_name]" required>
                                </div>
                            </div>
                            <br>
                            <!-- Supplier Selection -->
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label" style="font-size: 18px;font-weight:bold; color:black;">Supplier</label>
                                    <select class="form-select supplier-select" name="items[0][supplier_id]" required>
                                        <option value="" style="font-size: 17px;">-- Select --</option>
                                        <?php foreach($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>"><?php echo $supplier['supplier_name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <br>
                            <!-- Quantity -->
                            <div class="col-md-1">
                                <div class="mb-3">
                                    <label class="form-label" style="font-size: 18px; font-weight:bold; color:black;">Quantity</label>
                                    <input type="number" class="form-control" name="items[0][quantity]" min="1" required>
                                </div>
                            </div>
                            <br>
                            <!-- Price per Unit -->
                            <div class="col-md-1">
                                <div class="mb-3">
                                    <label class="form-label" style="font-size: 18px; font-weight:bold; color:black;">Price/Unit</label>
                                    <input type="number" step="0.01" class="form-control price-per-unit" name="items[0][price_per_unit]" required>
                                </div>
                            </div>
                            <br>
                            <!-- Purchase Date -->
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label" style="font-size: 18px; font-weight:bold; color:black;">Purchase Date</label>
                                    <input type="date" class="form-control" name="items[0][purchase_date]" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <br>
                            <!-- Expire Date -->
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label" style="font-size: 18px; font-weight:bold; color:black;">Expire Date</label>
                                    <input type="date" class="form-control" name="items[0][expire_date]">
                                </div>
                            </div>
                            <br>
                            <!-- Remove Item Button -->
                            <div class="col-md-1">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-danger form-control remove-item"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <br>
                        </div>
                    </div>
                </div>
                
                <!-- Add Item and Submit Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" id="add-item" style="font-size: 17px;">
                        <i class="fas fa-plus"></i> Add Another Item
                    </button>
                    <button type="submit" class="btn btn-primary" name="submit_items" style="font-size: 17px;">
                        <i class="fas fa-save"></i> Submit All Items
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let itemCount = 0;
            
            // Add new item row
            $('#add-item').click(function() {
                itemCount++;
                
                // Fetch the suppliers options HTML
                let suppliersOptions = '';
                <?php foreach($suppliers as $supplier): ?>
                suppliersOptions += `<option value="<?php echo $supplier['supplier_id']; ?>"><?php echo $supplier['supplier_name']; ?></option>`;
                <?php endforeach; ?>
                
                // Construct the new row HTML
                const newRow = `
                    <div class="item-row">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Item Name</label>
                                    <input type="text" class="form-control item-name" name="items[${itemCount}][item_name]" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Supplier</label>
                                    <select class="form-select supplier-select" name="items[${itemCount}][supplier_id]" required>
                                        <option value="">-- Select --</option>
                                        ${suppliersOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="items[${itemCount}][quantity]" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-3">
                                    <label class="form-label">Price/Unit</label>
                                    <input type="number" step="0.01" class="form-control price-per-unit" name="items[${itemCount}][price_per_unit]" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control" name="items[${itemCount}][purchase_date]" value="${new Date().toISOString().split('T')[0]}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Expire Date</label>
                                    <input type="date" class="form-control" name="items[${itemCount}][expire_date]">
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-danger form-control remove-item"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('#items-container').append(newRow);
            });
            
            // Remove item row
            $(document).on('click', '.remove-item', function() {
                if ($('.item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                } else {
                    showAlert('You need to have at least one item.', 'error');
                }
            });
            
            // Check if item exists and fetch details
            $(document).on('blur', '.item-name', function() {
                const itemNameInput = $(this);
                const itemName = itemNameInput.val().trim();
                const currentRow = itemNameInput.closest('.item-row');
                const supplierSelect = currentRow.find('.supplier-select');
                const supplierId = supplierSelect.val();
                
                if (itemName !== '' && supplierId !== '') {
                    checkItem(itemNameInput, supplierId);
                }
            });
            
            // Check when supplier changes
            $(document).on('change', '.supplier-select', function() {
                const supplierSelect = $(this);
                const supplierId = supplierSelect.val();
                const currentRow = supplierSelect.closest('.item-row');
                const itemNameInput = currentRow.find('.item-name');
                const itemName = itemNameInput.val().trim();
                
                if (itemName !== '' && supplierId !== '') {
                    checkItem(itemNameInput, supplierId);
                }
            });
            
            // Function to check item existence via AJAX
            function checkItem(itemNameInput, supplierId) {
                const itemName = itemNameInput.val().trim();
                const currentRow = itemNameInput.closest('.item-row');
                
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
                            currentRow.find('.price-per-unit').val(response.price_per_unit);
                            showAlert(`Item already exists with current quantity: ${response.current_quantity} from this supplier.`, 'info');
                        }
                    }
                });
            }
            
            // Show alert function
            function showAlert(message, type) {
                const alertBox = $('#alert');
                alertBox.text(message);
                alertBox.removeClass('alert-error alert-success alert-info').addClass('show');
                
                // Set color based on type
                if (type === 'error') {
                    alertBox.addClass('alert-error');
                } else if (type === 'success') {
                    alertBox.addClass('alert-success');
                } else {
                    alertBox.addClass('alert-info');
                }
                
                // Hide after 5 seconds
                setTimeout(() => {
                    alertBox.removeClass('show');
                }, 5000);
            }
            
            // Show any PHP messages as floating alerts
            <?php if(isset($message)): ?>
                showAlert('<?php echo $message; ?>', 'info');
            <?php endif; ?>
        });
    </script>
</body>
</html>