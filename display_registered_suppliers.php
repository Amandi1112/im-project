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
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        // Delete Supplier
        $supplier_id = $_POST['supplier_id'];
        
        $sql = "DELETE FROM supplier WHERE supplier_id = '$supplier_id'";
        
        if ($conn->query($sql) === TRUE) {
            $success = "Supplier deleted successfully!";
        } else {
            $error = "There was an error deleting the supplier. Please try again!!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View and Delete Suppliers</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background:url(images/background2.jpg);
            margin-top: 100px;
            margin: auto;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
    max-width: 600px;
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    margin: 20px auto 20px; /* Add margin-top here */
    border: 1px solid #ddd;
}


h2 {
    color:rgb(0, 0, 0); /* Blue */
    text-align: center;
    margin-bottom: 30px;
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
    color: #3498db; /* Blue */
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
    color: red;
}

.popup .close:hover {
    color: #ff3737; /* Darker Red */
}

/* Success and Error Messages */
#successPopup .popup {
    border: 1px solid #2ecc71; /* Green */
}

#successPopup h3 {
    color: #2ecc71; /* Green */
}

#errorPopup .popup {
    border: 1px solid #e74c3c; /* Red */
}

#errorPopup h3 {
    color: #e74c3c; /* Red */
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
    <h2 style="text-shadow: 2px 2px 5px lightblue; font-size: 30px;">Registered Suppliers</h2>
    <div class="container">
        
        <?php
        $sql = "SELECT supplier_id, supplier_name, address, nic FROM supplier";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Supplier ID</th><th>Supplier Name</th><th>Address</th><th>NIC</th><th>Actions</th></tr>";
            
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["supplier_id"] . "</td>";
                echo "<td>" . $row["supplier_name"] . "</td>";
                echo "<td>" . $row["address"] . "</td>";
                echo "<td>" . $row["nic"] . "</td>";
                echo "<td>";
                echo "<form method='post' style='display: inline-block; margin-right: 10px;'>";
                echo "<input type='hidden' name='action' value='delete'>";
                echo "<input type='hidden' name='supplier_id' value='" . $row["supplier_id"] . "'>";
                echo "<button type='submit' onclick='return confirm(\"Are you sure you want to delete this supplier? This action cannot be undone.\")'>Delete</button>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No suppliers registered yet.";
        }
        ?>
        
    </div>
    <br><br>
        <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>
</body>
</html>
