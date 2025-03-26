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
                s.supplier_name
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchased Items Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }

        .filter-section {
            background-color: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-label {
            color: #34495e;
            margin-bottom: 5px;
        }

        .form-control {
            border-radius: 5px;
            border: 1px solid #bdc3c7;
            padding: 8px 12px;
            margin-bottom: 15px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .btn-primary,
        .btn-secondary {
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-primary {
            background-color: #3498db;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
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
            border-radius: 5px;
            margin-top: 2px;
            padding: 0;
            list-style: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .ui-autocomplete li {
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s ease;
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
        }

        .card {
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .card-body {
            padding: 15px;
        }

        .card-title {
            color: #34495e;
            margin-bottom: 10px;
        }

        .card-text {
            color: #7f8c8d;
            font-size: 1rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background-color: #f0f3f4;
            color: #34495e;
            font-weight: 600;
        }

        .table tbody tr:hover {
            background-color: #f9fafa;
        }

        .total-row {
            font-weight: bold;
            background-color: #f0f3f4;
        }

        .expired {
            background-color: #ffe6e6;
            color: #c0392b;
        }

        .expiring-soon {
            background-color: #fff3cd;
            color: #d35400;
        }

        .alert-info {
            background-color: #e7f5ff;
            color: #3498db;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .text-end {
            text-align: right;
        }

        /* Styles for chart container */
        .chart-container {
            width: 80%;
            margin: auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Purchased amount Details</h1>

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
            <h4>Supplier Totals</h4>
            <div class="row">
                <?php foreach ($supplierTotals as $supplier): ?>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h5>
                            <p class="card-text">Total Amount: Rs. <?php echo number_format($supplier['supplier_total'], 2); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chart Visualization -->
        <div class="chart-container">
            <canvas id="supplierChart"></canvas>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>Purchase Date</th>
                        <th>Item Name</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
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
                    
                    foreach($purchases as $purchase): 
                        // Check if the item is expired or expiring soon
                        $status = '';
                        $rowClass = '';
                        $currentDate = new DateTime();
                        
                        if (!empty($purchase['expire_date'])) {
                            $expireDate = new DateTime($purchase['expire_date']);
                            $interval = $currentDate->diff($expireDate);
                            
                            if ($expireDate < $currentDate) {
                                $status = 'Expired';
                                $rowClass = 'expired';
                            } elseif ($interval->days <= 30) {
                                $status = 'Expiring soon (' . $interval->days . ' days)';
                                $rowClass = 'expiring-soon';
                            } else {
                                $status = 'Active';
                            }
                        } else {
                            $status = 'No expiry';
                        }
                        
                        $totalQuantity += $purchase['quantity'];
                        $totalAmount += $purchase['total_price'];
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
                        <td><?php echo $purchase['item_name']; ?></td>
                        <td><?php echo $purchase['supplier_name']; ?></td>
                        <td><?php echo $purchase['quantity']; ?></td>
                        <td><?php echo number_format($purchase['price_per_unit'], 2); ?></td>
                        <td><?php echo number_format($purchase['total_price'], 2); ?></td>
                        <td><?php echo !empty($purchase['expire_date']) ? date('d M Y', strtotime($purchase['expire_date'])) : 'N/A'; ?></td>
                        <td><?php echo $status; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (count($purchases) > 0): ?>
                    <tr class="total-row">
                        <td colspan="3" class="text-end">Total:</td>
                        <td><?php echo $totalQuantity; ?></td>
                        <td></td>
                        <td><?php echo number_format($totalAmount, 2); ?></td>
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
