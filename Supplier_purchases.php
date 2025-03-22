<?php
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session to store messages
session_start();

// Function to generate new item_id
function generateItemId($conn) {
    $sql = "SELECT MAX(SUBSTRING(item_id, 2)) as max_id FROM items";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $next_id = intval($row['max_id']) + 1;
    return 'I' . str_pad($next_id, 5, '0', STR_PAD_LEFT);
}

// Function to calculate total price
function calculateTotalPrice($quantity, $price_per_unit) {
    return $quantity * $price_per_unit;
}

// Function to validate input data
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables
$item_name = $category_id = $supplier_id = $quantity = $price_per_unit = $purchase_date = "";
$error = $success = "";

// Get messages from session if they exist
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Get categories for dropdown
$categories_query = "SELECT category_id, category_name FROM categories ORDER BY category_name";
$categories_result = $conn->query($categories_query);

// Get suppliers for dropdown
$suppliers_query = "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // Validate inputs
    $item_name = validateInput($_POST['item_name']);
    $category_id = validateInput($_POST['category_id']);
    $supplier_id = validateInput($_POST['supplier_id']);
    $quantity = validateInput($_POST['quantity']);
    $price_per_unit = validateInput($_POST['price_per_unit']);
    $purchase_date = validateInput($_POST['purchase_date']);
    
    // Check for empty fields
    if (empty($item_name) || empty($category_id) || empty($supplier_id) || 
        empty($quantity) || empty($price_per_unit) || empty($purchase_date)) {
        $_SESSION['error'] = "All fields are required";
    } else {
        // Calculate total price
        $total_price = calculateTotalPrice($quantity, $price_per_unit);
        
        // Generate new item_id
        $item_id = generateItemId($conn);
        
        // Prepare SQL statement for inserting new purchase
        $sql = "INSERT INTO items (item_id, item_name, category_id, supplier_id, quantity, price_per_unit, total_price, purchase_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssidds", $item_id, $item_name, $category_id, $supplier_id, $quantity, $price_per_unit, $total_price, $purchase_date);
        
        // Execute the query
        if ($stmt->execute()) {
            $_SESSION['success'] = "Purchase added successfully with Item ID: " . $item_id;
            
            // Log the activity
            $user_id = 1; // Assume logged in user ID or get from session
            $activity = "Added new purchase: " . $item_name . " from supplier " . $supplier_id;
            
            $log_sql = "INSERT INTO activity_log (user_id, activity) VALUES (?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("is", $user_id, $activity);
            $log_stmt->execute();
            
            $stmt->close();
            
            // Redirect to the same page to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
            
            // Redirect to the same page
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
    
    // Redirect even if there's an error
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get recent purchases for display
$recent_purchases_query = "SELECT i.item_id, i.item_name, c.category_name, s.supplier_name, 
                           i.quantity, i.price_per_unit, i.total_price, i.purchase_date 
                           FROM items i 
                           JOIN categories c ON i.category_id = c.category_id 
                           JOIN supplier s ON i.supplier_id = s.supplier_id 
                           ORDER BY i.created_at DESC LIMIT 10";
$recent_purchases = $conn->query($recent_purchases_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Purchase Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background:url("images/background60.jpg");
            margin: 0;
            padding: 20px;
            font-weight: bold;
        }
        .container {
            background-color: #d5731846;
    /* Changed color */
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0d6efd;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 500;
        }
        .alert {
            margin-top: 20px;
        }
        .table {
            margin-top: 30px;
        }
        .purchase-form {
            background-color:rgb(255, 245, 241);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn btn-primary {
            background-color: rgb(219, 126, 55);
            border-color:rgb(219, 126, 55);
        }
        .btn btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .home-btn {
            background-color: rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            margin: 0 5px; /* Space between buttons */
        }
        .home-btn:hover {
            background-color: #f28252;
        }
        .nav-btn-container {
            text-align: center; /* Center the navigation buttons */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; font-weight: bold; color: white; font-size: 2em; text-shadow: 2px 2px 5px lightblue;">Supplier Purchase Management</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="purchase-form">
                    <h3>Add New Purchase</h3>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="item_name" class="form-label">Item Name</label>
                                <input type="text" class="form-control" id="item_name" name="item_name" value="<?php echo $item_name; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php while($category = $categories_result->fetch_assoc()): ?>
                                        <option value="<?php echo $category['category_id']; ?>" <?php if($category_id == $category['category_id']) echo "selected"; ?>>
                                            <?php echo $category['category_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="supplier_id" class="form-label">Supplier</label>
                                <select class="form-select" id="supplier_id" name="supplier_id">
                                    <option value="">Select Supplier</option>
                                    <?php while($supplier = $suppliers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>" <?php if($supplier_id == $supplier['supplier_id']) echo "selected"; ?>>
                                            <?php echo $supplier['supplier_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="purchase_date" class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo $purchase_date; ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo $quantity; ?>" min="1">
                            </div>
                            <div class="col-md-6">
                                <label for="price_per_unit" class="form-label">Price Per Unit</label>
                                <input type="number" class="form-control" id="price_per_unit" name="price_per_unit" value="<?php echo $price_per_unit; ?>" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" name="submit" class="btn btn-primary">Add Purchase</button>
                                <button type="reset" class="btn btn-secondary">Reset</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h3>Recent Purchases</h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>Item ID</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                                <th>Price/Unit</th>
                                <th>Total</th>
                                <th>Purchase Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_purchases->num_rows > 0): ?>
                                <?php while($row = $recent_purchases->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['item_id']; ?></td>
                                        <td><?php echo $row['item_name']; ?></td>
                                        <td><?php echo $row['category_name']; ?></td>
                                        <td><?php echo $row['supplier_name']; ?></td>
                                        <td><?php echo $row['quantity']; ?></td>
                                        <td><?php echo number_format($row['price_per_unit'], 2); ?></td>
                                        <td><?php echo number_format($row['total_price'], 2); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($row['purchase_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No purchases found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <br><br>
    <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate total price automatically
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInput = document.getElementById('quantity');
            const priceInput = document.getElementById('price_per_unit');
            
            function updateTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const total = quantity * price;
                
                // You could display this somewhere if needed
                console.log('Total: ' + total.toFixed(2));
            }
            
            quantityInput.addEventListener('input', updateTotal);
            priceInput.addEventListener('input', updateTotal);
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>