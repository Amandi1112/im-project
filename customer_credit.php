<?php
// Database connection configuration
$servername = "localhost";
$username = "root"; // Change this to your database username
$password = ""; // Change this to your database password
$dbname = "mywebsite"; // Change this to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Determine which report to show
$reportType = isset($_GET['report']) ? $_GET['report'] : 'menu';
$selectedMember = isset($_GET['member']) ? $_GET['member'] : '';

// Function to get all members for dropdown
function getAllMembers($conn) {
    $query = "SELECT membership_number, nic_number FROM membership_numbers ORDER BY membership_number";
    $result = $conn->query($query);
    $members = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }
    
    return $members;
}

// Set the header to download as Excel file if requested
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    $filename = "credit_balance_";
    
    if ($reportType == 'summary') {
        $filename .= "summary";
    } elseif ($reportType == 'individual' && !empty($selectedMember)) {
        $filename .= "member_".$selectedMember;
    }
    
    header('Content-Disposition: attachment;filename="'.$filename.'.xls"');
}

// Query to get summary data - all members
function getSummaryData($conn) {
    $query = "SELECT m.membership_number, m.nic_number, m.credit_limit, 
              COUNT(ct.transaction_id) as transaction_count,
              SUM(ct.total_price) as total_spent
              FROM membership_numbers m
              LEFT JOIN customer_transactions ct ON m.membership_number = ct.membership_number
              GROUP BY m.membership_number, m.nic_number, m.credit_limit
              ORDER BY m.membership_number";
              
    return $conn->query($query);
}

// Query to get member detail
function getMemberDetail($conn, $membershipNumber) {
    $query = "SELECT m.membership_number, m.nic_number, m.credit_limit, 
              COUNT(ct.transaction_id) as transaction_count,
              SUM(ct.total_price) as total_spent
              FROM membership_numbers m
              LEFT JOIN customer_transactions ct ON m.membership_number = ct.membership_number
              WHERE m.membership_number = ?
              GROUP BY m.membership_number, m.nic_number, m.credit_limit";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $membershipNumber);
    $stmt->execute();
    return $stmt->get_result();
}

// Query to get transactions for a specific member
function getMemberTransactions($conn, $membershipNumber) {
    $query = "SELECT ct.transaction_id, ct.item_id, i.item_name, 
              ct.quantity, ct.price_per_unit, ct.total_price, ct.transaction_date
              FROM customer_transactions ct
              JOIN items i ON ct.item_id = i.item_id
              WHERE ct.membership_number = ?
              ORDER BY ct.transaction_date DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $membershipNumber);
    $stmt->execute();
    return $stmt->get_result();
}

