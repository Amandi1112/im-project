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

// Initialize variables with default values
$supplier_name = '';
$item_name = '';
$purchases = [];

// AJAX request for supplier suggestions
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_term']) && isset($_GET['type'])) {
    $searchTerm = $conn->real_escape_string($_GET['search_term']);
    $type = $_GET['type'];
    
    if ($type == 'supplier') {
        $sql = "SELECT supplier_id, supplier_name FROM supplier 
                WHERE supplier_name LIKE '%$searchTerm%' 
                ORDER BY supplier_name LIMIT 10";
        $result = $conn->query($sql);
        
        $suggestions = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = [
                    'id' => $row['supplier_id'],
                    'name' => $row['supplier_name']
                ];
            }
        }
    } elseif ($type == 'item') {
        // Modified to include unit size and type in the item search
        $sql = "SELECT item_id, item_name, unit_size, unit, type FROM items 
                WHERE item_name LIKE '%$searchTerm%' 
                ORDER BY item_name, unit_size LIMIT 10";
        $result = $conn->query($sql);
        
        $suggestions = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = [
                    'id' => $row['item_id'],
                    'name' => $row['item_name'] . ' (' . $row['unit_size'] . ' ' . $row['unit'] . ')',
                    'item_name' => $row['item_name'],
                    'unit_size' => $row['unit_size'],
                    'unit' => $row['unit'],
                    'type' => $row['type']
                ];
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit;
}

// AJAX request for expiry details
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['get_expiry_details']) && isset($_GET['item_id'])) {
    $item_id = (int)$_GET['item_id'];
    
    // Get expiry details
    $expiry_details = getExpiryDetails($conn, $item_id);
    
    if (count($expiry_details) > 0) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover">';
        echo '<thead class="table-dark">';
        echo '<tr>';
        echo '<th><i class="fas fa-calendar me-1"></i> Expiry Date</th>';
        echo '<th><i class="fas fa-cubes me-1"></i> Batch Quantity</th>';
        echo '<th><i class="fas fa-warehouse me-1"></i> Current Quantity</th>';
        echo '<th><i class="fas fa-tag me-1"></i> Unit Price</th>';
        echo '<th><i class="fas fa-calendar-plus me-1"></i> Purchase Date</th>';
        echo '<th><i class="fas fa-info-circle me-1"></i> Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $currentDate = new DateTime();
        
        foreach ($expiry_details as $detail) {
            $status = '';
            $statusClass = '';
            $rowClass = '';
            
            if (!empty($detail['expire_date'])) {
                $expireDate = new DateTime($detail['expire_date']);
                $interval = $currentDate->diff($expireDate);
                
                if ($expireDate < $currentDate) {
                    $status = 'Expired';
                    $statusClass = 'status-expired';
                    $rowClass = 'table-danger';
                } elseif ($interval->days <= 7) {
                    $status = 'Expires in ' . $interval->days . ' days';
                    $statusClass = 'status-expiring';
                    $rowClass = 'table-warning';
                } elseif ($interval->days <= 30) {
                    $status = 'Expires in ' . $interval->days . ' days';
                    $statusClass = 'status-expiring';
                    $rowClass = 'table-warning';
                } else {
                    $status = 'Active (' . $interval->days . ' days left)';
                    $statusClass = 'status-active';
                    $rowClass = 'table-success';
                }
            } else {
                $status = 'No expiry date';
                $statusClass = 'status-none';
                $rowClass = '';
            }
            
            // Calculate current quantity for this specific batch
            // For now, we'll show the original batch quantity
            // You might want to implement batch-specific tracking later
            $current_batch_qty = $detail['batch_quantity']; // This should be calculated based on your inventory system
            
            echo '<tr class="' . $rowClass . '">';
            echo '<td>' . (!empty($detail['expire_date']) ? date('d M Y', strtotime($detail['expire_date'])) : 'No expiry') . '</td>';
            echo '<td>' . $detail['batch_quantity'] . '</td>';
            echo '<td>' . $current_batch_qty . '</td>';
            echo '<td>Rs.' . number_format($detail['avg_price'], 2) . '</td>';
            echo '<td>' . date('d M Y', strtotime($detail['first_purchase']));
            if ($detail['first_purchase'] != $detail['last_purchase']) {
                echo ' - ' . date('d M Y', strtotime($detail['last_purchase']));
            }
            echo '</td>';
            echo '<td><span class="status-badge ' . $statusClass . '">' . $status . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-info">No expiry details found for this item.</div>';
    }
    
    exit;
}


// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$supplier_filter = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : '';
$supplier_name = isset($_GET['supplier_name']) ? $_GET['supplier_name'] : '';
$item_filter = isset($_GET['item_id']) ? $_GET['item_id'] : '';
$item_name = isset($_GET['item_name']) ? $_GET['item_name'] : '';
$min_price = isset($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';

// Function to calculate current quantity for an item across all purchases
function getCurrentQuantity($conn, $item_id) {
    // Get total purchased quantity for this item
    $sql = "SELECT COALESCE(SUM(quantity), 0) as total_purchased 
            FROM item_purchases 
            WHERE item_id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $total_purchased = $row['total_purchased'];
            
            // Get total sold/used quantity from member purchases
            $sql2 = "SELECT COALESCE(SUM(quantity), 0) as total_sold 
                     FROM purchases 
                     WHERE item_id = ?";
            
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param("i", $item_id);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($result2 && $row2 = $result2->fetch_assoc()) {
                    $total_sold = $row2['total_sold'];
                    return max(0, $total_purchased - $total_sold);
                }
            }
            
            return $total_purchased;
        }
    }
    
    return 0;
}

// Function to calculate current quantity for an item with specific expire date
function getCurrentQuantityByExpireDate($conn, $item_id, $expire_date) {
    // Get total purchased quantity for this item with this expire date
    $sql = "SELECT COALESCE(SUM(quantity), 0) as total_purchased 
            FROM item_purchases 
            WHERE item_id = ? AND expire_date = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $item_id, $expire_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $total_purchased = $row['total_purchased'];
            
            // For tracking sales/usage by expire date, you might need to modify your purchases table
            // to include expire_date tracking. For now, we'll use a simplified approach
            // where we assume FIFO (First In, First Out) for sales
            
            // Get total sold/used quantity from member purchases for this item
            $sql2 = "SELECT COALESCE(SUM(quantity), 0) as total_sold 
                     FROM purchases 
                     WHERE item_id = ?";
            
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param("i", $item_id);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($result2 && $row2 = $result2->fetch_assoc()) {
                    $total_sold = $row2['total_sold'];
                    
                    // For now, we'll distribute the sold quantity proportionally
                    // across all available stock with different expire dates
                    $sql3 = "SELECT COALESCE(SUM(quantity), 0) as total_available 
                             FROM item_purchases 
                             WHERE item_id = ?";
                    
                    $stmt3 = $conn->prepare($sql3);
                    if ($stmt3) {
                        $stmt3->bind_param("i", $item_id);
                        $stmt3->execute();
                        $result3 = $stmt3->get_result();
                        
                        if ($result3 && $row3 = $result3->fetch_assoc()) {
                            $total_available = $row3['total_available'];
                            
                            if ($total_available > 0) {
                                // Calculate proportional usage
                                $usage_ratio = $total_sold / $total_available;
                                $used_from_this_batch = $total_purchased * $usage_ratio;
                                return max(0, $total_purchased - $used_from_this_batch);
                            }
                        }
                    }
                }
            }
            
            return $total_purchased;
        }
    }
    
    return 0;
}

function getExpiryDetails($conn, $item_id) {
    $sql = "SELECT 
                ip.expire_date,
                SUM(ip.quantity) as batch_quantity,
                AVG(ip.price_per_unit) as avg_price,
                MIN(ip.purchase_date) as first_purchase,
                MAX(ip.purchase_date) as last_purchase
            FROM item_purchases ip
            WHERE ip.item_id = ?
            GROUP BY ip.expire_date
            ORDER BY ip.expire_date ASC";
    
    $stmt = $conn->prepare($sql);
    $details = [];
    
    if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $details[] = $row;
            }
        }
    }
    
    return $details;
}

