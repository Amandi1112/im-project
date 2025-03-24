<?php
require('fpdf/fpdf.php'); // Make sure to provide the correct path to fpdf.php

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Supplier Balance Report', 0, 1, 'C');
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Invoice PDF Class
class InvoicePDF extends FPDF
{
    // Page header
    function Header()
    {
        // Logo - uncomment and adjust if you have a logo
        // $this->Image('logo.png', 10, 6, 30);
        
        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'INVOICE', 0, 1, 'C');
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Thank you for your business.', 0, 0, 'C');
        $this->Ln(5);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // Company Info
    function companyInfo($companyName, $address, $contact)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $companyName, 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 5, $address);
        $this->Cell(0, 5, $contact, 0, 1);
        $this->Ln(5);
    }
    
    // clerk Info
    function clerkInfo($supplier_name, $supplier_id)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Supplier Details:', 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Supplier Name: ' . $supplier_name, 0, 1);
        $this->Cell(0, 6, 'Supplier ID: ' . $supplier_id, 0, 1);
        $this->Ln(5);
    }
    
    // Invoice Details
    function invoiceDetails($invoice_number, $payment_date)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Invoice Details:', 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Invoice Number: ' . $invoice_number, 0, 1);
        $this->Cell(0, 6, 'Payment Date: ' . date('F j, Y', strtotime($payment_date)), 0, 1);
        $this->Ln(5);
    }
    
    // Payment Table
    function paymentTable($amount, $balance_due)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Payment Details:', 0, 1);
        
        // Table Header
        $this->SetFillColor(240, 240, 240);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(90, 10, 'Description', 1, 0, 'C', true);
        $this->Cell(90, 10, 'Amount', 1, 1, 'C', true);
        
        // Table Data
        $this->SetFont('Arial', '', 10);
        $this->Cell(90, 10, 'Payment', 1, 0, 'L');
        $this->Cell(90, 10, '$' . number_format($amount, 2), 1, 1, 'R');
        
        $this->Ln(5);
        
        // Total and Balance Due
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(180, 10, 'Total Payment: $' . number_format($amount, 2), 0, 1, 'R');
        $this->Cell(180, 10, 'Remaining Balance Due: $' . number_format($balance_due, 2), 0, 1, 'R');
    }
}

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

// Start session for CSRF protection
session_start();

// Create a CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch due amounts for each supplier
$dueAmounts = [];
$sql = "
    SELECT
        s.supplier_id,
        COALESCE(SUM(i.total_price), 0) - COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = s.supplier_id), 0) AS balance_due
    FROM
        supplier s
    LEFT JOIN
        items i ON s.supplier_id = i.supplier_id
    GROUP BY
        s.supplier_id
    HAVING
        balance_due > 0;
";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $dueAmounts[] = $row;
    }
}

// Fetch all payments
$payments = [];
$sql = "
    SELECT
        sp.id,
        sp.supplier_id,
        sp.amount,
        sp.payment_date
    FROM
        supplier_payments sp
    JOIN
        supplier s ON sp.supplier_id = s.supplier_id
    ORDER BY
        sp.payment_date DESC;
";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
}

// Function to generate invoice
function generateInvoice($conn, $supplier_id, $amount, $payment_date, $payment_id = null) {
    // Get supplier name
    $sql = "SELECT supplier_name FROM supplier WHERE supplier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier_name = '';
    if ($row = $result->fetch_assoc()) {
        $supplier_name = $row['supplier_name'];
    }
    
    // Calculate remaining balance
// Calculate remaining balance
// Calculate remaining balance
$sql = "
    SELECT
        COALESCE(SUM(i.total_price), 0) - COALESCE((SELECT SUM(amount) FROM supplier_payments WHERE supplier_id = ?), 0) AS balance_due
    FROM
        items i
    WHERE
        i.supplier_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $supplier_id, $supplier_id);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $supplier_id, $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $balance_due = 0;
    if ($row = $result->fetch_assoc()) {
        $balance_due = $row['balance_due'];
    }
    
    // Generate unique invoice number
    $invoice_number = 'INV-' . date('Ymd') . '-' . uniqid();
    
    // Create PDF
    $pdf = new InvoicePDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Add company info
    $pdf->companyInfo(
        'T&C co-op city shop',
        "Pahala Karawita, Karawita, Ratnapura, Sri Lanka",
        'Phone: (123) 456-7890 | Email: co-op@sanasa.com'
    );
    
    // Add supplier info
    $pdf->clerkInfo($supplier_name, $supplier_id);
    
    // Add invoice details
    $pdf->invoiceDetails($invoice_number, $payment_date);
    
    // Add payment table
    $pdf->paymentTable($amount, $balance_due);
    
    // Save invoice to file
    $invoice_dir = 'invoices';
    if (!file_exists($invoice_dir)) {
        mkdir($invoice_dir, 0777, true);
    }
    $invoice_file = $invoice_dir . '/' . $invoice_number . '.pdf';
    $pdf->Output('F', $invoice_file);
    
    // If payment_id is provided, check if an invoice already exists
    if ($payment_id) {
        $sql = "SELECT id FROM invoices WHERE payment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Store invoice info in database
            $sql = "INSERT INTO invoices (invoice_number, supplier_id, amount, payment_date, file_path, payment_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssdssi', $invoice_number, $supplier_id, $amount, $payment_date, $invoice_file, $payment_id);
            $stmt->execute();
        }
    } else {
        // Store invoice info in database
        $sql = "INSERT INTO invoices (invoice_number, supplier_id, amount, payment_date, file_path) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssdss', $invoice_number, $supplier_id, $amount, $payment_date, $invoice_file);
        $stmt->execute();
    }
    
    return [
        'invoice_number' => $invoice_number,
        'invoice_file' => $invoice_file
    ];
}

