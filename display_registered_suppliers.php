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
    } elseif (isset($_POST['action']) && $_POST['action'] == 'update') {
        // Update Supplier
        $supplier_id = $_POST['supplier_id'];
        $supplier_name = $_POST['supplier_name'];
        $address = $_POST['address'];
        $nic = $_POST['nic'];
        $contact_number = $_POST['contact_number'];
        
        $sql = "UPDATE supplier SET 
                supplier_name = '$supplier_name',
                address = '$address',
                nic = '$nic',
                contact_number = '$contact_number'
                WHERE supplier_id = '$supplier_id'";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Supplier updated successfully!";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['error'] = "Error updating supplier: " . $conn->error;
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
            background: linear-gradient(135deg,rgb(208, 212, 232) 0%,rgb(223, 245, 254) 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .container {
            width: 98%;
            max-width: 1800px;
            margin: 30px auto;
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
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
            margin-top: 30px;
            font-size: 1.45em;
        }
        
        th, td {
            padding: 22px 28px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 1.1em;
            letter-spacing: 0.7px;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        button {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
        }
        
        .delete-btn {
            background: linear-gradient(to right, #ff4757, #dc3545);
        }
        
        .edit-btn {
            background: linear-gradient(to right, #4CAF50, #2E7D32);
            margin-right: 10px;
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
            background: linear-gradient(to right,rgb(31, 42, 32), #2E7D32);
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
        
        /* Edit form styles */
        .edit-form-container {
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
        
        .edit-form-container.active {
            opacity: 1;
            visibility: visible;
        }
        
        .edit-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 500px;
            max-width: 90%;
            position: relative;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        
        .edit-form-container.active .edit-form {
            transform: translateY(0);
        }
        
        .edit-form h3 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .form-actions button {
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .save-btn {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
        }
        
        .cancel-btn {
            background: #f1f1f1;
            color: #333;
            border: 1px solid #ddd;
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
        <h2 style="font-size: 35px;">Registered Suppliers</h2>
        
        <?php
        $sql = "SELECT supplier_id, supplier_name, address, nic, contact_number FROM supplier";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<thead><tr><th style='font-size:20px;'>Supplier ID</th><th style='font-size:20px;'>Supplier Name</th><th style='font-size:20px;'>Address</th><th style='font-size:20px;'>NIC</th><th style='font-size:20px;'>Contact Number</th><th style='font-size:20px;'>Actions</th></tr></thead>";
            echo "<tbody>";
            
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td style='font-size:20px;'>" . $row["supplier_id"] . "</td>";
                echo "<td style='font-size:20px;'>" . $row["supplier_name"] . "</td>";
                echo "<td style='font-size:20px;'>" . $row["address"] . "</td>";
                echo "<td style='font-size:20px;'>" . $row["nic"] . "</td>";
                echo "<td style='font-size:20px;'>" . $row["contact_number"] . "</td>";
                echo "<td>";
                echo "<button class='edit-btn' onclick='openEditForm(" . json_encode($row) . ")'>Edit</button>";
                echo "<form method='post' style='display: inline;'>";
                echo "<input type='hidden' name='action' value='delete'>";
                echo "<input type='hidden' name='supplier_id' value='" . $row["supplier_id"] . "'>";
                echo "<button type='submit' class='delete-btn' onclick='return confirm(\"Are you sure you want to delete this supplier? This action cannot be undone.\")'>Delete</button>";
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
            <a href="supplier.php" class="btn btn-primary" style="font-size: 20px;">Supplier Registration</a>
            <a href="new2.php" class="btn btn-secondary" style="font-size: 20px;">Item Purchases</a>
        </div>
    </div>

    <!-- Edit Form Popup -->
    <div class="edit-form-container" id="editFormContainer">
        <div class="edit-form">
            <h3>Edit Supplier Details</h3>
            <form method="post" id="editSupplierForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="supplier_id" id="editSupplierId">
                
                <div class="form-group">
                    <label for="editSupplierName">Supplier Name</label>
                    <input type="text" id="editSupplierName" name="supplier_name" required>
                </div>
                
                <div class="form-group">
                    <label for="editAddress">Address</label>
                    <input type="text" id="editAddress" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="editNic">NIC</label>
                    <input type="text" id="editNic" name="nic" required>
                </div>
                
                <div class="form-group">
                    <label for="editContactNumber">Contact Number</label>
                    <input type="text" id="editContactNumber" name="contact_number" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="cancel-btn" onclick="closeEditForm()">Cancel</button>
                    <button type="submit" class="save-btn">Save Changes</button>
                </div>
            </form>
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
    <a href="home.php" class="btn btn-primary floating-btn animate__animated animate__fadeInUp">
        <i class="fas fa-home"></i>
    </a>

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
        
        // Edit form functions
        function openEditForm(supplier) {
            document.getElementById('editSupplierId').value = supplier.supplier_id;
            document.getElementById('editSupplierName').value = supplier.supplier_name;
            document.getElementById('editAddress').value = supplier.address;
            document.getElementById('editNic').value = supplier.nic;
            document.getElementById('editContactNumber').value = supplier.contact_number;
            document.getElementById('editFormContainer').classList.add('active');
        }
        
        function closeEditForm() {
            document.getElementById('editFormContainer').classList.remove('active');
        }
        
        // Close edit form when clicking outside
        document.getElementById('editFormContainer').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditForm();
            }
        });
    </script>
</body>
</html>