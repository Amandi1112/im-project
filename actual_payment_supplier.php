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

    // Get purchases for the supplier within date range
    $purchase_query = $conn->prepare("
        SELECT ip.*, i.item_name
        FROM item_purchases ip
        JOIN items i ON ip.item_id = i.item_id
        WHERE i.supplier_id = ? AND ip.purchase_date BETWEEN ? AND ?
        ORDER BY ip.purchase_date
    ");
    $purchase_query->bind_param("sss", $supplier_id, $start_date, $end_date);
    $purchase_query->execute();
    $purchases      = $purchase_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $total_amount   = array_sum(array_column($purchases, 'total_price'));

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
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false); // Ensure everything stays on one page
    $pdf->AddPage();
    $pdf->SetMargins(12, 12, 12); // Tighter margins

    // =============== HEADER SECTION ===============
    // Company Info (top left)
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(30, 50, 80); // Dark blue
    $pdf->Cell(100, 6, 'T&C co-op City Shop', 0, 0, 'L');

    // Invoice info (top right)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'STATEMENT #: ' . $invoice_number, 0, 1, 'R');

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(100, 4, 'Pahala Karawita, Karawita, Ratnapura, Sri Lanka', 0, 0, 'L');
    $pdf->Cell(0, 4, 'Date: ' . date('m/d/Y'), 0, 1, 'R');

    $pdf->Cell(100, 4, 'Phone: (123) 456-7890 | Email: co_op@sanasa.com', 0, 0, 'L');
    $pdf->Cell(0, 4, 'Period: ' . date('m/d/Y', strtotime($start_date)) . ' - ' . date('m/d/Y', strtotime($end_date)), 0, 1, 'R');

    $pdf->Ln(5);

    // =============== SUPPLIER INFO ===============
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(30, 50, 80);
    $pdf->Cell(0, 5, 'BILL TO:', 0, 1);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 4, $supplier['supplier_name'], 0, 1);
    $pdf->Cell(0, 4, $supplier['address'], 0, 1);
    $pdf->Cell(0, 4, 'ID: ' . $supplier['supplier_id'] . ' | Contact: ' . $supplier['contact_number'], 0, 1);

    $pdf->Ln(5);

    // =============== TRANSACTION TABLE ===============
    // Table Header
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(20, 6, 'DATE', 1, 0, 'C', true);
    $pdf->Cell(65, 6, 'DESCRIPTION', 1, 0, 'C', true);
    $pdf->Cell(15, 6, 'QTY', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'UNIT PRICE', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'AMOUNT', 1, 1, 'C', true);

    // Table Rows
    $pdf->SetFont('Arial', '', 7);
    foreach ($purchases as $purchase) {
        $pdf->Cell(20, 5, date('m/d/Y', strtotime($purchase['purchase_date'])), 'LR', 0, 'C');
        $pdf->Cell(65, 5, substr($purchase['item_name'], 0, 40), 'LR', 0, 'L'); // Limit description length
        $pdf->Cell(15, 5, $purchase['quantity'], 'LR', 0, 'C');
        $pdf->Cell(20, 5, 'Rs.' . number_format($purchase['price_per_unit'], 2), 'LR', 0, 'R');
        $pdf->Cell(20, 5, 'Rs.' . number_format($purchase['total_price'], 2), 'LR', 1, 'R');
    }

    // Table Footer
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(120, 6, 'SUBTOTAL', 'LTB', 0, 'R');
    $pdf->Cell(20, 6, 'Rs.' . number_format($total_amount, 2), 'RTB', 1, 'R');

    $pdf->Ln(8);

    // =============== PAYMENT SUMMARY ===============
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(30, 50, 80);
    $pdf->Cell(0, 5, 'PAYMENT SUMMARY', 0, 1);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(30, 5, 'Total Purchases:', 0, 0);
    $pdf->Cell(20, 5, 'Rs.' . number_format($total_amount, 2), 0, 1, 'R');

    $pdf->Cell(30, 5, 'Payments Received:', 0, 0);
    $pdf->Cell(20, 5, 'Rs.' . number_format($total_payments, 2), 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->Cell(30, 6, 'BALANCE DUE:', 0, 0);
    $pdf->Cell(20, 6, 'Rs.' . number_format($balance, 2), 0, 1, 'R');

    $pdf->Ln(5);

    // =============== PAYMENT HISTORY (if exists) ===============
    if (!empty($payments)) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(30, 50, 80);
        $pdf->Cell(0, 5, 'PAYMENT HISTORY', 0, 1);

        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 6, 'DATE', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'AMOUNT', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        foreach ($payments as $payment) {
            $pdf->Cell(30, 5, date('m/d/Y', strtotime($payment['payment_date'])), 'LR', 0, 'C');
            $pdf->Cell(30, 5, 'Rs.' . number_format($payment['amount'], 2), 'LR', 1, 'R');
        }
        $pdf->Cell(60, 0, '', 'T'); // Closing line
        $pdf->Ln(8);
    }

    // =============== FOOTER SECTION ===============
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(100, 100, 100);

    $pdf->Ln(5);

    // Signature lines
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(90, 4, '__________________________', 0, 0, 'L');
    $pdf->Cell(0, 4, '__________________________', 0, 1, 'R');
    $pdf->Cell(90, 4, 'Supplier Authorization', 0, 0, 'L');
    $pdf->Cell(0, 4, 'Company Representative', 0, 1, 'R');

    // Final page number
    $pdf->SetY(-10);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->Cell(0, 5, 'Page 1 of 1', 0, 0, 'C');

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

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 6px 6px 0 0;
            margin-right: 5px;
        }

        .nav-tabs .nav-link.active {
            color: #667eea;
            background-color: rgba(102, 126, 234, 0.1);
            border-bottom: 2px solid #667eea;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: #667eea;
        }

        .tab-content {
            padding: 20px 0;
        }

        note {
            font-style: italic;
            color: #6c757d;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            h2 {
                font-size: 24px;
                margin-bottom: 20px;
            }

            .table th,
            .table td {
                padding: 8px 10px;
                font-size: 13px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Supplier Purchases & Payments</h2>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'purchase'): ?>
            <div class="alert alert-success">Purchase added successfully!</div>
        <?php elseif (isset($_GET['success']) && $_GET['success'] == 'payment'): ?>
            <div class="alert alert-success">Payment recorded successfully!</div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

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
                        <label for="supplierSearch" class="form-label">Search Supplier</label>
                        <input type="text" class="form-control" id="supplierSearch" placeholder="Type supplier name...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>Total Purchases</th>
                                    <th>Total Payments</th>
                                    <th>Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                    <td><?php echo $row['supplier_name']; ?></td>
                                    <td>Rs. <?php echo number_format($row['total_purchases'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($row['total_payments'], 2); ?></td>
                                    <td class="<?php echo $balance_class; ?>">Rs. <?php echo number_format($row['balance'], 2); ?></td>
                                    <td>
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
            <table class="table">
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
