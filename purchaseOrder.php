<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Configuration
$low_stock_threshold = 10; // Items with quantity below this are considered low stock

// Function to get low stock items
function getLowStockItems($pdo, $threshold) {
    $sql = "SELECT i.item_id, i.item_code, i.item_name, i.price_per_unit, 
                   i.unit_size, i.current_quantity, i.unit, i.type,
                   s.supplier_name, s.contact_number, s.address
            FROM items i
            LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
            WHERE i.current_quantity < :threshold
            ORDER BY i.current_quantity ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['threshold' => $threshold]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to calculate suggested order quantity
function calculateOrderQuantity($currentStock, $threshold) {
    $suggestedStock = $threshold * 5; // Order 5 times the threshold
    return max($suggestedStock - $currentStock, $threshold);
}

// Handle PDF generation
if (isset($_POST['generate_pdf'])) {
    require_once('fpdf/fpdf.php'); // Make sure FPDF is installed
    
    class PDF extends FPDF {
        private $companyName = "YOUR COMPANY NAME";
        private $companyAddress = "123 Business Street, City, State 12345";
        private $companyPhone = "+1 (555) 123-4567";
        private $companyEmail = "procurement@yourcompany.com";
        
        function Header() {
            // Company Logo placeholder (add your logo here)
            // $this->Image('logo.png', 10, 6, 30);
            
            // Company Information
            $this->SetFont('Arial', 'B', 20);
            $this->SetTextColor(51, 51, 51);
            $this->Cell(0, 10, $this->companyName, 0, 1, 'L');
            
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(102, 102, 102);
            $this->Cell(0, 4, $this->companyAddress, 0, 1, 'L');
            $this->Cell(0, 4, 'Phone: ' . $this->companyPhone . ' | Email: ' . $this->companyEmail, 0, 1, 'L');
            
            // Horizontal line
            $this->SetDrawColor(51, 51, 51);
            $this->Line(10, 35, 200, 35);
            
            // Document title
            $this->Ln(8);
            $this->SetFont('Arial', 'B', 24);
            $this->SetTextColor(51, 51, 51);
            $this->Cell(0, 12, 'PURCHASE ORDER', 0, 1, 'C');
            
            // Document info box
            $this->SetFont('Arial', 'B', 10);
            $this->SetFillColor(240, 240, 240);
            $this->SetTextColor(51, 51, 51);
            
            // PO Number and Date
            $poNumber = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $this->Cell(95, 6, 'Purchase Order Number:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(95, 6, $poNumber, 1, 1, 'L');
            
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(95, 6, 'Date:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(95, 6, date('F j, Y'), 1, 1, 'L');
            
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(95, 6, 'Delivery Required By:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(95, 6, date('F j, Y', strtotime('+14 days')), 1, 1, 'L');
            
            $this->Ln(5);
        }
        
        function Footer() {
            $this->SetY(-25);
            
            // Footer line
            $this->SetDrawColor(51, 51, 51);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            
            $this->Ln(2);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(102, 102, 102);
            $this->Cell(0, 4, 'This is a computer-generated purchase order and does not require a signature.', 0, 1, 'C');
            $this->Cell(0, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        }
        
        function supplierSection($supplierName, $contact, $address) {
            // Supplier Information Box
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(51, 51, 51);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(190, 8, 'SUPPLIER INFORMATION', 1, 1, 'L', true);
            
            $this->SetFont('Arial', 'B', 11);
            $this->SetFillColor(248, 248, 248);
            $this->SetTextColor(51, 51, 51);
            $this->Cell(40, 6, 'Supplier Name:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 11);
            $this->Cell(150, 6, $supplierName, 1, 1, 'L');
            
            if (!empty($contact)) {
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(40, 6, 'Contact:', 1, 0, 'L', true);
                $this->SetFont('Arial', '', 11);
                $this->Cell(150, 6, $contact, 1, 1, 'L');
            }
            
            if (!empty($address)) {
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(40, 6, 'Address:', 1, 0, 'L', true);
                $this->SetFont('Arial', '', 11);
                $this->Cell(150, 6, $address, 1, 1, 'L');
            }
            
            $this->Ln(3);
        }
        
        function itemTableHeader() {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(51, 51, 51);
            $this->SetTextColor(255, 255, 255);
            $this->SetDrawColor(51, 51, 51);
            
            $this->Cell(20, 8, 'Item Code', 1, 0, 'C', true);
            $this->Cell(55, 8, 'Item Description', 1, 0, 'C', true);
            $this->Cell(18, 8, 'Current', 1, 0, 'C', true);
            $this->Cell(18, 8, 'Order Qty', 1, 0, 'C', true);
            $this->Cell(20, 8, 'Unit Price', 1, 0, 'C', true);
            $this->Cell(20, 8, 'Total', 1, 0, 'C', true);
            $this->Cell(15, 8, 'Unit', 1, 0, 'C', true);
            $this->Cell(14, 8, 'Type', 1, 1, 'C', true);
        }
        
        function itemRow($item, $orderQty, $isAlternate = false) {
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(51, 51, 51);
            $this->SetDrawColor(200, 200, 200);
            
            if ($isAlternate) {
                $this->SetFillColor(250, 250, 250);
            } else {
                $this->SetFillColor(255, 255, 255);
            }
            
            $itemTotal = $orderQty * $item['price_per_unit'];
            
            $this->Cell(20, 7, $item['item_code'], 1, 0, 'C', true);
            $this->Cell(55, 7, substr($item['item_name'], 0, 35), 1, 0, 'L', true);
            $this->Cell(18, 7, $item['current_quantity'], 1, 0, 'C', true);
            $this->Cell(18, 7, $orderQty, 1, 0, 'C', true);
            $this->Cell(20, 7, 'LKR ' . number_format($item['price_per_unit'], 2), 1, 0, 'R', true);
            $this->Cell(20, 7, 'LKR ' . number_format($itemTotal, 2), 1, 0, 'R', true);
            $this->Cell(15, 7, $item['unit'], 1, 0, 'C', true);
            $this->Cell(14, 7, $item['type'], 1, 1, 'C', true);
            
            return $itemTotal;
        }
        
        function supplierTotal($total) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetFillColor(240, 240, 240);
            $this->SetTextColor(51, 51, 51);
            $this->Cell(131, 8, 'Supplier Subtotal:', 1, 0, 'R', true);
            $this->Cell(20, 8, 'LKR ' . number_format($total, 2), 1, 0, 'R', true);
            $this->Cell(29, 8, '', 1, 1, 'C', true);
        }
        
        function grandTotal($total) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(51, 51, 51);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(131, 10, 'GRAND TOTAL:', 1, 0, 'R', true);
            $this->Cell(20, 10, 'LKR ' . number_format($total, 2), 1, 0, 'R', true);
            $this->Cell(29, 10, '', 1, 1, 'C', true);
        }
    }
    
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    
    // Get selected items from form
    $selectedItems = $_POST['selected_items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    if (!empty($selectedItems)) {
        // Get items data
        $placeholders = str_repeat('?,', count($selectedItems) - 1) . '?';
        $sql = "SELECT i.item_id, i.item_code, i.item_name, i.price_per_unit, 
                       i.unit_size, i.current_quantity, i.unit, i.type,
                       s.supplier_name, s.contact_number, s.address
                FROM items i
                LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
                WHERE i.item_id IN ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($selectedItems);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group items by supplier
        $supplierGroups = [];
        foreach ($items as $item) {
            $supplier = $item['supplier_name'] ?: 'Unknown Supplier';
            $supplierGroups[$supplier][] = $item;
        }
        
        $totalOrderValue = 0;
        $rowCount = 0;
        
        foreach ($supplierGroups as $supplierName => $supplierItems) {
            // Add new page if needed
            if ($pdf->GetY() > 200) {
                $pdf->AddPage();
            }
            
            // Supplier section
            $pdf->supplierSection(
                $supplierName,
                $supplierItems[0]['contact_number'] ?? '',
                $supplierItems[0]['address'] ?? ''
            );
            
            // Table header
            $pdf->itemTableHeader();
            
            $supplierTotal = 0;
            $rowCount = 0;
            
            foreach ($supplierItems as $item) {
                $orderQty = $quantities[$item['item_id']] ?? calculateOrderQuantity($item['current_quantity'], $low_stock_threshold);
                $itemTotal = $pdf->itemRow($item, $orderQty, $rowCount % 2 == 1);
                $supplierTotal += $itemTotal;
                $rowCount++;
                
                // Add new page if needed
                if ($pdf->GetY() > 250) {
                    $pdf->AddPage();
                    $pdf->supplierSection(
                        $supplierName,
                        $supplierItems[0]['contact_number'] ?? '',
                        $supplierItems[0]['address'] ?? ''
                    );
                    $pdf->itemTableHeader();
                }
            }
            
            // Supplier total
            $pdf->supplierTotal($supplierTotal);
            $totalOrderValue += $supplierTotal;
            $pdf->Ln(5);
        }
        
        // Grand total
        $pdf->grandTotal($totalOrderValue);
        
        // Output PDF
        $pdf->Output('D', 'Professional_Purchase_Order_' . date('Ymd_His') . '.pdf');
        exit;
    }
}

// Get low stock items for display
$lowStockItems = getLowStockItems($pdo, $low_stock_threshold);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - Low Stock Items</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, rgb(208, 212, 232) 0%, rgb(223, 245, 254) 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .main-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }
        
        .header {
            background: linear-gradient(to right, #23316eff, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .stats {
            background: #f8f9fa;
            padding: 25px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.95em;
            font-weight: 500;
        }
        
        .main-content {
            padding: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-controls {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .threshold-control {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .threshold-control label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .threshold-control input {
            padding: 10px 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            width: 80px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .threshold-control input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(to right, #2ed573, #1dd1a1);
            color: white;
            box-shadow: 0 4px 15px rgba(46, 213, 115, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(46, 213, 115, 0.4);
        }
        
        .btn-warning {
            background: linear-gradient(to right, #0b2512ff, #27723cff);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 165, 2, 0.3);
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(255, 165, 2, 0.4);
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-wrapper {
            overflow-x: auto;
            max-height: 600px;
        }
        
        .table-wrapper::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        td {
            padding: 15px 12px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .stock-critical {
            background: rgba(255, 77, 87, 0.1) !important;
            border-left: 4px solid #ff4d57;
        }
        
        .stock-low {
            background: rgba(255, 165, 2, 0.1) !important;
            border-left: 4px solid #ffa502;
        }
        
        .stock-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            display: inline-block;
        }
        
        .badge-critical {
            background: linear-gradient(to right, #ff4d57, #c44569);
        }
        
        .badge-low {
            background: linear-gradient(to right, #ffa502, #ff7675);
        }
        
        .quantity-input {
            width: 80px;
            padding: 8px 10px;
            border: 1.5px solid #e0e0e0;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .quantity-input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .supplier-info {
            font-size: 13px;
            color: #6c757d;
            margin-top: 4px;
        }
        
        .no-items {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .no-items i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #2ed573;
        }
        
        .no-items h2 {
            color: #495057;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .alert {
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            background: rgba(102, 126, 234, 0.1);
            color: #495057;
            font-weight: 500;
        }
        
        .alert strong {
            color: #667eea;
        }
        
        .footer {
            text-align: center;
            padding: 25px;
            background: #f8f9fa;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
            font-size: 14px;
        }
        
        .generate-btn-container {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .generate-btn {
            font-size: 16px;
            padding: 15px 40px;
            background: linear-gradient(to right, #0f222bff, #6791d9ff);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(46, 213, 115, 0.3);
        }
        
        .generate-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(46, 213, 115, 0.4);
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 10px;
            }
            
            .container {
                margin: 0;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }
            
            .quantity-input {
                width: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-shopping-cart"></i> Purchase-Order Details</h1>
                <p>Low Stock Items Management</p>
            </div>
            
            <div class="stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($lowStockItems); ?></div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $low_stock_threshold; ?></div>
                        <div class="stat-label">Stock Threshold</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_unique(array_column($lowStockItems, 'supplier_name'))); ?></div>
                        <div class="stat-label">Suppliers Involved</div>
                    </div>
                </div>
            </div>
            
            <div class="main-content">
                <?php if (!empty($lowStockItems)): ?>
                    <div class="alert">
                        <strong><i class="fas fa-exclamation-triangle"></i> Notice:</strong> The following items have stock levels below the threshold. Please review and generate a purchase order.
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-section">
                            <div class="form-controls">
                                <div class="threshold-control">
                                    <label for="threshold"><i class="fas fa-chart-line"></i> Stock Threshold:</label>
                                    <input type="number" id="threshold" name="threshold" value="<?php echo $low_stock_threshold; ?>" min="1" max="100">
                                </div>
                               
                                <button type="button" class="btn btn-warning" onclick="selectAll()">
                                    <i class="fas fa-check-square"></i> Select All
                                </button>
                                <button type="button" class="btn btn-warning" onclick="deselectAll()">
                                    <i class="fas fa-times-circle"></i> Deselect All
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>Item Code</th>
                                            <th>Item Name</th>
                                            <th>Unit size</th>
                                            <th>Current Stock</th>
                                            <th>Status</th>
                                          
                                            <th>Order Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total Cost</th>
                                            <th>Supplier</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lowStockItems as $item): ?>
                                            <?php
                                            $stockClass = $item['current_quantity'] <= ($low_stock_threshold / 2) ? 'stock-critical' : 'stock-low';
                                            $badgeClass = $item['current_quantity'] <= ($low_stock_threshold / 2) ? 'badge-critical' : 'badge-low';
                                            $suggestedQty = calculateOrderQuantity($item['current_quantity'], $low_stock_threshold);
                                            ?>
                                            <tr class="<?php echo $stockClass; ?>">
                                                <td>
                                                    <input type="checkbox" name="selected_items[]" value="<?php echo $item['item_id']; ?>" class="item-checkbox">
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($item['item_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo $item['unit_size'] . $item['unit']; ?></td>
                                                <td>
                                                    <strong><?php echo $item['current_quantity']; ?></strong>
                                                    <span style="color: #888; font-size: 12px; margin-left: 4px;">
                                                        <?php echo htmlspecialchars($item['type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="stock-badge <?php echo $badgeClass; ?>">
                                                        <?php echo $item['current_quantity'] <= ($low_stock_threshold / 2) ? 'CRITICAL' : 'LOW'; ?>
                                                    </span>
                                                </td>
                                             
                                                <td>
                                                    <input type="number" name="quantities[<?php echo $item['item_id']; ?>]" 
                                                           value="<?php echo $suggestedQty; ?>" min="1" class="quantity-input">
                                                </td>
                                                <td>
                                                    <span class="unit-price"><?php echo $item['price_per_unit']; ?></span>
                                                </td>
                                                <td class="total-cost">
                                                    LKR <?php echo number_format($suggestedQty * $item['price_per_unit'], 2); ?>
                                                </td>
                                                <td>
                                                    <div><?php echo htmlspecialchars($item['supplier_name'] ?: 'No Supplier'); ?></div>
                                                    <?php if ($item['contact_number']): ?>
                                                        <div class="supplier-info">
                                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($item['contact_number']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="generate-btn-container">
                            <button type="submit" name="generate_pdf" class="generate-btn">
                                <i class="fas fa-file-pdf"></i> Generate Purchase Order PDF
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="no-items">
                        <i class="fas fa-check-circle"></i>
                        <h2>All Items Well Stocked!</h2>
                        <p>No items found with stock below the threshold of <?php echo $low_stock_threshold; ?> units.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p>&copy; 2024 Inventory Management System | Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>
    </div>
    
    <script>
        function selectAll() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }
        
        function deselectAll() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }
        
        // Auto-calculate totals when quantity changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('tr');
                const price = parseFloat(row.querySelector('.unit-price').textContent);
                const quantity = parseInt(this.value) || 0;
                const total = quantity * price;
                row.querySelector('.total-cost').textContent = 'LKR ' + total.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'generate_pdf') {
                const selectedItems = document.querySelectorAll('.item-checkbox:checked');
                if (selectedItems.length === 0) {
                    alert('Please select at least one item to generate the purchase order.');
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>