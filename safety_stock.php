<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission to add safety stock
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_safety_stock"])) {
    $item_id = $_POST["item_id"];
    $safety_stock_quantity = $_POST["safety_stock_quantity"];

    // Check if safety stock already exists for the item
    $sql = "SELECT * FROM safety_stock WHERE item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Safety stock for item $item_id already exists. Please update instead.');</script>";
    } else {
        // Insert safety stock into the database
        $sql = "INSERT INTO safety_stock (item_id, safety_stock_quantity) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $item_id, $safety_stock_quantity);
        if ($stmt->execute()) {
            echo "<script>alert('Safety stock added successfully!');</script>";
        } else {
            echo "<script>alert('Error: " . $conn->error . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <style>
        body {
            background: url("images/background60.jpg");
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            font-weight: bold;
        }
        .container {
            max-width: 600px;
            margin-top: 100px;
            margin: auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            background-color: #d5731846;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        button {
            padding: 10px 15px;
            background-color: rgb(135, 74, 0);
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: rgb(221, 125, 35);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .warning {
            color: red;
            font-weight: bold;
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
            margin: 0 5px;
        }
        .home-btn:hover {
            background-color: #f28252;
        }
        .nav-btn-container {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1 style="color: rgb(114, 8, 75); text-shadow: 2px 2px 5px lightblue; text-align: center; font-size: 45px; background-color: #ffffff;">Inventory Management</h1>

    <!-- Form to Add Safety Stock -->
    <div class="container">
        <h2 style="text-align: center; text-shadow: 2px 2px 5px lightblue; font-size: 25px;">Add Safety Stock</h2>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="item_id">Item ID:</label>
                <input type="text" id="item_id" name="item_id" required>
            </div>
            <div class="form-group">
                <label for="safety_stock_quantity">Safety Stock Quantity:</label>
                <input type="number" id="safety_stock_quantity" name="safety_stock_quantity" required>
            </div>
            <button type="submit" name="add_safety_stock">Add Safety Stock</button>
        </form>

        <br><br>
        <!-- Display Items with Safety Stock Warning -->
        <h2 style="text-align: center; text-shadow: 2px 2px 5px lightblue; font-size: 25px;">Items List</h2>
        <?php
        // Fetch items and their safety stock levels
        $sql = "SELECT i.item_id, i.item_name, i.quantity, s.safety_stock_quantity
                FROM items i
                LEFT JOIN safety_stock s ON i.item_id = s.item_id";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Item ID</th><th>Item Name</th><th>Quantity</th><th>Safety Stock</th><th>Status</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["item_id"] . "</td>";
                echo "<td>" . $row["item_name"] . "</td>";
                echo "<td>" . $row["quantity"] . "</td>";
                echo "<td>" . ($row["safety_stock_quantity"] ?? 'N/A') . "</td>";
                if (isset($row["safety_stock_quantity"]) && $row["quantity"] <= $row["safety_stock_quantity"]) {
                    echo "<td class='warning'>Warning: Low Stock!</td>";
                } else {
                    echo "<td>OK</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No items found.</p>";
        }

        $conn->close();
        ?>
    </div>
    <br><br>
    <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>
</body>
</html>
