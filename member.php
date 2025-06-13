<?php
// Database Configuration (Replace with your actual database credentials)
$DB_HOST = 'localhost';
$DB_NAME = 'mywebsite';
$DB_USER = 'root';
$DB_PASS = '';

// Member Registration Class
class MemberRegistration {
    private $conn;

    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }

    // Generate cooperative number: C + 5 digits
    private function generateCoopNumber() {
        $stmt = $this->conn->query("SELECT MAX(id) AS last_number FROM members");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $lastNumber = $result['last_number'] ? intval(substr($result['last_number'], 1)) : 0;
        $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

        return 'C' . $newNumber;
    }

    // Calculate age from date of birth
    private function calculateAge($date_of_birth) {
        $birthdate = new DateTime($date_of_birth);
        $today = new DateTime('today');
        return $today->diff($birthdate)->y;
    }

    // Check for duplicate NIC
    private function checkDuplicateNIC($nic) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM members WHERE nic = :nic");
        $stmt->bindParam(':nic', $nic);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    // Comprehensive input validation
    private function validateMemberData($data) {
        $errors = [];

        // Validate full name
        if (empty($data['full_name']) || !preg_match('/^[A-Za-z\s\-\']+$/', $data['full_name'])) {
            $errors[] = "Invalid full name";
        }

        // Validate NIC
        if (empty($data['nic']) ||
            (!preg_match('/^[0-9]{9}[vVxX]?$/', $data['nic']) &&
             !preg_match('/^[0-9]{12}$/', $data['nic']))) {
            $errors[] = "Invalid NIC number";
        }

        // Check for duplicate NIC
        if ($this->checkDuplicateNIC($data['nic'])) {
            $errors[] = "NIC number already exists in the system";
        }

        // Validate date of birth
        if (empty($data['date_of_birth']) ||
            new DateTime($data['date_of_birth']) >= new DateTime('today')) {
            $errors[] = "Invalid date of birth";
        }

        // Validate telephone number
        if (empty($data['telephone_number']) ||
            !preg_match('/^(0[0-9]{9}|\+[0-9]{10,14})$/', $data['telephone_number'])) {
            $errors[] = "Invalid telephone number";
        }

        // Validate monthly income
        if (!is_numeric($data['monthly_income']) ||
            $data['monthly_income'] < 0 ||
            $data['monthly_income'] > 1000000) {
            $errors[] = "Invalid monthly income";
        }

        // Validate address
        if (empty($data['address']) || strlen($data['address']) < 10) {
            $errors[] = "Invalid or too short address";
        }
/*
        // Validate credit limit
        if (!is_numeric($data['credit_limit']) ||
            $data['credit_limit'] < 0 ||
            $data['credit_limit'] > 1000000) {
            $errors[] = "Invalid credit limit";
        }
            */

        return $errors;
    }

    public function addMember($memberData) {
        // Validate member data
        $memberData['credit_limit'] = $memberData['monthly_income'] * 0.3;
        $validationErrors = $this->validateMemberData($memberData);
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'errors' => $validationErrors
            ];
        }

        // Generate cooperative membership number
        $id = $this->generateCoopNumber();

        // Calculate age
        $age = $this->calculateAge($memberData['date_of_birth']);

        // Prepare SQL statement
        $sql = "INSERT INTO members (
            full_name,
            bank_membership_number,
            id,
            address,
            nic,
            date_of_birth,
            age,
            telephone_number,
            occupation,
            monthly_income,
            credit_limit
        ) VALUES (
            :full_name,
            :bank_membership_number,
            :id,
            :address,
            :nic,
            :date_of_birth,
            :age,
            :telephone_number,
            :occupation,
            :monthly_income,
            :credit_limit
        )";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':full_name', $memberData['full_name']);
            $stmt->bindValue(':bank_membership_number', $memberData['bank_membership_number']);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':address', $memberData['address']);
            $stmt->bindValue(':nic', $memberData['nic']);
            $stmt->bindValue(':date_of_birth', $memberData['date_of_birth']);
            $stmt->bindValue(':age', $age);
            $stmt->bindValue(':telephone_number', $memberData['telephone_number']);
            $stmt->bindValue(':occupation', $memberData['occupation']);
            $stmt->bindValue(':monthly_income', $memberData['monthly_income']);
            $stmt->bindValue(':credit_limit', $memberData['credit_limit']);

            $result = $stmt->execute();

            return [
                'success' => $result,
                'id' => $id
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Handle AJAX Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Database connection
        $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $memberRegistration = new MemberRegistration($pdo);

        // Sanitize and validate input
        $memberData = [
            'full_name' => filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING),
            'bank_membership_number' => filter_input(INPUT_POST, 'bank_membership_number', FILTER_SANITIZE_STRING),
            'address' => filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING),
            'nic' => filter_input(INPUT_POST, 'nic', FILTER_SANITIZE_STRING),
            'date_of_birth' => filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING),
            'telephone_number' => filter_input(INPUT_POST, 'telephone_number', FILTER_SANITIZE_STRING),
            'occupation' => filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_STRING),
            'monthly_income' => filter_input(INPUT_POST, 'monthly_income', FILTER_VALIDATE_FLOAT),
            'credit_limit' => filter_input(INPUT_POST, 'credit_limit', FILTER_VALIDATE_FLOAT)
        ];

        $result = $memberRegistration->addMember($memberData);

        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;

    } catch (Exception $e) {
        // Error handling
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Registration System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 1150px;
            background: rgba(255, 255, 255, 0.98);
            padding: 56px 56px 44px 56px;
            border-radius: 22px;
            box-shadow: 0 12px 40px 0 rgba(102,126,234,0.13), 0 2px 8px rgba(118,75,162,0.08);
            backdrop-filter: blur(2px);
        }

        h2 {
            color: #4b2996;
            text-align: center;
            margin-bottom: 44px;
            font-weight: 800;
            font-size: 44px;
            letter-spacing: 1px;
            position: relative;
            padding-bottom: 18px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 5px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(102,126,234,0.13);
        }

        .form-group {
            margin-bottom: 32px;
        }

        /* Center the logo above the form */
        .form-logo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 4px 18px rgba(102,126,234,0.10);
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        label {
            display: block;
            margin-bottom: 12px;
            font-weight: 700;
            color: #4b2996;
            font-size: 24px;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="number"],
        input[type="tel"],
        input[type="date"],
        textarea {
            width: 100%;
            padding: 22px 24px;
            border: 2px solid #d1d5db;
            border-radius: 12px;
            font-size: 22px;
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

        .note {
            font-size: 18px;
            color: #764ba2;
            margin-top: 8px;
            font-style: italic;
            opacity: 0.85;
        }

        .grid {
            display: grid;
            gap: 32px;
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .btn-group {
            display: flex;
            gap: 28px;
            margin-top: 44px;
            justify-content: center;
        }

        button {
            padding: 22px 48px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: 0.5px;
            transition: all 0.2s;
            box-shadow: 0 4px 18px rgba(102,126,234,0.10);
        }

        button[type="submit"] {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 6px 18px rgba(102, 126, 234, 0.18);
            position: relative;
            overflow: hidden;
        }
        button[type="submit"]:hover {
            background: linear-gradient(90deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 10px 28px rgba(102, 126, 234, 0.22);
        }
        button[type="reset"] {
            background: #b2bec3;
            color: #fff;
        }
        button[type="reset"]:hover {
            background: #636e72;
            transform: translateY(-2px) scale(1.03);
        }

        .home-link {
            display: block;
            text-align: center;
            margin-top: 32px;
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            font-size: 24px;
            transition: color 0.2s, text-decoration 0.2s;
        }
        .home-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Toastr overrides to match our theme */
        .toast-success {
            background-color: #2ed573 !important;
            font-size: 20px !important;
        }
        .toast-error {
            background-color: #ff4757 !important;
            font-size: 20px !important;
        }
        .toast-info {
            background-color: #667eea !important;
            font-size: 20px !important;
        }

        @media (max-width: 1100px) {
            .container {
                padding: 18px;
                max-width: 98vw;
            }
            .grid-cols-2 {
                grid-template-columns: 1fr;
            }
            .btn-group {
                flex-direction: column;
            }
            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="images/logo.jpeg" alt="Logo" class="form-logo" id="logo">
        <h2>Member Registration</h2>

        <form id="memberRegistrationForm">
            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="bank_membership_number">Bank Membership Number</label>
                    <input type="text" id="bank_membership_number" name="bank_membership_number"
                        maxlength="6" pattern="[A-Za-z0-9]{6}" required>
                    <p class="note">Format: letter 'B'+ 5 numbers</p>
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" required></textarea>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label for="nic">NIC Number</label>
                    <input type="text" id="nic" name="nic" required>
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required>
                </div>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="text" id="age" name="age" readonly>
                </div>

                <div class="form-group">
                    <label for="telephone_number">Telephone Number</label>
                    <input type="tel" id="telephone_number" name="telephone_number" required>
                </div>
            </div>

            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label for="occupation">Occupation</label>
                    <input type="text" id="occupation" name="occupation">
                </div>

                <div class="form-group">
                    <label for="monthly_income">Monthly Income</label>
                    <input type="number" id="monthly_income" name="monthly_income" required>
                </div>
            </div>

            <div class="form-group">
    <label for="credit_limit">Credit Limit</label>
    <input type="number" id="credit_limit" name="credit_limit" readonly required>
</div>

            <div class="btn-group">
                <button type="submit">Register Member</button>
                <button type="reset" id="resetFormBtn">Reset Form</button>
            </div>

            <a href="home.php" class="home-link">Back to Home</a>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
    $(document).ready(function() {
        // Toastr configuration
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
        };

        // Calculate age based on date of birth
        $('#date_of_birth').on('change', function() {
            const dob = new Date($(this).val());
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            const monthDifference = today.getMonth() - dob.getMonth();
            if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < dob.getDate())) {
                $('#age').val(age - 1);
            } else {
                $('#age').val(age);
            }
        });

        // Calculate credit limit as 30% of monthly income
$('#monthly_income').on('input', function() {
    const monthlyIncome = parseFloat($(this).val()) || 0;
    const creditLimit = monthlyIncome * 0.3;
    $('#credit_limit').val(creditLimit.toFixed(2));
});

        // Form submission handler
        $('#memberRegistrationForm').on('submit', function(e) {
            e.preventDefault();

            // Client-side validation
            if (!validateForm()) {
                return;
            }

            // Disable submit button to prevent multiple submissions
            const submitButton = $('button[type="submit"]');
            submitButton.prop('disabled', true).html('Processing...');

            // Prepare form data
            const formData = new FormData(this);

            // AJAX submission
            $.ajax({
                url: '',  // Submit to same page
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success('Member registered successfully!', 'Success', {
                            onHidden: function() {
                                // Reset form
                                $('#memberRegistrationForm')[0].reset();
                            }
                        });

                        // If coop number is returned, display it
                        if (response.id) {
                            toastr.info(`Cooperative Number: ${response.id}`, 'Cooperative Number');
                        }
                    } else {
                        // Handle validation errors
                        if (response.errors) {
                            response.errors.forEach(error => {
                                toastr.error(error, 'Validation Error');
                            });
                        } else {
                            toastr.error(response.error || 'Registration failed', 'Error');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    toastr.error('An unexpected error occurred', 'Error');
                    console.error(error);
                },
                complete: function() {
                    // Re-enable submit button
                    submitButton.prop('disabled', false).html('Register Member');
                }
            });
        });

        // Client-side form validation
        function validateForm() {
            let isValid = true;
            const fields = [
                'full_name', 'bank_membership_number', 'address',
                'nic', 'date_of_birth', 'telephone_number',
                'monthly_income', 'credit_limit'
            ];

            fields.forEach(field => {
                const $field = $(`#${field}`);
                const value = $field.val().trim();

                // Basic validation
                if (!value) {
                    toastr.error(`${$field.prev('label').text()} is required`, 'Validation Error');
                    isValid = false;
                }
            });

            // Additional specific validations
            const nicRegex = /^([0-9]{9}[vVxX]?|[0-9]{12})$/;
            if (!nicRegex.test($('#nic').val())) {
                toastr.error('Invalid NIC number format', 'Validation Error');
                isValid = false;
            }

            const telephoneRegex = /^(0[0-9]{9}|\+[0-9]{10,14})$/;
            if (!telephoneRegex.test($('#telephone_number').val())) {
                toastr.error('Invalid telephone number format', 'Validation Error');
                isValid = false;
            }

            // Date of birth validation
            const dob = new Date($('#date_of_birth').val());
            const today = new Date();
            if (dob >= today) {
                toastr.error('Date of birth cannot be in the future', 'Validation Error');
                isValid = false;
            }

            // Credit limit validation
            const creditLimit = parseFloat($('#credit_limit').val());
            if (isNaN(creditLimit) || creditLimit < 0 || creditLimit > 1000000) {
                toastr.error('Invalid credit limit', 'Validation Error');
                isValid = false;
            }

            return isValid;
        }
    });
    </script>
</body>
</html>
