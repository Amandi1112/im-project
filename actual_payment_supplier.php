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

require('fpdf/fpdf.php');

// Handle form submission for new purchases
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_purchase'])) {
        $supplier_id = $_POST['supplier_id'];
        $item_id = $_POST['item_id'];
        $quantity = $_POST['quantity'];
        $price_per_unit = $_POST['price_per_unit'];
        $purchase_date = $_POST['purchase_date'];
        $expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;

        // Calculate total price
        $total_price = $quantity * $price_per_unit;

        // Insert purchase record
        $stmt = $conn->prepare("INSERT INTO item_purchases (item_id, quantity, price_per_unit, total_price, purchase_date, expire_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiddss", $item_id, $quantity, $price_per_unit, $total_price, $purchase_date, $expire_date);

        if ($stmt->execute()) {
            // Update item quantity
            $update_stmt = $conn->prepare("UPDATE items SET current_quantity = current_quantity + ? WHERE item_id = ?");
            $update_stmt->bind_param("ii", $quantity, $item_id);
            $update_stmt->execute();

            header("Location: {$_SERVER['PHP_SELF']}?success=purchase");
            exit;
        } else {
            $error_message = "Error adding purchase: " . $conn->error;
        }
    }

    // Handle payment to supplier
    if (isset($_POST['make_payment'])) {
        // First, validate the supplier
        $supplier_name = $_POST['payment_supplier_id'];

        // Fetch the supplier ID based on the supplier name
        $supplier_stmt = $conn->prepare("SELECT supplier_id FROM supplier WHERE supplier_name = ?");
        $supplier_stmt->bind_param("s", $supplier_name);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();

        if ($supplier_result->num_rows > 0) {
            $supplier_row = $supplier_result->fetch_assoc();
            $supplier_id = $supplier_row['supplier_id'];

            $amount = $_POST['amount'];
            $payment_date = $_POST['payment_date'];

            $balance_query = $conn->prepare("
            SELECT
                COALESCE(
                    (SELECT SUM(ip.total_price)
                     FROM items i
                     JOIN item_purchases ip ON i.item_id = ip.item_id
                     WHERE i.supplier_id = ?),
                    0
                ) - COALESCE(
                    (SELECT SUM(sp.amount)
                     FROM supplier_payments sp
                     WHERE sp.supplier_id = ?),
                    0
                ) as balance
        ");
            $balance_query->bind_param("ss", $supplier_id, $supplier_id);
            $balance_query->execute();
            $result = $balance_query->get_result();
            $balance_row = $result->fetch_assoc();
            $current_balance = $balance_row['balance'];
            // Validate payment amount doesn't exceed balance
            if ($amount > $current_balance) {
                $error_message = "Payment amount (Rs. " . number_format($amount, 2) .
                                ") cannot exceed current balance (Rs. " .
                                number_format($current_balance, 2) . ")";
            } else {
                // Insert payment record
                $stmt = $conn->prepare("INSERT INTO supplier_payments (supplier_id, amount, payment_date) VALUES (?, ?, ?)");
                $stmt->bind_param("sds", $supplier_id, $amount, $payment_date);

                if ($stmt->execute()) {
                    header("Location: {$_SERVER['PHP_SELF']}?success=payment");
                    exit;
                } else {
                    $error_message = "Error recording payment: " . $conn->error;
                }
            }
        } else {
            $error_message = "Supplier not found. Please select a valid supplier.";
        }
    }
}

