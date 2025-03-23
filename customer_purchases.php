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

// Initialize variables
$showInvoice = false;
$invoiceData = [];
$searchedInvoices = [];

// Handle form submission for purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_purchase'])) {
    $membership_number = $_POST['membership_number'];
    $item_id = $_POST['item_id'];
    $quantity = $_POST['quantity'];

    // Get member information
    $stmt = $pdo->prepare("SELECT credit_limit FROM membership_numbers WHERE membership_number = :membership_number");
    $stmt->bindParam(':membership_number', $membership_number);
    $stmt->execute();
    $creditLimit = $stmt->fetchColumn();

    // Get item information
    $stmt = $pdo->prepare("SELECT price_per_unit, item_name, quantity as stock_quantity FROM items WHERE item_id = :item_id");
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();
    $itemInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $pricePerUnit = $itemInfo['price_per_unit'];
    $itemName = $itemInfo['item_name'];
    $stockQuantity = $itemInfo['stock_quantity'];

    // Check if there is enough stock
    if ($quantity > $stockQuantity) {
        $message = '<div class="alert alert-danger">Insufficient stock. Available stock: ' . $stockQuantity . '</div>';
    } else {
        // Calculate total price
        $totalPrice = $quantity * $pricePerUnit;

        // Calculate current used credit
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM customer_transactions WHERE membership_number = :membership_number");
        $stmt->bindParam(':membership_number', $membership_number);
        $stmt->execute();
        $usedCredit = $stmt->fetchColumn() ?: 0;

        // Calculate available credit
        $availableCredit = $creditLimit - $usedCredit;

        if ($totalPrice <= $availableCredit) {
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

            // Update stock quantity
            $newStockQuantity = $stockQuantity - $quantity;
            $stmt = $pdo->prepare("UPDATE items SET quantity = :quantity WHERE item_id = :item_id");
            $stmt->bindParam(':quantity', $newStockQuantity);
            $stmt->bindParam(':item_id', $item_id);
            $stmt->execute();

            // Create invoice data
            $newCreditUsed = $usedCredit + $totalPrice;
            $newAvailableCredit = $creditLimit - $newCreditUsed;

            // Get customer NIC
            $stmt = $pdo->prepare("SELECT nic_number FROM membership_numbers WHERE membership_number = :membership_number");
            $stmt->bindParam(':membership_number', $membership_number);
            $stmt->execute();
            $nicNumber = $stmt->fetchColumn();

            $invoiceData = [
                'transaction_id' => $newId,
                'membership_number' => $membership_number,
                'nic_number' => $nicNumber,
                'transaction_date' => date('Y-m-d H:i:s'),
                'item_name' => $itemName,
                'item_id' => $item_id,
                'quantity' => $quantity,
                'price_per_unit' => $pricePerUnit,
                'total_price' => $totalPrice,
                'credit_limit' => $creditLimit,
                'credit_used' => $newCreditUsed,
                'available_credit' => $newAvailableCredit
            ];

            $showInvoice = true;
            $message = '<div class="alert alert-success">Transaction successful! (ID: ' . $newId . ')</div>';
        } else {
            $message = '<div class="alert alert-danger">Insufficient credit. Available credit: Rs.' . number_format($availableCredit, 2) . '</div>';
        }
    }
}

