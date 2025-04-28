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
        $sql = "SELECT item_id, item_name FROM items 
                WHERE item_name LIKE '%$searchTerm%' 
                ORDER BY item_name LIMIT 10";
        $result = $conn->query($sql);
        
        $suggestions = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = [
                    'id' => $row['item_id'],
                    'name' => $row['item_name']
                ];
            }
        }
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
$item_filter = isset($_GET['item_id']) ? $_GET['item_id'] : '';
$item_name = isset($_GET['item_name']) ? $_GET['item_name'] : '';

// Function to get all purchases with item and supplier details
function getPurchaseDetails($conn, $start_date = '', $end_date = '', $supplier_filter = '', $item_filter = '') {
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
    
    if (!empty($item_filter)) {
        $conditions[] = "i.item_id = ?";
        $params[] = $item_filter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY ip.purchase_date DESC, ip.purchase_id DESC";
    
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
            $purchases[] = $row;
        }
    }
    
    return $purchases;
}

// Get all purchase details with filters
$purchases = getPurchaseDetails($conn, $start_date, $end_date, $supplier_filter, $item_filter);

// Generate PDF report if requested
if (isset($_GET['generate_pdf'])) {
    require('fpdf/fpdf.php');

    class PDF extends FPDF {
        // Company header with logo
        
        function Header() {
            $this->Rect(0, 0, $this->GetPageWidth(), 50, 'F');
            $this->SetFillColor(41, 128, 185); // Primary blue
            $this->Rect(0, 0, $this->GetPageWidth(), 50, 'F');
            // Add logo (replace with your actual logo path)
            if (file_exists('images/logo.jpeg')) {
                $this->Image('images/logo.jpeg', 10, 5, 30);
            }
            
            // Company information
            $this->SetTextColor(255, 255, 255); // White text
            $this->SetFont('Arial', 'B', 18);
            $this->Cell(0, 8, 'T&C co-op City shop', 0, 1, 'C');
            $this->SetFont('Arial', '', 11);
            $this->Cell(0, 6, 'Karawita', 0, 1, 'C');
            $this->Cell(0, 6, 'Phone: +94 11 2345678 | Email: accounts@coopshop.lk', 0, 1, 'C');
            
            // Report title
            $this->SetFont('Arial','B',16);
            $this->Cell(0,15,'PURCHASE ITEMS REPORT',0,1,'C');
            $this->Ln(5);
            
            // Report period
            $this->SetFont('Arial','',10);
            $period = "All Purchases";
            if (!empty($_GET['start_date']) || !empty($_GET['end_date'])) {
                $period = "From: " . (!empty($_GET['start_date']) ? $_GET['start_date'] : 'Beginning') . 
                          " To: " . (!empty($_GET['end_date']) ? $_GET['end_date'] : 'Now');
            }
            $this->Cell(0,5,'Period: ' . $period,0,1,'C');
            
            // Filter details
            $filters = [];
            if (!empty($_GET['supplier_name'])) $filters[] = "Supplier: " . $_GET['supplier_name'];
            if (!empty($_GET['item_name'])) $filters[] = "Item: " . $_GET['item_name'];
            
            if (!empty($filters)) {
                $this->Cell(0,5,'Filters: ' . implode(', ', $filters),0,1,'C');
            }
            
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
        
        // Colored table header
        function TableHeader() {
            $this->SetFont('Arial','B',9);
            $this->SetFillColor(54, 123, 180); // Blue header
            $this->SetTextColor(255);
            $this->SetDrawColor(54, 123, 180);
            $this->SetLineWidth(0.3);
            
            $header = array(
                'Purchase Date',
                'Item Name', 
                'Supplier',
                'Qty',
                'Unit Price',
                'Total Price',
                'Expiry Date',
                'Status'
            );
            
            // Adjusted widths for portrait mode
            $w = array(25, 30, 45, 10, 20, 20, 25, 15);
            
            for($i=0; $i<count($header); $i++) {
                $this->Cell($w[$i],7,$header[$i],1,0,'C',true);
            }
            $this->Ln();
        }
        
        // Table content with alternating colors
        function TableContent($purchases) {
            $this->SetFont('Arial','',8);
            $this->SetTextColor(0);
            $this->SetFillColor(224, 235, 255); // Light blue alternate row
            $fill = false;
            
            // Adjusted widths for portrait mode
            $w = array(25, 30, 45, 10, 20, 20, 25, 15);
            
            foreach($purchases as $purchase) {
                // Determine status and row color
                $status = $this->getExpirationStatus($purchase['expire_date']);
                $rowColor = $this->getStatusColor($status);
                
                if($rowColor) {
                    $this->SetFillColor($rowColor[0], $rowColor[1], $rowColor[2]);
                    $fill = true;
                } else {
                    // Fixed syntax error here - using separate SetFillColor calls
                    if($fill) {
                        $this->SetFillColor(240, 240, 240);
                    } else {
                        $this->SetFillColor(255, 255, 255);
                    }
                    $fill = !$fill;
                }
                
                // Format dates
                $purchase_date = date('d M Y', strtotime($purchase['purchase_date']));
                $expire_date = !empty($purchase['expire_date']) ? date('d M Y', strtotime($purchase['expire_date'])) : 'N/A';
                
                // Check if we need a new page due to limited space
                if($this->GetY() > 250) {
                    $this->AddPage();
                    $this->TableHeader();
                }
                
                // Cell contents - adjust text handling for smaller cells
                $this->Cell($w[0],6,$purchase_date,'LR',0,'L',$fill);
                
                // Item name with potential wrapping
                $this->Cell($w[1],6,$this->CellFitScale($w[1], 6, $purchase['item_name']),'LR',0,'L',$fill);
                $this->Cell($w[2],6,$this->CellFitScale($w[2], 6, $purchase['supplier_name']),'LR',0,'L',$fill);
                
                $this->Cell($w[3],6,$purchase['quantity'],'LR',0,'R',$fill);
                $this->Cell($w[4],6,number_format($purchase['price_per_unit'], 2),'LR',0,'R',$fill);
                $this->Cell($w[5],6,number_format($purchase['total_price'], 2),'LR',0,'R',$fill);
                $this->Cell($w[6],6,$expire_date,'LR',0,'L',$fill);
                $this->Cell($w[7],6,$status,'LR',0,'L',$fill);
                $this->Ln();
            }
            
            // Closing line
            $this->Cell(array_sum($w),0,'','T');
        }
        
        // Helper function to fit text in cells
        function CellFitScale($w, $h, $txt, $border=0, $ln=0, $align='', $fill=false) {
            // If the text is short enough, just return it
            if($this->GetStringWidth($txt) <= $w) {
                return $txt;
            }
            
            // Text is too long, truncate with ellipsis
            $txt_length = strlen($txt);
            $ellipsis = '...';
            
            while($this->GetStringWidth($txt . $ellipsis) > $w) {
                $txt = substr($txt, 0, --$txt_length);
            }
            
            return $txt . $ellipsis;
        }
        
        // Get expiration status text
        function getExpirationStatus($expire_date) {
            if (empty($expire_date)) return 'No expiry';
            
            $currentDate = new DateTime();
            $expireDate = new DateTime($expire_date);
            $interval = $currentDate->diff($expireDate);
            
            if ($expireDate < $currentDate) return 'Expired';
            if ($interval->days <= 30) return 'Expiring ('.$interval->days.'d)';
            return 'Active';
        }
        
        // Get color based on status
        function getStatusColor($status) {
            if (strpos($status, 'Expired') !== false) return array(255, 200, 200); // Light red
            if (strpos($status, 'Expiring') !== false) return array(255, 255, 150); // Light yellow
            return false; // Use default alternating colors
        }
        
        // Summary section
        function Summary($totalQuantity, $totalAmount, $count) {
            $this->Ln(10);
            $this->SetFont('Arial','B',12);
            $this->Cell(0,6,'SUMMARY',0,1,'L');
            $this->SetFont('Arial','',10);
            
            $this->Cell(50,6,'Total Purchases:',0,0,'L');
            $this->Cell(0,6,$count,0,1,'L');
            
            $this->Cell(50,6,'Total Quantity:',0,0,'L');
            $this->Cell(0,6,$totalQuantity,0,1,'L');
            
            $this->Cell(50,6,'Total Amount:',0,0,'L');
            $this->Cell(0,6,'Rs. '.number_format($totalAmount, 2),0,1,'L');
            
            $this->Ln(5);
            $this->SetFont('Arial','I',8);
            $this->Cell(0, 6, 'Report generated on: ' . date('F j, Y'), 0, 1, 'L');
        }
        
        // Signature section with improved spacing
        // Signature section with improved spacing
// Signature section with side-by-side panels
// Signature section with perfectly aligned side-by-side panels
// Signature section with perfectly matched spacing
function SignatureSection() {
    $this->Ln(15);
    
    // Set common dimensions for both panels
    $panelWidth = 85; // Width of each signature panel
    $lineLength = 60; // Length of the signature line
    $startY = $this->GetY(); // Starting Y position
    
    // Vertical spacing values (in mm)
    $afterTitle = 8;    // Space after "Co-op City Staff"/"Bank Manager"
    $afterLine = 6;     // Space after signature line
    $afterSignature = 6; // Space after "Signature" text
    $afterName = 6;     // Space after name field

    // Co-op City Staff section (LEFT)
    $this->SetFont('Arial','B',10);
    $this->Cell($panelWidth, 6, 'Co-op City Staff:', 0, 1, 'L');
    $this->Ln($afterTitle);
    
    // Signature line
    $this->Cell($lineLength, 2, '', 'B', 1, 'L');
    $this->Ln($afterLine);
    
    // Signature label
    $this->Cell($panelWidth, 6, 'Signature', 0, 1, 'L');
    $this->Ln($afterSignature);
    
    // Name field
    $this->SetFont('Arial','I',9);
    $this->Cell($panelWidth, 6, 'Name: ___________________', 0, 1, 'L');
    $this->Ln($afterName);
    
    // Date field
    $this->Cell($panelWidth, 6, 'Date: ' . date('Y-m-d'), 0, 1, 'L');
    
    // Reset position for Bank Manager section
    $this->SetY($startY);
    $this->SetX($panelWidth + 20);
    
    // Bank Manager section (RIGHT) - identical spacing to left section
    $this->SetFont('Arial','B',10);
    $this->Cell($panelWidth, 6, 'Bank Manager:', 0, 1, 'L');
    $this->SetX($panelWidth + 20);
    $this->Ln($afterTitle);
    
    // Signature line
    $this->SetX($panelWidth + 20);
    $this->Cell($lineLength, 2, '', 'B', 1, 'L');
    $this->SetX($panelWidth + 20);
    $this->Ln($afterLine);
    
    // Signature label
    $this->SetX($panelWidth + 20);
    $this->Cell($panelWidth, 6, 'Signature', 0, 1, 'L');
    $this->SetX($panelWidth + 20);
    $this->Ln($afterSignature);
    
    // Name field
    $this->SetFont('Arial','I',9);
    $this->SetX($panelWidth + 20);
    $this->Cell($panelWidth, 6, 'Name: ___________________', 0, 1, 'L');
    $this->SetX($panelWidth + 20);
    $this->Ln($afterName);
    
    // Date field
    $this->SetX($panelWidth + 20);
    $this->Cell($panelWidth, 6, 'Date: ___________________', 0, 1, 'L');
    
    $this->Ln(10); // Final document spacing
}
    }

    // Generate PDF - changed to portrait orientation
    $pdf = new PDF('P'); // Portrait orientation
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Build table
    $pdf->TableHeader();
    $pdf->TableContent($purchases);
    
    // Calculate totals
    $totalQuantity = array_sum(array_column($purchases, 'quantity'));
    $totalAmount = array_sum(array_column($purchases, 'total_price'));
    
    // Add summary and signature
    $pdf->Summary($totalQuantity, $totalAmount, count($purchases));
    $pdf->SignatureSection();
    
    // Output PDF
    $pdf->Output('Purchase_Report_'.date('Ymd_His').'.pdf', 'D');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchased Items Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
           font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
        }
        
        .container {
            max-width: 1400px;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .page-header:hover {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .table-responsive {
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .table-responsive:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .table {
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
            position: sticky;
            top: 0;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 15px;
        }
        
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-top: 1px solid #e3e6f0;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
            transform: scale(1.005);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }
        
        .total-row {
            font-weight: bold;
            background-color: var(--secondary-color);
            color: var(--dark-color);
        }
        
        .expired {
            background-color: rgba(231, 74, 59, 0.1);
            border-left: 4px solid var(--danger-color);
        }
        
        .expiring-soon {
            background-color: rgba(246, 194, 62, 0.1);
            border-left: 4px solid var(--warning-color);
        }
        
        .filter-section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .filter-section:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: none;
            font-size: 0.9rem;
        }
        
        .ui-menu-item {
            padding: 8px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }
        
        .ui-menu-item:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .search-container {
            position: relative;
        }
        
        .hidden-id-field {
            display: none;
        }
        
        .filter-row {
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
            transform: translateY(-1px);
        }
        
        .btn-light {
            transition: all 0.3s;
        }
        
        .btn-light:hover {
            background-color: #e2e6ea;
            transform: translateY(-1px);
        }
        
        .form-control, .form-select {
            border-radius: 5px;
            padding: 10px 15px;
            border: 1px solid #d1d3e2;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .alert {
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .status-expired {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }
        
        .status-expiring {
            background-color: rgba(246, 194, 62, 0.1);
            color: #dda20a;
        }
        
        .status-none {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(78, 115, 223, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(78, 115, 223, 0); }
            100% { box-shadow: 0 0 0 0 rgba(78, 115, 223, 0); }
        }
        
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }
        
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        button {
            background: linear-gradient(to right, #28a745, #218838);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        button:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.4);
        }
    </style>
</head>
<body>
    <div class="container mt-4 animate__animated animate__fadeIn">
        <div class="page-header animate__animated animate__fadeInDown">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 style="color:black;"><i class="fas fa-shopping-cart me-2"></i> Purchased Items Details</h2>
                </div>
                <div class="col-md-4 text-end">
                    <form method="GET" action="" style="display: inline;">
                        <!-- Pass all filter parameters to the PDF generation -->
                        <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                        <input type="hidden" name="supplier_id" value="<?php echo $supplier_filter; ?>">
                        <input type="hidden" name="supplier_name" value="<?php echo htmlspecialchars($supplier_name); ?>">
                        <input type="hidden" name="item_id" value="<?php echo $item_filter; ?>">
                        <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item_name); ?>">
                        <button type="submit" name="generate_pdf" value="1" class="btn btn-light">
                            <i class="fas fa-file-pdf me-1"></i> Generate Report
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section animate__animated animate__fadeIn">
            <form method="GET" action="">
                <div class="row filter-row">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label"><i class="far fa-calendar-alt me-1"></i> From Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label"><i class="far fa-calendar-alt me-1"></i> To Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="supplier_name" class="form-label"><i class="fas fa-truck me-1"></i> Supplier</label>
                        <div class="search-container">
                            <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                   value="<?php echo htmlspecialchars($supplier_name); ?>" 
                                   placeholder="Type supplier name...">
                            <input type="hidden" id="supplier_id" name="supplier_id" value="<?php echo $supplier_filter; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="item_name" class="form-label"><i class="fas fa-box-open me-1"></i> Item</label>
                        <div class="search-container">
                            <input type="text" class="form-control" id="item_name" name="item_name" 
                                   value="<?php echo htmlspecialchars($item_name); ?>" 
                                   placeholder="Type item name...">
                            <input type="hidden" id="item_id" name="item_id" value="<?php echo $item_filter; ?>">
                        </div>
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
                    <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="far fa-calendar me-1"></i> Purchase Date</th>
                        <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="fas fa-box me-1"></i> Item Name</th>
                        <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="fas fa-truck me-1"></i> Supplier</th>
                        <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="fas fa-cubes me-1"></i> Quantity</th>
                        <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="fas fa-tag me-1"></i> Price/Unit</th>
                        <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="fas fa-money-bill-wave me-1"></i> Total Price</th>
                        <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="fas fa-hourglass-end me-1"></i> Expire Date</th>
                        <th style="color:black; font-weight: bold; font-size: 12.5px;"><i class="fas fa-info-circle me-1"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalQuantity = 0;
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
                        
                        $totalQuantity += $purchase['quantity'];
                        $totalAmount += $purchase['total_price'];
                    ?>
                    <tr class="<?php echo $rowClass; ?> animate__animated animate__fadeIn">
                        <td><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
                        <td><?php echo $purchase['item_name']; ?></td>
                        <td><?php echo $purchase['supplier_name']; ?></td>
                        <td><?php echo $purchase['quantity']; ?></td>
                        <td>Rs. <?php echo number_format($purchase['price_per_unit'], 2); ?></td>
                        <td>Rs. <?php echo number_format($purchase['total_price'], 2); ?></td>
                        <td><?php echo !empty($purchase['expire_date']) ? date('d M Y', strtotime($purchase['expire_date'])) : 'N/A'; ?></td>
                        <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (count($purchases) > 0): ?>
                    <tr class="total-row animate__animated animate__fadeIn">
                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                        <td><strong><?php echo $totalQuantity; ?></strong></td>
                        <td></td>
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

    <a href="home.php" class="btn btn-primary floating-btn pulse animate__animated animate__fadeInUp">
        <i class="fas fa-home" style="color:rgb(75, 97, 135);"></i>
    </a>

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
                    $("#supplier_id").val('');
                }
            });

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
                                    id: item.id
                                };
                            }));
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $("#item_id").val(ui.item.id);
                    $("#item_name").val(ui.item.label);
                    return false;
                },
                focus: function(event, ui) {
                    $("#item_name").val(ui.item.label);
                    return false;
                }
            }).data("ui-autocomplete")._renderItem = function(ul, item) {
                return $("<li>")
                    .append("<div><i class='fas fa-box-open me-2'></i>" + item.label + "</div>")
                    .appendTo(ul);
            };

            // Clear hidden ID field when user clears the text input
            $("#item_name").on('input', function() {
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
    </script>
</body>
</html>