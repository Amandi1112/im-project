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

            // Insert payment record
            $stmt = $conn->prepare("INSERT INTO supplier_payments (supplier_id, amount, payment_date) VALUES (?, ?, ?)");
            $stmt->bind_param("sds", $supplier_id, $amount, $payment_date);

            if ($stmt->execute()) {
                header("Location: {$_SERVER['PHP_SELF']}?success=payment");
                exit;
            } else {
                $error_message = "Error recording payment: " . $conn->error;
            }
        } else {
            $error_message = "Supplier not found. Please select a valid supplier.";
        }
    }
}

// Rest of the code remains the same as in the original script...

// The generate invoice and other sections remain unchanged

    // Handle payment to supplier
    if (isset($_POST['make_payment'])) {
        $supplier_id = $_POST['payment_supplier_id'];
        $amount = $_POST['amount'];
        $payment_date = $_POST['payment_date'];

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
    <style>
        .form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .invoice-section {
            margin-top: 30px;
        }
        .payment-section {
            margin-top: 30px;
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
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
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
                <div class="form-section payment-section">
                    <h4>Make Payment to Supplier</h4>
                    <form method="POST" action="">
                        <div class="row mb-3">
                        <div class="col-md-6">
                                <div class="col-md-6">
    <label for="payment_supplier_id" class="form-label">Supplier</label>
    <input list="supplierSuggestions" class="form-control" id="payment_supplier_id" name="payment_supplier_id" required 
           placeholder="Type or select supplier" autocomplete="off" style="width:600px;">
    <datalist id="supplierSuggestions">
        <?php
        $suppliers->data_seek(0); // Reset pointer
        while ($supplier = $suppliers->fetch_assoc()): ?>
            <option value="<?php echo $supplier['supplier_name']; ?>" data-id="<?php echo $supplier['supplier_id']; ?>">
        <?php endwhile; ?>
    </datalist>
    <input type="hidden" id="selected_supplier_id" name="selected_supplier_id">
    
</div>

<script>
document.getElementById('payment_supplier_id').addEventListener('input', function() {
    const input = this.value;
    const options = document.getElementById('supplierSuggestions').options;
    let found = false;
    
    // Search for matching supplier
    for (let i = 0; i < options.length; i++) {
        if (options[i].value === input) {
            document.getElementById('selected_supplier_id').value = options[i].getAttribute('data-id');
            found = true;
            break;
        }
    }
    
    // If not found in existing suppliers, clear the ID
    if (!found) {
        document.getElementById('selected_supplier_id').value = '';
    }
});
</script>
                            </div>
                            <div class="col-md-3">
                                <label for="amount" class="form-label">Amount (Rs.)</label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" min="0.01" required>
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

            <!-- Invoices Tab -->
            <div class="tab-pane fade" id="invoices" role="tabpanel">
                <div class="form-section invoice-section">
                    <h4>Generate Invoice</h4>
                    <p><note>--[This provides you to generate invoices of the payments which are already done within given period]--</note></p>
                    <form method="GET" action="">
                        <input type="hidden" name="generate_invoice" value="1">
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
</div>

<script>
document.getElementById('payment_supplier_id').addEventListener('input', function() {
    const input = this.value;
    const options = document.getElementById('supplierSuggestions').options;
    let found = false;
    
    // Search for matching supplier
    for (let i = 0; i < options.length; i++) {
        if (options[i].value === input) {
            document.getElementById('selected_supplier_id').value = options[i].getAttribute('data-id');
            found = true;
            break;
        }
    }
    
    // If not found in existing suppliers, clear the ID
    if (!found) {
        document.getElementById('selected_supplier_id').value = '';
    }
});
</script>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success">Generate Invoice</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Balances Tab -->
            <div class="tab-pane fade" id="balances" role="tabpanel">
                <div class="form-section">
                    <h4>Supplier Balances</h4>
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
                            // Balances Tab - Replace the existing query with this corrected version
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
                            <tr>
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

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Make Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="modal_supplier_id" name="payment_supplier_id">
                        <div class="mb-3">
                            <label for="modal_supplier_name" class="form-label">Supplier</label>
                            <input type="text" class="form-control" id="modal_supplier_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="modal_amount" class="form-label">Amount (Rs.)</label>
                            <input type="number" step="0.01" class="form-control" id="modal_amount" name="amount" required>
                        </div>
                        <div class="mb-3">
                            <label for="modal_payment_date" class="form-label">Payment Date</label>
                            <input type="date" class="form-control" id="modal_payment_date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="modal_payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="modal_payment_method" name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="modal_reference" class="form-label">Reference/Notes</label>
                            <input type="text" class="form-control" id="modal_reference" name="reference" placeholder="Cheque number, transaction ID, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="make_payment" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set default dates for invoice generation
            const today = new Date().toISOString().split('T')[0];
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
            const oneMonthAgoStr = oneMonthAgo.toISOString().split('T')[0];

            $('#start_date').val(oneMonthAgoStr);
            $('#end_date').val(today);

            // Auto-fill price when item is selected
            $('#item_id').change(function() {
                const selectedOption = $(this).find('option:selected');
                const price = selectedOption.data('price');
                if (price) {
                    $('#price_per_unit').val(price);
                }
            });

            // Handle pay now button click
            $('.pay-btn').click(function() {
                const supplierId = $(this).data('supplier-id');
                const supplierName = $(this).data('supplier-name');
                const balance = $(this).data('balance');

                $('#modal_supplier_id').val(supplierId);
                $('#modal_supplier_name').val(supplierName);
                $('#modal_amount').val(balance > 0 ? balance : '');

                var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                paymentModal.show();
            });
        });
    </script>
</body>
</html>
