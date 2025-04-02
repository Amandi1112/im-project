<?php
// Database connection
$host = '127.0.0.1';
$dbname = 'mywebsite';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle PDF generation request
if (isset($_GET['generate_pdf']) && $_GET['generate_pdf'] == '1') {
    require('fpdf/fpdf.php');
    
    // Color scheme from previous examples
    $primaryColor = array(102, 126, 234);   // #667eea
    $primaryDark = array(90, 103, 216);     // #5a67d8
    $secondaryColor = array(237, 242, 247); // #edf2f7
    $successColor = array(72, 187, 120);    // #48bb78
    $warningColor = array(237, 137, 54);    // #ed8936
    $infoColor = array(66, 153, 225);       // #4299e1
    $lightColor = array(247, 250, 252);     // #f7fafc
    $darkColor = array(45, 55, 72);         // #2d3748
    $grayColor = array(113, 128, 150);      // #718096
    $grayLight = array(226, 232, 240);      // #e2e8f0

    // Get parameters
    $supplierId = $_GET['supplier_id'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    if (!empty($supplierId) && !empty($startDate) && !empty($endDate)) {
        // Get supplier details
        $supplierQuery = "SELECT * FROM supplier WHERE supplier_id = ?";
        $supplierStmt = $pdo->prepare($supplierQuery);
        $supplierStmt->execute([$supplierId]);
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($supplier) {
            // Get items supplied by this supplier
            $itemsQuery = "SELECT * FROM items WHERE supplier_id = ?";
            $itemsStmt = $pdo->prepare($itemsQuery);
            $itemsStmt->execute([$supplierId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get purchases of these items within date range
            // Get purchases of these items within date range
$itemIds = array_column($items, 'item_id');

// Check if there are any items before creating placeholders
if (count($itemIds) > 0) {
    $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
    
    $purchasesQuery = "SELECT p.*, i.item_name 
                      FROM item_purchases p
                      JOIN items i ON p.item_id = i.item_id
                      WHERE p.item_id IN ($placeholders)
                      AND p.purchase_date BETWEEN ? AND ?";
    
    $params = array_merge($itemIds, [$startDate, $endDate]);
    $purchasesStmt = $pdo->prepare($purchasesQuery);
    $purchasesStmt->execute($params);
    $purchases = $purchasesStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // No items found for this supplier
    $purchases = [];
}
            
            // Get payments to this supplier within date range
            $paymentsQuery = "SELECT * FROM supplier_payments 
                             WHERE supplier_id = ? 
                             AND payment_date BETWEEN ? AND ?";
            $paymentsStmt = $pdo->prepare($paymentsQuery);
            $paymentsStmt->execute([$supplierId, $startDate, $endDate]);
            $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $totalPurchases = array_sum(array_column($purchases, 'total_price'));
            $totalPayments = array_sum(array_column($payments, 'amount'));
            $outstandingBalance = $totalPurchases - $totalPayments;
            
            // Create PDF in landscape
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            
            // ========== HEADER SECTION ========== //
            // Header with primary color background
            $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->Rect(10, 10, 190, 20, 'F');
            
            // Shop name
            $pdf->SetTextColor(255);
            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->SetXY(15, 12);
            $pdf->Cell(0, 8, 'COOPERATIVE SHOP SUPPLIER REPORT', 0, 1, 'L');
            
            // Report info box
            $pdf->SetFillColor($primaryDark[0], $primaryDark[1], $primaryDark[2]);
        
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetXY(200, 12);
            $pdf->Cell(80, 8, 'DATE', 0, 1, 'C');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetXY(200, 18);
            $pdf->Cell(80, 6, date('F j, Y'), 0, 1, 'C');
            
            // Shop contact info
            $pdf->SetTextColor(255);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetXY(15, 22);
            $pdf->Cell(0, 5, '123 Business Avenue, Colombo 01 | Tel: +94 11 2345678 | Email: accounts@coopshop.lk', 0, 1, 'L');
            
            // ========== SUPPLIER INFORMATION SECTION ========== //
            $pdf->SetY(40);
            $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
            
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'SUPPLIER INFORMATION', 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 10);
            
            $pdf->Cell(40, 7, 'Supplier Name:', 0, 0);
            $pdf->Cell(0, 7, $supplier['supplier_name'], 0, 1);
            
            $pdf->Cell(40, 7, 'Supplier ID:', 0, 0);
            $pdf->Cell(0, 7, $supplier['supplier_id'], 0, 1);
            
            $pdf->Cell(40, 7, 'Contact:', 0, 0);
            $pdf->Cell(0, 7, $supplier['contact_number'], 0, 1);
            
            $pdf->Cell(40, 7, 'Address:', 0, 0);
            $pdf->MultiCell(0, 7, $supplier['address'], 0, 1);
            
            $pdf->Ln(5);
            
            // ========== FINANCIAL SUMMARY SECTION ========== //
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'FINANCIAL SUMMARY', 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 10);
            
            $pdf->Cell(70, 7, 'Total Purchases:', 0, 0);
            $pdf->Cell(0, 7, 'Rs. ' . number_format($totalPurchases, 2), 0, 1);
            
            $pdf->Cell(70, 7, 'Total Payments:', 0, 0);
            $pdf->Cell(0, 7, 'Rs. ' . number_format($totalPayments, 2), 0, 1);
            
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetFillColor($outstandingBalance > 0 ? $warningColor[0] : $successColor[0], 
                             $outstandingBalance > 0 ? $warningColor[1] : $successColor[1], 
                             $outstandingBalance > 0 ? $warningColor[2] : $successColor[2]);
            $pdf->Cell(70, 7, 'Outstanding Balance:', 0, 0);
            $pdf->Cell(50, 7, 'Rs. ' . number_format($outstandingBalance, 2), 1, 1, 'L', true);
            
            $pdf->Ln(10);
            
            
            // ========== PURCHASE HISTORY SECTION ========== //
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'PURCHASE HISTORY', 0, 1, 'L');
            
            if (!empty($purchases)) {
                // Table Header
                $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->SetTextColor(255);
                $pdf->SetFont('Helvetica', 'B', 10);
                
                $pdf->Cell(30, 9, 'DATE', 1, 0, 'C', true);
                $pdf->Cell(40, 9, 'ITEM', 1, 0, 'C', true);
                $pdf->Cell(20, 9, 'QTY', 1, 0, 'C', true);
                $pdf->Cell(35, 9, 'UNIT PRICE', 1, 0, 'C', true);
                $pdf->Cell(35, 9, 'TOTAL', 1, 0, 'C', true);
                $pdf->Cell(30, 9, 'EXPIRY', 1, 1, 'C', true);
                
                // Table Data
                $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
                $pdf->SetFont('Helvetica', '', 10);
                
                $fill = false;
                foreach ($purchases as $purchase) {
                    $pdf->SetFillColor($fill ? $grayLight[0] : 255);
                    $pdf->Cell(30, 7, $purchase['purchase_date'], 1, 0, 'C', $fill);
                    $pdf->Cell(40, 7, $purchase['item_name'], 1, 0, 'L', $fill);
                    $pdf->Cell(20, 7, $purchase['quantity'], 1, 0, 'R', $fill);
                    $pdf->Cell(35, 7, 'Rs. ' . number_format($purchase['price_per_unit'], 2), 1, 0, 'R', $fill);
                    $pdf->Cell(35, 7, 'Rs. ' . number_format($purchase['total_price'], 2), 1, 0, 'R', $fill);
                    $pdf->Cell(30, 7, $purchase['expire_date'] ?? 'N/A', 1, 1, 'C', $fill);
                    $fill = !$fill;
                }
            } else {
                $pdf->SetFont('Helvetica', 'I', 10);
                $pdf->Cell(0, 8, 'No purchases found for this period.', 0, 1, 'L');
            }
            
            $pdf->Ln(10);
            
            // ========== PAYMENT HISTORY SECTION ========== //
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'PAYMENT HISTORY', 0, 1, 'L');
            
            if (!empty($payments)) {
                // Table Header
                $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->SetTextColor(255);
                $pdf->SetFont('Helvetica', 'B', 10);
                
                $pdf->Cell(40, 8, 'PAYMENT ID', 1, 0, 'C', true);
                $pdf->Cell(40, 8, 'DATE', 1, 0, 'C', true);
                $pdf->Cell(40, 8, 'AMOUNT', 1, 1, 'C', true);
                
                // Table Data
                $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
                $pdf->SetFont('Helvetica', '', 10);
                
                $fill = false;
                foreach ($payments as $payment) {
                    $pdf->SetFillColor($fill ? $grayLight[0] : 255);
                    $pdf->Cell(40, 7, $payment['id'], 1, 0, 'C', $fill);
                    $pdf->Cell(40, 7, $payment['payment_date'], 1, 0, 'C', $fill);
                    $pdf->Cell(40, 7, 'Rs. ' . number_format($payment['amount'], 2), 1, 1, 'R', $fill);
                    $fill = !$fill;
                }
            } else {
                $pdf->SetFont('Helvetica', 'I', 10);
                $pdf->Cell(0, 8, 'No payments found for this period.', 0, 1, 'L');
            }

            // ========== SIGNATURE SECTION ========== //
$pdf->SetY(-80); // Position 50mm from bottom
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);

// Signature titles
$pdf->Cell(105, 5, 'Verified by:', 0, 0, 'L');
$pdf->Cell(200, 5, 'Approved by:', 0, 1, 'L');

// Add empty space for signatures (removed the 'B' border parameter)
$pdf->Cell(95, 20, '', 0, 0, 'L'); // Space for Co-op Staff signature
$pdf->Cell(200, 20, '', 0, 1, 'L'); // Space for Bank Manager signature

// Signature labels
$pdf->Cell(95, 5, 'Co-op City Shop Staff', 0, 0, 'L');
$pdf->Cell(200, 5, 'Bank Manager', 0, 1, 'L');

// Signature details (name and date)
$pdf->Cell(95, 5, 'Name: _________________________', 0, 0, 'L');
$pdf->Cell(200, 5, 'Name: _________________________', 0, 1, 'L');

$pdf->Cell(95, 5, 'Date: ' . date('Y-m-d'), 0, 0, 'L');
$pdf->Cell(200, 5, 'Date: ' . date('Y-m-d'), 0, 1, 'L');
            
            // ========== FOOTER SECTION ========== //
            $pdf->SetY(-30);
            $pdf->SetFont('Helvetica', 'I', 8);
            $pdf->SetTextColor($grayColor[0], $grayColor[1], $grayColor[2]);
            $pdf->Cell(0, 5, 'This is a computer generated report. Thank you for your business!', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Generated on ' . date('Y-m-d H:i:s'), 0, 1, 'C');
            $pdf->Cell(0, 5, 'Page ' . $pdf->PageNo(), 0, 0, 'C');
            
            // Output PDF
            $pdf->Output('D', 'Supplier_Report_' . $supplier['supplier_name'] . '_' . $startDate . '_' . $endDate . '.pdf');
            exit;
        }
    }
}

