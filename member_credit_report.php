<?php
// Include FPDF library
require('fpdf/fpdf.php');

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
    
    // Fix this condition (was checking $error_messageid which doesn't exist)
    if (empty($id) || empty($start_date) || empty($end_date)) {
        $error_message = "All fields are required";
    } else {
        // Generate report
        generateReport($conn, $id, $start_date, $end_date);
        $report_generated = true;
    }
}

function generateReport($conn, $id, $start_date, $end_date) {
    // Define color scheme
    $primaryColor = array(102, 126, 234);   // #667eea
    $primaryDark = array(90, 103, 216);     // #5a67d8
    $successColor = array(72, 187, 120);    // #48bb78
    $darkColor = array(45, 55, 72);         // #2d3748
    $grayColor = array(113, 128, 150);      // #718096
    $grayLight = array(226, 232, 240);      // #e2e8f0

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
    
    // Fetch purchases within date range
    $purchases_sql = "SELECT p.*, i.item_name, i.item_code 
                 FROM purchases p 
                 JOIN items i ON p.item_id = i.item_id 
                 WHERE p.member_id = ? AND p.purchase_date BETWEEN ? AND ?
                 ORDER BY p.purchase_date DESC";
    
    $stmt = $conn->prepare($purchases_sql);
    $stmt->bind_param("iss", $id, $start_date, $end_date);
    $stmt->execute();
    $purchases_result = $stmt->get_result();
    
    // Calculate total purchases amount
    $total_sql = "SELECT SUM(total_price) as total_spent
    FROM purchases 
    WHERE member_id = ? AND purchase_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($total_sql);
    $stmt->bind_param("iss", $id, $start_date, $end_date);
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_spent = $total_row['total_spent'] ?? 0;
    
    // Calculate available credit
    $available_credit = $member['credit_limit'] - $total_spent;
    
    // Create PDF in portrait
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
    $pdf->Cell(0, 8, 'T&C CO-OP CITY SHOP - KARAWITA', 0, 1, 'L');
    
    // Report info box
    $pdf->SetFillColor($primaryDark[0], $primaryDark[1], $primaryDark[2]);
    $pdf->Rect(140, 12, 60, 16, 'F');
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetXY(140, 12);
    $pdf->Cell(60, 8, 'CREDIT REPORT', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY(140, 18);
    $pdf->Cell(60, 6, date('F j, Y'), 0, 1, 'C');
    
    // Shop contact info
    $pdf->SetTextColor(255);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY(15, 22);
    $pdf->Cell(0, 5, 'Karawita | Tel: +94 11 2345678 | Email: accounts@coopshop.lk', 0, 1, 'L');
    
    // ========== MEMBER DETAILS SECTION ========== //
    $pdf->SetY(40);
    $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
    
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'MEMBER DETAILS', 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    
    $pdf->Cell(50, 7, 'Member ID:', 0, 0);
    $pdf->Cell(0, 7, $member['id'], 0, 1);
    
    $pdf->Cell(50, 7, 'Full Name:', 0, 0);
    $pdf->Cell(0, 7, $member['full_name'], 0, 1);
    
    $pdf->Cell(50, 7, 'Bank ID:', 0, 0);
    $pdf->Cell(0, 7, $member['bank_membership_number'], 0, 1);
    
    $pdf->Cell(50, 7, 'NIC:', 0, 0);
    $pdf->Cell(0, 7, $member['nic'], 0, 1);
    
    $pdf->Cell(50, 7, 'Phone:', 0, 0);
    $pdf->Cell(0, 7, $member['telephone_number'], 0, 1);
    
    $pdf->Ln(10);
    
    // ========== CREDIT SUMMARY SECTION (RIGHT ALIGNED) ========== //
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'CREDIT SUMMARY', 0, 1, 'L');
    
    // Right-align the credit summary details by adjusting X position
    $labelWidth = 70;
    $valueX = 100; // Starting X position for values (moved right)
    
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell($labelWidth, 7, 'Credit Limit:', 0, 0);
    $pdf->SetX($valueX);
    $pdf->Cell(0, 7, 'Rs. ' . number_format($member['credit_limit'], 2), 0, 1);
    
    $pdf->Cell($labelWidth, 7, 'Total Purchases (' . date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date)) . '):', 0, 0);
    $pdf->SetX($valueX);
    $pdf->Cell(0, 7, 'Rs. ' . number_format($total_spent, 2), 0, 1);
    
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
    $pdf->Cell($labelWidth, 7, 'Available Credit:', 0, 0);
    $pdf->SetX($valueX);
    $pdf->Cell(0, 7, 'Rs. ' . number_format($available_credit, 2), 1, 1, 'L', true);
    
    $pdf->Ln(10);
    
    // ========== PURCHASE HISTORY SECTION ========== //
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'PURCHASE HISTORY', 0, 1, 'L');
    
    if ($purchases_result->num_rows > 0) {
        // Table header
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255);
        $pdf->SetFont('Helvetica', 'B', 10);
        
        $pdf->Cell(25, 8, 'Date', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Item Code', 1, 0, 'C', true);
        $pdf->Cell(60, 8, 'Item Name', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Unit Price', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Total', 1, 1, 'C', true);
        
        // Table content
        $pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
        $pdf->SetFont('Helvetica', '', 9);
        
        $fill = false;
        while ($row = $purchases_result->fetch_assoc()) {
            $pdf->SetFillColor($fill ? $grayLight[0] : 255);
            $pdf->Cell(25, 7, $row['purchase_date'], 1, 0, 'C', $fill);
            $pdf->Cell(30, 7, $row['item_code'], 1, 0, 'C', $fill);
            $pdf->Cell(60, 7, $row['item_name'], 1, 0, 'L', $fill);
            $pdf->Cell(20, 7, $row['quantity'], 1, 0, 'C', $fill);
            $pdf->Cell(30, 7, 'Rs. ' . number_format($row['price_per_unit'], 2), 1, 0, 'R', $fill);
            $pdf->Cell(30, 7, 'Rs. ' . number_format($row['total_price'], 2), 1, 1, 'R', $fill);
            $fill = !$fill;
        }
        
        // Total row
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(135, 8, 'Total', 1, 0, 'R');
        $pdf->Cell(60, 8, 'Rs. ' . number_format($total_spent, 2), 1, 1, 'R');
    } else {
        $pdf->SetFont('Helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'No purchases found within the selected date range.', 0, 1, 'L');
    }
    
    // ========== SIGNATURE SECTION ========== //
$pdf->SetY($pdf->GetY() + 15); // Add space before signatures

// Define variables for better alignment
$pageWidth = $pdf->GetPageWidth();
$leftColX = 20;
$rightColX = $pageWidth / 2 + 10;
$lineWidth = 80; // Width of signature line
$grayColor = array(128, 128, 128); // Gray color for lines

// Co-op City Staff Signature (left aligned)
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetX($leftColX);
$pdf->Cell($lineWidth, 8, 'Co-op City Staff Signature:', 0, 0, 'L');

// Bank Manager Signature (right aligned)
$pdf->SetX($rightColX);
$pdf->Cell($lineWidth, 8, 'Bank Manager Signature:', 0, 1, 'L');

// Space for signatures
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(0, 20, '', 0, 1); // Space for signatures

// Signature lines
$pdf->SetDrawColor($grayColor[0], $grayColor[1], $grayColor[2]);
$pdf->Line($leftColX, $pdf->GetY() - 10, $leftColX + $lineWidth, $pdf->GetY() - 10); // Co-op line
$pdf->Line($rightColX, $pdf->GetY() - 10, $rightColX + $lineWidth, $pdf->GetY() - 10); // Bank line

// Add name/date fields under signature lines
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetX($leftColX);
$pdf->Cell($lineWidth/2, 5, 'Name:', 0, 0, 'L');
$pdf->SetX($rightColX);
$pdf->Cell($lineWidth/2, 5, 'Name:', 0, 1, 'L');

$pdf->SetX($leftColX);
$pdf->Cell($lineWidth/2, 5, 'Date:', 0, 0, 'L');
$pdf->SetX($rightColX);
$pdf->Cell($lineWidth/2, 5, 'Date:', 0, 1, 'L');

// Add space after signatures
$pdf->Ln(10);
    // ========== FOOTER SECTION ========== //
    $pdf->SetY(-20);
    $pdf->SetFont('Helvetica', 'I', 8);
    $pdf->SetTextColor($grayColor[0], $grayColor[1], $grayColor[2]);
    $pdf->Cell(0, 5, 'This is a computer generated report. Thank you for your business!', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Generated on ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Cell(0, 5, 'Page ' . $pdf->PageNo(), 0, 0, 'C');
    
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