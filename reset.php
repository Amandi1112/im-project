<?php
// Database connection
$host = '127.0.0.1';
$dbname = 'mywebsite';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Start session for flash messages
session_start();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'search_members' && isset($_GET['term'])) {
        $term = '%' . $_GET['term'] . '%';
        $stmt = $pdo->prepare("
            SELECT id, bank_membership_number, full_name, current_credit_balance, credit_limit,
                   CONCAT(full_name, ' (ID: ', id, ', Bank: ', bank_membership_number, ')') as label 
            FROM members 
            WHERE (full_name LIKE ? OR id LIKE ? OR bank_membership_number LIKE ?) 
            AND current_credit_balance > 0
            ORDER BY full_name
            LIMIT 15
        ");
        $stmt->execute([$term, $term, $term]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        exit;
    }
    
    if ($_GET['action'] == 'get_member_details' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   COUNT(p.purchase_id) as total_purchases,
                   SUM(p.total_price) as total_purchase_amount,
                   MAX(p.purchase_date) as last_purchase_date
            FROM members m
            LEFT JOIN purchases p ON m.id = p.member_id
            WHERE m.id = ?
            GROUP BY m.id
        ");
        $stmt->execute([$_GET['id']]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($member ? $member : null);
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = '';
    $error = '';
    
    if (isset($_POST['reset_single_member'])) {
        $memberId = $_POST['member_id'];
        $resetAmount = (float)$_POST['reset_amount'];
        $paymentNote = $_POST['payment_note'] ?? '';
        
        if (empty($memberId)) {
            $error = "Please select a member to reset.";
        } elseif ($resetAmount < 0) {
            $error = "Reset amount cannot be negative.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get current member details
                $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                $stmt->execute([$memberId]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$member) {
                    throw new Exception("Member not found.");
                }
                
                // Calculate new balance
                if ($resetAmount == 0) {
                    $newBalance = 0;
                    $resetAmount = $member['current_credit_balance'];
                } else {
                    $newBalance = max(0, $member['current_credit_balance'] - $resetAmount);
                }
                
                // Update member's credit balance
                $stmt = $pdo->prepare("
                    UPDATE members 
                    SET current_credit_balance = ?,
                        last_updated = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$newBalance, $memberId]);
                
                // Update all purchases for this member with the new balance
                $stmt = $pdo->prepare("
                    UPDATE purchases 
                    SET current_credit_balance = ?
                    WHERE member_id = ?
                ");
                $stmt->execute([$newBalance, $memberId]);
                
                // Log the reset activity
                $logNote = "Credit balance reset from Rs. " . number_format($member['current_credit_balance'], 2) . 
                          " to Rs. " . number_format($newBalance, 2);
                if (!empty($paymentNote)) {
                    $logNote .= " - Note: " . $paymentNote;
                }
                
                $pdo->commit();
                $success = "Credit balance successfully reset for member: " . $member['full_name'] . 
                          " (ID: " . $memberId . "). " . $logNote;
                           header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to reset credit balance: " . $e->getMessage();
                 header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
            }
        }
    }
    
    if (isset($_POST['reset_multiple_members'])) {
        $memberIds = $_POST['member_ids'] ?? [];
        $resetType = $_POST['reset_type'];
        $customAmount = (float)($_POST['custom_amount'] ?? 0);
        $batchNote = $_POST['batch_note'] ?? '';
        
        if (empty($memberIds)) {
            $error = "Please select at least one member to reset.";
        } else {
            try {
                $pdo->beginTransaction();
                $resetCount = 0;
                $totalResetAmount = 0;
                
                foreach ($memberIds as $memberId) {
                    // Get current member details
                    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                    $stmt->execute([$memberId]);
                    $member = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$member) continue;
                    
                    $newBalance = 0;
                    $resetAmount = 0;
                    
                    switch ($resetType) {
                        case 'full_reset':
                            $resetAmount = $member['current_credit_balance'];
                            $newBalance = 0;
                            break;
                        case 'half_reset':
                            $resetAmount = $member['current_credit_balance'] / 2;
                            $newBalance = $member['current_credit_balance'] - $resetAmount;
                            break;
                        case 'custom_amount':
                            $resetAmount = min($customAmount, $member['current_credit_balance']);
                            $newBalance = $member['current_credit_balance'] - $resetAmount;
                            break;
                    }
                    
                    // Update member's credit balance
                    $stmt = $pdo->prepare("
                        UPDATE members 
                        SET current_credit_balance = ?,
                            last_updated = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$newBalance, $memberId]);
                    
                    // Update all purchases for this member with the new balance
                    $stmt = $pdo->prepare("
                        UPDATE purchases 
                        SET current_credit_balance = ?
                        WHERE member_id = ?
                    ");
                    $stmt->execute([$newBalance, $memberId]);
                    
                    $resetCount++;
                    $totalResetAmount += $resetAmount;
                }
                
                $pdo->commit();
    $_SESSION['success_message'] = "Successfully reset credit balances for {$resetCount} members. " .
                      "Total amount reset: Rs. " . number_format($totalResetAmount, 2);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
    
                
            } catch (Exception $e) {
                 $pdo->rollBack();
    $_SESSION['error_message'] = "Failed to reset credit balances: " . $e->getMessage();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
            }
        }
    }
    
    // Store messages in session and redirect (PRG Pattern)
    if (!empty($success)) {
        $_SESSION['success_message'] = $success;
    }
    if (!empty($error)) {
        $_SESSION['error_message'] = $error;
    }
    
    // Redirect to prevent resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve flash messages from session
$success = '';
$error = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get members with outstanding credit balances
$membersWithCredit = $pdo->query("
    SELECT id, full_name, bank_membership_number, current_credit_balance, credit_limit,
           (credit_limit - current_credit_balance) as available_credit
    FROM members 
    WHERE current_credit_balance > 0
    ORDER BY current_credit_balance DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_members_with_credit,
        SUM(current_credit_balance) as total_outstanding_credit,
        AVG(current_credit_balance) as avg_credit_balance,
        MAX(current_credit_balance) as max_credit_balance
    FROM members 
    WHERE current_credit_balance > 0
")->fetch(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Balance Reset System</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1, h2, h3 {
            color: #2c3e50;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        textarea {
            resize: vertical;
            height: 80px;
        }
        
        button {
            background: linear-gradient(to right, #28a745, #218838);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
        }
        
        button:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(to right, #dc3545, #c82333);
        }
        
        .btn-danger:hover {
            background: linear-gradient(to right, #c82333, #bd2130);
        }
        
        .btn-warning {
            background: linear-gradient(to right, #ffc107, #e0a800);
        }
        
        .btn-warning:hover {
            background: linear-gradient(to right, #e0a800, #d39e00);
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
        
        .member-info {
            display: none;
            padding: 15px;
            background: #e8f4fc;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .info-item {
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #7f8c8d;
        }
        
        .reset-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .reset-option {
            flex: 1;
            min-width: 200px;
        }
        
        .checkbox-group {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .checkbox-item:last-child {
            border-bottom: none;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
        }
        
        .member-balance {
            color: #dc3545;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .reset-options {
                flex-direction: column;
            }
            
            .reset-option {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Credit Balance Reset System</h1>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Statistics Section -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_members_with_credit']; ?></div>
                <div class="stat-label">Members with Outstanding Credit</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">Rs. <?php echo number_format($stats['total_outstanding_credit'], 2); ?></div>
                <div class="stat-label">Total Outstanding Credit</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">Rs. <?php echo number_format($stats['avg_credit_balance'], 2); ?></div>
                <div class="stat-label">Average Credit Balance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">Rs. <?php echo number_format($stats['max_credit_balance'], 2); ?></div>
                <div class="stat-label">Highest Credit Balance</div>
            </div>
        </div>
        
        <!-- Single Member Reset Section -->
        <div class="section">
            <h2>Reset Individual Member Credit</h2>
            <form method="POST" action="">
                <input type="hidden" name="reset_single_member" value="1">
                
                <div class="form-group">
                    <label for="member_search">Search Member</label>
                    <input type="text" id="member_search" name="member_search" 
                           placeholder="Start typing member name, ID, or bank membership number..." autocomplete="off">
                    <input type="hidden" id="member_id" name="member_id">
                </div>
                
                <div id="memberInfo" class="member-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Full Name:</span>
                            <span id="info-name"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Bank Membership:</span>
                            <span id="info-bank-number"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Current Credit Balance:</span>
                            <span id="info-credit-balance" class="member-balance"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Credit Limit:</span>
                            <span id="info-credit-limit"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Purchases:</span>
                            <span id="info-total-purchases"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Purchase:</span>
                            <span id="info-last-purchase"></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reset_amount">Payment Amount (Leave 0 for full reset)</label>
                    <input type="number" id="reset_amount" name="reset_amount" min="0" step="0.01" value="0" 
                           placeholder="Enter payment amount received">
                </div>
                
                <div class="form-group">
                    <label for="payment_note">Payment Note</label>
                    <textarea id="payment_note" name="payment_note" 
                              placeholder="Optional note about the payment (e.g., bank payment reference, date, etc.)"></textarea>
                </div>
                
                <button type="submit">Reset Credit Balance</button>
            </form>
        </div>
        
        <!-- Batch Reset Section -->
        <div class="section">
            <h2>Batch Reset Multiple Members</h2>
            <form method="POST" action="">
                <input type="hidden" name="reset_multiple_members" value="1">
                
                <div class="form-group">
                    <label>Select Reset Type:</label>
                    <div class="reset-options">
                        <div class="reset-option">
                            <input type="radio" id="full_reset" name="reset_type" value="full_reset" checked>
                            <label for="full_reset">Full Reset (Clear all balances)</label>
                        </div>
                        <div class="reset-option">
                            <input type="radio" id="half_reset" name="reset_type" value="half_reset">
                            <label for="half_reset">50% Reset</label>
                        </div>
                        <div class="reset-option">
                            <input type="radio" id="custom_amount" name="reset_type" value="custom_amount">
                            <label for="custom_amount">Custom Amount</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="custom_amount_group" style="display:none;">
                    <label for="custom_amount_input">Custom Reset Amount (per member)</label>
                    <input type="number" id="custom_amount_input" name="custom_amount" min="0" step="0.01" 
                           placeholder="Enter amount to reset from each member's balance">
                </div>
                
                <div class="form-group">
                    <label for="batch_note">Batch Reset Note</label>
                    <textarea id="batch_note" name="batch_note" 
                              placeholder="Optional note for this batch reset operation"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Select Members to Reset:</label>
                    <div class="checkbox-group">
                        <?php foreach ($membersWithCredit as $member): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="member_ids[]" value="<?php echo $member['id']; ?>" 
                                       id="member_<?php echo $member['id']; ?>">
                                <label for="member_<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['full_name']); ?> 
                                    (ID: <?php echo $member['id']; ?>, Bank: <?php echo $member['bank_membership_number']; ?>) 
                                    - <span class="member-balance">Rs. <?php echo number_format($member['current_credit_balance'], 2); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="button" id="select_all">Select All</button>
                    <button type="button" id="deselect_all" class="btn-warning">Deselect All</button>
                    <button type="submit" class="btn-danger">Reset Selected Members</button>
                </div>
            </form>
        </div>
        
        <!-- Members with Outstanding Credit -->
        <div class="section">
            <h2>Members with Outstanding Credit Balances</h2>
            <?php if (count($membersWithCredit) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Bank Membership</th>
                            <th>Current Credit Balance</th>
                            <th>Credit Limit</th>
                            <th>Available Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membersWithCredit as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['id']); ?></td>
                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['bank_membership_number']); ?></td>
                                <td class="member-balance">Rs. <?php echo number_format($member['current_credit_balance'], 2); ?></td>
                                <td>Rs. <?php echo number_format($member['credit_limit'], 2); ?></td>
                                <td>Rs. <?php echo number_format($member['available_credit'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No members with outstanding credit balances found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // AJAX member search for single reset
        document.getElementById('member_search').addEventListener('input', function() {
            const term = this.value.trim();
            
            if (term.length < 2) {
                document.getElementById('member_id').value = '';
                document.getElementById('memberInfo').style.display = 'none';
                return;
            }
            
            fetch(`?action=search_members&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    // Create a dropdown-like display
                    let dropdown = document.getElementById('member_dropdown');
                    if (!dropdown) {
                        dropdown = document.createElement('div');
                        dropdown.id = 'member_dropdown';
                        dropdown.style.cssText = `
                            position: absolute;
                            background: white;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            max-height: 200px;
                            overflow-y: auto;
                            width: 100%;
                            z-index: 1000;
                            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                        `;
                        this.parentNode.style.position = 'relative';
                        this.parentNode.appendChild(dropdown);
                    }
                    
                    dropdown.innerHTML = '';
                    data.forEach(member => {
                        const option = document.createElement('div');
                        option.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;';
                        option.innerHTML = `
                            <strong>${member.full_name}</strong><br>
                            <small>ID: ${member.id} | Bank: ${member.bank_membership_number} | 
                            Balance: Rs. ${parseFloat(member.current_credit_balance).toLocaleString()}</small>
                        `;
                        option.addEventListener('click', () => {
                            document.getElementById('member_search').value = member.label;
                            document.getElementById('member_id').value = member.id;
                            loadMemberDetails(member.id);
                            dropdown.style.display = 'none';
                        });
                        option.addEventListener('mouseover', () => {
                            option.style.backgroundColor = '#f5f5f5';
                        });
                        option.addEventListener('mouseout', () => {
                            option.style.backgroundColor = 'white';
                        });
                        dropdown.appendChild(option);
                    });
                });
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('member_dropdown');
            if (dropdown && !e.target.closest('#member_search') && !e.target.closest('#member_dropdown')) {
                dropdown.style.display = 'none';
            }
        });
        
        // Load member details
        function loadMemberDetails(memberId) {
            fetch(`?action=get_member_details&id=${memberId}`)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        document.getElementById('info-name').textContent = data.full_name;
                        document.getElementById('info-bank-number').textContent = data.bank_membership_number;
                        document.getElementById('info-credit-balance').textContent = 'Rs. ' + parseFloat(data.current_credit_balance).toLocaleString();
                        document.getElementById('info-credit-limit').textContent = 'Rs. ' + parseFloat(data.credit_limit).toLocaleString();
                        document.getElementById('info-total-purchases').textContent = data.total_purchases || '0';
                        document.getElementById('info-last-purchase').textContent = data.last_purchase_date || 'No purchases';
                        
                        document.getElementById('memberInfo').style.display = 'block';
                        
                        // Set the reset amount to current balance by default
                        document.getElementById('reset_amount').value = data.current_credit_balance;
                    }
                });
        }
        
        // Show/hide custom amount input
        document.querySelectorAll('input[name="reset_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customGroup = document.getElementById('custom_amount_group');
                if (this.value === 'custom_amount') {
                    customGroup.style.display = 'block';
                } else {
                    customGroup.style.display = 'none';
                }
            });
        });
        
        // Select/Deselect all checkboxes
        document.getElementById('select_all').addEventListener('click', function() {
            document.querySelectorAll('input[name="member_ids[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        
        document.getElementById('deselect_all').addEventListener('click', function() {
            document.querySelectorAll('input[name="member_ids[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
        
        // Confirm batch reset
        document.querySelector('button[type="submit"].btn-danger').addEventListener('click', function(e) {
            const checkedBoxes = document.querySelectorAll('input[name="member_ids[]"]:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one member to reset.');
                return;
            }
            
            const resetType = document.querySelector('input[name="reset_type"]:checked').value;
            let confirmMessage = `Are you sure you want to reset credit balances for ${checkedBoxes.length} members?`;
            
            if (resetType === 'full_reset') {
                confirmMessage += '\n\nThis will completely clear all their credit balances to Rs. 0.00';
            } else if (resetType === 'half_reset') {
                confirmMessage += '\n\nThis will reduce their credit balances by 50%';
            } else {
                const customAmount = document.getElementById('custom_amount_input').value;
                confirmMessage += `\n\nThis will reduce each member's balance by Rs. ${customAmount}`;
            }
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>