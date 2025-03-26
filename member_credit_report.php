<?php
// Database configuration
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

// Set charset to utf8mb4 for proper encoding
$conn->set_charset("utf8mb4");

// Include FPDF library
require('fpdf/fpdf.php');

class PDF extends FPDF {
    private $title;
    private $organization;
    
    function __construct($orientation='P', $unit='mm', $size='A4') {
        parent::__construct($orientation, $unit, $size);
        $this->title = "Member Credit Balance Report";
        $this->organization = "Your Cooperative Name";
    }
    
    // Page header
    function Header() {
        // Logo
        $this->Image('logo.png', 10, 6, 30); // Replace with your logo path
        
        // Organization Name
        $this->SetFont('Arial','B',16);
        $this->Cell(0, 10, $this->organization, 0, 1, 'C');
        
        // Report Title
        $this->SetFont('Arial','B',14);
        $this->Cell(0, 10, $this->title, 0, 1, 'C');
        
        // Report Date
        $this->SetFont('Arial','',10);
        $this->Cell(0, 5, 'Generated on: ' . date('F j, Y, g:i a'), 0, 1, 'C');
        
        // Line break
        $this->Ln(10);
        
        // Table header - Updated with consistent column widths
        $this->SetFont('Arial','B',10);
        $this->SetFillColor(58, 83, 155); // Dark blue header
        $this->SetTextColor(255);
        $this->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $this->Cell(55, 8, 'Member Name', 1, 0, 'L', true);
        $this->Cell(30, 8, 'Coop Number', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Credit Limit', 1, 0, 'R', true);
        $this->Cell(30, 8, 'Balance', 1, 0, 'R', true);
        $this->Cell(30, 8, 'Available', 1, 1, 'R', true);
        
        // Reset text color
        $this->SetTextColor(0);
    }
    
    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(100);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Confidential - For internal use only', 0, 0, 'R');
    }
    
    // Colored table row - Updated to match header widths exactly
    function fillRow($data) {
        $this->SetFont('Arial','',9);
        
        // Set fill color based on balance
        if ($data['current_credit_balance'] < 0) {
            $this->SetFillColor(255, 220, 220); // Light red for negative
        } elseif ($data['current_credit_balance'] == 0) {
            $this->SetFillColor(240, 240, 240); // Light gray for zero
        } else {
            $this->SetFillColor(220, 255, 220); // Light green for positive
        }
        
        // Set text color based on balance
        if ($data['current_credit_balance'] < 0) {
            $this->SetTextColor(200, 0, 0); // Dark red for negative
        } elseif ($data['current_credit_balance'] == 0) {
            $this->SetTextColor(100); // Gray for zero
        }
        
        $available = $data['credit_limit'] - $data['current_credit_balance'];
        
        // Column widths exactly match the header
        $this->Cell(15, 7, $data['id'], 'LR', 0, 'C', true);
        $this->Cell(55, 7, $data['full_name'], 'LR', 0, 'L', true);
        $this->Cell(30, 7, $data['coop_number'], 'LR', 0, 'C', true);
        $this->Cell(30, 7, number_format($data['credit_limit'], 2), 'LR', 0, 'R', true);
        $this->Cell(30, 7, number_format($data['current_credit_balance'], 2), 'LR', 0, 'R', true);
        $this->Cell(30, 7, number_format($available, 2), 'LR', 1, 'R', true);
        
        // Reset colors
        $this->SetFillColor(255);
        $this->SetTextColor(0);
    }
}

