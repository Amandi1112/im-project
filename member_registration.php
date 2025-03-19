<?php
// Start the session at the very beginning
session_start();

// Database connection parameters
$host = 'localhost';
$dbname = 'mywebsite'; // Replace with your database name
$username = 'root';    // Replace with your database username
$password = '';    // Replace with your database password

try {
    // Create a PDO connection to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Function to generate a unique membership number starting with "C"
    function generateMembershipNumber($pdo) {
        // Generate a random 5-digit number prefixed with "C"
        do {
            $randomNumber = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $membershipNumber = "C" . $randomNumber;

            // Check if the generated membership number already exists in the database
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM membership_numbers WHERE membership_number = :membership_number");
            $stmt->execute([':membership_number' => $membershipNumber]);
            $count = $stmt->fetchColumn();
        } while ($count > 0); // Repeat until a unique membership number is found

        return $membershipNumber;
    }

    // Check if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get the NIC from the POST request
        $nic = trim($_POST['nic']);

        if (!empty($nic)) {
            // First check if the NIC already exists in the membership_numbers table
            $checkStmt = $pdo->prepare("SELECT membership_number FROM membership_numbers WHERE nic_number = :nic");
            $checkStmt->execute([':nic' => $nic]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingRecord) {
                // NIC already has a membership number, simply return it
                $membershipNumber = $existingRecord['membership_number'];
                $_SESSION['message_type'] = 'success';
                $_SESSION['message'] = "NIC already has a membership number: <strong>$membershipNumber</strong>";
                
            } else {
                // NIC does not have a membership number, generate a new one
                $membershipNumber = generateMembershipNumber($pdo);

                // Check if the NIC exists in members table
                $memberStmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE nic_number = :nic");
                $memberStmt->execute([':nic' => $nic]);
                $memberExists = $memberStmt->fetchColumn();


                // Store the membership number in the membership_numbers table
                $storeStmt = $pdo->prepare("
                    INSERT INTO membership_numbers (membership_number, nic_number)
                    VALUES (:membership_number, :nic_number)
                ");
                $storeStmt->execute([
                    ':membership_number' => $membershipNumber,
                    ':nic_number' => $nic
                ]);

                $_SESSION['message_type'] = 'success';
                $_SESSION['message'] = "Membership Number generated and stored: <strong>$membershipNumber</strong>";
            }

        } else {
            $_SESSION['message_type'] = 'error';
            $_SESSION['message'] = "NIC cannot be empty.";
        }
    }
} catch (PDOException $e) {
    // Handle database connection errors
    $_SESSION['message_type'] = 'error';
    $_SESSION['message'] = "Database error: " . $e->getMessage();
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
        }
        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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
        .notification-icon {
            margin-right: 15px;
            font-size: 20px;
        }
        .notification-content {
            flex: 1;
        }
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 40px 20px;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            width: 450px;
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            font-size: 16px;
            color: #555;
            margin-bottom: 8px;
            display: block;
            font-weight: 500;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        input[type="text"]:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        .button-container {
            text-align: center;
            margin-top: 25px;
        }
        button {
            background-color: #4a90e2;
            color: #fff;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.1s;
            font-weight: 500;
        }
        button:hover {
            background-color: #3a7bbd;
        }
        button:active {
            transform: scale(0.98);
        }
        .actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .btn {
            background-color: rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body style="background:url(images/background60.jpg)">
    <div class="page-wrapper">
        <!-- Notification area outside of the main container -->
        <div class="notification-area">
            <?php
            // Display message if it exists and then clear it
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

        <div class="main-content">
            <div class="container">
                <h1>Generate Membership Number</h1>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nic">Enter NIC:</label>
                        <input type="text" id="nic" name="nic" required autocomplete="off">
                    </div>
                    
                    <div class="button-container">
                        <div class="actions">
                            <button type="submit" name="generate" class="btn">Generate Number</button>
                            <a href="display_coop_number.php" class="btn">View Membership Numbers</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>