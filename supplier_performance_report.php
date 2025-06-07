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

// At the top, after DB connection and before HTML output
$reportData = [];
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$supplierId = $_GET['supplier_id'] ?? '';

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
        $itemIds = array_column($items, 'item_id');
        $purchases = [];
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

        // Prepare report data for HTML
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

// Handle PDF generation request
if (isset($_GET['generate_pdf']) && $_GET['generate_pdf'] == '1') {
    require('fpdf/fpdf.php');

    class PDF extends FPDF {
        // Header with company information
        function Header() {
            // Blue header background
            $this->SetFillColor(41, 128, 185);
            $this->Rect(0, 0, $this->GetPageWidth(), 50, 'F');
            
            // Add logo if available
            if (file_exists('images/logo.jpeg')) {
                $this->Image('images/logo.jpeg', 10, 5, 30);
            }
            
            // Company information
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 18);
            $this->Cell(0, 8, 'T&C CO-OP CITY SHOP', 0, 1, 'C');
            $this->SetFont('Arial', '', 11);
            $this->Cell(0, 6, 'Karawita', 0, 1, 'C');
            $this->Cell(0, 6, 'Phone: +94 11 2345678 | Email: accounts@coopshop.lk', 0, 1, 'C');
            
            // Report title
            $this->SetFont('Arial','B',16);
            $this->Cell(0,15,'SUPPLIER PERFORMANCE REPORT',0,1,'C');
            $this->Ln(5);
            
            // Report period
            $this->SetFont('Arial','',10);
            $period = "From: " . $_GET['start_date'] . " To: " . $_GET['end_date'];
            $this->Cell(0,5,'Period: ' . $period,0,1,'C');
            
            // Horizontal line
            $this->Line(10, $this->GetY()+5, $this->GetPageWidth()-10, $this->GetY()+5);
            $this->Ln(10);
        }
        
        // Professional footer
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,5,'Confidential - For internal use only',0,0,'L');
            $this->Cell(0,5,'Page '.$this->PageNo().'/{nb}',0,0,'R');
        }
        
        // Supplier Information Section
        function SupplierInfo($supplier) {
            $this->SetFont('Arial','B',12);
            $this->Cell(0, 6, 'SUPPLIER INFORMATION', 0, 1, 'L');
            $this->SetFont('Arial','',10);
            
            $this->Cell(40, 6, 'Supplier Name:', 0, 0);
            $this->Cell(0, 6, $supplier['supplier_name'], 0, 1);
            
            $this->Cell(40, 6, 'Supplier ID:', 0, 0);
            $this->Cell(0, 6, $supplier['supplier_id'], 0, 1);
            
            $this->Cell(40, 6, 'Contact:', 0, 0);
            $this->Cell(0, 6, $supplier['contact_number'], 0, 1);
            
            $this->Cell(40, 6, 'Address:', 0, 0);
            $this->MultiCell(0, 6, $supplier['address'], 0, 1);
            
            $this->Ln(5);
        }
        
        // Financial Summary Section
        function FinancialSummary($totalPurchases, $totalPayments, $outstandingBalance) {
            $this->SetFont('Arial','B',12);
            $this->Cell(0, 6, 'FINANCIAL SUMMARY', 0, 1, 'L');
            $this->SetFont('Arial','',10);
            
            $this->Cell(70, 6, 'Total Purchases:', 0, 0);
            $this->Cell(0, 6, 'Rs. ' . number_format($totalPurchases, 2), 0, 1);
            
            $this->Cell(70, 6, 'Total Payments:', 0, 0);
            $this->Cell(0, 6, 'Rs. ' . number_format($totalPayments, 2), 0, 1);
            
            // Determine color for outstanding balance
            if ($outstandingBalance > 0) {
                $this->SetFillColor(255, 200, 200); // Light red for positive balance (owed)
            } elseif ($outstandingBalance < 0) {
                $this->SetFillColor(255, 255, 150); // Light yellow for overpayment
            } else {
                $this->SetFillColor(200, 255, 200); // Light green for balanced
            }
            
            $this->SetFont('Arial','B',10);
            $this->Cell(70, 6, 'Outstanding Balance:', 0, 0);
            $this->Cell(50, 6, 'Rs. ' . number_format($outstandingBalance, 2), 1, 1, 'L', true);
            
            $this->Ln(10);
        }
        
        // Purchase History Table
        function PurchaseHistoryTable($purchases) {
            $this->SetFont('Arial','B',12);
            $this->Cell(0, 6, 'PURCHASE HISTORY', 0, 1, 'L');
            
            if (empty($purchases)) {
                $this->SetFont('Arial','I',10);
                $this->Cell(0, 6, 'No purchases found for this period.', 0, 1, 'L');
                $this->Ln(10);
                return;
            }
            
            // Table Header
            $this->SetFont('Arial','B',9);
            $this->SetFillColor(54, 123, 180);
            $this->SetTextColor(255);
            $this->SetDrawColor(54, 123, 180);
            $this->SetLineWidth(0.3);
            
            $header = array('Date', 'Item', 'Qty', 'Unit Price', 'Total', 'Expiry');
            $w = array(25, 60, 15, 25, 25, 25);
            
            for($i=0; $i<count($header); $i++) {
                $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            // Table Content
            $this->SetFont('Arial','',8);
            $this->SetTextColor(0);
            $fill = false;
            
            foreach($purchases as $purchase) {
                // Check if we need a new page
                if($this->GetY() > 250) {
                    $this->AddPage();
                    $this->SetFont('Arial','B',9);
                    $this->SetFillColor(54, 123, 180);
                    $this->SetTextColor(255);
                    for($i=0; $i<count($header); $i++) {
                        $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
                    }
                    $this->Ln();
                    $this->SetFont('Arial','',8);
                    $this->SetTextColor(0);
                }
                
                // Alternate row color
                $this->SetFillColor($fill ? 224 : 255, $fill ? 235 : 255, 255);
                
                // Format dates
                $purchase_date = date('d M Y', strtotime($purchase['purchase_date']));
                $expire_date = !empty($purchase['expire_date']) ? date('d M Y', strtotime($purchase['expire_date'])) : 'N/A';
                
                $this->Cell($w[0], 6, $purchase_date, 'LR', 0, 'L', $fill);
                $this->Cell($w[1], 6, $this->trimText($purchase['item_name'], 25), 'LR', 0, 'L', $fill);
                $this->Cell($w[2], 6, $purchase['quantity'], 'LR', 0, 'R', $fill);
                $this->Cell($w[3], 6, number_format($purchase['price_per_unit'], 2), 'LR', 0, 'R', $fill);
                $this->Cell($w[4], 6, number_format($purchase['total_price'], 2), 'LR', 0, 'R', $fill);
                $this->Cell($w[5], 6, $expire_date, 'LR', 0, 'L', $fill);
                $this->Ln();
                
                $fill = !$fill;
            }
            
            // Closing line
            $this->Cell(array_sum($w), 0, '', 'T');
            $this->Ln(10);
        }
        
        // Payment History Table
        function PaymentHistoryTable($payments) {
            $this->SetFont('Arial','B',12);
            $this->Cell(0, 6, 'PAYMENT HISTORY', 0, 1, 'L');
            
            if (empty($payments)) {
                $this->SetFont('Arial','I',10);
                $this->Cell(0, 6, 'No payments found for this period.', 0, 1, 'L');
                $this->Ln(10);
                return;
            }
            
            // Table Header
            $this->SetFont('Arial','B',9);
            $this->SetFillColor(54, 123, 180);
            $this->SetTextColor(255);
            $this->SetDrawColor(54, 123, 180);
            $this->SetLineWidth(0.3);
            
            $header = array('Payment ID', 'Date', 'Amount', 'Method', 'Reference');
            $w = array(30, 30, 30, 40, 40);
            
            for($i=0; $i<count($header); $i++) {
                $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            // Table Content
            $this->SetFont('Arial','',8);
            $this->SetTextColor(0);
            $fill = false;
            
            foreach($payments as $payment) {
                // Check if we need a new page
                if($this->GetY() > 250) {
                    $this->AddPage();
                    $this->SetFont('Arial','B',9);
                    $this->SetFillColor(54, 123, 180);
                    $this->SetTextColor(255);
                    for($i=0; $i<count($header); $i++) {
                        $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
                    }
                    $this->Ln();
                    $this->SetFont('Arial','',8);
                    $this->SetTextColor(0);
                }
                
                // Alternate row color
                $this->SetFillColor($fill ? 224 : 255, $fill ? 235 : 255, 255);
                
                $payment_date = date('d M Y', strtotime($payment['payment_date']));
                
                $this->Cell($w[0], 6, $payment['id'], 'LR', 0, 'L', $fill);
                $this->Cell($w[1], 6, $payment_date, 'LR', 0, 'L', $fill);
                $this->Cell($w[2], 6, 'Rs. ' . number_format($payment['amount'], 2), 'LR', 0, 'R', $fill);
                $this->Cell($w[3], 6, $payment['payment_method'] ?? 'N/A', 'LR', 0, 'L', $fill);
                $this->Cell($w[4], 6, $payment['reference_number'] ?? 'N/A', 'LR', 0, 'L', $fill);
                $this->Ln();
                
                $fill = !$fill;
            }
            
            // Closing line
            $this->Cell(array_sum($w), 0, '', 'T');
            $this->Ln(10);
        }
        
        // Signature section
        function SignatureSection() {
            $this->Ln(15);
            
            // Set common dimensions
            $panelWidth = 85;
            $lineLength = 60;
            $startY = $this->GetY();
            
            // Vertical spacing values
            $afterTitle = 8;
            $afterLine = 6;
            $afterSignature = 6;
            $afterName = 6;
            
            // Co-op City Staff section (LEFT)
            $this->SetFont('Arial','B',10);
            $this->Cell($panelWidth, 6, 'Co-op City Staff:', 0, 1, 'L');
            $this->Ln($afterTitle);
            $this->Cell($lineLength, 2, '', 'B', 1, 'L');
            $this->Ln($afterLine);
            $this->Cell($panelWidth, 6, 'Signature', 0, 1, 'L');
            $this->Ln($afterSignature);
            $this->SetFont('Arial','I',9);
            $this->Cell($panelWidth, 6, 'Name: ___________________', 0, 1, 'L');
            $this->Ln($afterName);
            $this->Cell($panelWidth, 6, 'Date: ' . date('Y-m-d'), 0, 1, 'L');
            
            // Bank Manager section (RIGHT)
            $this->SetY($startY);
            $this->SetX($panelWidth + 20);
            $this->SetFont('Arial','B',10);
            $this->Cell($panelWidth, 6, 'Bank Manager:', 0, 1, 'L');
            $this->SetX($panelWidth + 20);
            $this->Ln($afterTitle);
            $this->SetX($panelWidth + 20);
            $this->Cell($lineLength, 2, '', 'B', 1, 'L');
            $this->SetX($panelWidth + 20);
            $this->Ln($afterLine);
            $this->SetX($panelWidth + 20);
            $this->Cell($panelWidth, 6, 'Signature', 0, 1, 'L');
            $this->SetX($panelWidth + 20);
            $this->Ln($afterSignature);
            $this->SetFont('Arial','I',9);
            $this->SetX($panelWidth + 20);
            $this->Cell($panelWidth, 6, 'Name: ___________________', 0, 1, 'L');
            $this->SetX($panelWidth + 20);
            $this->Ln($afterName);
            $this->SetX($panelWidth + 20);
            $this->Cell($panelWidth, 6, 'Date: ___________________', 0, 1, 'L');
            
            $this->Ln(10);
        }
        
        // Helper function to trim long text
        function trimText($text, $maxLength) {
            if (strlen($text) > $maxLength) {
                return substr($text, 0, $maxLength-3) . '...';
            }
            return $text;
        }
    }

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
            $itemIds = array_column($items, 'item_id');
            $purchases = [];
            
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
            
            // Generate PDF
            $pdf = new PDF('P');
            $pdf->AliasNbPages();
            $pdf->AddPage();
            
            // Add content
            $pdf->SupplierInfo($supplier);
            $pdf->FinancialSummary($totalPurchases, $totalPayments, $outstandingBalance);
            $pdf->PurchaseHistoryTable($purchases);
            $pdf->PaymentHistoryTable($payments);
            $pdf->SignatureSection();
            
            // Output PDF
            $pdf->Output('D', 'Supplier_Report_' . $supplier['supplier_name'] . '_' . $startDate . '_' . $endDate . '.pdf');
            exit;
        }
    }
}

// Handle AJAX supplier search for autocomplete
if (isset($_GET['search_term'])) {
    $term = $_GET['search_term'];
    $stmt = $pdo->prepare("SELECT supplier_id, supplier_name FROM supplier WHERE supplier_name LIKE ? ORDER BY supplier_name LIMIT 10");
    $stmt->execute(["%$term%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
    exit;
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
        <form id="reportForm" method="GET" action="">
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
               
                
                // Submit form after a small delay to show loading animation
                setTimeout(() => {
                    this.submit();
                }, 500);
            });
        });
        
        
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
            
       
            
            // Redirect to the same page with PDF generation parameters
            window.location.href = `<?php echo $_SERVER['PHP_SELF']; ?>?generate_pdf=1&supplier_id=${supplierId}&start_date=${startDate}&end_date=${endDate}`;
        }
        
        // Hide loading when page fully loads
        window.addEventListener('load', hideLoading);
    </script>
</body>
</html>