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
            max-width: 600px;
            padding: 0;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 48px 48px 38px 48px;
            border-radius: 22px;
            box-shadow: 0 12px 40px 0 rgba(102,126,234,0.13), 0 2px 8px rgba(118,75,162,0.08);
            text-align: center;
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .form-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 40px rgba(102,126,234,0.18), 0 2px 8px rgba(118,75,162,0.10);
        }
        
        .form-logo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 4px 18px rgba(102,126,234,0.10);
        }
        
        h2 {
            color: #4b2996;
            margin-bottom: 32px;
            font-weight: 800;
            font-size: 36px;
            letter-spacing: 1px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            padding-bottom: 14px;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 4px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(102,126,234,0.13);
        }
        
        .form-group {
            margin-bottom: 28px;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            color: #4b2996;
            font-weight: 700;
            font-size: 20px;
            letter-spacing: 0.5px;
        }
        
        input[type="text"],
        textarea {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #d1d5db;
            border-radius: 10px;
            font-size: 18px;
            background: rgba(245,245,255,0.98);
            color: #2c3e50;
            transition: border 0.2s, box-shadow 0.2s, background 0.2s;
            box-shadow: 0 2px 8px rgba(102,126,234,0.04);
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.13);
            background: #fff;
        }
        
        .input-invalid {
            border-color: #ff4757 !important;
            box-shadow: 0 0 0 4px rgba(255, 71, 87, 0.13) !important;
        }
        
        .note {
            font-size: 15px;
            color: #764ba2;
            margin-top: 6px;
            font-style: italic;
            opacity: 0.85;
        }
        
        .validation-hint {
            font-size: 14px;
            color: #ff4757;
            margin-top: 6px;
            display: none;
        }
        
        .form-button {
            margin: 32px 0 18px 0;
        }
        
        button {
            width: 100%;
            padding: 18px 0;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 6px 18px rgba(102, 126, 234, 0.18);
        }
        
        button:hover {
            background: linear-gradient(90deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 10px 28px rgba(102, 126, 234, 0.22);
        }
        
        .btn-group {
            display: flex;
            gap: 18px;
            margin-top: 24px;
            justify-content: center;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.2s;
            text-align: center;
            flex: 1;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, #764ba2, #667eea);
            box-shadow: 0 5px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(to right, #4CAF50, #2E7D32);
            color: white;
            box-shadow: 0 3px 8px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(to right, #2E7D32, #4CAF50);
            box-shadow: 0 5px 12px rgba(76, 175, 80, 0.4);
        }
        
        .floating-alert {
            position: fixed;
            top: 30px;
            right: 30px;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateX(150%);
            transition: transform 0.4s ease;
            z-index: 1000;
            color: white;
            font-weight: 600;
            font-size: 16px;
            max-width: 400px;
            word-wrap: break-word;
        }
        
        .floating-alert.show {
            transform: translateX(0);
        }
        
        .alert-error {
            background: linear-gradient(45deg, #ff4757, #ff3742);
        }
        
        .alert-success {
            background: linear-gradient(45deg, #2ed573, #17c0eb);
        }
        
        @media (max-width: 700px) {
            .form-container {
                padding: 18px 6px 10px 6px;
            }
            .container {
                max-width: 98vw;
            }
            .btn-group {
                flex-direction: column;
                gap: 10px;
            }
            .btn {
                width: 100%;
            }
            .floating-alert {
                right: 10px;
                left: 10px;
                max-width: none;
                transform: translateY(-150%);
            }
            .floating-alert.show {
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <img src="images/logo.jpeg" alt="Logo" class="form-logo" id="logo">
            <h2>Supplier Registration</h2>
            <br>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="supplier_name" style="font-size: 17px; color:black;">Supplier Name</label>
                    <input type="text" id="supplier_name" name="supplier_name" required>
                    <p class="note" style="font-weight: bold;">Format: company name - agent's name</p>
                    <p class="validation-hint" id="name-hint">Please enter supplier name in the format: Company Name - Agent Name</p>
                </div>
                
                <div class="form-group">
                    <label for="nic" style="font-size: 17px; color:black;">NIC Number</label>
                    <input type="text" id="nic" name="nic" required>
                    <p class="note" style="font-weight: bold;">Format: 123456789V or 123456789012</p>
                </div>
                
                <div class="form-group">
                    <label for="address" style="font-size: 17px; color:black;">Company Address</label>
                    <input type="text" id="address" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="contact_number" style="font-size: 17px; color:black;">Contact Number</label>
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
            const supplierNameInput = document.getElementById('supplier_name');
            const nameHint = document.getElementById('name-hint');
            
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
            
            // Supplier name validation
            if (supplierNameInput && nameHint) {
                supplierNameInput.addEventListener('input', function() {
                    const value = this.value.trim();
                    const isValid = validateSupplierName(value);
                    
                    if (value.length > 0 && !isValid) {
                        this.classList.add('input-invalid');
                        nameHint.style.display = 'block';
                    } else {
                        this.classList.remove('input-invalid');
                        nameHint.style.display = 'none';
                    }
                });
                
                supplierNameInput.addEventListener('blur', function() {
                    const value = this.value.trim();
                    if (value.length > 0 && !validateSupplierName(value)) {
                        this.classList.add('input-invalid');
                        nameHint.style.display = 'block';
                    }
                });
            }

            // Supplier name validation function
            function validateSupplierName(name) {
                if (!name.includes('-')) return false;
                
                const parts = name.split('-');
                if (parts.length !== 2) return false;
                
                const companyPart = parts[0].trim();
                const agentPart = parts[1].trim();
                
                return companyPart.length >= 2 && agentPart.length >= 2;
            }

            // Show alert function
            function showAlert(message, type) {
                if (!alertBox) return;
                
                alertBox.textContent = message;
                alertBox.className = 'floating-alert show';
                
                // Set color based on type
                if (type === 'error') {
                    alertBox.classList.add('alert-error');
                } else if (type === 'success') {
                    alertBox.classList.add('alert-success');
                }
                
                // Hide after 6 seconds
                setTimeout(() => {
                    alertBox.classList.remove('show');
                    // If success, clear the form and optionally redirect
                    <?php if($success): ?>
                        setTimeout(() => {
                            // Clear form fields
                            document.getElementById('supplier_name').value = '';
                            document.getElementById('nic').value = '';
                            document.getElementById('address').value = '';
                            document.getElementById('contact_number').value = '';
                            // Optionally redirect to clear URL parameters
                            // window.location.href = window.location.href.split('?')[0];
                        }, 1000);
                    <?php endif; ?>
                }, 6000);
            }

            // Show messages as floating alerts
            <?php if($error): ?>
                showAlert('<?php echo addslashes($error); ?>', 'error');
            <?php endif; ?>
            
            <?php if($success): ?>
                showAlert('<?php echo addslashes($success); ?>', 'success');
            <?php endif; ?>
            
            // Add input focus effects
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    const label = this.parentNode.querySelector('label');
                    if (label) {
                        label.style.color = '#667eea';
                    }
                });
                
                input.addEventListener('blur', function() {
                    const label = this.parentNode.querySelector('label');
                    if (label) {
                        label.style.color = '#555';
                    }
                });
            });
        });
    </script>
</body>
</html>