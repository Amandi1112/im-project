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
}

// Fetch all suppliers for the dropdown list
$suppliers = getSuppliers($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Item Addition</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom Styles -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 30px;
        }
        h2 {
            color: #343a40;
        }
        .item-row {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        .supplier-info {
            font-size: 0.85rem;
            margin-top: 5px;
            color: #6c757d;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        .remove-item {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Bulk Item Addition</h2>
        
        <!-- Display message if there are any success or error messages -->
        <?php if(isset($message)): ?>
        <div class="alert alert-info">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Form for adding items -->
        <form method="post" action="">
            <!-- Supplier information note -->
            <div class="supplier-info mb-3">
                <strong>Note:</strong> Items are linked to specific suppliers. You can now select different suppliers for each item.
            </div>
            
            <!-- Container for dynamically added item rows -->
            <div id="items-container">
                <!-- Initial item row -->
                <div class="item-row">
                    <div class="row">
                        <!-- Item Name -->
                        <div class="col-md-3">
                            <div class="mb-2">
                                <label class="form-label">Item Name</label>
                                <input type="text" class="form-control item-name" name="items[0][item_name]" required>
                            </div>
                        </div>
                        <!-- Supplier Selection -->
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
                        <!-- Quantity -->
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="items[0][quantity]" min="1" required>
                            </div>
                        </div>
                        <!-- Price per Unit -->
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">Price/Unit</label>
                                <input type="number" step="0.01" class="form-control price-per-unit" name="items[0][price_per_unit]" required>
                            </div>
                        </div>
                        <!-- Purchase Date -->
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" name="items[0][purchase_date]" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <!-- Expire Date -->
                        <div class="col-md-2">
                            <div class="mb-2">
                                <label class="form-label">Expire Date</label>
                                <input type="date" class="form-control" name="items[0][expire_date]">
                            </div>
                        </div>
                        <!-- Remove Item Button -->
                        <div class="col-md-1">
                            <div class="mb-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger form-control remove-item"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Item and Submit Buttons -->
            <div class="mb-3">
                <button type="button" class="btn btn-secondary" id="add-item"><i class="fas fa-plus"></i> Add Another Item</button>
                <button type="submit" class="btn btn-primary" name="submit_items"><i class="fas fa-save"></i> Submit All Items</button>
                 <!-- Back to Home Link -->
                <a href="home.php" class="btn btn-light"><i class="fas fa-home"></i> Back to Home</a>
                <!-- View Purchases Link -->
                <a href="display_purchase_details.php" class="btn btn-light"><i class="fas fa-eye"></i> View Purchases</a>
            </div>
            
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
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
                    alert('You need to have at least one item.');
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
                            alert(`Item already exists with current quantity: ${response.current_quantity} from this supplier.`);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
