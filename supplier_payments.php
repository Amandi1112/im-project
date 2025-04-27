<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// AJAX request for supplier suggestions
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_term'])) {
    $searchTerm = $conn->real_escape_string($_GET['search_term']);
    $sql = "SELECT supplier_id, supplier_name FROM supplier 
            WHERE supplier_name LIKE '%$searchTerm%' 
            ORDER BY supplier_name LIMIT 10";
    $result = $conn->query($sql);
    
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = [
            'id' => $row['supplier_id'],
            'name' => $row['supplier_name']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit;
}

// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$supplier_filter = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : '';
$supplier_name = isset($_GET['supplier_name']) ? $_GET['supplier_name'] : '';

// Function to get all purchases with item and supplier details
function getPurchaseDetails($conn, $start_date = '', $end_date = '', $supplier_filter = '') {
    $sql = "SELECT 
                ip.purchase_id,
                ip.purchase_date,
                ip.expire_date,
                ip.quantity,
                ip.price_per_unit,
                ip.total_price,
                i.item_id,
                i.item_code,
                i.item_name,
                s.supplier_id,
                s.supplier_name,
                COALESCE(ip.unit, i.unit) AS unit
            FROM 
                item_purchases ip
            JOIN 
                items i ON ip.item_id = i.item_id
            JOIN 
                supplier s ON i.supplier_id = s.supplier_id";
    
    // Add WHERE conditions based on filters
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
    
    if (!empty($supplier_filter)) {
        $conditions[] = "s.supplier_id = ?";
        $params[] = $supplier_filter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY ip.purchase_date DESC, ip.purchase_id DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $purchases = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $purchases[] = $row;
        }
    }
    
    return $purchases;
}

// Function to get supplier totals
function getSupplierTotals($conn, $start_date = '', $end_date = '', $supplier_filter = '') {
    $sql = "SELECT 
                s.supplier_id,
                s.supplier_name,
                SUM(ip.total_price) as supplier_total
            FROM 
                item_purchases ip
            JOIN 
                items i ON ip.item_id = i.item_id
            JOIN 
                supplier s ON i.supplier_id = s.supplier_id";
    
    // Add WHERE conditions based on filters
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
    
    if (!empty($supplier_filter)) {
        $conditions[] = "s.supplier_id = ?";
        $params[] = $supplier_filter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY s.supplier_id, s.supplier_name ORDER BY supplier_total DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $supplierTotals = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $supplierTotals[] = $row;
        }
    }
    
    return $supplierTotals;
}

// Get all purchase details with filters
$purchases = getPurchaseDetails($conn, $start_date, $end_date, $supplier_filter);
$supplierTotals = getSupplierTotals($conn, $start_date, $end_date, $supplier_filter);

// Prepare data for chart
$labels = array_column($supplierTotals, 'supplier_name');
$data = array_column($supplierTotals, 'supplier_total');
?>