// Function to get all purchases with item and supplier details (grouped by item)
// Function to get all purchases with item and supplier details (grouped by item and expire date)
// Function to get all purchases with item and supplier details (grouped by item)
function getPurchaseDetails($conn, $start_date = '', $end_date = '', $supplier_filter = '', $item_filter = '', $min_price = '', $max_price = '') {
    $sql = "SELECT 
                i.item_id,
                i.item_code,
                i.item_name,
                i.type,
                s.supplier_id,
                s.supplier_name,
                COALESCE(i.unit, 'other') AS unit,
                i.unit_size,
                SUM(ip.quantity) as total_quantity,
                AVG(ip.price_per_unit) as avg_price_per_unit,
                SUM(ip.total_price) as total_price,
                MIN(ip.purchase_date) as first_purchase_date,
                MAX(ip.purchase_date) as last_purchase_date,
                MIN(ip.expire_date) as earliest_expire_date,
                MAX(ip.expire_date) as latest_expire_date,
                COUNT(DISTINCT ip.expire_date) as expire_date_count
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
    
    if (!empty($item_filter)) {
        $conditions[] = "i.item_id = ?";
        $params[] = $item_filter;
        $types .= 's';
    }
    
    if (!empty($min_price)) {
        $conditions[] = "ip.price_per_unit >= ?";
        $params[] = $min_price;
        $types .= 'd';
    }
    
    if (!empty($max_price)) {
        $conditions[] = "ip.price_per_unit <= ?";
        $params[] = $max_price;
        $types .= 'd';
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY i.item_id, s.supplier_id
              ORDER BY MAX(ip.purchase_date) DESC, i.item_name ASC";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $purchases = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Calculate current quantity for each item (sum of all expiry dates)
            $row['current_quantity'] = getCurrentQuantity($conn, $row['item_id']);
            
            // Get detailed expiry information
            $row['expiry_details'] = getExpiryDetails($conn, $row['item_id']);
            
            // Calculate total units (quantity * unit_size)
            $row['total_units'] = $row['total_quantity'] * $row['unit_size'];
            $row['current_units'] = $row['current_quantity'] * $row['unit_size'];
            
            // Use the last purchase date as the display date
            $row['purchase_date'] = $row['last_purchase_date'];
            $row['expire_date'] = $row['earliest_expire_date'];
            $row['quantity'] = $row['total_quantity'];
            $row['price_per_unit'] = $row['avg_price_per_unit'];
            
            $purchases[] = $row;
        }
    }
    
    return $purchases;
}

