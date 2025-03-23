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

// Fetch all invoices
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
    ORDER BY
        ct.transaction_date DESC
");
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated Invoices</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        .home-btn, .print-btn {
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
        .home-btn:hover, .print-btn:hover {
            background-color: #f28252;
        }
        .nav-btn-container {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                background: none;
            }
            .container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Generated Invoices</h2>
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
            <?php foreach ($invoices as $invoice) { ?>
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
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>
</div>

</body>
</html>
