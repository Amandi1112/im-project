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

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add category form submission
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        
        if (empty($category_name)) {
            $error = "Category name is required!";
        } else {
            // Generate category_id starting with 'C' followed by 5 numbers
            $lastIdQuery = "SELECT MAX(CAST(SUBSTR(category_id, 2) AS UNSIGNED)) AS last_id FROM categories WHERE category_id LIKE 'C%'";
            $result = $conn->query($lastIdQuery);
            $row = $result->fetch_assoc();
            $lastId = $row['last_id'];

            if ($lastId === NULL) {
                $category_id = 'C00001';
            } else {
                $category_id = 'C' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
            }

            // Check if category name already exists
            $checkStmt = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ?");
            $checkStmt->bind_param("s", $category_name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "Category name already exists! Please choose a unique name.";
            } else {
                // Insert new category
                $insertStmt = $conn->prepare("INSERT INTO categories (category_id, category_name) VALUES (?, ?)");
                $insertStmt->bind_param("ss", $category_id, $category_name);
                
                if ($insertStmt->execute()) {
                    $success = "Category added successfully!";
                } else {
                    $error = "Error adding category: " . $conn->error;
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        }
    }
    
    // Delete category form submission
    if (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
        $category_id = $_POST['category_id'];
        
        // Check if category exists before deleting
        $checkStmt = $conn->prepare("SELECT category_id FROM categories WHERE category_id = ?");
        $checkStmt->bind_param("s", $category_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 0) {
            $error = "Category not found!";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $deleteStmt->bind_param("s", $category_id);
            
            if ($deleteStmt->execute()) {
                $success = "Category deleted successfully!";
            } else {
                $error = "Error deleting category: " . $conn->error;
            }
            $deleteStmt->close();
        }
        $checkStmt->close();
    }
}

// Display success message from session if exists
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories and Items</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url("images/background60.jpg");
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            padding: 30px;
            border-radius: 8px;
            background-color: #d5731846;
            /* Changed color */
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px; /* Reduced margin-bottom */
            margin-left: auto;   /* Add auto margins */
            margin-right: auto;
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
        textarea, 
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f0f0f0;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            animation: slideIn 0.4s;
        }
        
        .close-btn {
            position: absolute;
            right: 15px;
            top: 10px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .modal-header {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .modal-title {
            margin: 0;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }
        
        .modal-body {
            margin-bottom: 20px;
            color: #555;
        }
        
        .modal-footer {
            text-align: right;
            padding-top: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-success {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .btn-info {
            background-color: #2196F3;
            color: white;
        }
        
        .modal-success .modal-header {
            color: #155724;
            background-color: #d4edda;
        }
        
        .modal-error .modal-header {
            color: #721c24;
            background-color: #f8d7da;
        }
        
        @keyframes fadeIn {
            from {opacity: 0}
            to {opacity: 1}
        }
        
        @keyframes slideIn {
            from {top: -300px; opacity: 0}
            to {top: 0; opacity: 1}
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
            margin-top: 20px;
        }
        
        .btn{
            background-color: rgb(135, 74, 0);
            color: white;
        }
        
        .btn:hover{
           background-color: rgb(221, 125, 35);
           color: white;
        }
        
        /* Delete button styling */
        .delete-btn {
            background-color: #f44336;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: auto;
        }
        
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        
        /* Confirmation modal */
        #confirmDeleteModal .modal-content {
            width: 350px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: space-between;
        }
        
        .modal-footer button {
            width: 45%;
        }
        
        /* Cancel button */
        .cancel-btn {
            background-color: #808080;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #606060;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="text-align: center; font-weight: bold; color: rgb(39, 6, 34); font-size: 2em; text-shadow: 2px 2px 5px lightblue;">Add New Category</h2>
        <form method="post">
            <input type="hidden" name="add_category" value="1">
            <div class="form-group">
                <label>Category Name:</label>
                <input type="text" name="category_name" required>
            </div>
            <button type="submit" class="btn">Add Category</button>
        </form>
    </div>
    
    <div class="container">
        <h2 style="text-align: center; font-weight: bold; color:rgb(39, 6, 34); font-size: 2em; text-shadow: 2px 2px 5px lightblue;">Categories</h2>
        <?php
        $sql = "SELECT * FROM categories";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Category ID</th><th>Category Name</th><th>Action</th></tr>";
            
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row["category_id"]) . "</td>";
                echo "<td>" . htmlspecialchars($row["category_name"]) . "</td>";
                echo "<td>
                        <button type='button' class='delete-btn' onclick='confirmDelete(\"" . htmlspecialchars($row["category_id"]) . "\", \"" . htmlspecialchars($row["category_name"]) . "\")'>Delete</button>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No categories yet.";
        }
        ?>
    </div>
    
    <!-- Success Modal -->
    <div id="successModal" class="modal modal-success">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Success</h3>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($success) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success close-modal">OK</button>
            </div>
        </div>
    </div>
    
    <!-- Error Modal -->
    <div id="errorModal" class="modal modal-error">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Error</h3>
            </div>
            <div class="modal-body">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger close-modal">OK</button>
            </div>
        </div>
    </div>
    
    <!-- Confirm Delete Modal -->
    <div id="confirmDeleteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete category "<span id="categoryName"></span>"?</p>
            </div>
            <div class="modal-footer">
                <form method="post" id="deleteForm">
                    <input type="hidden" name="delete_category" value="1">
                    <input type="hidden" name="category_id" id="categoryIdInput">
                    <button type="button" class="btn cancel-btn" onclick="closeModal('confirmDeleteModal')">Cancel</button>
                    <button type="submit" class="delete-btn">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get modals
            var successModal = document.getElementById('successModal');
            var errorModal = document.getElementById('errorModal');
            var confirmDeleteModal = document.getElementById('confirmDeleteModal');
            
            // Show success modal if there's a success message
            <?php if($success): ?>
            successModal.style.display = "block";
            <?php endif; ?>
            
            // Show error modal if there's an error message
            <?php if($error): ?>
            errorModal.style.display = "block";
            <?php endif; ?>
            
            // Close modal when clicking on × button
            var closeButtons = document.getElementsByClassName('close-btn');
            for (var i = 0; i < closeButtons.length; i++) {
                closeButtons[i].onclick = function() {
                    successModal.style.display = "none";
                    errorModal.style.display = "none";
                    confirmDeleteModal.style.display = "none";
                }
            }
            
            // Close modal when clicking on OK button
            var closeModalButtons = document.getElementsByClassName('close-modal');
            for (var i = 0; i < closeModalButtons.length; i++) {
                closeModalButtons[i].onclick = function() {
                    successModal.style.display = "none";
                    errorModal.style.display = "none";
                }
            }
            
            // Close modal when clicking outside of it
            window.onclick = function(event) {
                if (event.target == successModal || event.target == errorModal || event.target == confirmDeleteModal) {
                    successModal.style.display = "none";
                    errorModal.style.display = "none";
                    confirmDeleteModal.style.display = "none";
                }
            }
        });
        
        // Function to show delete confirmation modal
        function confirmDelete(categoryId, categoryName) {
            document.getElementById('categoryName').textContent = categoryName;
            document.getElementById('categoryIdInput').value = categoryId;
            document.getElementById('confirmDeleteModal').style.display = "block";
        }
        
        // Function to close a specific modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }
    </script>
    
    <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>
</body>
</html>
<?php $conn->close(); ?>