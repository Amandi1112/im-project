<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    echo "Error creating database: " . $conn->error;
}

// Select database
$conn->select_db($dbname);

// Create members table if not exists
$sql = "CREATE TABLE IF NOT EXISTS members (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membership_number VARCHAR(6) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    membership_age INT(3) NOT NULL,
    nic_number VARCHAR(12) NOT NULL UNIQUE,
    telephone_number VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    echo "Error creating table: " . $conn->error;
}

// Insert sample data if table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM members");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Sample data generation
    $first_names = array("John", "Jane", "Michael", "Emily", "David", "Sarah", "Robert", "Linda", "William", "Elizabeth",
                        "Richard", "Jennifer", "Thomas", "Susan", "Charles", "Margaret", "James", "Jessica", "Andrew", "Karen");
    $last_names = array("Smith", "Johnson", "Williams", "Brown", "Jones", "Miller", "Davis", "Garcia", "Rodriguez", "Wilson",
                        "Martinez", "Anderson", "Taylor", "Thomas", "Hernandez", "Moore", "Martin", "Jackson", "Thompson", "White");
    $streets = array("Main St", "Oak Ave", "Maple Rd", "Cedar Ln", "Pine Dr", "Elm St", "Washington Ave", "Park Rd", "Lake Blvd", "River Dr");
    $cities = array("Springfield", "Rivertown", "Oakville", "Maplewood", "Lakeside", "Hillcrest", "Westwood", "Eastdale", "Northfield", "Southport");
    
    // Prepare the statement
    $stmt = $conn->prepare("INSERT INTO members (membership_number, full_name, address, membership_age, nic_number, telephone_number) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Generate 100 sample members
    for ($i = 1; $i <= 100; $i++) {
        // Generate membership number: B + 5 digits
        $membership_number = 'B' . str_pad($i, 5, '0', STR_PAD_LEFT);
        
        // Generate full name
        $first_name = $first_names[array_rand($first_names)];
        $last_name = $last_names[array_rand($last_names)];
        $full_name = $first_name . ' ' . $last_name;
        
        // Generate address
        $house_number = rand(1, 999);
        $street = $streets[array_rand($streets)];
        $city = $cities[array_rand($cities)];
        $address = $house_number . ' ' . $street . ', ' . $city;
        
        // Generate membership age (1-20 years)
        $membership_age = rand(1, 20);
        
        // Generate NIC number (9 digits + v)
        $nic_base = str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $nic_number = $nic_base . 'v';
        
        
        // Generate telephone number
        $telephone_number = '0' . rand(700000000, 799999999);
        
        // Bind parameters and execute
        $stmt->bind_param("sssiss", $membership_number, $full_name, $address, $membership_age, $nic_number, $telephone_number);
        $stmt->execute();
    }
    
    $stmt->close();
}

