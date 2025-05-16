<?php
require('fpdf/fpdf.php'); // Make sure to include the FPDF library

// Database connection
$host = '127.0.0.1';
$dbname = 'mywebsite';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Handle AJAX requests and invoice generation
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_member_info' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($member ? $member : null);
        exit;
    }
    
    if ($_GET['action'] == 'get_item_info' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   GROUP_CONCAT(DISTINCT s.supplier_name SEPARATOR ', ') as supplier_names,
                   COUNT(DISTINCT i2.item_id) as supplier_count
            FROM items i
            LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
            LEFT JOIN items i2 ON i.item_name = i2.item_name AND i2.item_id != i.item_id
            WHERE i.item_id = ?
            GROUP BY i.item_id
        ");
        $stmt->execute([$_GET['id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Also check if there are other items with the same name from different suppliers
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM items 
            WHERE item_name = (SELECT item_name FROM items WHERE item_id = ?) 
            AND item_id != ?
        ");
        $stmt->execute([$_GET['id'], $_GET['id']]);
        $sameNameCount = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $item['has_multiple_suppliers'] = ($sameNameCount['count'] > 0);
        
        echo json_encode($item ? $item : null);
        exit;
    }
    
    if ($_GET['action'] == 'search_members' && isset($_GET['term'])) {
        $term = '%' . $_GET['term'] . '%';
        $stmt = $pdo->prepare("
            SELECT id, CONCAT(full_name, ' (Coop: ', id, ')') as label 
            FROM members 
            WHERE full_name LIKE ? OR id LIKE ?
            ORDER BY full_name
            LIMIT 10
        ");
        $stmt->execute([$term, $term]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        exit;
    }

    if ($_GET['action'] == 'search_items' && isset($_GET['term'])) {
        $term = '%' . $_GET['term'] . '%';
        $stmt = $pdo->prepare("
            SELECT item_id as id, CONCAT(item_name, ' (Rs. ', price_per_unit, ')') as label 
            FROM items 
            WHERE item_name LIKE ? AND current_quantity > 0
            ORDER BY item_name
            LIMIT 10
        ");
        $stmt->execute([$term]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        exit;
    }
    
    if ($_GET['action'] == 'generate_invoice' && isset($_GET['purchase_id'])) {
        generateInvoicePDF($_GET['purchase_id'], $pdo);
        exit;
    }
}

// Function to generate invoice PDF
function generateInvoicePDF($purchaseId, $pdo) {
    // Get purchase details for all items in this transaction
    $stmt = $pdo->prepare("
    SELECT p.*, m.id as member_id, m.full_name, m.bank_membership_number, 
           m.address, m.telephone_number, i.item_name, i.price_per_unit, 
           i.item_code, s.supplier_name
    FROM purchases p
    JOIN members m ON p.member_id = m.id
    JOIN items i ON p.item_id = i.item_id
    LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
    WHERE p.purchase_id = ? OR 
          (p.member_id = (SELECT member_id FROM purchases WHERE purchase_id = ?) 
           AND p.purchase_date = (SELECT purchase_date FROM purchases WHERE purchase_id = ?))
    ORDER BY p.purchase_id
");
    $stmt->execute([$purchaseId, $purchaseId, $purchaseId]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($purchases)) die("Invalid purchase ID");

    // Get the first purchase for member/supplier info (they'll be the same for all)
    $firstPurchase = $purchases[0];
    
    // Calculate totals
    $subtotal = 0;
    foreach ($purchases as $purchase) {
        $subtotal += $purchase['total_price'];
    }

    // Create PDF with professional design
    // Create PDF with professional design in landscape
$pdf = new FPDF('L','mm','A4'); // Changed to landscape orientation
$pdf->AddPage();

// ========== COLOR SCHEME ========== //
$primaryColor = array(102, 126, 234);   // #667eea
$primaryDark = array(90, 103, 216);    // #5a67d8
$secondaryColor = array(237, 242, 247); // #edf2f7
$dangerColor = array(229, 62, 62);      // #e53e3e
$successColor = array(72, 187, 120);    // #48bb78
$warningColor = array(237, 137, 54);    // #ed8936
$infoColor = array(66, 153, 225);       // #4299e1
$lightColor = array(247, 250, 252);     // #f7fafc
$darkColor = array(45, 55, 72);         // #2d3748
$grayColor = array(113, 128, 150);      // #718096
$grayLight = array(226, 232, 240);      // #e2e8f0

// ========== HEADER SECTION ========== //
// Header with primary color background (wider for landscape)
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Rect(10, 10, 277, 20, 'F');

// Shop name
$pdf->SetTextColor(255);
$pdf->SetFont('Helvetica','B',16);
$pdf->SetXY(15, 12);
$pdf->Cell(0,8,'T&C co-op City shop',0,1,'L');

// Invoice info box (position adjusted for landscape)
$pdf->SetFillColor($primaryDark[0], $primaryDark[1], $primaryDark[2]);
$pdf->Rect(200, 12, 80, 16, 'F'); // Moved further right
$pdf->SetFont('Helvetica','B',12);
$pdf->SetXY(200, 12);
$pdf->Cell(80,8,'INVOICE',0,1,'C');
$pdf->SetFont('Helvetica','',10);
$pdf->SetXY(200, 18);
$pdf->Cell(80,6,'#INV-'.str_pad($purchaseId, 5, '0', STR_PAD_LEFT),0,1,'C');

// Shop contact info (adjusted for landscape width)
$pdf->SetTextColor(255);
$pdf->SetFont('Helvetica','',9);
$pdf->SetXY(15, 22);
$pdf->Cell(0,5,'Pahala Karawita, Karawita, Ratnapura, Sri Lanka | Tel: +94 11 2345678 | Email: co_op@sanasa.com',0,1,'L');

// Invoice date and payment terms
$pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
$pdf->SetFont('Helvetica','',10);
$pdf->SetXY(15, 40);
$pdf->Cell(50,5,'Invoice Date:',0,0);
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell(0,5,$firstPurchase['purchase_date'],0,1);

$pdf->SetFont('Helvetica','',10);
$pdf->SetXY(15, 45);
$pdf->Cell(50,5,'Payment Terms:',0,0);
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell(0,5,'Credit Account',0,1);

// ========== BILL TO SECTION ========== //
$pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->Rect(15, 55, 130, 30, 'F'); // Wider for landscape
$pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
$pdf->SetFont('Helvetica','B',12);
$pdf->SetXY(15, 55);
$pdf->Cell(130,8,'BILL TO',0,1,'L');
$pdf->SetFont('Helvetica','',10);

$pdf->SetXY(17, 63);
$pdf->Cell(126,5,$firstPurchase['full_name'],0,1);
$pdf->SetXY(17, 68);
$pdf->Cell(126,5,$firstPurchase['address'],0,1);
$pdf->SetXY(17, 73);
$pdf->Cell(126,5,'Tel: '.$firstPurchase['telephone_number'],0,1);
$pdf->SetXY(17, 78);
$pdf->Cell(126,5,'Coop: '.$firstPurchase['id'],0,1);

// ========== ITEM TABLE ========== //
$pdf->SetY(90);

// Table Header (wider columns for landscape)
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255);
$pdf->SetFont('Helvetica','B',10);

$pdf->Cell(15,10,'#',1,0,'C',true);
$pdf->Cell(100,10,'ITEM DESCRIPTION',1,0,'L',true); // Wider
$pdf->Cell(40,10,'ITEM CODE',1,0,'C',true); // Wider
$pdf->Cell(35,10,'UNIT PRICE',1,0,'R',true);
$pdf->Cell(25,10,'QTY',1,0,'C',true);
$pdf->Cell(35,10,'TOTAL',1,1,'R',true); // Wider

// Table Rows
$pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
$pdf->SetFont('Helvetica','',10);

$rowNum = 1;
foreach ($purchases as $purchase) {
    $pdf->Cell(15,8,$rowNum,1,0,'C');
    $pdf->Cell(100,8,$purchase['item_name'],1,0,'L');
    $pdf->Cell(40,8,$purchase['item_code'],1,0,'C');
    $pdf->Cell(35,8,'Rs. '.number_format($purchase['price_per_unit'],2),1,0,'R');
    $pdf->Cell(25,8,$purchase['quantity'],1,0,'C');
    $pdf->Cell(35,8,'Rs. '.number_format($purchase['total_price'],2),1,1,'R');
    $rowNum++;
}

// Subtotal row
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell(215,8,'SUBTOTAL',1,0,'R'); // Adjusted width
$pdf->Cell(35,8,'Rs. '.number_format($subtotal,2),1,1,'R');

// ========== NEW CREDIT LIMIT ========== //
// Check if credit_limit exists before using it
$creditLimit = isset($firstPurchase['credit_limit']) ? $firstPurchase['credit_limit'] : 0;
$newCreditLimit = $creditLimit - $subtotal;

if ($creditLimit > 0) {
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('Helvetica','B',10);
    $pdf->Cell(180,8,'New Credit Limit:',0,0,'R');
    $pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
    $pdf->Cell(35,8,'Rs. '.number_format($newCreditLimit,2),1,1,'R',true);
}

// ========== SIGNATURE SECTION ========== //
// Increased the initial Y position by 15mm (from +10 to +15)
// ========== SIGNATURE SECTION ========== //
// ========== SIGNATURE SECTION ========== //
$pdf->SetY($pdf->GetY() + 15); // Start signature section 15mm below previous content

// Signature Labels with comfortable spacing
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell(115, 8, 'Customer Signature', 0, 0, 'C'); // Restored to original height
$pdf->Cell(140, 8, 'Authorized Signature', 0, 1, 'C');

// Space between labels and signature lines (added 5mm gap)
$pdf->Cell(0, 5, '', 0, 1); // This creates a 5mm vertical gap

// Space for signatures (original height)
$pdf->Cell(115, 15, '', 0, 0, 'C');
$pdf->Cell(140, 15, '', 0, 1, 'C');

// Signature lines positioned with better spacing
$yPosition = $pdf->GetY() - 10; // Lines will be 10mm above current position
$pdf->SetDrawColor($grayColor[0], $grayColor[1], $grayColor[2]);
$pdf->Line(45, $yPosition, 125, $yPosition); // Customer line
$pdf->Line(160, $yPosition, 240, $yPosition); // Authorized line

// Additional space after signature lines
$pdf->SetY($yPosition + 5); // Move 5mm below signature lines

// Footer note
$pdf->SetFont('Helvetica','I',8);
$pdf->SetTextColor($grayColor[0], $grayColor[1], $grayColor[2]);
$pdf->Cell(0,5,'This is a computer generated invoice. Thank you for your business!',0,1,'C');
$pdf->Cell(0,5,'Generated on '.date('Y-m-d H:i:s'),0,1,'C');

// Clear any output buffers before sending PDF
while (ob_get_level()) {
    ob_end_clean();
}

// Output the PDF
$pdf->Output('I', 'Invoice_'.$purchaseId.'.pdf');
exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['make_purchase'])) {
        $memberId = $_POST['member_id'];
        $items = $_POST['items'] ?? [];
        
        // Get member details
        $memberStmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $memberStmt->execute([$memberId]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            $error = "Invalid member selected!";
        } elseif (empty($items)) {
            $error = "Please add at least one item to the purchase";
        } else {
            // Validate items array structure
            $validItems = [];
            foreach ($items as $itemData) {
                if (!isset($itemData['item_id']) || !isset($itemData['quantity'])) {
                    $error = "Invalid item data format";
                    break;
                }
                
                $itemId = $itemData['item_id'];
                $quantity = (int)$itemData['quantity'];
                
                if ($quantity <= 0) continue;
                
                $validItems[] = [
                    'item_id' => $itemId,
                    'quantity' => $quantity
                ];
            }
            
            if (empty($validItems)) {
                $error = "No valid items selected for purchase";
            } else {
                // Start transaction
                $pdo->beginTransaction();
                $purchaseIds = []; // Store all purchase IDs for invoice generation
                
                try {
                    $totalPurchaseAmount = 0;
                    $purchaseDetails = [];
                    
                    // First validate all items and quantities
                    foreach ($validItems as $itemData) {
                        $itemId = $itemData['item_id'];
                        $quantity = $itemData['quantity'];
                        
                        // Get item details
                        $itemStmt = $pdo->prepare("SELECT * FROM items WHERE item_id = ?");
                        $itemStmt->execute([$itemId]);
                        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$item) {
                            throw new Exception("Invalid item selected: ID $itemId");
                        }
                        
                        if ($item['current_quantity'] < $quantity) {
                            throw new Exception("Not enough stock available for {$item['item_name']}! Available: {$item['current_quantity']}");
                        }
                        
                        $itemTotal = $item['price_per_unit'] * $quantity;
                        $totalPurchaseAmount += $itemTotal;
                        
                        $purchaseDetails[] = [
                            'item_id' => $itemId,
                            'quantity' => $quantity,
                            'price_per_unit' => $item['price_per_unit'],
                            'total_price' => $itemTotal,
                            'item_name' => $item['item_name']
                        ];
                    }
                    
                    // Check if member has enough credit
                    if (($member['current_credit_balance'] + $totalPurchaseAmount) > $member['credit_limit']) {
                        throw new Exception("Purchase exceeds credit limit! Available credit: " . 
                                 ($member['credit_limit'] - $member['current_credit_balance']));
                    }
                    
                    // Process each item
                    foreach ($purchaseDetails as $purchase) {
                        // Insert purchase record
                        $purchaseStmt = $pdo->prepare("
    INSERT INTO purchases (member_id, item_id, full_name, quantity, 
                          price_per_unit, total_price, purchase_date, current_credit_balance)
    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)
");
$purchaseStmt->execute([
    $memberId, $purchase['item_id'], $member['full_name'], 
    $purchase['quantity'], $purchase['price_per_unit'], $purchase['total_price'], 
    $member['current_credit_balance'] + $totalPurchaseAmount
]);
                        
                        $purchaseIds[] = $pdo->lastInsertId();
                        
                        // Update item stock
                        $updateItemStmt = $pdo->prepare("
                            UPDATE items 
                            SET current_quantity = current_quantity - ? 
                            WHERE item_id = ?
                        ");
                        $updateItemStmt->execute([$purchase['quantity'], $purchase['item_id']]);
                        
                        // Log activity
                       
                    }
                    
                    // Update member's credit balance once for the entire purchase
                    $updateMemberStmt = $pdo->prepare("
                        UPDATE members 
                        SET current_credit_balance = current_credit_balance + ? 
                        WHERE id = ?
                    ");
                    $updateMemberStmt->execute([$totalPurchaseAmount, $memberId]);
                    
                    $pdo->commit();
                    header("Location: ".$_SERVER['PHP_SELF']."?success=1&purchase_id=".$purchaseIds[0]);
                exit();
                    
                    // Generate invoice for the first item (you might want to modify this for multiple items)
                    if (!empty($purchaseIds)) {
                        $invoiceLink = "?action=generate_invoice&purchase_id=" . $purchaseIds[0];
                        $success .= " <a href='$invoiceLink' target='_blank'>Download Invoice</a>";
                    }
                    
                    // Clear the form after successful submission
                    echo '<script>
                        document.getElementById("member_search").value = "";
                        document.getElementById("member_id").value = "";
                        document.getElementById("memberInfo").style.display = "none";
                        document.getElementById("items-container").innerHTML = "";
                        document.getElementById("total-amount").textContent = "0.00";
                        document.getElementById("add-item-btn").click(); // Add one empty row
                    </script>';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Transaction failed: " . $e->getMessage();
                }
            }
        }
    }
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// Get recent transactions
$transactions = $pdo->query("
    SELECT p.*, m.full_name, m.bank_membership_number, i.item_name 
    FROM purchases p
    JOIN members m ON p.member_id = m.id
    JOIN items i ON p.item_id = i.item_id
    ORDER BY p.purchase_date DESC, p.purchase_id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Shop - Credit Purchase System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1, h2, h3 {
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
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
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        
        .member-info, .item-info {
            display: none;
            padding: 15px;
            background: #e8f4fc;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .item-info {
            background: #e8fcf5;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .info-item {
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #7f8c8d;
        }
        
        datalist {
            position: absolute;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 0 0 4px 4px;
            width: 100%;
            z-index: 1000;
        }
        
        .item-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .item-row input[type="text"], 
        .item-row input[type="number"] {
            flex: 1;
        }
        
        .item-row .item-name {
            flex: 2;
        }
        
        .item-row .item-quantity {
            flex: 1;
            max-width: 100px;
        }
        
        .item-row .item-price {
            flex: 1;
            max-width: 100px;
        }
        
        .item-row .item-total {
            flex: 1;
            max-width: 100px;
            font-weight: bold;
        }
        
        .total-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            text-align: right;
            font-size: 1.2em;
        }
        
        .invoice-link {
            color: #1a73e8;
            text-decoration: none;
            font-weight: bold;
        }
        
        .invoice-link:hover {
            text-decoration: underline;
        }
        
        /* New styles for item tooltip */
        .item-search {
            position: relative;
        }
        
        .item-search:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 0;
            background-color: #333;
            color: white;
            padding: 5px;
            border-radius: 4px;
            white-space: pre-line;
            z-index: 100;
            min-width: 200px;
            font-size: 14px;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .item-row {
                flex-direction: column;
            }
            
            .item-row input {
                width: 100%;
                max-width: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cooperative Shop Credit Purchase System</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="section">
            <h2>New Purchase</h2>
            <form method="POST" action="">
                <input type="hidden" name="make_purchase" value="1">
                
                <div class="form-group">
                    <label for="member_search">Search Member</label>
                    <input type="text" id="member_search" name="member_search" list="member_list" 
                           placeholder="Start typing member name    or    membership number..." autocomplete="off">
                    <datalist id="member_list"></datalist>
                    <input type="hidden" id="member_id" name="member_id">
                </div>
                
                <div id="memberInfo" class="member-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Bank Membership No:</span>
                            <span id="info-bank-number"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Credit Limit:</span>
                            <span id="info-credit-limit"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Current Credit Balance:</span>
                            <span id="info-credit-balance"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Available Credit:</span>
                            <span id="info-credit-available"></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <h3>Items</h3>
                    <div id="items-container">
                        <!-- Items will be added here dynamically -->
                    </div>
                    <button type="button" id="add-item-btn" class="btn-secondary" style="font-family: 'Poppins', sans-serif;">+ Add Item</button>
                </div>
                
                <div class="total-section">
                    <strong>Total Amount: Rs. <span id="total-amount">0.00</span></strong>
                </div>
                
                <div class="form-group">
                    <button type="submit" style="font-family: 'Poppins', sans-serif;">Process Purchase</button>
                </div>
            </form>
        </div>
        
        <div class="section">
            <h2>Recent Transactions</h2>
            <?php if (count($transactions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Bank No.</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Credit Bal.</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['purchase_date']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['bank_membership_number']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['quantity']); ?></td>
                                <td>Rs. <?php echo number_format($transaction['price_per_unit'], 2); ?></td>
                                <td>Rs. <?php echo number_format($transaction['total_price'], 2); ?></td>
                                <td>Rs. <?php echo number_format($transaction['current_credit_balance'], 2); ?></td>
                                <td>
                                    <a href="?action=generate_invoice&purchase_id=<?php echo $transaction['purchase_id']; ?>" 
                                       target="_blank" class="invoice-link">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No transactions found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Template for item row -->
    <template id="item-row-template">
        <div class="item-row" data-id="">
            <input type="text" class="item-search" placeholder="Search item..." list="item_list" autocomplete="off">
            <input type="hidden" class="item-id" name="">
            <input type="number" class="item-quantity" name="" min="1" value="1" placeholder="Qty">
            <span class="item-price">Rs. 0.00</span>
            <span class="item-total">Rs. 0.00</span>
            <button type="button" class="btn-danger remove-item-btn">×</button>
        </div>
    </template>
    
    
    <script>
        // Load member info when selected
        function loadMemberInfo(memberId) {
            if (!memberId) {
                document.getElementById('memberInfo').style.display = 'none';
                return;
            }
            
            fetch('?action=get_member_info&id=' + memberId)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        document.getElementById('info-bank-number').textContent = data.bank_membership_number;
                        document.getElementById('info-credit-limit').textContent = 'Rs. ' + data.credit_limit.toLocaleString();
                        document.getElementById('info-credit-balance').textContent = 'Rs. ' + data.current_credit_balance.toLocaleString();
                        
                        const availableCredit = data.credit_limit - data.current_credit_balance;
                        document.getElementById('info-credit-available').textContent = 'Rs. ' + availableCredit.toLocaleString();
                        
                        document.getElementById('memberInfo').style.display = 'block';
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // AJAX member search
        document.getElementById('member_search').addEventListener('input', function() {
            const term = this.value.trim();
            const memberIdInput = document.getElementById('member_id');
            
            if (term.length < 2) {
                memberIdInput.value = '';
                document.getElementById('memberInfo').style.display = 'none';
                return;
            }
            
            fetch(`?action=search_members&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    const datalist = document.getElementById('member_list');
                    datalist.innerHTML = '';
                    
                    data.forEach(member => {
                        const option = document.createElement('option');
                        option.value = member.label;
                        option.setAttribute('data-id', member.id);
                        datalist.appendChild(option);
                    });
                });
        });
        
        // Handle member selection from datalist
        document.getElementById('member_search').addEventListener('change', function() {
            const memberList = document.getElementById('member_list');
            const options = memberList.querySelectorAll('option');
            const memberIdInput = document.getElementById('member_id');
            
            // Find the selected member
            for (let option of options) {
                if (option.value === this.value) {
                    memberIdInput.value = option.getAttribute('data-id');
                    loadMemberInfo(memberIdInput.value);
                    return;
                }
            }
            
            // If no match found, clear the ID
            memberIdInput.value = '';
            document.getElementById('memberInfo').style.display = 'none';
        });
        
        // AJAX item search
        function searchItems(term, callback) {
            if (term.length < 2) {
                callback([]);
                return;
            }
            
            fetch(`?action=search_items&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => callback(data))
                .catch(error => {
                    console.error('Error:', error);
                    callback([]);
                });
        }
        
        // Add new item row
        document.getElementById('add-item-btn').addEventListener('click', function() {
            const container = document.getElementById('items-container');
            const template = document.getElementById('item-row-template');
            const clone = template.content.cloneNode(true);
            
            const newRow = clone.querySelector('.item-row');
            newRow.dataset.id = Date.now();
            
            // Set proper array indexes for form submission
            const index = document.querySelectorAll('.item-row').length;
            const itemIdInput = newRow.querySelector('.item-id');
            const quantityInput = newRow.querySelector('.item-quantity');
            
            itemIdInput.name = `items[${index}][item_id]`;
            quantityInput.name = `items[${index}][quantity]`;
            
            container.appendChild(clone);
            
            // Initialize the new row
            initItemRow(newRow);
        });
        
        // Initialize an item row with event listeners
        function initItemRow(row) {
            const itemSearch = row.querySelector('.item-search');
            const itemIdInput = row.querySelector('.item-id');
            const quantityInput = row.querySelector('.item-quantity');
            const priceDisplay = row.querySelector('.item-price');
            const totalDisplay = row.querySelector('.item-total');
            const removeBtn = row.querySelector('.remove-item-btn');
            
            // Create a unique datalist for this row
            const datalistId = 'item_list_' + row.dataset.id;
            const datalist = document.createElement('datalist');
            datalist.id = datalistId;
            itemSearch.setAttribute('list', datalistId);
            row.appendChild(datalist);
            
            // Handle item search
            itemSearch.addEventListener('input', function() {
                searchItems(this.value.trim(), function(data) {
                    datalist.innerHTML = '';
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.label;
                        option.setAttribute('data-id', item.id);
                        datalist.appendChild(option);
                    });
                });
            });
            
            // Handle item selection
            itemSearch.addEventListener('change', function() {
                const options = datalist.querySelectorAll('option');
                
                for (let option of options) {
                    if (option.value === this.value) {
                        const itemId = option.getAttribute('data-id');
                        itemIdInput.value = itemId;
                        
                        // Get item details
                        fetch('?action=get_item_info&id=' + itemId)
                            .then(response => response.json())
                            .then(data => {
                                if (data) {
                                    priceDisplay.textContent = 'Rs. ' + data.price_per_unit.toLocaleString();
                                    
                                    // Create a tooltip with additional info
                                    let tooltipText = `Available Quantity: ${data.current_quantity}`;
                                    
                                    if (data.has_multiple_suppliers) {
                                        tooltipText += "\nNote: This item is available from multiple suppliers";
                                    }
                                    
                                    // Update the search input with the tooltip
                                    itemSearch.title = tooltipText;
                                    
                                    // Show a notification if multiple suppliers
                                    if (data.has_multiple_suppliers) {
                                        alert("Note: This item is available from multiple suppliers. Please verify you're selecting the correct one.");
                                    }
                                    
                                    // Update quantity input max value
                                    quantityInput.max = data.current_quantity;
                                    quantityInput.placeholder = `Max: ${data.current_quantity}`;
                                    
                                    calculateRowTotal(row);
                                }
                            });
                        return;
                    }
                }
                
                // If no match found, clear the values
                itemIdInput.value = '';
                priceDisplay.textContent = 'Rs. 0.00';
                itemSearch.title = '';
                quantityInput.max = '';
                quantityInput.placeholder = 'Qty';
                calculateRowTotal(row);
            });
            
            // Handle quantity changes
            quantityInput.addEventListener('input', function() {
                calculateRowTotal(row);
            });
            
            // Remove row button
            removeBtn.addEventListener('click', function() {
                row.remove();
                calculateTotalAmount();
                reindexItemRows();
            });
        }
        
        // Reindex item rows to maintain proper array indexes
        function reindexItemRows() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach((row, index) => {
                const itemIdInput = row.querySelector('.item-id');
                const quantityInput = row.querySelector('.item-quantity');
                
                itemIdInput.name = `items[${index}][item_id]`;
                quantityInput.name = `items[${index}][quantity]`;
            });
        }
        
        // Calculate total for a single row
        function calculateRowTotal(row) {
            const quantityInput = row.querySelector('.item-quantity');
            const priceDisplay = row.querySelector('.item-price');
            const totalDisplay = row.querySelector('.item-total');
            
            const quantity = parseInt(quantityInput.value) || 0;
            const priceText = priceDisplay.textContent.replace('Rs. ', '').replace(',', '');
            const price = parseFloat(priceText) || 0;
            
            const total = quantity * price;
            totalDisplay.textContent = 'Rs. ' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            calculateTotalAmount();
        }
        
        // Calculate total amount for all items
        function calculateTotalAmount() {
            const rows = document.querySelectorAll('.item-row');
            let total = 0;
            
            rows.forEach(row => {
                const totalText = row.querySelector('.item-total').textContent.replace('Rs. ', '').replace(',', '');
                total += parseFloat(totalText) || 0;
            });
            
            document.getElementById('total-amount').textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        // Add first item row when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('add-item-btn').click();
        });
    </script>
</body>
</html>