// Check if PDF generation is requested
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // Create PDF instance
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Fetch member data
    $sql = "SELECT id, full_name, coop_number, credit_limit, current_credit_balance 
            FROM members 
            ORDER BY full_name";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $pdf->fillRow($row);
        }
        
        // Add totals row - Updated to match column widths
        $pdf->SetFont('Arial','B',10);
        $pdf->SetFillColor(220, 220, 220); // Light gray for totals
        $pdf->Cell(100, 7, 'TOTALS', 'LTB', 0, 'R', true); // 15+55+30=100
        
        $total_limit = $conn->query("SELECT SUM(credit_limit) as total FROM members")->fetch_assoc()['total'];
        $total_balance = $conn->query("SELECT SUM(current_credit_balance) as total FROM members")->fetch_assoc()['total'];
        $total_available = $total_limit - $total_balance;
        
        $pdf->Cell(30, 7, number_format($total_limit, 2), 'TB', 0, 'R', true);
        $pdf->Cell(30, 7, number_format($total_balance, 2), 'TB', 0, 'R', true);
        $pdf->Cell(30, 7, number_format($total_available, 2), 'TRB', 1, 'R', true);
    } else {
        $pdf->Cell(0, 10, 'No member data found', 1, 1, 'C');
    }
    
    // Output PDF
    $pdf->Output('D', 'Member_Credit_Report_'.date('Ymd_His').'.pdf');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Credit Balance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3a539b;
            --secondary-color: #1f3a93;
            --accent-color: #f89406;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --negative-color: #d9534f;
            --positive-color: #5cb85c;
            --neutral-color: #5bc0de;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 30px auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .report-header h2 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .report-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .report-logo {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            max-height: 50px;
        }
        
        .report-body {
            padding: 25px;
        }
        
        .action-buttons {
            margin-bottom: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-pdf {
            background-color: var(--negative-color);
            border-color: var(--negative-color);
        }
        
        .btn-print {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .table-report {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }
        
        .table-report thead th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 15px;
            font-weight: 600;
        }
        
        /* Updated column width styles to match PDF */
        .table-report th:nth-child(1),
        .table-report td:nth-child(1) {
            width: 5%;
        }
        
        .table-report th:nth-child(2),
        .table-report td:nth-child(2) {
            width: 35%;
        }
        
        .table-report th:nth-child(3),
        .table-report td:nth-child(3) {
            width: 15%;
        }
        
        .table-report th:nth-child(4),
        .table-report td:nth-child(4),
        .table-report th:nth-child(5),
        .table-report td:nth-child(5),
        .table-report th:nth-child(6),
        .table-report td:nth-child(6) {
            width: 15%;
        }
        
        .table-report tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table-report tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .table-report tbody tr:hover {
            background-color: #f1f1f1;
        }
        
        .table-report td {
            padding: 12px 15px;
        }
        
        .negative-balance {
            color: var(--negative-color);
            font-weight: 600;
        }
        
        .positive-balance {
            color: var(--positive-color);
        }
        
        .zero-balance {
            color: var(--neutral-color);
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .report-footer {
            padding: 15px 25px;
            background-color: var(--light-gray);
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }
        
        @media print {
            body {
                background: none;
                padding: 0;
            }
            
            .report-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .no-print, .action-buttons {
                display: none !important;
            }
            
            .table-report thead th {
                background-color: #3a539b !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <img src="images/logo.jpeg" alt="Logo" class="report-logo no-print">
            <h2>Member Credit Balance Report</h2>
            <p>Generated on <?php echo date('F j, Y, g:i a'); ?></p>
        </div>
        
        <div class="report-body" style="background-color:rgb(200, 240, 253);">
            <div class="action-buttons no-print">
                <a href="?export=pdf" class="btn btn-pdf text-black">
                    <i class="fas fa-file-pdf"></i> Export as PDF
                </a>
                <button onclick="window.print()" class="btn btn-print text-white">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table-report">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member Name</th>
                            <th class="text-center">Coop Number</th>
                            <th class="text-right">Credit Limit</th>
                            <th class="text-right">Balance</th>
                            <th class="text-right">Available Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT id, full_name, coop_number, credit_limit, current_credit_balance 
                                FROM members 
                                ORDER BY full_name";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $available = $row['credit_limit'] - $row['current_credit_balance'];
                                $balance_class = '';
                                
                                if ($row['current_credit_balance'] < 0) {
                                    $balance_class = 'negative-balance';
                                } elseif ($row['current_credit_balance'] == 0) {
                                    $balance_class = 'zero-balance';
                                } else {
                                    $balance_class = 'positive-balance';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['coop_number']); ?></td>
                                    <td class="text-right"><?php echo number_format($row['credit_limit'], 2); ?></td>
                                    <td class="text-right <?php echo $balance_class; ?>">
                                        <?php echo number_format($row['current_credit_balance'], 2); ?>
                                    </td>
                                    <td class="text-right"><?php echo number_format($available, 2); ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="6" class="text-center">No member data found</td></tr>';
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">TOTALS</th>
                            <th class="text-right"><?php 
                                $total_limit = $conn->query("SELECT SUM(credit_limit) as total FROM members")->fetch_assoc()['total'];
                                echo number_format($total_limit, 2); 
                            ?></th>
                            <th class="text-right"><?php 
                                $total_balance = $conn->query("SELECT SUM(current_credit_balance) as total FROM members")->fetch_assoc()['total'];
                                echo number_format($total_balance, 2); 
                            ?></th>
                            <th class="text-right"><?php 
                                echo number_format($total_limit - $total_balance, 2); 
                            ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <div class="report-footer no-print">
            <p>Confidential - For internal use only &bull; Generated by <?php echo $conn->host_info; ?></p>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>