// Handle adding new member
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_member'])) {
    // Generate the next membership number
    $result = $conn->query("SELECT MAX(SUBSTRING(membership_number, 2)) as max_num FROM members");
    $row = $result->fetch_assoc();
    $next_num = intval($row['max_num']) + 1;
    $membership_number = 'B' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
    
    $full_name = $_POST['full_name'];
    $address = $_POST['address'];
    $membership_age = $_POST['membership_age'];
    $nic_number = $_POST['nic_number'];
    $telephone_number = $_POST['telephone_number'];
    
    $stmt = $conn->prepare("INSERT INTO members (membership_number, full_name, address, membership_age, nic_number, telephone_number) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiss", $membership_number, $full_name, $address, $membership_age, $nic_number, $telephone_number);
    
    try {
        $stmt->execute();
        // Success message
        $add_success = true;
    } catch (Exception $e) {
        // Error message - likely duplicate NIC
        $add_error = $e->getMessage();
    }
    
    $stmt->close();
    
    // Redirect to avoid resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle delete request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle form submission for editing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_member'])) {
    $id = $_POST['id'];
    $membership_number = $_POST['membership_number'];
    $full_name = $_POST['full_name'];
    $address = $_POST['address'];
    $membership_age = $_POST['membership_age'];
    $nic_number = $_POST['nic_number'];
    $telephone_number = $_POST['telephone_number'];
    
    $stmt = $conn->prepare("UPDATE members SET membership_number=?, full_name=?, address=?, membership_age=?, nic_number=?, telephone_number=? WHERE id=?");
    $stmt->bind_param("sssissi", $membership_number, $full_name, $address, $membership_age, $nic_number, $telephone_number, $id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Get total number of records
$result = $conn->query("SELECT COUNT(*) as total FROM members");
$row = $result->fetch_assoc();
$total_records = $row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch members with pagination
$sql = "SELECT * FROM members ORDER BY id LIMIT $offset, $records_per_page";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Management System</title>
    <style>
        body {
            background-image: url('images/background60.jpg');
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #d5731846;
    /* Changed color */

    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            
            border-radius: 5px;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            margin-bottom: 18px;
        }
        .add-btn {
            background-color:rgb(219, 126, 55);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .add-btn:hover{
            background-color:#f28252;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .edit-btn, .delete-btn {
            
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 5px;
        }
        .edit-btn {
            background-color:rgb(219, 126, 55);
            color: white;
        }
        .edit-btn:hover{
            background-color:rgb(246, 148, 74);
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        .delete-btn:hover{
            background-color:#3c763d;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
            
        }
        .pagination a {
            background-color: #a94442;
            color: black;
            float: left;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
        }
        .pagination a.active {
            background-color: #4CAF50;
            color: white;
            border: 1px solid #4CAF50;
        }
        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 60%;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-submit {
            grid-column: span 2;
            text-align: center;
        }
        .submit-btn {
            background-color:rgb(219, 126, 55);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-btn:hover{
            background-color:#f28252;
        }
        .success-message {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            border: 1px solid #d6e9c6;
        }
        .error-message {
            background-color: #f2dede;
            color: #a94442;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            border: 1px solid #ebccd1;
        }.nav-btn-container {
            text-align: right; /* Center the navigation buttons */
        }
        .home-btn{
            background-color: rgb(219, 126, 55);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            margin-bottom: 10px;

        }
        .home-btn:hover{
            background-color: #f28252;
        }
    </style>
</head>
<body>
    <div class="container">
    <h1 style="text-align: center; font-weight: bold; color: black; font-size: 2em; text-shadow: 2px 2px 5px lightblue;">Member Details</h1>
    <div class="nav-btn-container">
        <a href="clerk_dashboard.php" class="home-btn">Home</a>
    </div> 
        <div class="header-actions">
            <div>
                <span>Total Members: <?php echo $total_records; ?></span>
            </div>
            <button class="add-btn" onclick="openAddModal()">Add New Member</button>
        </div>
        
        <?php if(isset($add_success) && $add_success): ?>
        <div class="success-message">Member added successfully!</div>
        <?php endif; ?>
        
        <?php if(isset($add_error)): ?>
        <div class="error-message">Error: <?php echo $add_error; ?></div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Membership #</th>
                    <th>Full Name</th>
                    <th>Address</th>
                    <th>Membership Age</th>
                    <th>NIC Number</th>
                    <th>Telephone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['membership_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                    <td><?php echo $row['membership_age']; ?></td>
                    <td><?php echo htmlspecialchars($row['nic_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['telephone_number']); ?></td>
                    <td class="action-buttons">
                        <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                        <a href="?delete=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this member?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <div class="pagination">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" <?php if($i == $page) echo "class='active'"; ?>><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Member</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label for="membership_number">Membership Number:</label>
                    <input type="text" id="edit_membership_number" name="membership_number" required pattern="B\d{5}" title="Format should be B followed by 5 digits">
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="edit_address" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="membership_age">Membership Age (years):</label>
                    <input type="number" id="edit_membership_age" name="membership_age" required min="1">
                </div>
                
                <div class="form-group">
                    <label for="nic_number">NIC Number:</label>
                    <input type="text" id="edit_nic_number" name="nic_number" required pattern="\d{9}v" title="Format should be 9 digits followed by v">
                </div>
                
                <div class="form-group">
                    <label for="telephone_number">Telephone Number:</label>
                    <input type="text" id="edit_telephone_number" name="telephone_number" required pattern="0\d{9}" title="Format should be 0 followed by 9 digits">
                </div>
                
                <div class="form-submit">
                    <button type="submit" name="update_member" class="submit-btn">Update Member</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add New Member</h2>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="add_full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="add_address" name="address" required>
                </div>
                
                <div class="form-group">
                    <label for="membership_age">Membership Age (years):</label>
                    <input type="number" id="add_membership_age" name="membership_age" required min="1">
                </div>
                
                <div class="form-group">
                    <label for="nic_number">NIC Number:</label>
                    <input type="text" id="add_nic_number" name="nic_number" required pattern="\d{9}v" title="Format should be 9 digits followed by v">
                </div>
                
                <div class="form-group">
                    <label for="telephone_number">Telephone Number:</label>
                    <input type="text" id="add_telephone_number" name="telephone_number" required pattern="0\d{9}" title="Format should be 0 followed by 9 digits">
                </div>
                
                <div class="form-submit">
                    <button type="submit" name="add_member" class="submit-btn">Add Member</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Get the modals
        const editModal = document.getElementById("editModal");
        const addModal = document.getElementById("addModal");
        
        // Open the edit modal with member data
        function openEditModal(member) {
            document.getElementById("edit_id").value = member.id;
            document.getElementById("edit_membership_number").value = member.membership_number;
            document.getElementById("edit_full_name").value = member.full_name;
            document.getElementById("edit_address").value = member.address;
            document.getElementById("edit_membership_age").value = member.membership_age;
            document.getElementById("edit_nic_number").value = member.nic_number;
            document.getElementById("edit_telephone_number").value = member.telephone_number;
            editModal.style.display = "block";
        }
        
        // Close the edit modal
        function closeEditModal() {
            editModal.style.display = "none";
        }
        
        // Open the add modal
        function openAddModal() {
            document.getElementById("add_full_name").value = "";
            document.getElementById("add_address").value = "";
            document.getElementById("add_membership_age").value = "";
            document.getElementById("add_nic_number").value = "";
            document.getElementById("add_telephone_number").value = "";
            addModal.style.display = "block";
        }
        
        // Close the add modal
        function closeAddModal() {
            addModal.style.display = "none";
        }
        
        // Close the modals if clicked outside
        window.onclick = function(event) {
            if (event.target == editModal) {
                editModal.style.display = "none";
            }
            if (event.target == addModal) {
                addModal.style.display = "none";
            }
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>