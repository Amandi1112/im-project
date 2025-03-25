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
        $stmt = $this->conn->query("SELECT MAX(coop_number) AS last_number FROM members");
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

        return $errors;
    }

    public function addMember($memberData) {
        // Validate member data
        $validationErrors = $this->validateMemberData($memberData);
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'errors' => $validationErrors
            ];
        }

        // Generate cooperative membership number
        $coop_number = $this->generateCoopNumber();

        // Calculate age
        $age = $this->calculateAge($memberData['date_of_birth']);

        // Prepare SQL statement
        $sql = "INSERT INTO members (
            full_name, 
            bank_membership_number, 
            coop_number,
            address, 
            nic, 
            date_of_birth, 
            age,
            telephone_number, 
            occupation, 
            monthly_income
        ) VALUES (
            :full_name, 
            :bank_membership_number, 
            :coop_number,
            :address, 
            :nic, 
            :date_of_birth, 
            :age,
            :telephone_number, 
            :occupation, 
            :monthly_income
        )";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':full_name', $memberData['full_name']);
            $stmt->bindValue(':bank_membership_number', $memberData['bank_membership_number']);
            $stmt->bindValue(':coop_number', $coop_number);
            $stmt->bindValue(':address', $memberData['address']);
            $stmt->bindValue(':nic', $memberData['nic']);
            $stmt->bindValue(':date_of_birth', $memberData['date_of_birth']);
            $stmt->bindValue(':age', $age);
            $stmt->bindValue(':telephone_number', $memberData['telephone_number']);
            $stmt->bindValue(':occupation', $memberData['occupation']);
            $stmt->bindValue(':monthly_income', $memberData['monthly_income']);

            $result = $stmt->execute();

            return [
                'success' => $result,
                'coop_number' => $coop_number
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
            'monthly_income' => filter_input(INPUT_POST, 'monthly_income', FILTER_VALIDATE_FLOAT)
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-2xl bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Member Registration</h2>
        
        <form id="memberRegistrationForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <div>
                    <label for="bank_membership_number" class="block text-sm font-medium text-gray-700">Bank Membership Number</label>
                    <input type="text" id="bank_membership_number" name="bank_membership_number" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
            </div>

            <div>
                <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                <textarea id="address" name="address" required 
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="nic" class="block text-sm font-medium text-gray-700">NIC Number</label>
                    <input type="text" id="nic" name="nic" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <div>
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="telephone_number" class="block text-sm font-medium text-gray-700">Telephone Number</label>
                    <input type="tel" id="telephone_number" name="telephone_number" required 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <div>
                    <label for="occupation" class="block text-sm font-medium text-gray-700">Occupation</label>
                    <input type="text" id="occupation" name="occupation" 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
            </div>

            <div>
                <label for="monthly_income" class="block text-sm font-medium text-gray-700">Monthly Income</label>
                <input type="number" id="monthly_income" name="monthly_income" required 
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            </div>

            <div class="text-center">
                <button type="submit" 
                    class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Register Member
                </button>
                 </button>
                <button type="reset" id="resetFormBtn"
                    class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Reset Form
                </button>
            </div>
        </form>
    </div>

    <script>
    $(document).ready(function() {
        // Toastr configuration
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
        };

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
                        if (response.coop_number) {
                            toastr.info(`Cooperative Number: ${response.coop_number}`, 'Cooperative Number');
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
                'monthly_income'
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

            return isValid;
        }
    });
    </script>
</body>
</html>