<?php
session_start(); // Added at the top for message handling

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

$error = $success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        // Delete Supplier
        $supplier_id = $_POST['supplier_id'];
        
        $sql = "DELETE FROM supplier WHERE supplier_id = '$supplier_id'";
        
        if ($conn->query($sql) === TRUE) {
            $_SESSION['success'] = "Supplier deleted successfully!";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['error'] = "There was an error deleting the supplier. Please try again!";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Get messages from session
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View and Delete Suppliers</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg,rgb(208, 212, 232) 0%,rgb(223, 245, 254) 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }
        
        h2 {
            color: #333;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
            position: relative;
            padding-bottom: 10px;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: #667eea;
            border-radius: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        button {
            padding: 8px 15px;
            background: linear-gradient(to right, #ff4757, #dc3545);
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(220, 53, 69, 0.4);
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, #5a6fd1, #6a3fa1);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(to right, #4CAF50, #2E7D32);
            color: white;
            box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(to right, #43a047, #286c2d);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.4);
        }
        
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
            z-index: 1000;
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
        }
        
        .success-popup .popup {
            border-top: 4px solid #2ed573;
        }
        
        /* White text for success/error messages */
        .popup-container .popup h3,
        .popup-container .popup p {
            color: white !important;
        }
        
        .error-popup .popup {
            background: #ff4757;
        }
        
        .success-popup .popup {
            background: #2ed573;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body style="font-weight: bold;">
    <div class="container">
        <h2>Registered Suppliers</h2>
        
        <?php
        $sql = "SELECT supplier_id, supplier_name, address, nic, contact_number FROM supplier";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<thead><tr><th>Supplier ID</th><th>Supplier Name</th><th>Address</th><th>NIC</th><th>Contact Number</th><th>Actions</th></tr></thead>";
            echo "<tbody>";
            
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["supplier_id"] . "</td>";
                echo "<td>" . $row["supplier_name"] . "</td>";
                echo "<td>" . $row["address"] . "</td>";
                echo "<td>" . $row["nic"] . "</td>";
                echo "<td>" . $row["contact_number"] . "</td>";
                echo "<td>";
                echo "<form method='post' style='display: inline;'>";
                echo "<input type='hidden' name='action' value='delete'>";
                echo "<input type='hidden' name='supplier_id' value='" . $row["supplier_id"] . "'>";
                echo "<button type='submit' onclick='return confirm(\"Are you sure you want to delete this supplier? This action cannot be undone.\")'>Delete</button>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p style='text-align: center; color: #666;'>No suppliers registered yet.</p>";
        }
        ?>
        
        <div class="btn-group">
            <a href="supplier.php" class="btn btn-primary">Supplier Registration</a>
            <a href="purchase2.php" class="btn btn-secondary">Item Purchases</a>
        </div>
    </div>

    <!-- Error Popup -->
    <div class="popup-container error-popup" id="errorPopup">
        <div class="popup">
            <span class="close">&times;</span>
            <h3>Error</h3>
            <p><?php echo $error; ?></p>
        </div>
    </div>

    <!-- Success Popup -->
    <div class="popup-container success-popup" id="successPopup">
        <div class="popup">
            <span class="close">&times;</span>
            <h3>Success</h3>
            <p><?php echo $success; ?></p>
        </div>
    </div>

    <script>
        // Show popups if there are messages
        <?php if($error): ?>
            document.getElementById('errorPopup').classList.add('active');
        <?php endif; ?>
        
        <?php if($success): ?>
            document.getElementById('successPopup').classList.add('active');
        <?php endif; ?>
        
        // Close popups
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', function() {
                this.closest('.popup-container').classList.remove('active');
                window.location.href = window.location.href.split('?')[0];
            });
        });
        
        // Close popups when clicking outside
        document.querySelectorAll('.popup-container').forEach(popup => {
            popup.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    window.location.href = window.location.href.split('?')[0];
                }
            });
        });
    </script>
</body>
</html>