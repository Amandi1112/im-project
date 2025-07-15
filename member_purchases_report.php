<?php
require('fpdf/fpdf.php');

class PDF extends FPDF {
    // Company header with logo
    function Header() {
        // Add logo (replace with your actual logo path)
        if (file_exists('C:/xampp/htdocs/project/images/logo.jpeg')) {
            $this->Image('C:/xampp/htdocs/project/images/logo.jpeg', 10, 8, 30);
        }
        
        // Company information
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,'T&C co-op City shop',0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,'Pahala Karawita, Karawita, Ratnapura, Sri Lanka',0,1,'C');
        $this->Cell(0,5,'Phone: +94 11 2345678 | Email: co_op@sanasa.com',0,1,'C');
        
        // Report title
        $this->SetFont('Arial','B',16);
        $this->Cell(0,15,'PURCHASE ITEMS REPORT',0,1,'C');        
       
        
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
    $this->SetFont('Arial','B',10);
    $this->SetFillColor(54, 123, 180);
    $this->SetTextColor(255);
    $this->SetDrawColor(54, 123, 180);
    $this->SetLineWidth(0.3);
    
    $header = array(
        'Purchase Date',
        'Item Name',
        'Supplier ID',
        'Qty',
        'Size',
        'Unit Price',
        'Total Price',
        'Expiry Date'
    );
    
    $w = array(27, 25, 25, 20, 25, 25, 25, 20);
    
    for($i=0; $i<count($header); $i++) {
        $this->Cell($w[$i],7,$header[$i],1,0,'C',true);
    }
    $this->Ln();
}

    
    // Table content with adjusted column sizes
    function TableContent($purchases) {
        $this->SetFont('Arial','',9);
        $this->SetTextColor(0);
        $this->SetFillColor(224, 235, 255); // Light blue alternate row
        $fill = false;
        
        // Use same widths as in TableHeader
        $w = array(27, 25, 25, 20, 25, 25, 25, 20);
        
        foreach($purchases as $purchase) {
            // Check if we need a new page
            if($this->GetY() > 250) {
                $this->AddPage();
                $this->TableHeader();
            }
            
            // Determine status and row color
            $status = $this->getExpirationStatus($purchase['expire_date']);
            $rowColor = $this->getStatusColor($status);
            
            if($rowColor) {
                $this->SetFillColor($rowColor[0], $rowColor[1], $rowColor[2]);
                $fill = true;
            } else {
                $fill = $fill ? false : true;
                $this->SetFillColor(255, 255, 255);
            }
            
            // Format dates
            $purchase_date = date('d M Y', strtotime($purchase['purchase_date']));
            $expire_date = !empty($purchase['expire_date']) ? date('d M Y', strtotime($purchase['expire_date'])) : 'N/A';
            
            // Get unit name
            $unitName = $this->getUnitName($purchase['unit']);
            
            // Cells with adjusted widths - using supplier_id instead of supplier_name
            $this->Cell($w[0],6,$purchase_date,'LR',0,'C',$fill);
    $this->Cell($w[1],6,$this->StringLimit($purchase['item_name'], 35),'LR',0,'L',$fill);
    $this->Cell($w[2],6,$purchase['supplier_id'],'LR',0,'C',$fill);
    $this->Cell($w[3],6,$purchase['quantity'] . ' units','LR',0,'C',$fill);
    $this->Cell($w[4],6,$purchase['unit_size'] . ' ' . $unitName,'LR',0,'C',$fill);
    $this->Cell($w[5],6,'Rs.'.number_format($purchase['price_per_unit'], 2),'LR',0,'R',$fill);
    $this->Cell($w[6],6,'Rs.'.number_format($purchase['total_price'], 2),'LR',0,'R',$fill);
    $this->Cell($w[7],6,$expire_date,'LR',0,'C',$fill);
           
            $this->Ln();
        }
        
        // Closing line
        $this->Cell(array_sum($w),0,'','T');
    }
    
    // Helper function to get proper unit name
    function getUnitName($unit) {
        $unitNames = [
            'g' => 'grams',
            'kg' => 'kilograms',
            'packets' => 'packets',
            'bottles' => 'bottles',
            'other' => 'units'
        ];
        
        return $unitNames[$unit] ?? 'units';
    }
    
    // Helper function to limit string length for better fit in portrait mode
    function StringLimit($string, $limit) {
        if(strlen($string) > $limit) {
            return substr($string, 0, $limit-3) . '...';
        }
        return $string;
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
        $this->Cell(0,6,$totalQuantity. ' ' . 'units',0,1,'L');
        
        $this->Cell(50,6,'Total Amount:',0,0,'L');
        $this->Cell(0,6,'Rs.'.number_format($totalAmount, 2),0,1,'L');
        
        $this->Ln(3.5);
        $this->SetFont('Arial','I',8);
        $this->Cell(0, 6, 'Report generated on: ' . date('F j, Y'), 0, 1, 'L');
        $this->Ln(3.5);
    }
    
    // Signature section
    function SignatureSection() {
        // Co-op City Staff signature
        $this->SetFont('Arial','B',10);
        $this->Cell(90, 5, 'Co-op City Staff:', 0, 0, 'L');
        $this->Cell(90, 5, 'Bank Manager:', 0, 1, 'L');
        
        $this->SetFont('Arial','',10);
        $this->Cell(90, 5, '', 0, 0, 'L');
        $this->Cell(90, 5, '', 0, 1, 'L');
        
        $this->SetFont('Arial','I',9);
        $this->Cell(90, 5, 'Signature: _________________________', 0, 0, 'L');
        $this->Cell(90, 5, 'Signature: _________________________', 0, 1, 'L');
        
        // Added line space between name and date
        $this->Ln(3.5);
        
        $this->Cell(90, 5, 'Date: ' . date('Y-m-d'), 0, 0, 'L');
        $this->Cell(90, 5, 'Date: _________________________', 0, 1, 'L');
    }
}

