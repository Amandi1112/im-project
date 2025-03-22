<?php
// Configuration
$host = 'localhost';
$dbname = 'mywebsite';
$user = 'root';
$password = '';

// Connect to database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}

// Fetch items
$stmt = $pdo->prepare("SELECT * FROM items");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customers
$stmt = $pdo->prepare("SELECT * FROM membership_numbers");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $membership_number = $_POST['membership_number'];
    $item_id = $_POST['item_id'];
    $quantity = $_POST['quantity'];

    // Check credit limit
    $stmt = $pdo->prepare("SELECT credit_limit FROM membership_numbers WHERE membership_number = :membership_number");
    $stmt->bindParam(':membership_number', $membership_number);
    $stmt->execute();
    $creditLimit = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT price_per_unit FROM items WHERE item_id = :item_id");
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();
    $pricePerUnit = $stmt->fetchColumn();

    $totalPrice = $quantity * $pricePerUnit;

    if ($totalPrice <= $creditLimit) {
        // Generate transaction_id starting with 'T'
        $stmt = $pdo->prepare("SELECT MAX(transaction_id) FROM customer_transactions");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        
        if ($lastId) {
            $numericPart = substr($lastId, 1); // Remove 'T'
            $newId = 'T' . str_pad((int)$numericPart + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newId = 'T00001';
        }

        // Insert transaction
        $stmt = $pdo->prepare("INSERT INTO customer_transactions 
            (transaction_id, membership_number, item_id, quantity, price_per_unit, total_price) 
            VALUES (:transaction_id, :membership_number, :item_id, :quantity, :price_per_unit, :total_price)");
        
        $stmt->bindParam(':transaction_id', $newId);
        $stmt->bindParam(':membership_number', $membership_number);
        $stmt->bindParam(':item_id', $item_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':price_per_unit', $pricePerUnit);
        $stmt->bindParam(':total_price', $totalPrice);
        $stmt->execute();

        // Prevent resubmission
        echo '<script>
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        </script>';
        
        $message = '<div class="alert alert-success">Transaction successful! (ID: ' . $newId . ')</div>';
    } else {
        $message = '<div class="alert alert-danger">Insufficient credit.</div>';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Purchases</title>
    

<style>
        body {
            background: url("images/background60.jpg");
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
        }
        .container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            
            margin-top:100px;
            margin: auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: #d5731846;
    /* Changed color */
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .alert {
            position: fixed;
            top: 2rem;
            left: -300px;
            width: 300px;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.4s ease;
            z-index: 1000;
        }
        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            color: #2e7d32;
        }
        .alert-danger {
            background: #ffebee;
            border-left: 4px solid #d32f2f;
            color: #b71c1c;
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
        form {
            margin-top: 20px;
        }
        label {
            display: block;
            margin-bottom: 10px;
        }
        select, input[type="number"] {
            width: 100%;
            height: 30px;
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ccc;
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
    <h2 style="color :rgb(0, 0, 0);text-shadow: 2px 2px 5px lightblue; text-align:center;font-size: 40px; ">Member Purchases</h2>

    <?php if (isset($message)) { echo $message; } ?>

    <form action="" method="post">
        <!-- Existing form fields remain the same -->
        <label for="membership_number">Customer:</label>
        <select id="membership_number" name="membership_number" style="height: 35px;">
            <?php foreach ($customers as $customer) { ?>
                <option value="<?php echo $customer['membership_number']; ?>">
                    <?php echo $customer['membership_number']; ?> - <?php echo $customer['nic_number']; ?>
                </option>
            <?php } ?>
        </select>

        <label for="item_id">Item:</label>
        <select id="item_id" name="item_id" style="height: 38px;">
            <?php foreach ($items as $item) { ?>
                <option value="<?php echo $item['item_id']; ?>">
                    <?php echo $item['item_name']; ?> (Rs.<?php echo $item['price_per_unit']; ?>)
                </option>
            <?php } ?>
        </select>

        <label for="quantity">Quantity:</label>
        <input type="number" id="quantity" name="quantity" required style="height: 15px; width: 577px;">

        
    </form>
    </div>
    <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
    <div class="nav-btn-container">
    <input type="submit" value="Make Purchase" class="home-btn">
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>

    <script>
        // Slide-in/slide-out animation
        const alert = document.querySelector('.alert');
        if (alert) {
            // Slide in
            alert.style.left = '20px';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alert.style.left = '-600px';
                setTimeout(() => alert.remove(), 600); // Remove after animation
            }, 5000);
        }

        // Example JavaScript code for dynamic updates or validation
        console.log("JavaScript is working.");
    </script>

</div>

    

</body>
</html>


