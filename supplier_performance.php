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

// Function to calculate supplier performance
function getSupplierPerformance($conn, $start_date = '', $end_date = '') {
    $sql = "SELECT 
                s.supplier_id,
                s.supplier_name,
                COUNT(ip.purchase_id) as total_purchases,
                SUM(ip.quantity) as total_quantity,
                SUM(ip.total_price) as total_amount,
                AVG(ip.price_per_unit) as avg_price,
                MIN(ip.purchase_date) as first_purchase,
                MAX(ip.purchase_date) as last_purchase
            FROM 
                supplier s
            LEFT JOIN 
                items i ON s.supplier_id = i.supplier_id
            LEFT JOIN 
                item_purchases ip ON i.item_id = ip.item_id";
    
    $conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($start_date)) {
        $conditions[] = "ip.purchase_date >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    
    if (!empty($end_date)) {
        $conditions[] = "ip.purchase_date <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY s.supplier_id, s.supplier_name ORDER BY total_amount DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $performances = [];
    
    while ($row = $result->fetch_assoc()) {
        $performances[] = $row;
    }
    
    return $performances;
}

// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get supplier performance data
$supplier_performances = getSupplierPerformance($conn, $start_date, $end_date);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Performance Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .performance-table {
            margin-top: 20px;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .print-button {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">Supplier Performance Summary</h2>
        
        <form method="GET" action="" class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4 align-self-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="?" class="btn btn-secondary">Reset</a>
                    <button type="button" onclick="printReport()" class="btn btn-success ms-2">Print Report</button>
                </div>
            </div>
        </form>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover performance-table">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Total Purchases</th>
                        <th>Total Quantity</th>
                        <th>Total Amount</th>
                        <th>Average Price</th>
                        <th>First Purchase</th>
                        <th>Last Purchase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalPurchases = 0;
                    $totalQuantity = 0;
                    $totalAmount = 0;
                    
                    foreach($supplier_performances as $performance): 
                        $totalPurchases += $performance['total_purchases'];
                        $totalQuantity += $performance['total_quantity'];
                        $totalAmount += $performance['total_amount'];
                    ?>
                    <tr>
                        <td><?php echo $performance['supplier_name']; ?></td>
                        <td><?php echo $performance['total_purchases']; ?></td>
                        <td><?php echo $performance['total_quantity']; ?></td>
                        <td><?php echo number_format($performance['total_amount'], 2); ?></td>
                        <td><?php echo number_format($performance['avg_price'], 2); ?></td>
                        <td><?php echo date('d M Y', strtotime($performance['first_purchase'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($performance['last_purchase'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (count($supplier_performances) > 0): ?>
                    <tr class="total-row">
                        <td>Total</td>
                        <td><?php echo $totalPurchases; ?></td>
                        <td><?php echo $totalQuantity; ?></td>
                        <td><?php echo number_format($totalAmount, 2); ?></td>
                        <td colspan="3"></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (count($supplier_performances) == 0): ?>
            <div class="alert alert-info">No supplier performance data found.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printReport() {
            window.location.href = 'supplier_performance_print.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>';
        }
    </script>
</body>
</html>