<!DOCTYPE html>
<html lang="en">
<
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchased Items Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 28px;
            position: relative;
            padding-bottom: 10px;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border-radius: 3px;
        }

        .filter-section {
            background-color: rgba(236, 240, 241, 0.7);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .filter-section:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-label {
            color: #34495e;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            border-radius: 6px;
            border: 1px solid #bdc3c7;
            padding: 10px 12px;
            margin-bottom: 15px;
            width: 100%;
            box-sizing: border-box;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .btn-primary,
        .btn-secondary {
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(to right, #5a6fd1, #6a4299);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(to right, #95a5a6, #7f8c8d);
            box-shadow: 0 4px 10px rgba(149, 165, 166, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(to right, #7f8c8d, #6c7a7b);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(149, 165, 166, 0.4);
        }

        .supplier-search-container {
            position: relative;
        }

        .ui-autocomplete {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            background-color: #fff;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            margin-top: 2px;
            padding: 0;
            list-style: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            font-family: 'Poppins', sans-serif;
        }

        .ui-autocomplete li {
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }

        .ui-autocomplete li:hover {
            background-color: #f0f3f4;
        }

        .supplier-totals {
            margin-bottom: 30px;
        }

        .supplier-totals h4 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 20px;
        }

        .card {
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
            border: none;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .card-body {
            padding: 15px;
        }

        .card-title {
            color: #34495e;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 16px;
        }

        .card-text {
            color: #7f8c8d;
            font-size: 14px;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            font-weight: 500;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }

        .total-row {
            font-weight: 600;
            background-color: rgba(102, 126, 234, 0.1);
        }

        .expired {
            background-color: rgba(255, 99, 71, 0.1);
            color: #c0392b;
        }

        .expiring-soon {
            background-color: rgba(255, 193, 7, 0.1);
            color: #d35400;
        }

        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
            font-size: 14px;
        }

        .text-end {
            text-align: right;
        }

        /* Styles for chart container */
        .chart-container {
            width: 100%;
            margin: 30px 0;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 24px;
                margin-bottom: 20px;
            }
            
            .card {
                margin-bottom: 10px;
            }
            
            .table th,
            .table td {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>

</head>
<body style="font-weight: bold; color:black;">
    <div class="container">
        <h1 style="font-weight: bold; color:black;">Purchased amount Details</h1>

        <!-- Filter Section -->
        <div class="filter-section">
            <form id="filterForm" method="GET" action="">
                <div class="row">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="supplier_name" class="form-label">Supplier</label>
                        <div class="supplier-search-container">
                            <input 
                                type="text" 
                                class="form-control" 
                                id="supplier_name" 
                                name="supplier_name" 
                                value="<?php echo htmlspecialchars($supplier_name); ?>" 
                                placeholder="Type supplier name..."
                                aria-label="Supplier name"
                                aria-describedby="supplier-name-help">
                            <input type="hidden" id="supplier_id" name="supplier_id" value="<?php echo $supplier_filter; ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="?" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Supplier Totals Section -->
        <div class="supplier-totals">
            <h4 style="font-weight: bold; color:black;">Supplier Totals</h4>
            <div class="row" style="font-weight: bold; color:black;">
                <?php foreach ($supplierTotals as $supplier): ?>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body" style="font-weight: bold; color:black;">
                            <h5 class="card-title"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h5>
                            <p class="card-text" style="font-weight: bold; color:black;">Total Amount: Rs. <?php echo number_format($supplier['supplier_total'], 2); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chart Visualization -->
        <div class="chart-container" style="font-weight: bold; color:black;">
            <canvas id="supplierChart" style="font-weight: bold; color:black;"></canvas>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover" style="font-weight: bold; color:black;">
            <thead class="thead-light">
            <tr>
                <th>Purchase Date</th>
                <th>Item Name</th>
                <th>Supplier</th>
                <th>Quantity (Unit)</th>
                <th>Price/Unit</th>
                <th>Total Price</th>
                <th>Expire Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
    <?php 
    $totalQuantity = 0;
    $totalAmount = 0;
    
    foreach ($purchases as $purchase) {
        // Calculate the status based on the expiration date
        $currentDate = new DateTime();
        $expireDate = new DateTime($purchase['expire_date']);
        $interval = $currentDate->diff($expireDate);
        $daysUntilExpire = $interval->days;
    
        if ($daysUntilExpire < 0) {
            $status = 'expired';
            $rowClass = 'expired';
        } elseif ($daysUntilExpire <= 7) {
            $status = 'expiring soon';
            $rowClass = 'expiring-soon';
        } else {
            $status = 'active';
            $rowClass = '';
        }
    
        $totalQuantity += $purchase['quantity'];
        $totalAmount += $purchase['total_price'];
        ?>
        <tr class="<?php echo $rowClass; ?>">
            <td><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
            <td><?php echo $purchase['item_name']; ?></td>
            <td><?php echo $purchase['supplier_name']; ?></td>
            <td><?php echo $purchase['quantity'] . ' ' . $purchase['unit']; ?></td>
            <td>Rs.<?php echo number_format($purchase['price_per_unit'], 2) . '/' . $purchase['unit']; ?></td>
            <td>Rs.<?php echo number_format($purchase['total_price'], 2); ?></td>
            <td><?php echo !empty($purchase['expire_date']) ? date('d M Y', strtotime($purchase['expire_date'])) : 'N/A'; ?></td>
            <td><?php echo ucwords($status); ?></td>
        </tr>
    <?php
    }
    ?>
    
    <?php if (count($purchases) > 0): ?>
    <tr class="total-row">
    <td></td>
        <td colspan="3" class="text-end">Total:</td>
        
        <td></td>
        <td>Rs.<?php echo number_format($totalAmount, 2); ?></td>
        <td colspan="2"></td>
    </tr>
    <?php endif; ?>
</tbody>
            </table>
            
            <?php if (count($purchases) == 0): ?>
            <div class="alert alert-info">No purchase records found.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Chart.js code
        const chartData = {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Total Purchase Amount',
                data: <?php echo json_encode($data); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        };

        const chartConfig = {
            type: 'bar',
            data: chartData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        const supplierChart = new Chart(
            document.getElementById('supplierChart'),
            chartConfig
        );

        $(function() {
            // Initialize datepicker
            flatpickr("#start_date, #end_date", {
                dateFormat: "Y-m-d",
            });

            $("#supplier_name").autocomplete({
                source: function(request, response) {
                    $.get({
                        url: window.location.href,
                        data: { search_term: request.term },
                        dataType: "json",
                        success: function(data) {
                            response($.map(data, function(item) {
                                return {
                                    label: item.name,
                                    value: item.name,
                                    id: item.id
                                };
                            }));
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#supplier_id").val(ui.item.id);
                    $("#supplier_name").val(ui.item.label);
                    return false;
                },
                focus: function(event, ui) {
                    $("#supplier_name").val(ui.item.label);
                    return false;
                }
            }).data("ui-autocomplete")._renderItem = function(ul, item) {
                return $("<li>")
                    .append("<div>" + item.label + "</div>")
                    .appendTo(ul);
            };

            // Clear hidden ID field when user clears the text input
            $("#supplier_name").on('input', function() {
                if ($(this).val() === '') {
                    $("#supplier_id").val('');
                }
            });
        });
    </script>
</body>
</html>
