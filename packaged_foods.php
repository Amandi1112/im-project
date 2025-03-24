<?php
session_start(); // Start the session

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

// function to show the generated unique item id for the items
$sql = "SELECT * FROM packaged_foods";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $item_id = $row['id'] + 1;
} else {
    $item_id = 1;
}


// Function to get all packaged food items
function getPackagedFoods($conn) {
    $sql = "SELECT * FROM packaged_foods ORDER BY id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return [];
    }
}

// Function to add a new packaged food item
function addPackagedFood($conn, $name, $category, $quantity, $selling_quantity) {
    $name = $conn->real_escape_string($name);
    $category = $conn->real_escape_string($category);
    $quantity = (int)$quantity;
    $selling_quantity = (int)$selling_quantity;

    // Validate that selling quantity doesn't exceed total quantity
    if ($selling_quantity > $quantity) {
        return "Error: Selling quantity cannot exceed total quantity";
    }

    // Generate a unique item_id
    $item_id = generateUniqueItemId($conn);
    $remaining_quantity = $quantity - $selling_quantity;

    $sql = "INSERT INTO packaged_foods (item_id, name, category, total_quantity, selling_quantity, remaining_quantity) 
            VALUES ('$item_id', '$name', '$category', $quantity, $selling_quantity, $remaining_quantity)";
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// Function to delete a packaged food item
function deletePackagedFood($conn, $id) {
    $id = (int)$id;
    $sql = "DELETE FROM packaged_foods WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// Function to update a packaged food item's selling quantity
function updateSellingQuantity($conn, $id, $selling_quantity) {
    $id = (int)$id;
    $selling_quantity = (int)$selling_quantity;

    // First, get the current item information
    $sql = "SELECT total_quantity FROM packaged_foods WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $total_quantity = $row['total_quantity'];

        // Validate that selling quantity doesn't exceed total quantity
        if ($selling_quantity > $total_quantity) {
            return "Error: Selling quantity cannot exceed total quantity";
        }

        // Calculate remaining quantity properly
        $remaining_quantity = $total_quantity - $selling_quantity;

        // Update the database
        $update_sql = "UPDATE packaged_foods SET selling_quantity = $selling_quantity, 
                       remaining_quantity = $remaining_quantity WHERE id = $id";
        if ($conn->query($update_sql) === TRUE) {
            return true;
        } else {
            return false;
        }
    }
    return false;
}

// Process form submissions
$message = "";
$promptScript = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $quantity = $_POST['quantity'];
        $selling_quantity = $_POST['selling_quantity'];
        $result = addPackagedFood($conn, $name, $category, $quantity, $selling_quantity);

        if ($result === true) {
            $_SESSION['message'] = "Item added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding item: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (isset($_POST['delete_item'])) {
        $id = $_POST['id'];
        if (deletePackagedFood($conn, $id)) {
            $_SESSION['message'] = "Item deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting item: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (isset($_POST['update_selling'])) {
        $id = $_POST['id'];
        $selling_quantity = $_POST['selling_quantity'];
        $result = updateSellingQuantity($conn, $id, $selling_quantity);

        if ($result === true) {
            $_SESSION['message'] = "Selling quantity updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $error_message = is_string($result) ? $result : "Error updating selling quantity: " . $conn->error;
            $_SESSION['message'] = $error_message;
            $_SESSION['message_type'] = "error";
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all packaged food items
$packaged_foods = getPackagedFoods($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packaged Foods Inventory</title>
    <style>
        body {
            background: url(images/background2.jpg);
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
        }
        .container {
            display: flex;
            flex-wrap: wrap;
        }
        .section {
            margin-right: 30px;
            margin-bottom: 30px;
        }
        .section h2 {
            align-content: center;
            color:rgb(35, 30, 30);
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        form {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        input, select {
            margin-bottom: 10px;
            padding: 5px;
            width: 100%;
        }
        button {
            background-color:rgb(135, 74, 0);
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #f28252;
        }
        button.delete {
            background-color: #f44336;
        }
        button.delete:hover {
            background-color: #da190b;
        }
        /* Popup Styles */
        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            border-radius: 8px;
            width: 300px;
            text-align: center;
        }
        .popup.success {
            border: 2px solid #4CAF50;
        }
        .popup.error {
            border: 2px solid #f44336;
        }
        .popup-message {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .popup button {
            background-color: #4CAF50;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .popup button.error-btn {
            background-color: #f44336;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
    </style>
</head>
<body>
<h1 style="text-align: center; font-weight: bold; color: black; font-size: 2.5em; text-shadow: 2px 2px 5px lightblue; margin-top: 70px;"> Remaining Quantity</h1>
    <!-- Popup Modal -->
    <div class="overlay" id="overlay"></div>
    <div class="popup" id="popup">
        <div class="popup-message" id="popupMessage"></div>
        <button onclick="closePopup()">OK</button>
    </div>
    <div class="container">
        <div class="section">
            <h2 style="color:rgb(61, 59, 59)">Add New Packaged Food Item</h2>
            <form method="post">
               
                <label for="category items">item:</label>
                <if category="Package foods">
                    <select id="category items" name="category items" required>
                        <option value="">Package foods</option>
                        <option value="Samaposha">Samaposha</option>
                        <option value="Yahaposha">Yahaposha</option>
                        <option value="Kottu mee">Kottu mee</option>
                        <option value="Maggie noodles">Maggie noodles</option>
                        <option value="Chochos">Chocos</option>
                        <option value="Cerelac">Cerelac</option>
                        <option value="Nestomalt">Nestomalt</option>
                        <option value="Milo">Milo</option>
                        <option value="Horlicks">Horlicks</option>  
                    </select>
                    </if>
                <if category="Snacks">
                    <select id="category items" name="category items" required>
                        <option value="">Snacks</option>
                        <option value="Chips">Chips</option>
                        <option value="Biscuits">Biscuits</option>
                        <option value="Wafers">Wafers</option>
                        <option value="Cakes">Cakes</option>
                        <option value="Sweets">Sweets</option>
                        <option value="Chocolate">Chocolate</option>
                        <option value="Ice cream">Ice cream</option>
                        <option value="Lays">Lays</option>
                        <option value="Pringles">Pringles</option>
                        <option value="Oreo">Oreo</option>
                        <option value="Kitkat">Kitkat</option>
                        <option value="Mars">Mars</option>
                        <option value="Snickers">Snickers</option>
                        <option value="Bounty">Bounty</option>
                        <option value="Twix">Twix</option>
                        <option value="Milkybar">Milkybar</option>
                        <option value="Galaxy">Galaxy</option>
                        <option value="Dairy milk">Dairy milk</option>
                        <option value="Ferrero Rocher">Ferrero Rocher</option>
                        <option value="Kinder Bueno">Kinder Bueno</option>
                        <option value="Kinder Joy">Kinder Joy</option>
                        <option value="Kinder Chocolate">Kinder Chocolate</option>
                        <option value="Kinder Schoko-Bons">Kinder Schoko-Bons</option>
                        <option value="Kinder Happy Hippo">Kinder Happy Hippo</option>
                        <option value="Kinder Delice">Kinder Delice</option>
                        <option value="Kinder Maxi">Kinder Maxi</option>
                        <option value="Kinder Pingui">Kinder Pingui</option>
                        <option value="Kinder Country Crisp">Kinder Country Crisp</option>
                        <option value="Kinder Chocolate Bar">Kinder Chocolate Bar</option>
                        <option value="Kinder Chocolate Mini">Kinder Chocolate Mini</option>
                        <option value="Kinder Chocolate Stick">Kinder Chocolate Stick</option>
                </select>
                </if>
                <if category="Cooking & Pantry Staples">
                    <select id="category items" name="category items" required>
                        <option value="">Cooking & Pantry Staples</option>
                        <option value="Rice">Rice</option>
                        <option value="Dhal">Dhal</option>
                        <option value="Sugar">Sugar</option>
                        <option value="Salt">Salt</option>
                        <option value="Pepper">Pepper</option>
                        <option value="Chilli powder">Chilli powder</option>
                        <option value="Curry powder">Curry powder</option>
                        <option value="Turmeric powder">Turmeric powder</option>
                        <option value="Cumin seeds">Cumin seeds</option>
                        <option value="Coriander seeds">Coriander seeds</option>
                        <option value="Cinnamon">Cinnamon</option>
                        <option value="Cardamom">Cardamom</option>
                        <option value="Cloves">Cloves</option>
                        <option value="Nutmeg">Nutmeg</option>
                        <option value="Mace">Mace</option>
                        <option value="Fennel seeds">Fennel seeds</option>
                        <option value="Mustard seeds">Mustard seeds</option>
                        <option value="Poppy seeds">Poppy seeds</option>
                        <option value="Sesame seeds">Sesame seeds</option>
                        <option value="Coconut">Coconut</option>
                        <option value="Ghee">Ghee</option>
                        <option value="Oil">Oil</option>
                        <option value="Butter">Butter</option>
                        <option value="Margarine">Margarine</option>
                        <option value="Cheese">Cheese</option>
                        <option value="Milk">Milk</option>
                        <option value="Yogurt">Yogurt</option>
                        <option value="Cream">Cream</option>
                        <option value="Ice cream">Ice cream</option>
                        <option value="Mayonnaise">Mayonnaise</option>
                        <option value="Ketchup">Ketchup</option>
                        <option value="Soy sauce">Soy sauce</option>
                        <option value="Vinegar">Vinegar</option>
                        <option value="Honey">Honey</option>
                        <option value="Jam">Jam</option>

                    </select>
                    </if>
                <if category="Personal & Household Care">
                    <select id="category items" name="category items" required>
                        <option value="">Personal & Household Care</option>
                        <option value="Shampoo">Shampoo</option>
                        <option value="Conditioner">Conditioner</option>
                        <option value="Body wash">Body wash</option>
                        <option value="Soap">Soap</option>
                        <option value="Hand wash">Hand wash</option>
                        <option value="Face wash">Face wash</option>
                        <option value="Face cream">Face cream</option>
                        <option value="Face mask">Face mask</option>
                        <option value="Face scrub">Face scrub</option>
                        <option value="Face toner">Face toner</option>
                        <option value="Face serum">Face serum</option>
                        <option value="Face oil">Face oil</option>
                        <option value="Face gel">Face gel</option>
                        <option value="Face lotion">Face lotion</option>
                        <option value="Face powder">Face powder</option>
                        <option value="Face foundation">Face foundation</option>
                        <option value="Face concealer">Face concealer</option>
                        <option value="Face highlighter">Face highlighter</option>
                        <option value="Face bronzer">Face bronzer</option>
                        <option value="Face blush">Face blush</option>
                        <option value="Face contour">Face contour</option>
                        <option value="Face primer">Face primer</option>
                        <option value="Face setting spray">Face setting spray</option>
                        <option value="Face makeup remover">Face makeup remover</option>
                        <option value="Face sunscreen">Face sunscreen</option>
                        <option value="Face lip balm">Face lip balm</option>
                        <option value="Face lip scrub">Face lip scrub</option>
                        <option value="Face lip mask">Face lip mask</option>
                        <option value="Face lip oil">Face lip oil</option>
                        <option value="Face lip gloss">Face lip gloss</option>
                        <option value="Face lip liner">Face lip liner</option>
                        <option value="Face lip stick">Face lip stick</option>
                        <option value="Face lip pencil">Face lip pencil</option>
                        <option value="Face lip stain">Face lip stain</option>
                        <option value="Face lip plumper">Face lip plumper</option>

                    </select>
                    </if>
                <if category="Sanitary Care products">
                    <select id="category item" name="category item" required>
                        <option value="">Sanitary Care products</option>
                        <option value="Toothpaste">Toothpaste</option>
                        <option value="Toothbrush">Toothbrush</option>
                        <option value="Mouthwash">Mouthwash</option>
                        <option value="Dental floss">Dental floss</option>
                        <option value="Tooth powder">Tooth powder</option>
                        <option value="Tooth gel">Tooth gel</option>
                        <option value="Tooth serum">Tooth serum</option>
                        <option value="Tooth oil">Tooth oil</option>
                        <option value="Tooth gel">Tooth gel</option>
                        <option value="Tooth lotion">Tooth lotion</option>
                        <option value="Tooth powder">Tooth powder</option>
                        <option value="Tooth foundation">Tooth foundation</option>
                        <option value="Tooth concealer">Tooth concealer</option>
                        <option value="Tooth highlighter">Tooth highlighter</option>
                        <option value="Tooth bronzer">Tooth bronzer</option>
                        <option value="Tooth blush">Tooth blush</option>
                        <option value="Tooth contour">Tooth contour</option>
                        <option value="Tooth primer">Tooth primer</option>
                        <option value="Tooth setting spray">Tooth setting spray</option>
                        <option value="Tooth makeup remover">Tooth makeup remover</option>
                        <option value="Tooth sunscreen">Tooth sunscreen</option>
                        <option value="Tooth lip balm">Tooth lip balm</option>
                        <option value="Tooth lip scrub">Tooth lip scrub</option>
                        <option value="Tooth lip mask">Tooth lip mask</option>
                        <option value="Tooth lip oil">Tooth lip oil</option>
                        <option value="Tooth lip gloss">Tooth lip gloss</option>
                        <option value="Tooth lip liner">Tooth lip liner</option>
                        <option value="Tooth lip stick">Tooth lip stick</option>
                        <option value="Tooth lip pencil">Tooth lip pencil</option>
                        <option value="Tooth lip stain">Tooth lip stain</option>

                    </select>
                    </if>
                <label for="quantity">Total Quantity:</label>
                <input type="number" id="quantity" name="quantity" min="1" required>
                <label for="selling_quantity">Selling Quantity:</label>
                <input type="number" id="selling_quantity" name="selling_quantity" min="0" required>
                <button type="submit" name="add_item">Add Item</button>
            </form>
        </div>
        <div class="section">
            <h2 style="color:rgb(61, 59, 59)">Current Inventory</h2>
            <table>
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Total Quantity</th>
                        <th>Selling Quantity</th>
                        <th>Remaining Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packaged_foods)): ?>
                        <tr>
                            <td colspan="8">No packaged food items found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($packaged_foods as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_id']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td><?php echo $item['total_quantity']; ?></td>
                                <td>
                                    <form method="post" style="background:none; padding:0; margin:0;">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <input type="number" name="selling_quantity" value="<?php echo $item['selling_quantity']; ?>" min="0" max="<?php echo $item['total_quantity']; ?>" style="width:60px;">
                                        <button type="submit" name="update_selling" style="padding:3px 3px;">Update</button>
                                    </form>
                                </td>
                                <td><?php echo $item['remaining_quantity']; ?></td>
                                <td>
                                    <form method="post" style="background:none; padding:0; margin:0;">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_item" class="delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        // Function to show the popup with a message and type
        function showPopup(message, type) {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('overlay');
            const popupMessage = document.getElementById('popupMessage');
            popupMessage.textContent = message;
            popup.className = `popup ${type}`;
            overlay.style.display = 'block';
            popup.style.display = 'block';
        }

        // Function to close the popup
        function closePopup() {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('overlay');
            overlay.style.display = 'none';
            popup.style.display = 'none';
        }

        // Automatically close the popup after 3 seconds
        setTimeout(closePopup, 3000);
    </script>
    <?php
// Display the message if it exists
if (isset($_SESSION['message'])) {
    echo "<script>showPopup('" . addslashes($_SESSION['message']) . "', '" . $_SESSION['message_type'] . "');</script>";
    // Clear the message after displaying it
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>
</body>
</html>
<?php
// Close the database connection
$conn->close();

?>




