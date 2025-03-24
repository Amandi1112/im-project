<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the request is for item details (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['item_name']) && isset($_POST['supplier_id']) && !isset($_POST['submit_items'])) {
    $itemName = trim($_POST['item_name']);
    $supplierId = trim($_POST['supplier_id']);
    
    // Prepare and execute query - now including supplier_id in the query
    $sql = "SELECT i.item_id, i.price_per_unit, i.current_quantity 
            FROM items i
            WHERE i.item_name = ? AND i.supplier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $itemName, $supplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response = ['exists' => false];
    
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        $response = [
            'exists' => true,
            'item_id' => $item['item_id'],
            'price_per_unit' => $item['price_per_unit'],
            'current_quantity' => $item['current_quantity']
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Function to generate unique item code
function generateItemCode($itemName, $supplierId, $conn) {
    // Take first 3 letters of item name and make uppercase
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $itemName), 0, 3));
    
    // Add first 2 characters of supplier ID
    $supplierPrefix = strtoupper(substr($supplierId, 0, 2));
    
    // Get current date in YYMM format
    $datePart = date('ym');
    
    // Find the latest item with similar prefix to get the next sequential number
    $sql = "SELECT item_code FROM items WHERE item_code LIKE '{$prefix}{$supplierPrefix}{$datePart}%' ORDER BY item_code DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastCode = $row['item_code'];
        // Extract the numerical part
        $sequence = intval(substr($lastCode, -4)) + 1;
    } else {
        $sequence = 1;
    }
    
    // Format the sequence with leading zeros
    $sequencePart = str_pad($sequence, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $supplierPrefix . $datePart . $sequencePart;
}

// Function to get item details if it exists with specific supplier
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

// Function to add a new item
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

// Function to add purchase record and update item quantity
function addPurchase($itemId, $quantity, $pricePerUnit, $purchaseDate, $expireDate, $conn) {
    $totalPrice = $quantity * $pricePerUnit;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Add purchase record
        $sql = "INSERT INTO item_purchases (item_id, quantity, price_per_unit, total_price, purchase_date, expire_date) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idddss", $itemId, $quantity, $pricePerUnit, $totalPrice, $purchaseDate, $expireDate);
        $stmt->execute();
        
        // Update item quantity and price
        $sql = "UPDATE items 
                SET current_quantity = current_quantity + ?, 
                    price_per_unit = ? 
                WHERE item_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddi", $quantity, $pricePerUnit, $itemId);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback in case of error
        $conn->rollback();
        return false;
    }
}

// Get suppliers for dropdown
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

// Process form submission for adding items
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
            // Check if item already exists with this supplier
            $existingItem = getItemDetails($itemName, $supplierId, $conn);
            
            if ($existingItem) {
                $itemId = $existingItem['item_id'];
            } else {
                // Add new item
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
    
    $message = "$successCount items added successfully. ";
    if ($errorCount > 0) {
        $message .= "$errorCount items failed to add.";
    }
}

// Get all suppliers for the dropdown
$suppliers = getSuppliers($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Item Addition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .item-row {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .supplier-info {
            font-size: 0.85rem;
            margin-top: 5px;
            color: #6c757d;
        }
        
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Bulk Item Addition</h2>
        
        <?php if(isset($message)): ?>
        <div class="alert alert-info">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="supplier-info mb-3">
                <strong>Note:</strong> Items are linked to specific suppliers. You can now select different suppliers for each item.
            </div>
            
            <div id="items-container">
                <div class="item-row">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-2">
                                <label class="form-label">Item Name</label>
                                <input type="text" class="form-control item-name" name="items[0][item_name]" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Supplier</label>
                                <select class="form-select supplier-select" name="items[0][supplier_id]" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>"><?php echo $supplier['supplier_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="items[0][quantity]" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">Price/Unit</label>
                                <input type="number" step="0.01" class="form-control price-per-unit" name="items[0][price_per_unit]" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" name="items[0][purchase_date]" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Expire Date</label>
                                <input type="date" class="form-control" name="items[0][expire_date]">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger form-control remove-item">X</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <button type="button" class="btn btn-secondary" id="add-item">Add Another Item</button>
                <button type="submit" class="btn btn-primary" name="submit_items">Submit All Items</button>
                <a href="home.php" class="btn btn-light">Back to Home</a>
                <a href="display_purchase_details.php" class="btn btn-light">View Purchases</a>
            </div>
            
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let itemCount = 0;
            
            // Add new item row
            $('#add-item').click(function() {
                itemCount++;
                
                // Get the suppliers options HTML
                let suppliersOptions = '';
                <?php foreach($suppliers as $supplier): ?>
                suppliersOptions += '<option value="<?php echo $supplier['supplier_id']; ?>"><?php echo $supplier['supplier_name']; ?></option>';
                <?php endforeach; ?>
                
                const newRow = `
                    <div class="item-row">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <label class="form-label">Item Name</label>
                                    <input type="text" class="form-control item-name" name="items[${itemCount}][item_name]" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">Supplier</label>
                                    <select class="form-select supplier-select" name="items[${itemCount}][supplier_id]" required>
                                        <option value="">-- Select --</option>
                                        ${suppliersOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-2">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="items[${itemCount}][quantity]" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-2">
                                    <label class="form-label">Price/Unit</label>
                                    <input type="number" step="0.01" class="form-control price-per-unit" name="items[${itemCount}][price_per_unit]" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control" name="items[${itemCount}][purchase_date]" value="${new Date().toISOString().split('T')[0]}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label">Expire Date</label>
                                    <input type="date" class="form-control" name="items[${itemCount}][expire_date]">
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="mb-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-danger form-control remove-item">X</button>
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
                    alert('You need at least one item');
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
            
            // Also check when supplier changes
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
            
            // Function to check item existence
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
                            alert(`Item exists with current quantity: ${response.current_quantity} from this supplier`);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>