// Get all purchase details with filters
$purchases = getPurchaseDetails($conn, $start_date, $end_date, $supplier_filter, $item_filter, $min_price, $max_price);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchased Items Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .page-header:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(-3px);
        }
        
        .page-header h2 {
            font-weight: 600;
            font-size: 28px;
            margin-bottom: 0;
        }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .filter-section:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .form-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, #5b6dc4, #67418f);
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-outline-secondary {
            border: 2px solid #e0e0e0;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f5f5f5;
        }
        
        .table-responsive {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .table-responsive:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            font-weight: 500;
            padding: 15px;
            border: none;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-top: 1px solid #f0f0f0;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .total-row {
            font-weight: 600;
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .expired {
            background-color: rgba(255, 71, 87, 0.1);
            border-left: 4px solid #ff4757;
        }
        
        .expiring-soon {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-active {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .status-expired {
            background-color: rgba(255, 71, 87, 0.1);
            color: #ff4757;
        }
        
        .status-expiring {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .status-none {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .quantity-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .qty-high {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .qty-medium {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .qty-low {
            background-color: rgba(255, 71, 87, 0.1);
            color: #ff4757;
        }
        
        .qty-out {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin: 20px;
        }
        
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .ui-menu-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }
        
        .ui-menu-item:hover {
            background-color: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }
            
            .filter-section {
                padding: 20px;
            }
            
            .table th, .table td {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container animate__animated animate__fadeIn">
        <div class="page-header animate__animated animate__fadeInDown">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-shopping-cart me-2"></i> Purchased Items Details</h2>
                </div>
                <div class="col-md-4 text-end" style="font-size: 20px;">
                    
                    <a href="member_purchases_report.php" class="btn btn-light"><i class="fas fa-file-pdf me-1"></i> Generate Report</a>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section animate__animated animate__fadeIn">
            <form method="GET" action="">
                <div class="row filter-row">
                    <div class="col-md-2">
                        <label for="start_date" class="form-label" style="font-size: 20px;"><i class="far fa-calendar-alt me-1"></i> From Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>" style="font-size: 20px;">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label" style="font-size: 20px;"><i class="far fa-calendar-alt me-1"></i> To Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>" style="font-size: 20px;">
                    </div>
                    <div class="col-md-2">
                        <label for="supplier_name" class="form-label" style="font-size: 20px;"><i class="fas fa-truck me-1"></i> Supplier</label>
                        <div class="search-container">
                            <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                   value="<?php echo htmlspecialchars($supplier_name); ?>" 
                                   placeholder="Type supplier name..." style="font-size: 20px;">
                            <input type="hidden" id="supplier_id" name="supplier_id" value="<?php echo $supplier_filter; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="item_name" class="form-label" style="font-size: 20px;"><i class="fas fa-box-open me-1"></i> Item</label>
                        <div class="search-container">
                            <input type="text" class="form-control" id="item_name" name="item_name" 
                                   value="<?php echo htmlspecialchars($item_name); ?>" 
                                   placeholder="Type item name..." style="font-size: 20px;">
                            <input type="hidden" id="item_id" name="item_id" value="<?php echo $item_filter; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="min_price" class="form-label" style="font-size: 20px;"><i class="fas fa-tag me-1"></i> Min Price</label>
                        <input type="number" step="0.01" class="form-control" id="min_price" name="min_price" 
                               value="<?php echo htmlspecialchars($min_price); ?>" 
                               placeholder="Min price" style="font-size: 20px;">
                    </div>
                    <div class="col-md-2">
                        <label for="max_price" class="form-label" style="font-size: 20px;"><i class="fas fa-tag me-1"></i> Max Price</label>
                        <input type="number" step="0.01" class="form-control" id="max_price" name="max_price" 
                               value="<?php echo htmlspecialchars($max_price); ?>" 
                               placeholder="Max price" style="font-size: 20px;">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter me-1"></i> Apply Filters</button>
                        <a href="?" class="btn btn-outline-secondary"><i class="fas fa-sync-alt me-1"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="table-responsive animate__animated animate__fadeInUp">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="font-size: 12px; font-weight: bold;"><i class="far fa-calendar me-1"></i> Last Purchase Date</th>
                        <th style="font-size: 13.5px; font-weight: bold;"><i class="fas fa-box me-1"></i> Item Name</th>
                        <th style="font-size: 13.5px; font-weight: bold;"><i class="fas fa-truck me-1"></i> Supplier</th>
                        <th style="font-size: 12px; font-weight: bold;"><i class="fas fa-cubes me-1"></i> Original Qty</th>
                        <th style="font-size: 12px; font-weight: bold;"><i class="fas fa-weight me-1"></i> Unit Size</th>
                        <th style="font-size: 12px; font-weight: bold;"><i class="fas fa-warehouse me-1"></i> Current Qty</th>
                        <th style="font-size: 12px; font-weight: bold;"><i class="fas fa-tag me-1"></i> Price/Unit</th>
                        <th style="font-size: 12px; font-weight: bold;"><i class="fas fa-money-bill-wave me-1"></i> Total Price</th>
                       
                        <th style="font-size: 12px; font-weight: bold;"><i class="fas fa-info-circle me-1"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalQuantity = 0;
                    $totalCurrentQuantity = 0;
                    $totalAmount = 0;
                    
                    foreach($purchases as $purchase): 
                        // Check if the item is expired or expiring soon
                        $status = '';
                        $statusClass = '';
                        $rowClass = '';
                        $currentDate = new DateTime();
                        
                        if (!empty($purchase['expire_date'])) {
                            $expireDate = new DateTime($purchase['expire_date']);
                            $interval = $currentDate->diff($expireDate);
                            
                            if ($expireDate < $currentDate) {
                                $status = 'Expired';
                                $statusClass = 'status-expired';
                                $rowClass = 'expired';
                            } elseif ($interval->days <= 30) {
                                $status = 'Expiring in ' . $interval->days . ' days';
                                $statusClass = 'status-expiring';
                                $rowClass = 'expiring-soon';
                            } else {
                                $status = 'Active';
                                $statusClass = 'status-active';
                            }
                        } else {
                            $status = 'No expiry';
                            $statusClass = 'status-none';
                        }
                        
                        // Determine quantity status
                        $currentQty = $purchase['current_quantity'];
                        $originalQty = $purchase['quantity'];
                        $qtyPercentage = $originalQty > 0 ? ($currentQty / $originalQty) * 100 : 0;
                        
                        if ($currentQty == 0) {
                            $qtyClass = 'qty-out';
                        } elseif ($qtyPercentage <= 25) {
                            $qtyClass = 'qty-low';
                        } elseif ($qtyPercentage <= 50) {
                            $qtyClass = 'qty-medium';
                        } else {
                            $qtyClass = 'qty-high';
                        }
                        
                        $totalQuantity += $purchase['quantity'];
                        $totalCurrentQuantity += $purchase['current_quantity'];
                        $totalAmount += $purchase['total_price'];
                    ?>
                    <tr class="<?php echo $rowClass; ?> animate__animated animate__fadeIn">
                        <td style="font-size: 17px;"><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
                        <td style="font-size: 17px;"><?php echo $purchase['item_name']; ?></td>
                        <td style="font-size: 17px;"><?php echo $purchase['supplier_name']; ?></td>
                        <td style="font-size: 17px;"><?php 
                            $qtyDisplay = $purchase['quantity'];
                            if (isset($purchase['unit']) && strtolower($purchase['unit']) == 'kg') {
                                $qtyDisplay .= ' kg';
                            } else {
                                $qtyDisplay .= ' ' . ($purchase['type'] ?? 'units');
                            }
                            echo $qtyDisplay; 
                        ?></td>
                        <td style="font-size: 17px;"><?php echo $purchase['unit_size'] . ' ' . $purchase['unit']; ?></td>
                        <td style="font-size: 17px;">
                            <span class="quantity-badge <?php echo $qtyClass; ?>">
                                <?php 
                                $currentQtyDisplay = $purchase['current_quantity'];
                                if (isset($purchase['unit']) && strtolower($purchase['unit']) == 'kg') {
                                    $currentQtyDisplay .= ' kg';
                                } else {
                                    $currentQtyDisplay .= ' ' . ($purchase['type'] ?? 'units');
                                }
                                echo $currentQtyDisplay; 
                                ?>
                            </span>
                        </td>
                        <td style="font-size: 17px;">Rs.<?php echo number_format($purchase['price_per_unit'], 2); ?></td>
                        <td style="font-size: 17px;">Rs.<?php echo number_format($purchase['total_price'], 2); ?></td>
                        <td style="font-size: 17px;">
                            <button type="button" class="btn btn-sm btn-info" onclick="showExpiryDetails(<?php echo $purchase['item_id']; ?>, '<?php echo addslashes($purchase['item_name']); ?>')">
                                <i class="fas fa-eye me-1"></i>
                                View Status
                                <?php if ($purchase['expire_date_count'] > 1): ?>
                                    <span class="badge bg-warning ms-1"><?php echo $purchase['expire_date_count']; ?></span>
                                <?php endif; ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (count($purchases) > 0): ?>
                    <tr class="total-row animate__animated animate__fadeIn">
                        
                        <td><strong><?php 
                            
                        ?></strong></td>
                        <td></td>
                        <td><strong><?php 
                            
                        ?></strong></td>
                        <td></td>
                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                        <td><strong>Rs.<?php echo number_format($totalAmount, 2); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (count($purchases) == 0): ?>
            <div class="alert alert-info text-center animate__animated animate__fadeIn">
                <i class="fas fa-info-circle me-2"></i> No purchase records found matching your criteria.
            </div>
            <?php endif; ?>
        </div>
    </div>

   

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set default date values to today and one month ago
        $(document).ready(function() {
            // Animate elements on scroll
            $(window).scroll(function() {
                $('.animate__animated').each(function() {
                    var position = $(this).offset().top;
                    var scroll = $(window).scrollTop();
                    var windowHeight = $(window).height();
                    
                    if (scroll + windowHeight > position + 100) {
                        $(this).addClass($(this).data('animate'));
                    }
                });
            });
            
            const today = new Date().toISOString().split('T')[0];
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
            const oneMonthAgoStr = oneMonthAgo.toISOString().split('T')[0];
            
            // Only set defaults if no dates are already selected
            if (!$('#start_date').val() && !$('#end_date').val()) {
                $('#start_date').val(oneMonthAgoStr);
                $('#end_date').val(today);
            }
            
            // Add smooth hover effect to table rows
            $('tbody tr').hover(
                function() {
                    $(this).css('transform', 'scale(1.005)');
                    $(this).css('box-shadow', '0 2px 10px rgba(0, 0, 0, 0.03)');
                },
                function() {
                    $(this).css('transform', 'scale(1)');
                    $(this).css('box-shadow', 'none');
                }
            );

            // Initialize autocomplete for item
            $("#item_name").autocomplete({
                source: function(request, response) {
                    $.get({
                        url: window.location.href,
                        data: { 
                            search_term: request.term,
                            type: 'item'
                        },
                        dataType: "json",
                        success: function(data) {
                            response($.map(data, function(item) {
                                return {
                                    label: item.name,
                                    value: item.name,
                                    id: item.id,
                                    item_name: item.item_name,
                                    unit_size: item.unit_size,
                                    unit: item.unit,
                                    type: item.type || 'units'
                                };
                            }));
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#item_id").val(ui.item.id);
                    $("#item_name").val(ui.item.item_name + ' (' + ui.item.unit_size + ' ' + ui.item.unit + ')');
                    return false;
                },
                focus: function(event, ui) {
                    $("#item_name").val(ui.item.item_name + ' (' + ui.item.unit_size + ' ' + ui.item.unit + ')');
                    return false;
                }
            }).data("ui-autocomplete")._renderItem = function(ul, item) {
                return $("<li>")
                    .append("<div><i class='fas fa-box-open me-2'></i>" + item.item_name + " <small class='text-muted'>(" + item.unit_size + " " + item.unit + ")</small></div>")
                    .appendTo(ul);
            };

            // Clear hidden ID field when user clears the item text input
            $("#item_name").on('input', function() {
                if ($(this).val() === '') {
                    $("#item_id").val('');
                }
            });

            // Initialize autocomplete for supplier
            $("#supplier_name").autocomplete({
                source: function(request, response) {
                    $.get({
                        url: window.location.href,
                        data: { 
                            search_term: request.term,
                            type: 'supplier'
                        },
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
                    .append("<div><i class='fas fa-truck me-2'></i>" + item.label + "</div>")
                    .appendTo(ul);
            };

            // Clear hidden ID field when user clears the text input
            $("#supplier_name").on('input', function() {
                if ($(this).val() === '') {
                    $("#item_id").val('');
                }
            });
            
            // Add animation to filter buttons
            $('.btn').hover(
                function() {
                    $(this).addClass('animate__animated animate__pulse');
                },
                function() {
                    $(this).removeClass('animate__animated animate__pulse');
                }
            );
        });

        function showExpiryDetails(itemId, itemName) {
    $('#itemName').text(itemName);
    $('#expiryDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    
    // Show modal
    $('#expiryModal').modal('show');
    
    // Fetch expiry details via AJAX
    $.ajax({
        url: window.location.href,
        type: 'GET',
        data: {
            get_expiry_details: 1,
            item_id: itemId
        },
        success: function(response) {
            $('#expiryDetailsContent').html(response);
        },
        error: function() {
            $('#expiryDetailsContent').html('<div class="alert alert-danger">Error loading expiry details.</div>');
        }
    });
}

    </script>

        <div class="modal fade" id="expiryModal" tabindex="-1" aria-labelledby="expiryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expiryModalLabel">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Expiry Details for: <span id="itemName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="expiryDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>