<?php
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
    // Check if all required fields are set
    if (isset($_POST['supplier_name']) && isset($_POST['nic']) && isset($_POST['address'])) {
        $supplier_name = $_POST['supplier_name'];
        $nic = $_POST['nic'];
        $address = $_POST['address'];
        $reg_date = date('Y-m-d H:i:s');

        // Generate supplier_id starting with 's' followed by 5 numbers
        $lastIdQuery = "SELECT MAX(CAST(SUBSTR(supplier_id, 2) AS UNSIGNED)) AS last_id FROM supplier WHERE supplier_id LIKE 'S%'";

        $result = $conn->query($lastIdQuery);
        $row = $result->fetch_assoc();
        $lastId = $row['last_id'];

        if ($lastId === NULL) {
            $supplier_id = 'S00001';
        } else {
            $supplier_id = 'S' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
        }

        // Validation
        if (!preg_match('/^[0-9]{9}[Vv]|^[0-9]{12}$/', $nic)) {
            $error = "Invalid NIC format! Please use either the old format (9 digits + V, e.g., 123456789V) or the new format (12 digits).";
        } else {
            // Check if supplier already exists
            $stmt = $conn->prepare("SELECT supplier_id FROM supplier WHERE nic = ?");
            $stmt->bind_param("s", $nic);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = "Supplier with this NIC already exists!";
            } else {
                // Insert new supplier
                $stmt = $conn->prepare("INSERT INTO supplier (supplier_id, supplier_name, nic, address, registration_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $supplier_id, $supplier_name, $nic, $address, $reg_date);

                if ($stmt->execute()) {
                    $success = "Supplier registered successfully!";
                } else {
                    if ($conn->errno == 1062) {
                        $error = "Supplier ID already exists! This should not happen.";
                    } else {
                        $error = "Error: " . $conn->error;
                    }
                }
                $stmt->close();
            }
        }
    } else {
        $error = "All fields are required!";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Registration</title>
    <style>
        body {
            background:url("images/background60.jpg");
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
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
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], 
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color:rgb(135, 74, 0);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background-color:rgb(221, 125, 35);
        }
        .note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Custom popup styles */
        .popup-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
        }
        .popup {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            width: 300px;
        }
        .popup .close {
            float: right;
            font-size: 18px;
            cursor: pointer;
        }
        .popup .close:hover {
            color: red;
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
<h2 style="text-shadow: 2px 2px 5px lightblue; font-size: 30px;">Supplier Registration Form</h2>
<br><br>
    <div class="container">
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label>Supplier Name:</label>
                <input type="text" name="supplier_name" required>
            </div>
            
            <div class="form-group">
                <label>NIC:</label>
                <input type="text" name="nic" required>
                <p class="note" style="color:black">Format: 123456789V or 123456789012</p>
            </div>
            
            <div class="form-group">
                <label>Address:</label>
                <input type="text" name="address" required>
            </div>
            
            <button type="submit">Register Supplier</button>
        </form>
    </div>
    <br><br>
    <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Back to Home Page</a>
        <a href="display_registered_suppliers.php" class="home-btn">View Registered Suppliers</a>
    </div>
    <?php if($error): ?>
    <div class="popup-container" id="errorPopup">
        <div class="popup">
            <span class="close">&times;</span>
            <h3>Error</h3>
            <p><?php echo $error; ?></p>
        </div>
    </div>
    <script>
        document.getElementById('errorPopup').style.display = 'flex';
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('errorPopup').style.display = 'none';
        });
    </script>
    <?php endif; ?>

    <?php if($success): ?>
    <div class="popup-container" id="successPopup">
        <div class="popup">
            <span class="close">&times;</span>
            <h3>Success</h3>
            <p><?php echo $success; ?></p>
        </div>
    </div>
    <script>
        document.getElementById('successPopup').style.display = 'flex';
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('successPopup').style.display = 'none';
            window.location.href = window.location.href; // Clear form
        });
    </script>
    <?php endif; ?>
</body>
</html>
