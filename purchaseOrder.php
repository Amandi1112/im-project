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
$medium_stock_threshold = 30; // Items with quantity below this (but above low) are medium stock


// Function to get low stock items
function getAllItems($pdo, $lowThreshold, $mediumThreshold, $filter = 'all') {
    $sql = "SELECT i.item_id, i.item_code, i.item_name, i.price_per_unit, 
                   i.unit_size, i.current_quantity, i.unit, i.type,
                   s.supplier_name, s.contact_number, s.address
            FROM items i
            LEFT JOIN supplier s ON i.supplier_id = s.supplier_id";
    
    // Add WHERE clause based on filter
    switch($filter) {
        case 'low':
            $sql .= " WHERE i.current_quantity <= :low_threshold";
            break;
        case 'medium':
            $sql .= " WHERE i.current_quantity > :low_threshold AND i.current_quantity < :medium_threshold";
            break;
        case 'high':
            $sql .= " WHERE i.current_quantity >= :medium_threshold";
            break;
        default:
            // Show all items
            break;
    }
    
    $sql .= " ORDER BY i.current_quantity ASC";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters based on filter
    if ($filter == 'low') {
        $stmt->execute(['low_threshold' => $lowThreshold]);
    } elseif ($filter == 'medium') {
        $stmt->execute(['low_threshold' => $lowThreshold, 'medium_threshold' => $mediumThreshold]);
    } elseif ($filter == 'high') {
        $stmt->execute(['medium_threshold' => $mediumThreshold]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStockStats($pdo, $lowThreshold, $mediumThreshold) {
    $sql = "SELECT 
                COUNT(CASE WHEN current_quantity <= :low_threshold THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN current_quantity > :low_threshold AND current_quantity < :medium_threshold THEN 1 END) as medium_stock_count,
                COUNT(CASE WHEN current_quantity >= :medium_threshold THEN 1 END) as high_stock_count,
                COUNT(*) as total_items
            FROM items";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['low_threshold' => $lowThreshold, 'medium_threshold' => $mediumThreshold]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to determine stock level
function getStockLevel($quantity, $lowThreshold, $mediumThreshold) {
    if ($quantity <= $lowThreshold) {
        return 'low';
    } elseif ($quantity < $mediumThreshold) {
        return 'medium';
    } else {
        return 'high';
    }
}
// Function to get low stock items
function getLowStockItems($pdo, $lowThreshold) {
    $sql = "SELECT i.item_id, i.item_code, i.item_name, i.current_quantity, i.unit
            FROM items i
            WHERE i.current_quantity <= :low_threshold
            ORDER BY i.current_quantity ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['low_threshold' => $lowThreshold]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Function to calculate suggested order quantity
function calculateOrderQuantity($currentStock, $threshold) {
    $suggestedStock = $threshold * 5; // Order 5 times the threshold
    return max($suggestedStock - $currentStock, $threshold);
}

// Get filter from URL parameter
$currentFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all';


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
// Get items based on current filter
$items = getAllItems($pdo, $low_stock_threshold, $medium_stock_threshold, $currentFilter);
$stats = getStockStats($pdo, $low_stock_threshold, $medium_stock_threshold);
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
        
        /* Enhanced Filter Styles */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 15px;
        }

        .filter-label {
            font-weight: 600;
            color: #495057;
            margin-right: 10px;
            font-size: 14px;
        }

        .filter-btn {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: #495057;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .filter-btn.active {
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .filter-btn.active::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255,255,255,0.5);
        }

        .filter-btn-all.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .filter-btn-low.active {
            background: linear-gradient(135deg, #ff4d57 0%, #c44569 100%);
        }

        .filter-btn-medium.active {
            background: linear-gradient(135deg, #ffa502 0%, #ff7675 100%);
        }

        .filter-btn-high.active {
            background: linear-gradient(135deg, #2ed573 0%, #1dd1a1 100%);
        }

        .filter-count {
            margin-left: 8px;
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }

        .filter-btn.active .filter-count {
            background: rgba(255,255,255,0.2);
        }

        /* Threshold Indicator */
        .threshold-indicator {
            margin-top: 20px;
        }

        .threshold-scale {
            height: 30px;
            background: #f5f5f5;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
        }

        .threshold-range {
            position: absolute;
            height: 100%;
            top: 0;
        }

        .low-range {
            background: linear-gradient(to right, #ff4d57, #ff7675);
            left: 0;
        }

        .medium-range {
            background: linear-gradient(to right, #ffa502, #ffcc00);
            left: 0;
        }

        .high-range {
            background: linear-gradient(to right, #2ed573, #1dd1a1);
            left: 0;
        }

        .threshold-mark {
            position: absolute;
            top: -20px;
            transform: translateX(-50%);
            font-size: 11px;
            font-weight: 600;
            color: #555;
            white-space: nowrap;
        }

        .threshold-mark::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 2px;
            height: 10px;
            background: currentColor;
            transform: translateX(-50%);
        }

        .threshold-mark.low {
            color: #ff4d57;
        }

        .threshold-mark.medium {
            color: #ffa502;
        }

        .threshold-mark.high {
            color: #2ed573;
        }

        /* Current Filter Display */
        .current-filter {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .filter-header {
            font-size: 15px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-header i {
            color: #667eea;
        }

        .filter-highlight {
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .filter-highlight.all {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
        }

        .filter-highlight.low {
            background: rgba(255, 77, 87, 0.2);
            color: #ff4d57;
        }

        .filter-highlight.medium {
            background: rgba(255, 165, 2, 0.2);
            color: #ffa502;
        }

        .filter-highlight.high {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }

        .filter-details {
            font-size: 13px;
            color: #6c757d;
        }

        .filter-percentage {
            font-weight: 600;
            color: #495057;
        }

        .stock-medium {
            background: rgba(255, 165, 2, 0.1) !important;
            border-left: 4px solid #ffa502;
        }
        
        .stock-high {
            background: rgba(46, 213, 115, 0.1) !important;
            border-left: 4px solid #2ed573;
        }
        
        .badge-medium {
            background: linear-gradient(to right, #ffa502, #ff7675);
        }
        
        .badge-high {
            background: linear-gradient(to right, #2ed573, #1dd1a1);
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
            
            .filter-buttons {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-shopping-cart"></i> Purchase Order Management</h1>
                <p>Complete Inventory Overview with Stock Level Filtering</p>
            </div>
            
            <div class="stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_items']; ?></div>
                        <div class="stat-label">Total Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['low_stock_count']; ?></div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['medium_stock_count']; ?></div>
                        <div class="stat-label">Medium Stock Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['high_stock_count']; ?></div>
                        <div class="stat-label">High Stock Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_unique(array_column($items, 'supplier_name'))); ?></div>
                        <div class="stat-label">Suppliers</div>
                    </div>
                </div>
            </div>
            
            <div class="main-content">
                <!-- Enhanced Filter Controls -->
                <div class="filter-container">
                    <div class="filter-buttons">
                        <span class="filter-label"><i class="fas fa-filter"></i> Filter by Stock Level:</span>
                        
                        <!-- All Items Filter -->
                        <a href="?filter=all" class="filter-btn filter-btn-all <?php echo $currentFilter == 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> All Items
                            <span class="filter-count"><?php echo $stats['total_items']; ?></span>
                        </a>
                        
                        <!-- Low Stock Filter -->
                        <a href="?filter=low" class="filter-btn filter-btn-low <?php echo $currentFilter == 'low' ? 'active' : ''; ?>">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock
                            <span class="filter-count"><?php echo $stats['low_stock_count']; ?></span>
                        </a>
                        
                        <!-- Medium Stock Filter -->
                        <a href="?filter=medium" class="filter-btn filter-btn-medium <?php echo $currentFilter == 'medium' ? 'active' : ''; ?>">
                            <i class="fas fa-minus-circle"></i> Medium Stock
                            <span class="filter-count"><?php echo $stats['medium_stock_count']; ?></span>
                        </a>
                        
                        <!-- High Stock Filter -->
                        <a href="?filter=high" class="filter-btn filter-btn-high <?php echo $currentFilter == 'high' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i> High Stock
                            <span class="filter-count"><?php echo $stats['high_stock_count']; ?></span>
                        </a>
                    </div>
                    
                    <!-- Visual Threshold Indicator -->
                    <div class="threshold-indicator">
                        <div class="threshold-scale">
                            <div class="threshold-mark low" style="left: <?php echo ($low_stock_threshold/$medium_stock_threshold)*100; ?>%">
                                <span>Low (≤<?php echo $low_stock_threshold; ?>)</span>
                            </div>
                            <div class="threshold-mark medium" style="left: 100%">
                                <span>High (≥<?php echo $medium_stock_threshold; ?>)</span>
                            </div>
                            <div class="threshold-range low-range" style="width: <?php echo ($low_stock_threshold/$medium_stock_threshold)*100; ?>%"></div>
                            <div class="threshold-range medium-range" style="width: <?php echo 100 - ($low_stock_threshold/$medium_stock_threshold)*100; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Enhanced Current Filter Display -->
                <div class="current-filter">
                    <div class="filter-header">
                        <i class="fas fa-eye"></i>
                        <strong>Current View:</strong> 
                        <?php
                        switch($currentFilter) {
                            case 'low':
                                echo "<span class='filter-highlight low'>Low Stock Items</span> (≤ {$low_stock_threshold} units)";
                                break;
                            case 'medium':
                                echo "<span class='filter-highlight medium'>Medium Stock Items</span> (" . ($low_stock_threshold + 1) . " - " . ($medium_stock_threshold - 1) . " units)";
                                break;
                            case 'high':
                                echo "<span class='filter-highlight high'>High Stock Items</span> (≥ {$medium_stock_threshold} units)";
                                break;
                            default:
                                echo "<span class='filter-highlight all'>All Items</span>";
                                break;
                        }
                        ?>
                    </div>
                    <div class="filter-details">
                        Showing <?php echo count($items); ?> items out of <?php echo $stats['total_items']; ?> total
                        <?php if($currentFilter != 'all'): ?>
                            <span class="filter-percentage">
                                (<?php echo round((count($items)/$stats['total_items'])*100); ?>% of inventory)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($items)): ?>
                    <form method="POST" action="">
                        <div class="form-section">
                            <div class="form-controls">
                                <div class="threshold-control">
                                    <label for="threshold"><i class="fas fa-chart-line"></i> Low Stock Threshold:</label>
                                    <input type="number" id="threshold" name="threshold" value="<?php echo $low_stock_threshold; ?>" min="1" max="100">
                                </div>
                                <div class="threshold-control">
                                    <label for="medium_threshold"><i class="fas fa-chart-bar"></i> Medium Stock Threshold:</label>
                                    <input type="number" id="medium_threshold" name="medium_threshold" value="<?php echo $medium_stock_threshold; ?>" min="1" max="200">
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
                                            <th>Unit Size</th>
                                            <th>Current Stock</th>
                                            <th>Status</th>
                                            <th>Order Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total Cost</th>
                                            <th>Supplier</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $stockLevel = getStockLevel($item['current_quantity'], $low_stock_threshold, $medium_stock_threshold);
                                            $stockClass = 'stock-' . $stockLevel;
                                            $badgeClass = 'badge-' . $stockLevel;
                                            $statusText = strtoupper($stockLevel);
                                            
                                            // Only calculate order quantity for low stock items
                                            $suggestedQty = $stockLevel == 'low' ? 
                                                calculateOrderQuantity($item['current_quantity'], $low_stock_threshold) : 0;
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
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <input type="number" name="quantities[<?php echo $item['item_id']; ?>]" 
                                                           value="<?php echo $suggestedQty; ?>" min="0" class="quantity-input">
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
                        <i class="fas fa-info-circle"></i>
                        <h2>No Items Found</h2>
                        <p>No items match the current filter criteria.</p>
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