// Handle form submission for adding payments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "error|Invalid form submission. Please try again.";
    } else {
        $supplier_id = $_POST['supplier_id'];
        $amount = $_POST['amount'];
        $payment_date = $_POST['payment_date'];

        // Insert payment
        $sql = "INSERT INTO supplier_payments (supplier_id, amount, payment_date) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sds', $supplier_id, $amount, $payment_date);

        if ($stmt->execute()) {
            // Get the payment ID of the inserted record
            $payment_id = $conn->insert_id;
            
            // Generate invoice
            $invoice = generateInvoice($conn, $supplier_id, $amount, $payment_date, $payment_id);
            
            $message = "success|New payment added successfully. Invoice generated: " . $invoice['invoice_number'];
            
            // Generate new CSRF token to prevent resubmission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Redirect to prevent form resubmission on refresh
            header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message));
            exit;
        } else {
            $message = "error|Error: " . $stmt->error;
        }
    }
}

// Handler for downloading invoice
if (isset($_GET['download_invoice']) && isset($_GET['payment_id'])) {
    $payment_id = $_GET['payment_id'];
    
    // Get payment details
    $sql = "SELECT supplier_id, amount, payment_date FROM supplier_payments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Check if an invoice already exists for this payment
        $sql = "SELECT file_path FROM invoices WHERE payment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $invoice_result = $stmt->get_result();
        
        if ($invoice_result->num_rows > 0) {
            // Invoice exists, get the file path
            $invoice_row = $invoice_result->fetch_assoc();
            $invoice_file = $invoice_row['file_path'];
        } else {
            // Generate new invoice
            $invoice = generateInvoice($conn, $row['supplier_id'], $row['amount'], $row['payment_date'], $payment_id);
            $invoice_file = $invoice['invoice_file'];
        }
        
        // Redirect to download
        header("Location: download_invoice.php?file=" . urlencode($invoice_file));
        exit;
    }
}

// Handle form submission for generating the report
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_report'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "error|Invalid form submission. Please try again.";
    } else {
        $sql = "
            SELECT
                s.supplier_id,
                COALESCE(SUM(i.total_price), 0) AS total_purchased,
                COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = s.supplier_id), 0) AS total_paid,
                COALESCE(SUM(i.total_price), 0) - COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = s.supplier_id), 0) AS balance_due
            FROM
                supplier s
            LEFT JOIN
                items i ON s.supplier_id = i.supplier_id
            GROUP BY
                s.supplier_id;
        ";

        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            // Create new PDF document
            $pdf = new PDF();
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);

            // Add table header
            $pdf->Cell(40, 10, 'Supplier ID', 1);
            $pdf->Cell(45, 10, 'Total Purchased', 1);
            $pdf->Cell(45, 10, 'Total Paid', 1);
            $pdf->Cell(45, 10, 'Balance Due', 1);
            $pdf->Ln();

            // Add table data
            $pdf->SetFont('Arial', '', 12);
            while ($row = $result->fetch_assoc()) {
                $pdf->Cell(40, 10, $row['supplier_id'], 1);
                $pdf->Cell(45, 10, '$' . number_format($row['total_purchased'], 2), 1);
                $pdf->Cell(45, 10, '$' . number_format($row['total_paid'], 2), 1);
                $pdf->Cell(45, 10, '$' . number_format($row['balance_due'], 2), 1);
                $pdf->Ln();
            }

            // Generate new CSRF token to prevent resubmission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Output the PDF
            $pdf->Output('D', 'supplier_balance_report_' . date('Y-m-d') . '.pdf');
            exit;
        } else {
            $message = "error|No data found to generate the report";
        }
    }
}

