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

// Get all purchase details with filters
$purchases = getPurchaseDetails($conn, $start_date, $end_date, $supplier_filter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchased Items Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <style>
        .table-responsive {
            margin-top: 20px;
        }
        .table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .expired {
            background-color: #ffe6e6;
        }
        .expiring-soon {
            background-color: #fff3cd;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .supplier-search-container {
            position: relative;
        }
        .hidden-id-field {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">Purchased Items Details</h2>
        
        <div class="mb-3">
            <a href="supplier_purchases.php" class="btn btn-primary">Add New Purchases</a>
            <a href="home.php" class="btn btn-light">Back to Home</a>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
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
                            <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                   value="<?php echo htmlspecialchars($supplier_name); ?>" 
                                   placeholder="Type supplier name...">
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
    <script>
        // Set default date values to today and one month ago
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
            const oneMonthAgoStr = oneMonthAgo.toISOString().split('T')[0];
            
            // Only set defaults if no dates are already selected
            if (!document.getElementById('start_date').value && !document.getElementById('end_date').value) {
                document.getElementById('start_date').value = oneMonthAgoStr;
                document.getElementById('end_date').value = today;
            }
        });

        // Supplier autocomplete functionality
        $(function() {
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