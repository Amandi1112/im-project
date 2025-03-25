<?php
// Start the session at the very beginning
session_start();

// Database connection parameters
$host = 'localhost';
$dbname = 'mywebsite';
$username = 'root';
$password = '';

class MembershipManager {
    private $pdo;

    public function __construct($host, $dbname, $username, $password) {
        try {
            // Create a PDO connection to the database
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->handleError("Database Connection Error: " . $e->getMessage());
        }
    }

    /**
     * Generate a unique membership number
     * @return string Unique membership number
     */
    private function generateMembershipNumber() {
        do {
            $randomNumber = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $membershipNumber = "C" . $randomNumber;

            // Check if the generated membership number already exists
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM membership_numbers WHERE membership_number = :membership_number");
            $stmt->execute([':membership_number' => $membershipNumber]);
            $count = $stmt->fetchColumn();
        } while ($count > 0);

        return $membershipNumber;
    }

    /**
     * Calculate age from NIC number
     * @param string $nic NIC number
     * @return int|null Calculated age or null if invalid
     */
    public function calculateAgeFromNIC($nic) {
        // Remove any whitespace and convert to uppercase
        $nic = strtoupper(trim($nic));
        
        // Validate NIC format
        if (strlen($nic) == 10) {
            // Old NIC format (YYXXXXXXXXX)
            $birthYear = 1900 + intval(substr($nic, 0, 2));
            $daysCode = intval(substr($nic, 2, 3));
            
            // Adjust for female NIC (days > 500)
            if ($daysCode > 500) {
                $birthYear += 100;
                $daysCode -= 500;
            }
        } elseif (strlen($nic) == 12) {
            // New NIC format (YYYYXXXXXXXXXX)
            $birthYear = intval(substr($nic, 0, 4));
            $daysCode = intval(substr($nic, 4, 3));
        } else {
            // Invalid NIC format
            return null;
        }
        
        // Calculate current age
        $currentYear = date('Y');
        $age = $currentYear - $birthYear;
        
        // Adjust age based on birthday
        $birthdayThisYear = date('Y-m-d', mktime(0, 0, 0, 1, $daysCode, $currentYear));
        
        if (strtotime($birthdayThisYear) > strtotime('today')) {
            $age--; // Haven't had birthday this year yet
        }
        
        return $age;
    }

    /**
     * Process membership number generation
     * @param string $nic NIC number
     * @return array Result of membership number generation
     */
    public function processMembershipNumber($nic) {
        // Validate NIC
        if (empty($nic)) {
            return [
                'status' => 'error',
                'message' => "NIC cannot be empty."
            ];
        }

        try {
            // Check if NIC already has a membership number
            $checkStmt = $this->pdo->prepare("SELECT membership_number FROM membership_numbers WHERE nic_number = :nic");
            $checkStmt->execute([':nic' => $nic]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // Calculate age
            $age = $this->calculateAgeFromNIC($nic);

            if ($existingRecord) {
                // NIC already has a membership number
                return [
                    'status' => 'success',
                    'membership_number' => $existingRecord['membership_number'],
                    'age' => $age,
                    'message' => "NIC already has a membership number"
                ];
            }

            // Generate new membership number
            $membershipNumber = $this->generateMembershipNumber();

            // Store the membership number
            $storeStmt = $this->pdo->prepare("
                INSERT INTO membership_numbers (membership_number, nic_number)
                VALUES (:membership_number, :nic_number)
            ");
            $storeStmt->execute([
                ':membership_number' => $membershipNumber,
                ':nic_number' => $nic
            ]);

            return [
                'status' => 'success',
                'membership_number' => $membershipNumber,
                'age' => $age,
                'message' => "Membership Number generated and stored"
            ];

        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => "Database error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Handle and log errors
     * @param string $message Error message
     */
    private function handleError($message) {
        // In a production environment, you might want to log this to a file
        error_log($message);
        
        // Set session error message
        $_SESSION['message_type'] = 'error';
        $_SESSION['message'] = $message;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $membershipManager = new MembershipManager($host, $dbname, $username, $password);
    $result = $membershipManager->processMembershipNumber($_POST['nic']);

    // Set session messages based on result
    $_SESSION['message_type'] = $result['status'];
    
    // Construct detailed message
    $message = $result['message'];
    if (isset($result['membership_number'])) {
        $message .= ": <strong>{$result['membership_number']}</strong>";
    }
    if ($result['age'] !== null) {
        $message .= " | Age: <strong>{$result['age']}</strong> years";
    }
    
    $_SESSION['message'] = $message;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Membership Number</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #333;
            background: url(images/background60.jpg) no-repeat center center fixed;
            background-size: cover;
        }
        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: rgba(255,255,255,0.8);
        }
        .notification-area {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 350px;
            z-index: 1000;
        }
        .notification {
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateX(50px);
            animation: slideIn 0.3s forwards, fadeOut 0.5s 5s forwards;
        }
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        .notification-success {
            background-color: #e3f8e5;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .notification-error {
            background-color: #fae3e5;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 40px 20px;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 450px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .actions {
            display: flex;
            justify-content: space-between;
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-generate {
            background-color: #4a90e2;
            color: white;
        }
        .btn-view {
            background-color: rgb(135, 74, 0);
            color: white;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Notification Area -->
        <div class="notification-area">
            <?php
            if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
                $icon = ($_SESSION['message_type'] === 'success') ? '✓' : '✕';
                echo '<div class="notification notification-' . $_SESSION['message_type'] . '">';
                echo '<div class="notification-icon">' . $icon . '</div>';
                echo '<div class="notification-content">' . $_SESSION['message'] . '</div>';
                echo '</div>';
                
                // Clear the message
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
            ?>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <h1>Generate Membership Number</h1>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nic">Enter NIC:</label>
                        <input type="text" id="nic" name="nic" required autocomplete="off" 
                               placeholder="Enter 10 or 12 digit NIC" 
                               pattern="[0-9]{10}|[0-9]{12}" 
                               title="NIC must be 10 or 12 digits">
                    </div>
                    <div class="form-group">
                        <label for="nic">Enter NIC:</label>
                        <input type="text" id="nic" name="nic" required autocomplete="off">
                    </div>
                                    <!-- Add this new form group -->
                    <div class="form-group">
                        <label for="age">Calculated Age:</label>
                        <input type="text" id="age" name="age" 
                            value="<?php echo isset($result['age']) ? $result['age'] : ''; ?>" 
                            readonly 
                            class="form-control" 
                            placeholder="Age will be calculated automatically">
                    </div>

                    
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-generate">Generate Number</button>
                        <a href="display_coop_number.php" class="btn btn-view">View Membership Numbers</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>