// Process message from redirect
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Fetch suppliers for the dropdown
$suppliers = [];
$sql = "SELECT supplier_id, supplier_name FROM supplier";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Fetch invoices
$invoices = [];
$sql = "SELECT * FROM invoices ORDER BY payment_date DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Payment Management</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background:url("images/background60.jpg");
            font-family: 'Arial', sans-serif;
            margin: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin-top:100px;
            margin: auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: #d5731846;
            /* Changed color */
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: none;
            padding: 15px;
            border-radius: 5px;
            font-size: 16px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn-custom {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-custom:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .due-table, .payment-table {
            margin-top: 20px;
        }
    
        .home-btn {
            background-color: rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            margin: 0 5px; /* Space between buttons */
        }
        .home-btn:hover {
            background-color: #f28252;
        }
        .nav-btn-container {
            text-align: center; /* Center the navigation buttons */
        }
    </style>
</head>
<body>
    <h1 class="my-4 text-center" style="text-shadow: 2px 2px 5px lightblue; font-size: 30px;">Supplier Payment Management</h1>
    <div class="container">
        
        <?php if (isset($message)) {
            // Check if message contains pipe separator
            if (strpos($message, '|') !== false) {
                list($type, $msg) = explode("|", $message);
            } else {
                // Default to success if no type is specified
                $type = "success";
                $msg = $message;
            }
            echo "<div class='notification $type'>$msg</div>";
        } ?>
        <h2 class="my-4" style="text-shadow: 2px 2px 5px lightblue; font-size: 18px; font-weight:bold;">Due Amounts</h2>
        <table class="table table-bordered due-table" style="background-color:rgb(249, 228, 204); font-weight: bold;">
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Balance Due</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dueAmounts as $due): ?>
                    <tr>
                        <td><?php echo $due['supplier_id']; ?></td>
                        <td><?php echo '$' . number_format($due['balance_due'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <br>
    
    <div class="container">
        <h2 class="my-4" style="text-shadow: 2px 2px 5px lightblue; font-size: 18px; font-weight:bold;">Add Payment</h2>
        <form method="post" action="" id="paymentForm">
            <!-- Add CSRF token field -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="supplier_id">Supplier</label>
                <select class="form-control" id="supplier_name" name="supplier_name" required>
                    <option value="" disabled selected>Select a supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['supplier_name']; ?>">
                            <?php echo $supplier['supplier_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
               
                <br>
                <label for="amount">Amount</label>
                <input type="number" class="form-control" id="amount" name="amount" step="0.01" placeholder="Enter amount" required>
                <br>
                <div class="form-group">
                    <label for="payment_date">Payment Date</label>
                    <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                </div>
                <br>
                <button type="submit" name="add_payment" class="btn btn-custom btn-block">Add Payment & Generate Invoice</button>
            </div>
        </form>
    </div>
    
    <br>
    <div class="container">
        <h2 class="my-4" style="text-shadow: 2px 2px 5px lightblue; font-size: 18px; font-weight:bold;">Payment History</h2>
        <table class="table table-bordered payment-table">
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Amount Paid</th>
                    <th>Payment Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo $payment['supplier_id']; ?></td>
                        <td><?php echo '$' . number_format($payment['amount'], 2); ?></td>
                        <td><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></td>
                        <td>
                            <a href="?download_invoice=1&payment_id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-info">Generate Invoice</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add this section if you want to display generated invoices -->
    <?php if (!empty($invoices)): ?>
    <br>
    <div class="container">
        <h2 class="my-4" style="text-shadow: 2px 2px 5px lightblue; font-size: 18px; font-weight:bold;">Generated Invoices</h2>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Supplier ID</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo $invoice['invoice_number']; ?></td>
                        <td><?php echo $invoice['supplier_id']; ?></td>
                        <td><?php echo '$' . number_format($invoice['amount'], 2); ?></td>
                        <td><?php echo date('F j, Y', strtotime($invoice['payment_date'])); ?></td>
                        <td>
                            <a href="download_invoice.php?file=<?php echo urlencode($invoice['file_path']); ?>" class="btn btn-sm btn-primary">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            
        </table>
    </div>
    
    
    <?php endif; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.display = 'block';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 5000); // Increased display time to 5 seconds
            }

            const paymentForm = document.getElementById('paymentForm');
            paymentForm.addEventListener('submit', function(event) {
                const amount = document.getElementById('amount').value;
                if (amount <= 0) {
                    event.preventDefault();
                    alert('Amount must be greater than zero.');
                }
            });
        });
    </script>
</body>
</html>