// Query to get all transactions
function getAllTransactions($conn) {
    $query = "SELECT ct.membership_number, ct.transaction_id, ct.item_id, 
              i.item_name, ct.quantity, ct.price_per_unit, ct.total_price, ct.transaction_date
              FROM customer_transactions ct
              JOIN items i ON ct.item_id = i.item_id
              ORDER BY ct.membership_number, ct.transaction_date DESC";
              
    return $conn->query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Balance Report System</title>
    <style>
        body {
            background: url("images/background60.jpg");
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2, h3 {
            color: #333;
        }
        .report-header {
            margin-bottom: 20px;
            text-align: center;
        }
        .report-date {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 20px;
            text-align: center;
        }
        .summary-table, .transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .summary-table th, .summary-table td, 
        .transaction-table th, .transaction-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .summary-table th, .transaction-table th {
            background-color: #f2f2f2;
        }
        .member-section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .positive-balance {
            color: green;
        }
        .negative-balance {
            color: red;
        }
        .export-btn, .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .print-btn {
            background-color: #2196F3;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            margin-right: 10px;
            border-radius: 4px;
        }
        .menu-card {
            
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            background-color: #f9f9f9;
            text-align: center;
        }
        .menu-buttons {
            margin: 30px 0;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        .menu-buttons a {
            flex: 1;
            max-width: 300px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: white;
            background-color: rgb(135, 74, 0);
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .menu-buttons a:hover {
            background-color: rgb(221, 125, 35);
            transform: translateY(-3px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .back-link {
    display: inline-block;
    margin-bottom: 20px;
    background-color: rgb(135, 74, 0);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    width: 7.5%;
    font-size: 16px;
    text-decoration: none; /* This removes the underline */
}
        .back-link:hover {
            background-color:rgb(221, 125, 35);
        }
        .report-filters {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
        .container {
            max-width: 600px;
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
        
        .nav-btn-container {
            text-align: center; /* Center the navigation buttons */
        }
        .btn{
           background-color:rgb(135, 74, 0);
        }
        .btn:hover{
            background-color: rgb(221, 125, 35);
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
    <?php if ($reportType == 'menu'): ?>
        <!-- Main Menu -->
         <br><br><br>
        <div class="menu-card">
            <h1>Credit Balance Report System</h1>
            <p>Select the type of report you would like to generate:</p>
            
            <div class="menu-buttons">
                <a href="?report=summary">Summary Report</a>
                <a href="?report=individual">Individual Member Report</a>
            </div>
        </div>
    
    <?php elseif ($reportType == 'summary'): ?>
        <!-- Summary Report -->
        <div class="report-header">
            <h1 style="background-color:rgb(251, 236, 226); width: 700px; text-align: center; margin-left:400px; border: radius 8px;">Customer Credit Balance - Summary Report</h1>
        </div>
        
        <div class="report-date" style="color:black; font-weight: bold; font-size: 20px;">
            Report Generated on: <?php echo date('F d, Y h:i A'); ?>
        </div>
        
        <div class="no-print">
            <a href="?report=menu" class="back-link">Back to Menu</a><br>
            <a href="javascript:window.print()" class="print-btn">Print Report</a>
            
        </div>
        
        <table class="summary-table" style="background-color:rgb(255, 255, 255);">
            <thead>
                <tr>
                    <th>Membership No.</th>
                    <th>NIC Number</th>
                    <th>Credit Limit</th>
                    <th>Total Spent</th>
                    <th>Remaining Balance</th>
                    <th>Transactions</th>
                    <th class="no-print">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $memberResult = getSummaryData($conn);
                if ($memberResult->num_rows > 0) {
                    while ($member = $memberResult->fetch_assoc()) {
                        $remainingBalance = $member['credit_limit'] - ($member['total_spent'] ?: 0);
                        $balanceClass = $remainingBalance >= 0 ? 'positive-balance' : 'negative-balance';
                ?>
                    <tr>
                        <td><?php echo $member['membership_number']; ?></td>
                        <td><?php echo $member['nic_number']; ?></td>
                        <td><?php echo number_format($member['credit_limit'], 2); ?></td>
                        <td><?php echo number_format($member['total_spent'] ?: 0, 2); ?></td>
                        <td class="<?php echo $balanceClass; ?>"><?php echo number_format($remainingBalance, 2); ?></td>
                        <td><?php echo $member['transaction_count'] ?: 0; ?></td>
                        <td class="no-print">
                            <a href="?report=individual&member=<?php echo $member['membership_number']; ?>">View Details</a>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='7'>No members found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    
    <?php elseif ($reportType == 'individual'): ?>
        <!-- Individual Member Selection or Report -->
        <div class="report-header">
            <h1 style="background-color:rgb(251, 236, 226); width: 700px; text-align: center; margin-left:400px; border: radius 8px;">Customer Credit Balance - Individual Report</h1>
        </div>
        <?php if (empty($selectedMember)): ?>
            <!-- Member Selection Form -->
            <div class="container">
                <h2 style="text-shadow: 2px 2px 5px lightblue; font-size: 25px; margin-top: 10px;">Select Member</h2>
                <form action="" method="GET">
                    <input type="hidden" name="report" value="individual">
                    <div class="form-group">
                        <label for="member">Membership Number:</label>
                        <select id="member" name="member" required>
                            <option value="">-- Select a Member --</option>
                            <?php
                            $members = getAllMembers($conn);
                            foreach ($members as $member) {
                                echo "<option value='".$member['membership_number']."'>".$member['membership_number']." (NIC: ".$member['nic_number'].")</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">Generate Report</button>
                </form>
            </div>
            <br>
        <div class="nav-btn-container">
            <a href="?report=menu" class="back-link"> Back to Menu</a>
        </div>
        <?php else: ?>
            <!-- Individual Member Report -->
            <?php
            $memberResult = getMemberDetail($conn, $selectedMember);
            if ($memberResult->num_rows > 0) {
                $member = $memberResult->fetch_assoc();
                $remainingBalance = $member['credit_limit'] - ($member['total_spent'] ?: 0);
                $balanceClass = $remainingBalance >= 0 ? 'positive-balance' : 'negative-balance';
                
                // Get transactions for this member
                $transactionResult = getMemberTransactions($conn, $selectedMember);
            ?>
                <div class="report-date" style="color:black; font-weight: bold; font-size: 20px;">
                    Report : <?php echo date('F d, Y h:i A'); ?>
                </div>
                
                <div class="no-print">
                    <a href="?report=individual" class="back-link" style="width: 216px;">← Back to Member Selection</a><br>
                    <a href="javascript:window.print()" class="print-btn">Print Report</a>
                    
                </div>
                
                <div class="member-section">
                    <h2>Member Information</h2>
                    <table class="summary-table" style="background-color: white;">
                        <tr>
                            <th>Membership Number</th>
                            <td><?php echo $member['membership_number']; ?></td>
                        </tr>
                        <tr>
                            <th>NIC Number</th>
                            <td><?php echo $member['nic_number']; ?></td>
                        </tr>
                        <tr>
                            <th>Credit Limit</th>
                            <td><?php echo number_format($member['credit_limit'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Total Spent</th>
                            <td><?php echo number_format($member['total_spent'] ?: 0, 2); ?></td>
                        </tr>
                        <tr>
                            <th>Remaining Balance</th>
                            <td class="<?php echo $balanceClass; ?>"><?php echo number_format($remainingBalance, 2); ?></td>
                        </tr>
                        <tr>
                            <th>Number of Transactions</th>
                            <td><?php echo $member['transaction_count'] ?: 0; ?></td>
                        </tr>
                    </table>
                    
                    <h2>Transaction History</h2>
                    <?php if ($transactionResult->num_rows > 0): ?>
                        <table class="transaction-table">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Price Per Unit</th>
                                    <th>Total Price</th>
                                    <th>Transaction Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($transaction = $transactionResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $transaction['transaction_id']; ?></td>
                                        <td><?php echo $transaction['item_name']; ?> (<?php echo $transaction['item_id']; ?>)</td>
                                        <td><?php echo $transaction['quantity']; ?></td>
                                        <td><?php echo number_format($transaction['price_per_unit'], 2); ?></td>
                                        <td><?php echo number_format($transaction['total_price'], 2); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($transaction['transaction_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No transactions found for this member.</p>
                    <?php endif; ?>
                </div>
               
            <?php } else { ?>
                <div class="alert">
                    <p>Member not found. Please select a valid membership number.</p>
                    <a href="?report=individual" class="btn">Go Back</a>
                </div>
                
            <?php } ?>
        <?php endif; ?>
    <?php endif; ?>
    <br>
    <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>
    
    <?php
    // Close the connection
    $conn->close();
    ?>
</body>
</html>