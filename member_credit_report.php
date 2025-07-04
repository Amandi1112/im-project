<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite"; // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables
$id = $start_date = $end_date = "";
$error_message = "";
$report_generated = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = sanitize_input($_POST["id"]);
    $start_date = sanitize_input($_POST["start_date"]);
    $end_date = sanitize_input($_POST["end_date"]);
    
    if (empty($id) || empty($start_date) || empty($end_date)) {
        $error_message = "All fields are required";
    } else {
        // Generate report
        generateReport($conn, $id, $start_date, $end_date);
        $report_generated = true;
    }
}

function generateReport($conn, $id, $start_date, $end_date) {
    require('fpdf/fpdf.php');

    class CreditPDF extends FPDF {
        private $primaryColor = array(41, 128, 185);
        private $successColor = array(72, 187, 120);
        private $warningColor = array(255, 193, 7);
        private $dangerColor = array(255, 100, 100);
        private $headerColor = array(54, 123, 180);
        private $lightColor = array(224, 235, 255);
        private $reportStartDate;
        private $reportEndDate;
        
        // Add constructor to receive dates
        function __construct($start_date, $end_date, $orientation = 'P') {
            parent::__construct($orientation);
            $this->reportStartDate = $start_date;
            $this->reportEndDate = $end_date;
        }
        
        function Header() {
            // Header background
            $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
            $this->Rect(0, 0, $this->GetPageWidth(), 50, 'F');
            
            // Add logo if exists
            if (file_exists('C:/xampp/htdocs/project/images/logo.jpeg')) {
                $this->Image('C:/xampp/htdocs/project/images/logo.jpeg', 10, 5, 30);
            }
            
            // Company information
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 18);
            $this->Cell(0, 8, 'T&C co-op City shop', 0, 1, 'C');
            $this->SetFont('Arial', '', 11);
            $this->Cell(0, 6, 'Pahala Karawita, Karawita, Ratnapura, Sri Lanka', 0, 1, 'C');
            $this->Cell(0, 6, 'Phone: +94 11 2345678 | Email: co_op@sanasa.com', 0, 1, 'C');
            
            // Report title
            $this->SetFont('Arial','B',16);
            $this->Cell(0,15,'MEMBER CREDIT REPORT',0,1,'C');
            $this->Ln(5);
            
            // Report period - now using the class properties
            $this->SetFont('Arial','',10);
            $period = "From: " . date('M j, Y', strtotime($this->reportStartDate)) . 
                     " To: " . date('M j, Y', strtotime($this->reportEndDate));
            $this->Cell(0,5,'Period: ' . $period,0,1,'C');
            
            // Horizontal line
            $this->Line(10, $this->GetY()+5, $this->GetPageWidth()-10, $this->GetY()+5);
            $this->Ln(10);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,5,'Confidential - For internal use only',0,0,'L');
            $this->Cell(0,5,'Page '.$this->PageNo().'/{nb}',0,0,'R');
        }
        
        function MemberDetails($member) {
            $this->SetFont('Arial','B',12);
            $this->Cell(0,8,'MEMBER DETAILS',0,1,'L');
            $this->SetFont('Arial','',10);
            
            $this->Cell(50,7,'Member ID:',0,0);
            $this->Cell(0,7,$member['id'],0,1);
            
            $this->Cell(50,7,'Full Name:',0,0);
            $this->Cell(0,7,$member['full_name'],0,1);
            
            $this->Cell(50,7,'Bank ID:',0,0);
            $this->Cell(0,7,$member['bank_membership_number'],0,1);
            
            $this->Cell(50,7,'NIC:',0,0);
            $this->Cell(0,7,$member['nic'],0,1);
            
            $this->Cell(50,7,'Phone:',0,0);
            $this->Cell(0,7,$member['telephone_number'],0,1);
            
            $this->Ln(10);
        }
        
        function CreditSummary($credit_limit, $total_spent, $available_credit) {
            $this->SetFont('Arial','B',12);
            $this->Cell(0,8,'CREDIT SUMMARY',0,1,'L');
            $this->SetFont('Arial','',10);
            
            $labelWidth = 70;
            $valueX = 100;
            
            $this->Cell($labelWidth,7,'Credit Limit:',0,0);
            $this->SetX($valueX);
            $this->Cell(0,7,'Rs. ' . number_format($credit_limit, 2),0,1);
            
            $this->Cell($labelWidth,7,'Total Purchases:',0,0);
            $this->SetX($valueX);
            $this->Cell(0,7,'Rs. ' . number_format($total_spent, 2),0,1);
            
            $this->SetFont('Arial','B',10);
            $this->SetFillColor($this->successColor[0], $this->successColor[1], $this->successColor[2]);
            $this->Cell($labelWidth,7,'Available Credit:',0,0);
            $this->SetX($valueX);
            $this->Cell(0,7,'Rs. ' . number_format($available_credit, 2),1,1,'L',true);
            
            $this->Ln(10);
        }
        
        function PurchaseTable($purchases, $total_spent) {
            $this->SetFont('Arial','B',12);
            $this->Cell(0,8,'PURCHASE HISTORY',0,1,'L');
            
            if (empty($purchases)) {
                $this->SetFont('Arial','I',10);
                $this->Cell(0,8,'No purchases found within the selected date range.',0,1,'L');
                return;
            }
            
            // Table header
            $this->SetFillColor($this->headerColor[0], $this->headerColor[1], $this->headerColor[2]);
            $this->SetTextColor(255);
            $this->SetFont('Arial','B',9);
            $this->SetDrawColor($this->headerColor[0], $this->headerColor[1], $this->headerColor[2]);
            $this->SetLineWidth(0.3);
            
            // Updated column widths with narrower item name column and added unit size column
            $w = array(25, 30, 40, 20, 20, 30, 30); // Column widths: Date, Item Code, Item Name, Unit Size, Qty, Unit Price, Total
            $headers = array('Date', 'Item Code', 'Item Name','Qty', 'Unit Size', 'Unit Price', 'Total');
            
            for($i=0; $i<count($headers); $i++) {
                $this->Cell($w[$i],7,$headers[$i],1,0,'C',true);
            }
            $this->Ln();
            
            // Table content
            $this->SetTextColor(0);
            $fill = false;
            
            foreach($purchases as $row) {
                // Alternate row colors
                $this->SetFillColor($fill ? $this->lightColor[0] : 255);
                $fill = !$fill;
                
                $this->Cell($w[0],6,$row['purchase_date'],'LR',0,'C',$fill);
                $this->Cell($w[1],6,$row['item_code'],'LR',0,'C',$fill);
                $this->Cell($w[2],6,$this->CellFitScale($w[2], 6, $row['item_name']),'LR',0,'L',$fill);
                 $this->Cell($w[4],6,$row['quantity'],'LR',0,'C',$fill);
                // Display unit size and type (e.g., "1kg" or "500ml")
                
                $unitType = isset($row['type']) ? $row['type'] : '';
                $unitSizeText =$unitType;
                $this->Cell($w[3],6,$unitSizeText,'LR',0,'C',$fill);
                
               
                $this->Cell($w[5],6,'Rs. ' . number_format($row['price_per_unit'], 2),'LR',0,'R',$fill);
                $this->Cell($w[6],6,'Rs. ' . number_format($row['total_price'], 2),'LR',0,'R',$fill);
                $this->Ln();
            }
            
            // Total row
            $this->SetFont('Arial','B',10);
            $this->Cell(array_sum($w)-$w[6],8,'Total','T',0,'R');
            $this->Cell($w[6],8,'Rs. ' . number_format($total_spent, 2),'T',1,'R');
        }
        
        function SignatureSection() {
            $this->Ln(15);
            
            $panelWidth = 85;
            $lineLength = 60;
            $startY = $this->GetY();
            
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
            
            $this->Cell($panelWidth, 6, 'Date: ___________________', 0, 1, 'L');
            
            // Bank Manager section (RIGHT) - moved further right
            $rightPanelX = 130; // Increased from previous value to move right
            $this->SetY($startY);
            $this->SetX($rightPanelX);
            
            $this->SetFont('Arial','B',10);
            $this->Cell($panelWidth, 6, 'Bank Manager:', 0, 1, 'L');
            $this->SetX($rightPanelX);
            $this->Ln($afterTitle);
            
            // Signature line
            $this->SetX($rightPanelX);
            $this->Cell($lineLength, 2, '', 'B', 1, 'L');
            $this->SetX($rightPanelX);
            $this->Ln($afterLine);
            
            // Signature label
            $this->SetX($rightPanelX);
            $this->Cell($panelWidth, 6, 'Signature', 0, 1, 'L');
            $this->SetX($rightPanelX);
            $this->Ln($afterSignature);
            
            // Name field
            $this->SetFont('Arial','I',9);
            $this->SetX($rightPanelX);
            $this->Cell($panelWidth, 6, 'Name: ___________________', 0, 1, 'L');
            $this->SetX($rightPanelX);
            $this->Ln($afterName);
            
            // Date field
            $this->SetX($rightPanelX);
            $this->Cell($panelWidth, 6, 'Date: ___________________', 0, 1, 'L');
            
            $this->Ln(10); // Final document spacing
        }
        
        // Helper function to fit text in cells
        function CellFitScale($w, $h, $txt, $border=0, $ln=0, $align='', $fill=false) {
            if($this->GetStringWidth($txt) <= $w) {
                return $txt;
            }
            
            $txt_length = strlen($txt);
            $ellipsis = '...';
            
            while($this->GetStringWidth($txt . $ellipsis) > $w) {
                $txt = substr($txt, 0, --$txt_length);
            }
            
            return $txt . $ellipsis;
        }
    }

    // Fetch member details
    $member_sql = "SELECT * FROM members WHERE id = ?";
    $stmt = $conn->prepare($member_sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $member_result = $stmt->get_result();
    
    if ($member_result->num_rows === 0) {
        echo "No member found with the provided ID";
        return;
    }
    
    $member = $member_result->fetch_assoc();
    
    // Fetch purchases with unit size and type information
    $purchases_sql = "SELECT p.*, i.item_name, i.item_code, i.unit_size, i.type 
                     FROM purchases p 
                     JOIN items i ON p.item_id = i.item_id 
                     WHERE p.member_id = ? 
                     AND p.purchase_date BETWEEN ? AND ?
                     ORDER BY p.purchase_date DESC";
    
    $stmt = $conn->prepare($purchases_sql);
    $stmt->bind_param("sss", $id, $start_date, $end_date);
    $stmt->execute();
    $purchases_result = $stmt->get_result();
    $purchases = $purchases_result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate total purchases amount only for this member
    $total_sql = "SELECT SUM(total_price) as total_spent
                 FROM purchases 
                 WHERE member_id = ? 
                 AND purchase_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($total_sql);
    $stmt->bind_param("sss", $id, $start_date, $end_date);
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_spent = $total_row['total_spent'] ?? 0;
    
    // Calculate available credit
    $available_credit = $member['credit_limit'] - $total_spent;
    
    // Generate PDF
    $pdf = new CreditPDF($start_date, $end_date, 'P');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Add content sections
    $pdf->MemberDetails($member);
    $pdf->CreditSummary($member['credit_limit'], $total_spent, $available_credit);
    $pdf->PurchaseTable($purchases, $total_spent);
    $pdf->SignatureSection();
    
    // Output PDF
    $pdf->Output('Member_Credit_Report_' . $member['id'] . '.pdf', 'D');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Credit Summary Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #edf2f7;
            --danger: #e53e3e;
            --danger-dark: #c53030;
            --success: #48bb78;
            --success-dark: #38a169;
            --warning: #ed8936;
            --warning-dark: #dd6b20;
            --info: #4299e1;
            --info-dark: #3182ce;
            --light: #f7fafc;
            --dark: #2d3748;
            --gray: #718096;
            --gray-light: #e2e8f0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: var(--dark);
            text-align: center;
            margin-bottom: 25px;
            font-weight: 600;
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
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 20px;
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
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 6px;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .btn {
            background: linear-gradient(to right, #28a745, #218838);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.4);
        }
        
        .error {
            color: var(--danger);
            margin-bottom: 20px;
            padding: 12px;
            background-color: rgba(229, 62, 62, 0.1);
            border-radius: 6px;
            border-left: 4px solid var(--danger);
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .member-select-container {
            position: relative;
        }
        
        #memberResults {
            position: absolute;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            background: white;
            border: 1px solid var(--gray-light);
            border-top: none;
            border-radius: 0 0 6px 6px;
            display: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .member-item {
            padding: 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .member-item:hover {
            background-color: var(--secondary);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="font-family: 'Poppins', sans-serif;">Member Credit Summary Report</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group member-select-container">
                <label for="id">Select Member:</label>
                <div style="position: relative;">
                    <input type="text" id="member_search" placeholder="Search by name or member ID" autocomplete="off">
                    <div id="memberResults"></div>
                    <div id="loadingIndicator" style="position: absolute; right: 10px; top: 10px; display: none;">
                        <div class="loading"></div>
                    </div>
                </div>
                <input type="hidden" id="id" name="id" value="<?php echo $id; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
            </div>
            
            <button type="submit" class="btn" style="font-family: 'Poppins', sans-serif;">Generate Report</button>
            
        </form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const memberSearch = document.getElementById('member_search');
            const memberIdInput = document.getElementById('id');
            const memberResults = document.getElementById('memberResults');
            const loadingIndicator = document.getElementById('loadingIndicator');
            
            // Set default dates (current month)
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            document.getElementById('start_date').valueAsDate = firstDay;
            document.getElementById('end_date').valueAsDate = lastDay;
            
            // Member search functionality
            memberSearch.addEventListener('input', function() {
                const query = this.value.trim();
                
                if (query.length < 2) {
                    memberResults.style.display = 'none';
                    return;
                }
                
                // Show loading indicator
                loadingIndicator.style.display = 'block';
                
                fetch('get_members.php?q=' + encodeURIComponent(query))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        memberResults.innerHTML = '';
                        
                        if (data.length > 0) {
                            data.forEach(member => {
                                const div = document.createElement('div');
                                div.className = 'member-item';
                                div.textContent = `${member.full_name} (ID: ${member.id})`;
                                div.dataset.id = member.id;
                                div.dataset.name = member.full_name;
                                
                                div.addEventListener('click', function() {
                                    memberIdInput.value = this.dataset.id;
                                    memberSearch.value = this.dataset.name;
                                    memberResults.style.display = 'none';
                                });
                                
                                memberResults.appendChild(div);
                            });
                            
                            memberResults.style.display = 'block';
                        } else {
                            const div = document.createElement('div');
                            div.className = 'member-item';
                            div.textContent = 'No members found';
                            memberResults.appendChild(div);
                            memberResults.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        memberResults.style.display = 'none';
                    })
                    .finally(() => {
                        loadingIndicator.style.display = 'none';
                    });
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!memberSearch.contains(e.target) && !memberResults.contains(e.target)) {
                    memberResults.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>