// Handle AJAX request for supplier suggestions
if (isset($_GET['search_term'])) {
    $searchTerm = '%' . $_GET['search_term'] . '%';
    $stmt = $pdo->prepare("SELECT supplier_id, supplier_name FROM supplier WHERE supplier_name LIKE ? ORDER BY supplier_name LIMIT 10");
    $stmt->execute([$searchTerm]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($suppliers);
    exit;
}

// Handle form submission
$reportData = [];
$supplierId = '';
$startDate = '';
$endDate = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = $_POST['supplier_id'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    
    // Basic validation
    if (!empty($supplierId) && !empty($startDate) && !empty($endDate)) {
        // Get supplier details
        $supplierQuery = "SELECT * FROM supplier WHERE supplier_id = ?";
        $supplierStmt = $pdo->prepare($supplierQuery);
        $supplierStmt->execute([$supplierId]);
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($supplier) {
            // Get items supplied by this supplier
            $itemsQuery = "SELECT * FROM items WHERE supplier_id = ?";
            $itemsStmt = $pdo->prepare($itemsQuery);
            $itemsStmt->execute([$supplierId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get purchases of these items within date range
            // Get purchases of these items within date range
$itemIds = array_column($items, 'item_id');

// Check if there are any items before creating placeholders
if (count($itemIds) > 0) {
    $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
    
    $purchasesQuery = "SELECT p.*, i.item_name 
                      FROM item_purchases p
                      JOIN items i ON p.item_id = i.item_id
                      WHERE p.item_id IN ($placeholders)
                      AND p.purchase_date BETWEEN ? AND ?";
    
    $params = array_merge($itemIds, [$startDate, $endDate]);
    $purchasesStmt = $pdo->prepare($purchasesQuery);
    $purchasesStmt->execute($params);
    $purchases = $purchasesStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // No items found for this supplier
    $purchases = [];
}
            
            // Get payments to this supplier within date range
            $paymentsQuery = "SELECT * FROM supplier_payments 
                             WHERE supplier_id = ? 
                             AND payment_date BETWEEN ? AND ?";
            $paymentsStmt = $pdo->prepare($paymentsQuery);
            $paymentsStmt->execute([$supplierId, $startDate, $endDate]);
            $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $totalPurchases = array_sum(array_column($purchases, 'total_price'));
            $totalPayments = array_sum(array_column($payments, 'amount'));
            $outstandingBalance = $totalPurchases - $totalPayments;
            
            // Prepare report data
            $reportData = [
                'supplier' => $supplier,
                'items' => $items,
                'purchases' => $purchases,
                'payments' => $payments,
                'totalPurchases' => $totalPurchases,
                'totalPayments' => $totalPayments,
                'outstandingBalance' => $outstandingBalance,
                'startDate' => $startDate,
                'endDate' => $endDate
            ];
        }
    }
}

// Get all suppliers for initial load (if needed)
$suppliersQuery = "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name";
$suppliers = $pdo->query($suppliersQuery)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Performance Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <style>
        :root {
            --primary-color:rgb(202, 230, 249);
            --secondary-color:rgb(144, 195, 228);
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
        }
        
        *{
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            
            
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        h1, h2, h3 {
            color: var(--dark-color);
        }
        
        .report-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .report-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .form-container {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .form-container:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        select, input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        select:focus, input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button i {
            margin-right: 8px;
        }
        
        .report-container {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .report-container:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f1f1f1;
        }
        
        .summary-card {
            background-color: #e8f4fc;
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateX(5px);
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }
        
        .summary-card h3 {
            margin-top: 0;
            color: var(--primary-color);
            border-bottom: 1px solid #d4e6f1;
            padding-bottom: 10px;
        }
        
        .print-button {
            margin-top: 25px;
            text-align: right;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255,255,255,0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            display: none;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .chart-container {
            margin: 30px 0;
            height: 300px;
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.05);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .badge-danger {
            background-color: var(--accent-color);
            color: white;
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000 !important;
        }
        
        .ui-menu-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .ui-menu-item:last-child {
            border-bottom: none;
        }
        
        .ui-menu-item:hover {
            background-color: #f5f5f5;
        }
        
        .ui-state-focus {
            background-color: #e8f4fc !important;
            color: #2c3e50;
        }
        
        #supplier_name {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        #supplier_name:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        .supplier-input-container {
            position: relative;
        }
        
        .supplier-input-container i {
            position: absolute;
            right: 12px;
            top: 12px;
            color: #aaa;
        }
        
        @media print {
            .form-container, button, .no-print {
                display: none;
            }
            
            body {
                background-color: white;
                padding: 0;
                font-size: 12px;
            }
            
            .report-header {
                background-color: white !important;
                color: black !important;
                padding: 10px 0;
                box-shadow: none !important;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
        
        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-in {
            animation: slideIn 0.6s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="report-header animate__animated animate__fadeInDown">
        <h1><i class="fas fa-chart-line"></i> Supplier Performance Report</h1>
        <p style="color:black; font-weight:bold;">Analyze supplier transactions and performance metrics</p>
    </div>
    
    <div class="form-container animate__animated animate__fadeIn">
        <form id="reportForm" method="POST" action="">
            <div class="form-group">
                <label for="supplier_name"><i class="fas fa-truck"></i> Supplier</label>
                <div class="supplier-input-container">
                    <input type="text" name="supplier_name" id="supplier_name" 
                           value="<?= !empty($reportData) ? htmlspecialchars($reportData['supplier']['supplier_name']) : '' ?>" 
                           placeholder="Start typing supplier name..." required>
                    <i class="fas fa-search"></i>
                    <input type="hidden" name="supplier_id" id="supplier_id" 
                           value="<?= !empty($reportData) ? htmlspecialchars($reportData['supplier']['supplier_id']) : '' ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="start_date"><i class="far fa-calendar-alt"></i> Start Date</label>
                <input type="date" name="start_date" id="start_date" 
                       value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="end_date"><i class="far fa-calendar-alt"></i> End Date</label>
                <input type="date" name="end_date" id="end_date" 
                       value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            
            <button type="submit" id="generateBtn" style="color:black;">
                <i class="fas fa-cog"></i> Generate Report
            </button>
        </form>
    </div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <p>Generating report...</p>
    </div>
    
    <?php if (!empty($reportData)): ?>
    <div class="report-container animate__animated animate__fadeInUp" id="reportResults">
        <h2><i class="fas fa-file-alt"></i> Supplier Performance Report</h2>
        <p>Period: <?= htmlspecialchars($reportData['startDate']) ?> to <?= htmlspecialchars($reportData['endDate']) ?></p>
        
        <div class="summary-card slide-in">
            <h3 style="color:black;"><i class="fas fa-info-circle"></i> Supplier Information</h3>
            <p><strong>Name:</strong> <?= htmlspecialchars($reportData['supplier']['supplier_name']) ?></p>
            <p><strong>Supplier ID:</strong> <?= htmlspecialchars($reportData['supplier']['supplier_id']) ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($reportData['supplier']['contact_number']) ?></p>
            <p><strong>Address:</strong> <?= htmlspecialchars($reportData['supplier']['address']) ?></p>
        </div>
        
        <div class="summary-card slide-in">
            <h3 style="color:black;"><i class="fas fa-chart-pie"></i> Financial Summary</h3>
            <p><strong>Total Purchases:</strong> LKR <?= number_format($reportData['totalPurchases'], 2) ?>
                <span class="tooltip">
                    <i class="fas fa-info-circle"></i>
                    <span class="tooltiptext">Total amount spent on items from this supplier during the selected period</span>
                </span>
            </p>
            <p><strong>Total Payments:</strong> LKR <?= number_format($reportData['totalPayments'], 2) ?>
                <span class="tooltip">
                    <i class="fas fa-info-circle"></i>
                    <span class="tooltiptext">Total payments made to this supplier during the selected period</span>
                </span>
            </p>
            <p><strong>Outstanding Balance:</strong> LKR <?= number_format($reportData['outstandingBalance'], 2) ?>
                <?php if ($reportData['outstandingBalance'] > 0): ?>
                    <span class="badge badge-danger">Unpaid</span>
                <?php elseif ($reportData['outstandingBalance'] < 0): ?>
                    <span class="badge badge-warning">Overpaid</span>
                <?php else: ?>
                    <span class="badge badge-success">Balanced</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="chart-container no-print" id="purchasesChart">
            <canvas id="chartCanvas"></canvas>
        </div>
        
        <h3 class="fade-in"><i class="fas fa-boxes"></i> Items Supplied</h3>
        <div class="table-responsive fade-in">
            <table>
                <thead>
                    <tr style="color:black;">
                        <th  style="color: black;">Item Code</th>
                        <th  style="color: black;">Item Name</th>
                        <th style="color: black;">Price per Unit</th>
                        <th  style="color: black;">Current Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_code']) ?></td>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td>LKR <?= number_format($item['price_per_unit'], 2) ?></td>
                        <td><?= htmlspecialchars($item['current_quantity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <h3 class="fade-in"><i class="fas fa-shopping-cart"></i> Purchase History</h3>
        <?php if (!empty($reportData['purchases'])): ?>
        <div class="table-responsive fade-in">
            <table>
                <thead>
                    <tr style="color:black;">
                        <th  style="color: black;">Date</th>
                        <th  style="color: black;">Item</th>
                        <th  style="color: black;">Quantity</th>
                        <th  style="color: black;">Unit Price</th>
                        <th  style="color: black;">Total Price</th>
                        <th  style="color: black;">Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['purchases'] as $purchase): ?>
                    <tr>
                        <td><?= htmlspecialchars($purchase['purchase_date']) ?></td>
                        <td><?= htmlspecialchars($purchase['item_name']) ?></td>
                        <td><?= htmlspecialchars($purchase['quantity']) ?></td>
                        <td>LKR <?= number_format($purchase['price_per_unit'], 2) ?></td>
                        <td>LKR <?= number_format($purchase['total_price'], 2) ?></td>
                        <td><?= htmlspecialchars($purchase['expire_date'] ?? 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="fade-in">
            <p>No purchases found for this period.</p>
        </div>
        <?php endif; ?>
        
        <h3 class="fade-in"><i class="fas fa-money-bill-wave"></i> Payment History</h3>
        <?php if (!empty($reportData['payments'])): ?>
        <div class="table-responsive fade-in">
            <table>
                <thead>
                    <tr>
                        <th  style="color: black;">Payment ID</th>
                        <th  style="color: black;">Date</th>
                        <th  style="color: black;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['payments'] as $payment): ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['id']) ?></td>
                        <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                        <td>LKR <?= number_format($payment['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="fade-in">
            <p>No payments found for this period.</p>
        </div>
        <?php endif; ?>
        
        <div class="print-button no-print">
            <button onclick="generatePDF()" id="pdfBtn" style="color:black;">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Set default dates (last 30 days)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
            
            // Format dates as YYYY-MM-DD
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            // Set default values if not already set by form submission
            if (!document.getElementById('start_date').value) {
                document.getElementById('start_date').value = formatDate(oneMonthAgo);
            }
            if (!document.getElementById('end_date').value) {
                document.getElementById('end_date').value = formatDate(today);
            }
            
            // Initialize autocomplete for supplier name
            $("#supplier_name").autocomplete({
                source: function(request, response) {
                    $.get("<?php echo $_SERVER['PHP_SELF']; ?>", {
                        search_term: request.term
                    }, function(data) {
                        response($.map(JSON.parse(data), function(item) {
                            return {
                                label: item.supplier_name,
                                value: item.supplier_name,
                                id: item.supplier_id
                            };
                        }));
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#supplier_id").val(ui.item.id);
                    $("#supplier_name").val(ui.item.value);
                    return false;
                },
                focus: function(event, ui) {
                    $("#supplier_name").val(ui.item.label);
                    return false;
                }
            }).data("ui-autocomplete")._renderItem = function(ul, item) {
                return $("<li>")
                    .append(`<div>${item.label} <small style="color:#888">(ID: ${item.id})</small></div>`)
                    .appendTo(ul);
            };
            
            // Initialize chart if report data exists
            <?php if (!empty($reportData)): ?>
                renderChart();
            <?php endif; ?>
            
            // Form submission handler
            document.getElementById('reportForm').addEventListener('submit', function(e) {
                if (!$("#supplier_id").val()) {
                    alert("Please select a valid supplier from the list");
                    e.preventDefault();
                    return;
                }
                
                e.preventDefault();
                showLoading();
                
                // Submit form after a small delay to show loading animation
                setTimeout(() => {
                    this.submit();
                }, 500);
            });
        });
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function renderChart() {
            <?php if (!empty($reportData['purchases'])): ?>
                const purchases = <?= json_encode($reportData['purchases']) ?>;
                const payments = <?= json_encode($reportData['payments']) ?>;
                
                // Group purchases by date
                const purchaseDates = {};
                purchases.forEach(purchase => {
                    const date = purchase.purchase_date;
                    if (!purchaseDates[date]) {
                        purchaseDates[date] = 0;
                    }
                    purchaseDates[date] += parseFloat(purchase.total_price);
                });
                
                // Group payments by date
                const paymentDates = {};
                payments.forEach(payment => {
                    const date = payment.payment_date;
                    if (!paymentDates[date]) {
                        paymentDates[date] = 0;
                    }
                    paymentDates[date] += parseFloat(payment.amount);
                });
                
                // Get all unique dates
                const allDates = [...new Set([
                    ...Object.keys(purchaseDates),
                    ...Object.keys(paymentDates)
                ])].sort();
                
                // Prepare data for chart
                const purchaseData = allDates.map(date => purchaseDates[date] || 0);
                const paymentData = allDates.map(date => paymentDates[date] || 0);
                
                // Create chart
                const ctx = document.getElementById('chartCanvas').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: allDates,
                        datasets: [
                            {
                                label: 'Purchases',
                                data: purchaseData,
                                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                                borderColor: 'rgba(52, 152, 219, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Payments',
                                data: paymentData,
                                backgroundColor: 'rgba(46, 204, 113, 0.7)',
                                borderColor: 'rgba(46, 204, 113, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Amount (LKR)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date'
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Purchases vs Payments Timeline'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': LKR ' + context.raw.toFixed(2);
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        }
        
        function generatePDF() {
            const supplierId = document.getElementById('supplier_id').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (!supplierId || !startDate || !endDate) {
                alert('Please generate the report first before downloading as PDF');
                return;
            }
            
            showLoading();
            
            // Redirect to the same page with PDF generation parameters
            window.location.href = `<?php echo $_SERVER['PHP_SELF']; ?>?generate_pdf=1&supplier_id=${supplierId}&start_date=${startDate}&end_date=${endDate}`;
        }
        
        // Hide loading when page fully loads
        window.addEventListener('load', hideLoading);
    </script>
</body>
</html>