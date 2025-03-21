<?php
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

$error = $success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        
        if (empty($category_name)) {
            $error = "Category name is required!";
        } else {
            // Generate category_id starting with 'C' followed by 5 numbers
            $lastIdQuery = "SELECT MAX(CAST(SUBSTR(category_id, 2) AS UNSIGNED)) AS last_id FROM categories WHERE category_id LIKE 'C%'";

            $result = $conn->query($lastIdQuery);
            $row = $result->fetch_assoc();
            $lastId = $row['last_id'];

            if ($lastId === NULL) {
                $category_id = 'C00001';
            } else {
                $category_id = 'C' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
            }

    
            $stmt = $conn->prepare("INSERT INTO categories (category_id, category_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $category_id, $category_name);
            
            if ($stmt->execute()) {
                $success = "Category added successfully!";
            } else {
                if ($conn->errno == 1062) {
                    $error = "Category name already exists! Please choose a unique name.";
                } else {
                    $error = "Error adding category: " . $conn->error;
                }
            }
            $stmt->close();
        }
        
    }
    if (isset($_POST['add_item'])) {
        $item_name = trim($_POST['item_name']);
        $category_id = $_POST['category_id'];
        $supplier_id = $_POST['supplier_id'];
        $quantity = $_POST['quantity'];
        $price_per_unit = $_POST['price_per_unit'] ?? 0;
        $purchase_date = $_POST['purchase_date'];  // Get purchase date

        
        if (empty($item_name) || $category_id == 0 || $supplier_id == 0 || $quantity < 1 || $price_per_unit <= 0) {
            $error = "All fields are required with valid values!";
        } else {
            // Generate item_id starting with 'i' followed by 5 numbers
            $lastIdQuery = "SELECT MAX(CAST(SUBSTR(item_id, 2) AS UNSIGNED)) AS last_id FROM items WHERE item_id LIKE 'I%'";

            $result = $conn->query($lastIdQuery);
            $row = $result->fetch_assoc();
            $lastId = $row['last_id'];

            if ($lastId === NULL) {
                $item_id = 'I00001';
            } else {
                $item_id = 'I' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
            }

            $total_price = $quantity * $price_per_unit;

            $stmt = $conn->prepare("INSERT INTO items (item_id, item_name, category_id, supplier_id, quantity, price_per_unit, total_price, purchase_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssidds", $item_id, $item_name, $category_id, $supplier_id, $quantity, $price_per_unit, $total_price, $purchase_date);
            
            if ($stmt->execute()) {
                // Set success message in session
                $success = "Item added successfully!";

               // Redirect to the same page using the POST-Redirect-GET pattern
               header("Location: " . $_SERVER['REQUEST_URI']);
               exit;
           } else {
               $error = "Error adding item: " . $conn->error;
           }
           $stmt->close();
       }
    }
}
// Display success message from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']); // Remove the message from session
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories and Items</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #aaa;
            margin: 0;
            padding: 20px;
        }
        
        .container {
    max-width: 600px;

    padding: 30px;
    border-radius: 8px;
    background-color: #d5731846;
    /* Changed color */
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px; /* Reduced margin-bottom */
    margin-left: auto;   /* Add auto margins */
    margin-right: auto;
}
        
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], 
        textarea, 
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f0f0f0;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            animation: slideIn 0.4s;
        }
        
        .close-btn {
            position: absolute;
            right: 15px;
            top: 10px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .modal-header {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .modal-title {
            margin: 0;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }
        
        .modal-body {
            margin-bottom: 20px;
            color: #555;
        }
        
        .modal-footer {
            text-align: right;
            padding-top: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-success {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .btn-info {
            background-color: #2196F3;
            color: white;
        }
        
        .modal-success .modal-header {
            color: #155724;
            background-color: #d4edda;
        }
        
        .modal-error .modal-header {
            color: #721c24;
            background-color: #f8d7da;
        }
        
        @keyframes fadeIn {
            from {opacity: 0}
            to {opacity: 1}
        }
        
        @keyframes slideIn {
            from {top: -300px; opacity: 0}
            to {top: 0; opacity: 1}
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
        .btn{
            background-color:rgb(135, 74, 0);
            color:black;
        }
        .btn:hover{
           background-color:rgb(221, 125, 35);
           color:black;
        }

    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Category</h2>
        <form method="post">
            <input type="hidden" name="add_category">
            <div class="form-group">
                <label>Category Name:</label>
                <input type="text" name="category_name" required>
            </div>
            <button type="submit" class="btn">Add Category</button>
        </form>
    </div>
    
    <div class="container">
        <h2>Categories</h2>
        <?php
        $sql = "SELECT * FROM categories";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Category ID</th><th>Category Name</th></tr>";
            
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["category_id"] . "</td>";
                echo "<td>" . $row["category_name"] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No categories yet.";
        }
        ?>
    </div>
    
    <div class="container">
        <h2>Add New Item</h2>
        <form method="post">
            <input type="hidden" name="add_item">
            <div class="form-group">
                <label>Item Name:</label>
                <input type="text" name="item_name" required>
            </div>
            
            <div class="form-group">
                <label>Category:</label>
                <select name="category_id" required>
                    <option value="0">Select Category</option>
                    <?php
                    $categories = $conn->query("SELECT * FROM categories");
                    while($cat = $categories->fetch_assoc()):
                    ?>
                    <option value="<?= $cat['category_id'] ?>"><?= $cat['category_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Supplier:</label>
                <select name="supplier_id" required>
                    <option value="0">Select Supplier</option>
                    <?php
                    $supplier = $conn->query("SELECT * FROM supplier");
                    while($sup = $supplier->fetch_assoc()):
                    ?>
                    <option value="<?= $sup['supplier_id'] ?>"><?= $sup['supplier_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantity:</label>
                <input type="number" name="quantity" min="1" required>
            </div>
            
            <div class="form-group">
                <label>Price per Unit:</label>
                <input type="number" name="price_per_unit" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label>Purchase Date:</label>
                <input type="date" name="purchase_date" required>
            </div>
            <button type="submit" class="btn">Add Item</button>
        </form>
    </div>
    
    <div class="container">
        <h2>Items</h2>
        <?php
        $sql = "SELECT i.item_id, i.item_name, c.category_name, s.supplier_name, i.quantity, i.price_per_unit, i.total_price,i.purchase_date
                FROM items i 
                JOIN categories c ON i.category_id = c.category_id 
                JOIN supplier s ON i.supplier_id = s.supplier_id";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<table style='width: 60%;'>";
            echo "<tr><th>Item ID</th><th>Item Name</th><th>Supplier</th><th>Quantity</th><th>Price per Unit</th><th>Total Price</th><th>Purchase Date</th></tr>";
            
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["item_id"] . "</td>";
                echo "<td>" . $row["item_name"] . "</td>";
                
                echo "<td>" . $row["supplier_name"] . "</td>";
                echo "<td>" . $row["quantity"] . "</td>";
                echo "<td>" . $row["price_per_unit"] . "</td>";
                echo "<td>" . $row["total_price"] . "</td>";
                echo "<td>" . $row["purchase_date"] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No items yet.";
        }
        ?>
    </div>
    
    <!-- Success Modal -->
    <div id="successModal" class="modal modal-success">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Success</h3>
            </div>
            <div class="modal-body">
                <p><?= $success ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success close-modal">OK</button>
            </div>
        </div>
    </div>
    
    <!-- Error Modal -->
    <div id="errorModal" class="modal modal-error">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Error</h3>
            </div>
            <div class="modal-body">
                <p><?= $error ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger close-modal">OK</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get modals
            var successModal = document.getElementById('successModal');
            var errorModal = document.getElementById('errorModal');
            
            // Show success modal if there's a success message
            <?php if($success): ?>
            successModal.style.display = "block";
            <?php endif; ?>
            
            // Show error modal if there's an error message
            <?php if($error): ?>
            errorModal.style.display = "block";
            <?php endif; ?>
            
            // Close modal when clicking on × button
            var closeButtons = document.getElementsByClassName('close-btn');
            for (var i = 0; i < closeButtons.length; i++) {
                closeButtons[i].onclick = function() {
                    successModal.style.display = "none";
                    errorModal.style.display = "none";
                }
            }
            
            // Close modal when clicking on OK button
            var closeModalButtons = document.getElementsByClassName('close-modal');
            for (var i = 0; i < closeModalButtons.length; i++) {
                closeModalButtons[i].onclick = function() {
                    successModal.style.display = "none";
                    errorModal.style.display = "none";
                }
            }
            
            // Close modal when clicking outside of it
            window.onclick = function(event) {
                if (event.target == successModal || event.target == errorModal) {
                    successModal.style.display = "none";
                    errorModal.style.display = "none";
                }
            }
        });
    </script>
    <div class="nav-btn-container">
    <a href="home.php" class="home-btn">Back to Home Page</a>
</body>
</html>
<?php $conn->close(); ?>