// Handle form submission for search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_invoices'])) {
    $membership_number = $_POST['search_membership_number'];

    // Fetch invoices for the specified membership number
    $stmt = $pdo->prepare("
        SELECT
            ct.transaction_id,
            ct.membership_number,
            mn.nic_number,
            ct.item_id,
            i.item_name,
            ct.quantity,
            ct.price_per_unit,
            ct.total_price,
            ct.transaction_date
        FROM
            customer_transactions ct
        JOIN
            membership_numbers mn ON ct.membership_number = mn.membership_number
        JOIN
            items i ON ct.item_id = i.item_id
        WHERE
            ct.membership_number = :membership_number
        ORDER BY
            ct.transaction_date DESC
    ");
    $stmt->bindParam(':membership_number', $membership_number);
    $stmt->execute();
    $searchedInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle reset action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_search'])) {
    $searchedInvoices = [];
    $message = '<div class="alert alert-success">Search results cleared!</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Purchases</title>
    <style>
        body {
            background: url("images/background60.jpg");
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
        }
        .container {
            position: relative;
            width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: #d5731846;
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
        select, input[type="number"], input[type="text"] {
            width: 100%;
            height: 30px;
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ccc;
        }

        .home-btn, .print-btn, .reset-btn {
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
        .home-btn:hover, .print-btn:hover, .reset-btn:hover {
            background-color: #f28252;
        }
        .nav-btn-container {
            text-align: center;
            margin-top: 20px;
        }

        /* Invoice styles */
        .invoice-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .invoice-details {
            margin-bottom: 20px;
        }
        .invoice-details div {
            margin-bottom: 5px;
        }
        .invoice-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .invoice-summary {
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                background: none;
            }
            .container, .invoice-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2 style="color :rgb(0, 0, 0);text-shadow: 2px 2px 5px lightblue; text-align:center;font-size: 40px; ">Member Purchases</h2>

    <?php if (isset($message)) { echo $message; } ?>

    <?php if (!$showInvoice): ?>
    <form action="" method="post">
        <label for="membership_number">Customer:</label>
        <select id="membership_number" name="membership_number" style="height: 35px;">
            <?php foreach ($customers as $customer) {
                // Calculate used credit
                $stmt = $pdo->prepare("SELECT SUM(total_price) FROM customer_transactions WHERE membership_number = :membership_number");
                $stmt->bindParam(':membership_number', $customer['membership_number']);
                $stmt->execute();
                $usedCredit = $stmt->fetchColumn() ?: 0;

                // Calculate available credit
                $availableCredit = $customer['credit_limit'] - $usedCredit;
            ?>
                <option value="<?php echo $customer['membership_number']; ?>">
                    <?php echo $customer['membership_number']; ?> - <?php echo $customer['nic_number']; ?>
                    (Available: Rs.<?php echo number_format($availableCredit, 2); ?>)
                </option>
            <?php } ?>
        </select>

        <label for="item_id">Item:</label>
        <select id="item_id" name="item_id" style="height: 38px;">
            <?php foreach ($items as $item) { ?>
                <option value="<?php echo $item['item_id']; ?>">
                    <?php echo $item['item_name']; ?> (Rs.<?php echo $item['price_per_unit']; ?>) - Stock: <?php echo $item['quantity']; ?>
                </option>
            <?php } ?>
        </select>

        <label for="quantity">Quantity:</label>
        <input type="number" id="quantity" name="quantity" required style="height: 15px; width: 577px;" min="1">

        <div class="nav-btn-container">
            <input type="submit" name="make_purchase" value="Make Purchase" class="home-btn">
            <a href="home.php" class="home-btn">Back to Home Page</a>
        </div>
    </form>

    <form action="" method="post" style="margin-top: 20px;">
        <label for="search_membership_number">Search Invoices by Membership Number:</label>
        <input type="text" id="search_membership_number" name="search_membership_number" required>
        <div class="nav-btn-container">
            <input type="submit" name="search_invoices" value="Search Invoices" class="home-btn">
        </div>
    </form>

    <?php if (!empty($searchedInvoices)): ?>
        <h3>Search Results</h3>
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Membership Number</th>
                    <th>NIC Number</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Price per Unit</th>
                    <th>Total Price</th>
                    <th>Transaction Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($searchedInvoices as $invoice) { ?>
                    <tr>
                        <td><?php echo $invoice['transaction_id']; ?></td>
                        <td><?php echo $invoice['membership_number']; ?></td>
                        <td><?php echo $invoice['nic_number']; ?></td>
                        <td><?php echo $invoice['item_name']; ?></td>
                        <td><?php echo $invoice['quantity']; ?></td>
                        <td>Rs. <?php echo number_format($invoice['price_per_unit'], 2); ?></td>
                        <td>Rs. <?php echo number_format($invoice['total_price'], 2); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($invoice['transaction_date'])); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <div class="nav-btn-container no-print">
            <button class="print-btn" onclick="window.print()">Print Invoices</button>
            <form action="" method="post" style="display:inline;">
                <input type="submit" name="reset_search" value="Reset" class="reset-btn">
            </form>
        </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Credit Invoice -->
    <div class="invoice-container">
        <div class="invoice-header">
            <h2>CREDIT PURCHASE INVOICE</h2>
            <p>Transaction ID: <?php echo $invoiceData['transaction_id']; ?></p>
        </div>

        <div class="invoice-details">
            <div><strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($invoiceData['transaction_date'])); ?></div>
            <div><strong>Membership Number:</strong> <?php echo $invoiceData['membership_number']; ?></div>
            <div><strong>NIC:</strong> <?php echo $invoiceData['nic_number']; ?></div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Item Code</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo $invoiceData['item_name']; ?></td>
                    <td><?php echo $invoiceData['item_id']; ?></td>
                    <td><?php echo $invoiceData['quantity']; ?></td>
                    <td>Rs. <?php echo number_format($invoiceData['price_per_unit'], 2); ?></td>
                    <td>Rs. <?php echo number_format($invoiceData['total_price'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="invoice-summary">
            <div><strong>Purchase Amount:</strong> Rs. <?php echo number_format($invoiceData['total_price'], 2); ?></div>
            <div><strong>Credit Limit:</strong> Rs. <?php echo number_format($invoiceData['credit_limit'], 2); ?></div>
            <div><strong>Total Credit Used:</strong> Rs. <?php echo number_format($invoiceData['credit_used'], 2); ?></div>
            <div><strong>Remaining Credit Balance:</strong> Rs. <?php echo number_format($invoiceData['available_credit'], 2); ?></div>
        </div>

        <div class="nav-btn-container no-print">
            <button onclick="window.print()" class="print-btn">Print Invoice</button>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="home-btn">New Purchase</a>
            <a href="home.php" class="home-btn">Back to Home Page</a>
        </div>
    </div>
    <?php endif; ?>
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

    // Form validation
    document.querySelector('form')?.addEventListener('submit', function(e) {
        const quantity = document.getElementById('quantity').value;
        if (quantity <= 0) {
            e.preventDefault();
            alert('Please enter a valid quantity');
        }
    });
</script>

</body>
</html>