// Handle invoice generation
if (isset($_GET['generate_invoice'])) {
    $supplier_id  = $_GET['supplier_id'];
    $start_date   = $_GET['start_date'];
    $end_date     = $_GET['end_date'];

    // Generate unique invoice number
    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($supplier_id, 4, '0', STR_PAD_LEFT);

    // Get supplier details
    $supplier_query = $conn->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
    $supplier_query->bind_param("s", $supplier_id);
    $supplier_query->execute();
    $supplier_result = $supplier_query->get_result();
    $supplier        = $supplier_result->fetch_assoc();

    // Get purchases for the supplier within date range (use ip.supplier_id for accuracy)
    $purchase_query = $conn->prepare("
        SELECT ip.purchase_date, i.item_name, ip.quantity, ip.price_per_unit, ip.total_price
        FROM item_purchases ip
        JOIN items i ON ip.item_id = i.item_id
        WHERE ip.supplier_id = ? AND ip.purchase_date BETWEEN ? AND ?
        ORDER BY ip.purchase_date
    ");
    $purchase_query->bind_param("sss", $supplier_id, $start_date, $end_date);
    $purchase_query->execute();
    $purchases = $purchase_query->get_result()->fetch_all(MYSQLI_ASSOC);

    // Correctly calculate total purchases for this supplier in the date range
    $total_purchases_query = $conn->prepare("
        SELECT COALESCE(SUM(ip.total_price), 0) AS total_purchases
        FROM item_purchases ip
        WHERE ip.supplier_id = ? AND ip.purchase_date BETWEEN ? AND ?
    ");
    $total_purchases_query->bind_param("sss", $supplier_id, $start_date, $end_date);
    $total_purchases_query->execute();
    $total_purchases_result = $total_purchases_query->get_result()->fetch_assoc();
    $total_amount = $total_purchases_result['total_purchases'];

    // Get payments made to this supplier
    $payment_query = $conn->prepare("
        SELECT
            payment_date,
            amount
        FROM supplier_payments
        WHERE supplier_id = ? AND payment_date BETWEEN ? AND ?
        ORDER BY payment_date
    ");
    $payment_query->bind_param("sss", $supplier_id, $start_date, $end_date);
    $payment_query->execute();
    $payments       = $payment_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $total_payments = array_sum(array_column($payments, 'amount'));
    $balance        = $total_amount - $total_payments;

    // Generate PDF invoice with compact layout
   // Create PDF with new design matching the image
   $pdf = new FPDF('P', 'mm', 'A4');
   $pdf->AddPage();
   $pdf->SetMargins(10, 10, 10);
   $pdf->SetAutoPageBreak(true, 10);

   // Define colors
// Color scheme
$corporateBlue = [23, 37, 84];      // #172554
$accentBlue = [59, 130, 246];      // #3B82F6
$lightBlue = [239, 246, 255];      // #EFF6FF
$darkGray = [31, 41, 55];          // #1F2937
$mediumGray = [107, 114, 128];     // #6B7280
$lightGray = [243, 244, 246];      // #F3F4F6

// For compatibility with the rest of the code
$headerBlue = $corporateBlue;
   $lightGray = array(240, 240, 240);  // Light gray for alternate rows
   $darkBlue = array(41, 128, 185);    // Dark blue for titles
   $darkText = array(51, 51, 51);      // Dark text color

   // =============== HEADER SECTION ===============
   // Company name (top left) - LARGE BLUE HEADER
   $pdf->SetFont('Arial', 'B', 18);
   $pdf->SetTextColor($headerBlue[0], $headerBlue[1], $headerBlue[2]);
   $pdf->Cell(120, 10, 'T&C CO-OP CITY SHOP', 0, 0, 'L');

   // Statement title (top right)
   $pdf->SetFont('Arial', 'B', 18);
   $pdf->Cell(70, 10, 'INVOICE', 0, 1, 'R');

   // Company details (left)
   $pdf->SetFont('Arial', '', 10);
   $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
   $pdf->Cell(120, 5, 'Pahala Karawita, Karawita', 0, 0, 'L');

   // Statement number (right)
   $pdf->SetFont('Arial', '', 10);
   $pdf->Cell(70, 5, 'INVOICE NO #: ' . $invoice_number, 0, 1, 'R');

   // More company details
   $pdf->Cell(120, 5, 'Ratnapura, Sri Lanka', 0, 0, 'L');
   
   // Date
   $pdf->Cell(70, 5, 'Date: ' . date('d/m/Y'), 0, 1, 'R');

   // Phone and email
   $pdf->Cell(120, 5, 'Phone: +94 11 2345678 | Email: ', 0, 0, 'L');
   // Add a more attractive email display with a mailto link and icon
   $pdf->SetFont('Arial', 'B', 10);
   $pdf->SetTextColor(41, 128, 185); // Vibrant blue for email
   $pdf->Cell(0, 5, chr(64) . ' co_op@sanasa.com', 0, 0, 'R', false, 'mailto:co_op@sanasa.com');
   $pdf->SetFont('Arial', '', 10);
   $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
   
   // Period
   $pdf->Cell(70, 5, 'Period: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'R');

   // Draw horizontal line
   $pdf->Ln(5);
   $pdf->SetDrawColor(200, 200, 200);
   $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
   $pdf->Ln(7);

   // =============== BILL TO SECTION ===============
   // Bill To Header - Blue background
   $pdf->SetFillColor($headerBlue[0], $headerBlue[1], $headerBlue[2]);
   $pdf->SetTextColor(255, 255, 255);
   $pdf->SetFont('Arial', 'B', 12);
   $pdf->Cell(190, 8, 'BILL TO', 0, 1, 'L', true);
   $pdf->Ln(5);

   // Supplier info
   $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
   $pdf->SetFont('Arial', 'B', 12);
   $pdf->Cell(190, 6, $supplier['supplier_name'], 0, 1, 'L');
   
   $pdf->SetFont('Arial', '', 10);
   $pdf->Cell(190, 5, $supplier['address'], 0, 1, 'L');
   $pdf->Cell(190, 5, 'ID: ' . $supplier['supplier_id'] . ' | Contact: ' . $supplier['contact_number'], 0, 1, 'L');
   $pdf->Ln(5);

   // =============== PURCHASE DETAILS SECTION ===============
   // Purchase Details Header - Blue background
   $pdf->SetFillColor($headerBlue[0], $headerBlue[1], $headerBlue[2]);
   $pdf->SetTextColor(255, 255, 255);
   $pdf->SetFont('Arial', 'B', 12);
   $pdf->Cell(190, 8, 'PURCHASE DETAILS', 0, 1, 'L', true);
   $pdf->Ln(5);

   // Table headers
   $pdf->SetFillColor(240, 240, 240);
   $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
   $pdf->SetFont('Arial', 'B', 10);
   $pdf->SetDrawColor(200, 200, 200);
   
   // Table header row
   $pdf->Cell(30, 8, 'Date', 1, 0, 'C', true);
   $pdf->Cell(80, 8, 'Description', 1, 0, 'C', true);
   $pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
   $pdf->Cell(30, 8, 'Unit Price', 1, 0, 'C', true);
   $pdf->Cell(30, 8, 'Amount', 1, 1, 'C', true);

   // Table rows
   $pdf->SetFont('Arial', '', 10);
   $fill = false;
   
   foreach ($purchases as $purchase) {
       $pdf->Cell(30, 8, date('d/m/Y', strtotime($purchase['purchase_date'])), 1, 0, 'C', $fill);
       $pdf->Cell(80, 8, $purchase['item_name'], 1, 0, 'L', $fill);
       $pdf->Cell(20, 8, $purchase['quantity'], 1, 0, 'C', $fill);
       $pdf->Cell(30, 8, 'Rs.' . number_format($purchase['price_per_unit'], 2), 1, 0, 'R', $fill);
       $pdf->Cell(30, 8, 'Rs.' . number_format($purchase['total_price'], 2), 1, 1, 'R', $fill);
       $fill = !$fill; // Alternate row colors
   }

   // Summary section - right aligned
   $pdf->Ln(7);
   $pdf->SetFont('Arial', 'B', 10);
   
   // Total row width for right side
   $summaryWidth = 100;
   $leftPadding = 190 - $summaryWidth;
   
   // Summary table with borders
   $pdf->Cell($leftPadding, 8, '', 0, 0); // Empty cell for spacing
   $pdf->Cell($summaryWidth - 30, 8, 'Total Purchases:', 1, 0, 'R');
   $pdf->Cell(30, 8, 'Rs.' . number_format($total_amount, 2), 1, 1, 'R');
   
   $pdf->Cell($leftPadding, 8, '', 0, 0); // Empty cell for spacing
   $pdf->Cell($summaryWidth - 30, 8, 'Payment Amount:', 1, 0, 'R');
   $pdf->Cell(30, 8, 'Rs.' . number_format($total_payments, 2), 1, 1, 'R');
   
   // Balance due - highlighted
   $pdf->SetFont('Arial', 'B', 10);
   $pdf->Cell($leftPadding, 8, '', 0, 0); // Empty cell for spacing
   $pdf->SetFillColor(220, 230, 241); // Light blue background for balance
   $pdf->Cell($summaryWidth - 30, 8, 'BALANCE DUE:', 1, 0, 'R', true);
   $pdf->SetTextColor(255, 0, 0); // Red text for balance
   $pdf->Cell(30, 8, 'Rs.' . number_format($balance, 2), 1, 1, 'R', true);
   
   // Reset text color
   $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);

   // =============== PAYMENT HISTORY SECTION ===============
   if (!empty($payments)) {
       $pdf->Ln(7);
       $pdf->SetFillColor($headerBlue[0], $headerBlue[1], $headerBlue[2]);
       $pdf->SetTextColor(255, 255, 255);
       $pdf->SetFont('Arial', 'B', 12);
       $pdf->Cell(190, 8, 'PAYMENT HISTORY', 0, 1, 'L', true);
       $pdf->Ln(5);

       // Payment history table
       $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
       $pdf->SetFont('Arial', 'B', 10);
       $pdf->SetFillColor(240, 240, 240);
       $pdf->Cell(30, 8, 'Date', 1, 0, 'C', true);
       $pdf->Cell(30, 8, 'Amount', 1, 1, 'C', true);

       $pdf->SetFont('Arial', '', 10);
       $fill = false;
       foreach ($payments as $payment) {
           $pdf->Cell(30, 8, date('d/m/Y', strtotime($payment['payment_date'])), 1, 0, 'C', $fill);
           $pdf->Cell(30, 8, 'Rs.' . number_format($payment['amount'], 2), 1, 1, 'R', $fill);
           $fill = !$fill; // Alternate row colors
       }
   }

   // =============== FOOTER SECTION ===============
   $pdf->Ln(35);
   
   // Signature lines
   $pdf->SetFont('Arial', '', 10);
   $pdf->Cell(90, 6, '__________________________', 0, 0, 'L');
   $pdf->Cell(90, 6, '__________________________', 0, 1, 'R');
   
   $pdf->Cell(90, 5, 'Supplier Authorization', 0, 0, 'L');
   $pdf->Cell(90, 5, 'Co-Op city staff', 0, 1, 'R');

   // Output the PDF
   $pdf->Output('I', $invoice_number . '_Statement.pdf');
   exit;
}

// Get all suppliers for dropdown
$suppliers = $conn->query("SELECT * FROM supplier ORDER BY supplier_name");

// Get all items for dropdown
$items = $conn->query("SELECT * FROM items ORDER BY item_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Purchases & Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        h1, h2, h3, h4 {
            color: #2c3e50;
            font-weight: 600;
        }

        h2 {
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 3px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border-radius: 3px;
        }

        .form-section {
            background-color: rgba(236, 240, 241, 0.7);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .form-section:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-label {
            color: #34495e;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            border-radius: 6px;
            border: 1px solid #bdc3c7;
            padding: 10px 12px;
            margin-bottom: 15px;
            width: 100%;
            box-sizing: border-box;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .btn-primary,
        .btn-secondary,
        .btn-success,
        .btn-info {
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(to right, #5a6fd1, #6a4299);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(to right, #95a5a6, #7f8c8d);
            box-shadow: 0 4px 10px rgba(149, 165, 166, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(to right, #7f8c8d, #6c7a7b);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(149, 165, 166, 0.4);
        }

        .btn-success {
            background: linear-gradient(to right, #28a745, #218838);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-info {
            background: linear-gradient(to right, #17a2b8, #138496);
            box-shadow: 0 4px 10px rgba(23, 162, 184, 0.3);
        }

        .btn-info:hover {
            background: linear-gradient(to right, #138496, #117a8b);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(23, 162, 184, 0.4);
        }

        .supplier-search-container {
            position: relative;
        }

        .ui-autocomplete {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            background-color: #fff;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            margin-top: 2px;
            padding: 0;
            list-style: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            font-family: 'Poppins', sans-serif;
        }

        .ui-autocomplete li {
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }

        .ui-autocomplete li:hover {
            background-color: #f0f3f4;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            font-weight: 500;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover,
        .table tbody tr.highlight {
            background-color: rgba(255, 0, 0, 0.05);
        }

        .alert {
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .balance-positive {
            color: #dc3545;
            font-weight: bold;
        }


        .balance-negative {
            color: #28a745;
            font-weight: bold;
        }

        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 0 rgba(102,126,234,0.5), 0 5px 20px rgba(0,0,0,0.2);
            animation: glowPulse 2s infinite alternate;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        @keyframes glowPulse {
            from { box-shadow: 0 0 0 0 rgba(102,126,234,0.5), 0 5px 20px rgba(0,0,0,0.2); }
            to { box-shadow: 0 0 16px 8px rgba(102,126,234,0.18), 0 5px 20px rgba(0,0,0,0.2); }
        }

        .nav-tabs {
            border-bottom: none;
            background: rgba(102,126,234,0.08);
            border-radius: 10px 10px 0 0;
            overflow: hidden;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #667eea;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
            background: none;
            transition: background 0.2s, color 0.2s;
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(102,126,234,0.09);
        }

        .nav-tabs .nav-link:hover {
            background: linear-gradient(90deg, #e0e7ff 0%, #f3e8ff 100%);
            color: #667eea;
        }
        

        /* --- Glassmorphism Modal --- */
        #purchasesModal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(120deg, rgba(102,126,234,0.25) 0%, rgba(118,75,162,0.18) 100%);
            backdrop-filter: blur(6px);
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s;
            opacity: 0;
            pointer-events: none;
        }
        #purchasesModal.show {
            display: flex;
            opacity: 1;
            pointer-events: auto;
            animation: fadeInModal 0.4s;
        }
        @keyframes fadeInModal {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }
        #purchasesModal .modal-content {
            background: rgba(255,255,255,0.85);
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31,38,135,0.18);
            padding: 30px 25px 20px 25px;
            min-width: 340px;
            max-width: 600px;
            width: 95vw;
            position: relative;
            border: 1.5px solid rgba(102,126,234,0.13);
            animation: popIn 0.5s;
        }
        @keyframes popIn {
            from { transform: translateY(30px) scale(0.97); opacity: 0.7; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        #purchasesModal .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e3e6f0;
            margin-bottom: 18px;
        }
        #purchasesModal .modal-header h3 {
            font-size: 1.4rem;
            color: #667eea;
            font-weight: 600;
            margin: 0;
        }
        #purchasesModal .close-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            font-size: 1.3rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }
        #purchasesModal .close-btn:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: scale(1.1);
        }
        #purchasesModal .modal-body {
            max-height: 55vh;
            overflow-y: auto;
        }
        /* --- Table Card Style --- */
        .table-responsive {
            background: rgba(255,255,255,0.85);
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(102,126,234,0.08);
            padding: 10px 0 0 0;
            margin-bottom: 24px;
            border: 1.5px solid rgba(102,126,234,0.09);
        }
        .table th, .table td {
            border-bottom: 1px solid #e3e6f0;
        }
        .table tbody tr {
            transition: background 0.18s, box-shadow 0.18s;
        }
        .table tbody tr:hover, .table tbody tr.highlight {
            background: linear-gradient(90deg, #e0e7ff 0%, #f3e8ff 100%);
            box-shadow: 0 2px 8px rgba(102,126,234,0.07);
        }
        /* --- Datalist/Autocomplete Dropdown --- */
        .ui-autocomplete, #supplierSuggestions {
            border: 1.5px solid #667eea;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(102,126,234,0.13);
            background: rgba(255,255,255,0.98);
        }
        /* --- Responsive Modal --- */
        @media (max-width: 600px) {
            #purchasesModal .modal-content {
                min-width: 0;
                max-width: 98vw;
                padding: 18px 6px 10px 6px;
            }
        }
        /* --- Card-like Section for Form --- */
        .form-section {
            box-shadow: 0 2px 12px rgba(102,126,234,0.07);
            border: 1.5px solid rgba(102,126,234,0.09);
            background: rgba(255,255,255,0.92);
        }
        .form-section h4 {
            color: #764ba2;
            font-weight: 600;
            margin-bottom: 18px;
        }
        /* --- Animated Button on Hover --- */
        .btn {
            transition: transform 0.18s, box-shadow 0.18s;
        }
        .btn:hover {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 6px 18px rgba(102,126,234,0.13);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Supplier Purchases & Payments</h2>


        <!-- Popup Messages -->
        <div class="popup-container success-popup" id="successPopup">
            <div class="popup">
                <span class="close">&times;</span>
                <h3>Success</h3>
                <p id="successMessage"></p>
            </div>
        </div>
        <div class="popup-container error-popup" id="errorPopup">
            <div class="popup">
                <span class="close">&times;</span>
                <h3>Error</h3>
                <p id="errorMessage"></p>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show success popup if needed
            <?php if (isset($_GET['success']) && $_GET['success'] == 'purchase'): ?>
                document.getElementById('successMessage').textContent = 'Purchase added successfully!';
                document.getElementById('successPopup').classList.add('active');
            <?php elseif (isset($_GET['success']) && $_GET['success'] == 'payment'): ?>
                document.getElementById('successMessage').textContent = 'Payment recorded successfully!';
                document.getElementById('successPopup').classList.add('active');
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                document.getElementById('errorMessage').textContent = <?php echo json_encode($error_message); ?>;
                document.getElementById('errorPopup').classList.add('active');
            <?php endif; ?>
            // Close popups
            document.querySelectorAll('.popup .close').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    this.closest('.popup-container').classList.remove('active');
                    window.location.href = window.location.href.split('?')[0];
                });
            });
            // Close popup when clicking outside
            document.querySelectorAll('.popup-container').forEach(function(popup) {
                popup.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('active');
                        window.location.href = window.location.href.split('?')[0];
                    }
                });
            });
        });
        </script>
    <style>
        /* Popup styles */
        .popup-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .popup-container.active {
            opacity: 1;
            visibility: visible;
        }
        .popup {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 350px;
            max-width: 90%;
            position: relative;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        .popup-container.active .popup {
            transform: translateY(0);
        }
        .popup h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .popup p {
            margin-bottom: 20px;
            color: #555;
        }
        .popup .close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
            color: #666;
            transition: all 0.3s ease;
        }
        .popup .close:hover {
            color: #ff4757;
            transform: rotate(90deg);
        }
        .error-popup .popup {
            border-top: 4px solid #ff4757;
            background: #ff4757;
        }
        .success-popup .popup {
            border-top: 4px solid #2ed573;
            background: #2ed573;
        }
        .error-popup .popup h3,
        .error-popup .popup p,
        .success-popup .popup h3,
        .success-popup .popup p {
            color: white !important;
        }
    </style>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">Payments</button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="balances-tab" data-bs-toggle="tab" data-bs-target="#balances" type="button" role="tab">Balances</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Payments Tab -->
            <div class="tab-pane fade show active" id="payments" role="tabpanel">
                <div class="form-section">
                    <h4>Make Payment to Supplier</h4>
                    <form method="POST" action="" id="paymentForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="payment_supplier_id" class="form-label">Supplier</label>
                                <input list="supplierSuggestions" class="form-control" id="payment_supplier_id" name="payment_supplier_id" required
                                       placeholder="Type or select supplier" autocomplete="off">
                                <datalist id="supplierSuggestions">
                                    <?php
                                    $suppliers->data_seek(0); // Reset pointer
                                    while ($supplier = $suppliers->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['supplier_name']; ?>" data-id="<?php echo $supplier['supplier_id']; ?>">
                                    <?php endwhile; ?>
                                </datalist>
                                <input type="hidden" id="selected_supplier_id" name="selected_supplier_id">
                                <div id="supplierBalance" class="small text-muted mt-1"></div>
                            </div>
                            <div class="col-md-3">
                                <label for="amount" class="form-label">Amount (Rs.)</label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" min="0.01" required>
                                <div id="amountError" class="small text-danger mt-1"></div>
                            </div>
                            <div class="col-md-3">
                                <label for="payment_date" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" name="make_payment" class="btn btn-success">Record Payment</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="form-section">
                    <h4>Recent Payments</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $payments_query = $conn->query("
                                    SELECT sp.*, s.supplier_name
                                    FROM supplier_payments sp
                                    JOIN supplier s ON sp.supplier_id = s.supplier_id
                                    ORDER BY sp.payment_date DESC
                                    LIMIT 10
                                ");

                                while ($payment = $payments_query->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo $payment['supplier_name']; ?></td>
                                    <td>Rs. <?php echo number_format($payment['amount'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Balances Tab -->
            <div class="tab-pane fade" id="balances" role="tabpanel">
                <div class="form-section">
                    <h4>Supplier Balances</h4>
                    <div class="mb-3">
                        <label for="supplierSearch" class="form-label" style="font-size: 15px;">Search Supplier</label>
                        <input type="text" class="form-control" id="supplierSearch" placeholder="Type supplier name...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="font-size: 20px;">Supplier</th>
                                    <th style="font-size: 20px;">Total Purchases</th>
                                    <th style="font-size: 20px;">Total Payments</th>
                                    <th style="font-size: 20px;">Balance</th>
                                    <th style="font-size: 20px;">Action</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 15px;">
                                <?php
                                $balance_query = $conn->query("
                                SELECT
                                    s.supplier_id,
                                    s.supplier_name,
                                    COALESCE(
                                        (SELECT SUM(ip.total_price)
                                         FROM items i
                                         JOIN item_purchases ip ON i.item_id = ip.item_id
                                         WHERE i.supplier_id = s.supplier_id),
                                        0
                                    ) as total_purchases,
                                    COALESCE(
                                        (SELECT SUM(sp.amount)
                                         FROM supplier_payments sp
                                         WHERE sp.supplier_id = s.supplier_id),
                                        0
                                    ) as total_payments,
                                    COALESCE(
                                        (SELECT SUM(ip.total_price)
                                         FROM items i
                                         JOIN item_purchases ip ON i.item_id = ip.item_id
                                         WHERE i.supplier_id = s.supplier_id),
                                        0
                                    ) - COALESCE(
                                        (SELECT SUM(sp.amount)
                                         FROM supplier_payments sp
                                         WHERE sp.supplier_id = s.supplier_id),
                                        0
                                    ) as balance
                                FROM
                                    supplier s
                                ORDER BY
                                    balance DESC
                                ");

                                while ($row = $balance_query->fetch_assoc()):
                                    $balance_class = $row['balance'] > 0 ? 'balance-positive' : 'balance-negative';
                                ?>
                                <tr data-supplier-id="<?php echo $row['supplier_id']; ?>">
                                    <td style="font-size: 18px; font-weight: bold;"><?php echo $row['supplier_name']; ?></td>
                                    <td style="font-size: 18px; font-weight: bold;">Rs. <?php echo number_format($row['total_purchases'], 2); ?></td>
                                    <td style="font-size: 18px; font-weight: bold;">Rs. <?php echo number_format($row['total_payments'], 2); ?></td>
                                    <td class="<?php echo $balance_class; ?>">Rs. <?php echo number_format($row['balance'], 2); ?></td>
                                    <td style="font-size: 18px; font-weight: bold;">
                                        <a href="?generate_invoice=1&supplier_id=<?php echo $row['supplier_id']; ?>&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                                           class="btn btn-sm btn-info" target="_blank">Invoice</a>
                                           
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchases Modal -->
<div id="purchasesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Supplier Purchases</h3>
            <button class="close-btn">&times;</button>
        </div>
        <div class="modal-body"> 
            <table class="table" style="font-size: 20px;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Price Per Unit</th>
                        <th>Total Price</th>
                    </tr>
                </thead>
                <tbody id="purchasesTableBody">
                    <!-- Purchases will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<a href="home.php" class="btn btn-primary floating-btn animate__animated animate__fadeInUp">
        <i class="fas fa-home"></i>
    </a>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set default dates for invoice generation
            const today = new Date().toISOString().split('T')[0];
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
            const oneMonthAgoStr = oneMonthAgo.toISOString().split('T')[0];

            $('#start_date').val(oneMonthAgoStr);
            $('#end_date').val(today);

            // Update balance info when supplier changes
            $('#payment_supplier_id').on('input', function() {
                const supplierName = $(this).val();
                const option = $(`#supplierSuggestions option[value="${supplierName}"]`);

                if (option.length > 0) {
                    const supplierId = option.data('id');
                    $('#selected_supplier_id').val(supplierId);

                    // Fetch balance for this supplier
                    $.ajax({
                        url: 'get_supplier_balance.php',
                        type: 'GET',
                        data: { supplier_id: supplierId },
                        dataType: 'json',
                        success: function(data) {
                            if (data.error) {
                                $('#supplierBalance').html('<span class="text-danger">' + data.error + '</span>');
                                $('#amount').removeAttr('max');
                            } else if (data.balance !== undefined) {
                                const balance = parseFloat(data.balance);
                                let balanceText = '';
                                let balanceClass = '';

                                if (balance > 0) {
                                    balanceText = `Balance Due: Rs. ${balance.toFixed(2)}`;
                                    balanceClass = 'text-danger';
                                } else if (balance < 0) {
                                    balanceText = `Credit Balance: Rs. ${Math.abs(balance).toFixed(2)}`;
                                    balanceClass = 'text-success';
                                } else {
                                    balanceText = 'No balance due';
                                    balanceClass = 'text-success';
                                }

                                $('#supplierBalance').html(`<span class="${balanceClass}">${balanceText}</span>`);

                                // Set max amount for payment (only if balance is positive)
                                $('#amount').attr('max', balance > 0 ? balance : '');
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#supplierBalance').html('<span class="text-danger">Error fetching balance. Please try again.</span>');
                            console.error('AJAX Error:', status, error);
                        }
                    });
                } else {
                    $('#supplierBalance').text('');
                    $('#amount').removeAttr('max');
                }
            });

            // Validate amount before form submission
            $('#paymentForm').on('submit', function(e) {
                const amount = parseFloat($('#amount').val());
                const maxAmount = parseFloat($('#amount').attr('max')) || 0;

                if (amount > maxAmount) {
                    $('#amountError').text(`Payment cannot exceed current balance of Rs. ${maxAmount.toFixed(2)}`);
                    e.preventDefault();
                    return false;
                }
                return true;
            });

            // Clear error when amount changes
            $('#amount').on('input', function() {
                $('#amountError').text('');
            });

            // Initialize autocomplete for supplier search
            $('#supplierSearch').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: 'search_suppliers.php',
                        type: 'GET',
                        data: { term: request.term },
                        dataType: 'json',
                        success: function(data) {
                            response(data);
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $('#supplierSearch').val(ui.item.label);
                    // Highlight the relevant row
                    const supplierId = ui.item.value;
                    $('tr[data-supplier-id="' + supplierId + '"]').addClass('highlight').siblings().removeClass('highlight');
                    return false;
                }
            });

           // View Purchases click handler
$(document).on('click', '.view-purchases', function() {
    const supplierId = $(this).data('supplier-id');
    
    // Show loading state
    $('#purchasesTableBody').html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');
    
    // Fetch purchases
    $.ajax({
        url: 'get_supplier_purchases.php',
        method: 'GET',
        data: { supplier_id: supplierId },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                $('#purchasesTableBody').html(`<tr><td colspan="5" class="text-center">${response.error}</td></tr>`);
                return;
            }
            
            const purchasesTable = $('#purchasesTableBody');
            purchasesTable.empty();
            
            if (response.purchases.length === 0) {
                purchasesTable.html('<tr><td colspan="5" class="text-center">No purchases found</td></tr>');
                return;
            }
            
            response.purchases.forEach(purchase => {
                purchasesTable.append(`
                    <tr>
                        <td>${purchase.purchase_date}</td>
                        <td>${purchase.item_name}</td>
                        <td>${purchase.quantity}</td>
                        <td>${purchase.price_per_unit.toFixed(2)}</td>
                        <td>${purchase.total_price.toFixed(2)}</td>
                    </tr>
                `);
            });
            
            // Show the modal
            $('#purchasesModal').addClass('show');
        },
        error: function(xhr, status, error) {
            $('#purchasesTableBody').html(`<tr><td colspan="5" class="text-center">Error loading purchases: ${error}</td></tr>`);
            console.error(xhr.responseText);
        }
    });
});

// Close modal
$('#purchasesModal .close-btn').click(function() {
    $('#purchasesModal').removeClass('show');
});

// Close modal when clicking outside
$('#purchasesModal').click(function(e) {
    if (e.target === this) {
        $(this).removeClass('show');
    }
});
        });
    </script>
</body>
</html>
