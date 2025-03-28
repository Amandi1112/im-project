<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mywebsite');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize messages
$error = $success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required fields
    $required_fields = ['supplier_name', 'nic', 'address', 'contact_number'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $error = "All fields are required!";
            break;
        }
    }

    if (!$error) {
        // Sanitize inputs
        $supplier_name = $conn->real_escape_string(trim($_POST['supplier_name']));
        $nic = $conn->real_escape_string(trim($_POST['nic']));
        $address = $conn->real_escape_string(trim($_POST['address']));
        $contact_number = $conn->real_escape_string(trim($_POST['contact_number']));
        $reg_date = date('Y-m-d H:i:s');

        // NIC validation
        if (!preg_match('/^[0-9]{9}[Vv]|^[0-9]{12}$/', $nic)) {
            $error = "Invalid NIC format! Please use either the old format (9 digits + V, e.g., 123456789V) or the new format (12 digits).";
        } 
        // Contact number validation
        elseif (!preg_match('/^0[0-9]{9}$/', $contact_number)) {
            $error = "Contact number must start with 0 and be exactly 10 digits long.";
        } else {
            // Check if supplier already exists
            $stmt = $conn->prepare("SELECT supplier_id FROM supplier WHERE nic = ?");
            $stmt->bind_param("s", $nic);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Supplier with this NIC already exists!";
            } else {
                // Generate supplier_id
                $lastIdQuery = "SELECT MAX(CAST(SUBSTR(supplier_id, 2) AS UNSIGNED)) AS last_id FROM supplier WHERE supplier_id LIKE 'S%'";
                $result = $conn->query($lastIdQuery);
                $row = $result->fetch_assoc();
                $lastId = $row['last_id'];
                $supplier_id = ($lastId === NULL) ? 'S00001' : 'S' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);

                // Insert new supplier
                $stmt = $conn->prepare("INSERT INTO supplier (supplier_id, supplier_name, nic, address, registration_date, contact_number) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $supplier_id, $supplier_name, $nic, $address, $reg_date, $contact_number);

                if ($stmt->execute()) {
                    $success = "Supplier registered successfully! Supplier ID: $supplier_id";
                } else {
                    $error = $conn->errno == 1062 
                        ? "Supplier ID already exists! This should not happen." 
                        : "Error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Registration | Beautiful Interface</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        
        .container {
            width: 100%;
            max-width: 450px; /* Reduced from 500px */
            padding: 15px;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px; /* Reduced from 40px */
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); /* Slightly lighter shadow */
            text-align: center;
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .form-container:hover {
            transform: translateY(-3px); /* Reduced from -5px */
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .form-logo {
            width: 70px; /* Reduced from 80px */
            height: 70px; /* Reduced from 80px */
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px; /* Reduced from 20px */
            border: 2px solid rgba(102, 126, 234, 0.3); /* Thinner border */
        }
        
        h2 {
            color: #333;
            margin-bottom: 20px; /* Reduced from 30px */
            font-weight: 600;
            font-size: 24px; /* Reduced from 28px */
        }
        
        .form-group {
            margin-bottom: 15px; /* Reduced from 20px */
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 6px; /* Reduced from 8px */
            color: #555;
            font-weight: 500;
            font-size: 14px; /* Added for consistency */
        }
        
        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px 12px; /* Reduced from 12px 15px */
            border: 1.5px solid #e0e0e0; /* Thinner border */
            border-radius: 6px; /* Reduced from 8px */
            font-size: 13px; /* Reduced from 14px */
            transition: all 0.2s; /* Faster transition */
        }
        
        input:focus,
        textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2); /* Smaller shadow */
        }
        
        .form-button {
            margin: 20px 0; /* Reduced from 25px */
        }
        
        button {
            width: 100%;
            padding: 10px; /* Reduced from 12px */
            background: linear-gradient(to right, #667eea, #764ba2);
            border: none;
            border-radius: 6px; /* Reduced from 8px */
            color: white;
            font-size: 14px; /* Reduced from 16px */
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s; /* Faster transition */
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3); /* Smaller shadow */
        }
        
        button:hover {
            transform: translateY(-1px); /* Reduced from -2px */
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); /* Smaller shadow */
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .note {
            font-size: 12px; /* Reduced from 13px */
            color: #666;
            margin-top: 4px; /* Reduced from 6px */
            font-style: italic;
        }
        
        .btn-group {
            display: flex;
            gap: 10px; /* Reduced from 15px */
            margin-top: 15px; /* Reduced from 20px */
            justify-content: center;
        }
        
        .btn {
            padding: 8px 15px; /* Reduced from 10px 20px */
            border-radius: 6px; /* Reduced from 8px */
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s; /* Faster transition */
            text-align: center;
            flex: 1;
            font-size: 13px; /* Added for consistency */
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3); /* Smaller shadow */
        }
        
        .btn-primary:hover {
            transform: translateY(-1px); /* Reduced from -2px */
            box-shadow: 0 5px 12px rgba(102, 126, 234, 0.4); /* Smaller shadow */
        }
        
        .btn-secondary {
            background: linear-gradient(to right, #4CAF50, #2E7D32);
            color: white;
            box-shadow: 0 3px 8px rgba(76, 175, 80, 0.3); /* Smaller shadow */
        }
        
        .btn-secondary:hover {
            transform: translateY(-1px); /* Reduced from -2px */
            box-shadow: 0 5px 12px rgba(76, 175, 80, 0.4); /* Smaller shadow */
        }
        
        .floating-alert {
            position: fixed;
            top: 15px; /* Reduced from 20px */
            right: 15px; /* Reduced from 20px */
            padding: 12px 20px; /* Reduced from 15px 25px */
            border-radius: 6px; /* Reduced from 8px */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); /* Smaller shadow */
            transform: translateX(150%);
            transition: transform 0.3s ease; /* Faster transition */
            z-index: 1000;
            color: white;
            font-weight: 500;
            font-size: 13px; /* Reduced from 14px */
        }
        
        .floating-alert.show {
            transform: translateX(0);
        }
        
        .alert-error {
            background: #ff4757;
        }
        
        .alert-success {
            background: #2ed573;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 25px; /* Reduced from 30px */
            }
            
            .btn-group {
                flex-direction: column;
                gap: 8px; /* Reduced from 10px */
            }
            
            .btn {
                width: 100%;
                padding: 8px; /* Reduced from 10px */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <img src="images/logo.jpeg" alt="Logo" class="form-logo" id="logo">
            <h2>Supplier Registration</h2>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="supplier_name">Supplier Name</label>
                    <input type="text" id="supplier_name" name="supplier_name" required>
                    <p class="note">Format: company name - agent's name</p>
                </div>
                
                <div class="form-group">
                    <label for="nic">NIC Number</label>
                    <input type="text" id="nic" name="nic" required>
                    <p class="note">Format: 123456789V or 123456789012</p>
                </div>
                
                <div class="form-group">
                    <label for="address">Company Address</label>
                    <input type="text" id="address" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" 
                        pattern="0[0-9]{9}" maxlength="10" required
                        title="Contact number must start with 0 and be exactly 10 digits">
                </div>
                
                <div class="form-button">
                    <button type="submit">Register Supplier</button>
                </div>
            </form>
            
            <div class="btn-group">
                <a href="home.php" class="btn btn-primary">Back to Home</a>
                <a href="display_registered_suppliers.php" class="btn btn-secondary">View Suppliers</a>
            </div>
        </div>
    </div>
    
    <!-- Floating Alert -->
    <div class="floating-alert" id="alert"></div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const logo = document.getElementById('logo');
            const alertBox = document.getElementById('alert');
            
            // Add animation to logo on hover
            if (logo) {
                logo.addEventListener('mouseenter', function() {
                    this.style.transform = 'rotate(10deg) scale(1.1)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                logo.addEventListener('mouseleave', function() {
                    this.style.transform = 'rotate(0) scale(1)';
                });
            }
            
            // Show messages as floating alerts
            <?php if($error): ?>
                showAlert('<?php echo $error; ?>', 'error');
            <?php endif; ?>
            
            <?php if($success): ?>
                showAlert('<?php echo $success; ?>', 'success');
            <?php endif; ?>
            
            // Show alert function
            function showAlert(message, type) {
                alertBox.textContent = message;
                alertBox.className = 'floating-alert show';
                
                // Set color based on type
                if (type === 'error') {
                    alertBox.classList.add('alert-error');
                } else if (type === 'success') {
                    alertBox.classList.add('alert-success');
                }
                
                // Hide after 5 seconds
                setTimeout(() => {
                    alertBox.classList.remove('show');
                    <?php if($success): ?>
                        setTimeout(() => {
                            window.location.href = window.location.href.split('?')[0];
                        }, 500);
                    <?php endif; ?>
                }, 5000);
            }
            
            // Add input focus effects
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentNode.querySelector('label').style.color = '#667eea';
                });
                
                input.addEventListener('blur', function() {
                    this.parentNode.querySelector('label').style.color = '#555';
                });
            });
        });
    </script>
</body>
</html>