// Database connection and data retrieval
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

function getPurchaseDetails($conn, $start_date = '', $end_date = '', $supplier_filter = '', $item_filter = '') {
    $sql = "SELECT 
                ip.purchase_date, 
                ip.expire_date, 
                ip.quantity, 
                ip.price_per_unit, 
                ip.total_price, 
                i.item_name, 
                s.supplier_id,
                i.unit_size,
                COALESCE(ip.unit, i.unit) AS unit
            FROM item_purchases ip
            JOIN items i ON ip.item_id = i.item_id
            JOIN supplier s ON i.supplier_id = s.supplier_id";

    $conditions = []; $params = []; $types = '';
    if (!empty($start_date)) { $conditions[] = "ip.purchase_date >= ?"; $params[] = $start_date; $types .= 's'; }
    if (!empty($end_date)) { $conditions[] = "ip.purchase_date <= ?"; $params[] = $end_date; $types .= 's'; }
    if (!empty($supplier_filter)) { $conditions[] = "s.supplier_id = ?"; $params[] = $supplier_filter; $types .= 's'; }
    if (!empty($item_filter)) { $conditions[] = "i.item_id = ?"; $params[] = $item_filter; $types .= 's'; }
    
    if (!empty($conditions)) $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " ORDER BY ip.purchase_date DESC, ip.purchase_id DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $purchases = [];
    while($row = $result->fetch_assoc()) $purchases[] = $row;
    return $purchases;
}

// Get data with filters
$purchases = getPurchaseDetails(
    $conn, 
    $_GET['start_date'] ?? '', 
    $_GET['end_date'] ?? '', 
    $_GET['supplier_id'] ?? '', 
    $_GET['item_id'] ?? ''
);

// Calculate totals
$totalQuantity = array_sum(array_column($purchases, 'quantity'));
$totalAmount = array_sum(array_column($purchases, 'total_price'));

// Generate PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Build table
$pdf->TableHeader();
$pdf->TableContent($purchases);
$pdf->Summary($totalQuantity, $totalAmount, count($purchases));
$pdf->SignatureSection();

// Output PDF
$pdf->Output('Purchase_Report_'.date('Ymd_His').'.pdf', 'D');

// Close connection
$conn->close();
?>