<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "mywebsite");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];
    $marital_status = $_POST['marital_status'];
    $date_of_birth = $_POST['date_of_birth'];
    $address = $_POST['address'];
    $religion = $_POST['religion'];
    $nic = $_POST['nic'];
    $contact_number = $_POST['contact_number'];
    $spouse_name = $_POST['spouse_name'];

    $sql = "INSERT INTO personal_details (gender, age, marital_status, date_of_birth, address, religion, nic, contact_number, email, spouse_name) 
            VALUES ('$gender', '$age', '$marital_status', '$date_of_birth', '$address', '$religion', '$nic', '$contact_number', '$email', '$spouse_name')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Data saved successfully!');</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Personal Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f8ff;
            background-image: url('images/background60.jpg');
            background-size: cover;
            background-position: center;
        }
        form {
            max-width: 600px;
            margin: auto;
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        input, select, textarea {
    width: calc(100% - 16px); /* Adjust width to match input fields */
    padding: 8px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box; /* Ensures padding doesn't affect width */
}


        button {
            background-color:rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #f28252;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center; font-weight: bold; color: black; font-size: 2em; text-shadow: 2px 2px 5px lightblue;">Employee Personal Data Form</h2>
    <form method="POST" action="">
        <label for="full_name">Full Name:</label>
        <input type="text" name="full_name" id="full_name" required>

        <label for="email">Email Address:</label>
        <select name="email" id="email" required>
            <?php
            // Fetch email addresses from login details table
            $result = $conn->query("SELECT email FROM users");
            if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<option value='" . $row['email'] . "'>" . $row['email'] . "</option>";
            }
            } else {
            echo "<option value=''>No login users available</option>";
            }
            ?>
        </select>

        <label for="gender">Gender:</label>
        <select name="gender" id="gender" required>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>

        <label for="age">Age:</label>
        <input type="number" name="age" id="age" required>

        <label for="marital_status">Marital Status:</label>
        <select name="marital_status" id="marital_status" required>
            <option value="Single">Single</option>
            <option value="Married">Married</option>
            <option value="Divorced">Divorced</option>
        </select>

        <label for="date_of_birth">Date of Birth:</label>
        <input type="date" name="date_of_birth" id="date_of_birth" required>

        <label for="address">Address:</label>
        <input type="address" name="address" id="address" required>

        <label for="religion">Religion:</label>
        <input type="text" name="religion" id="religion" required>

        <label for="nic">NIC:</label>
        <input type="text" name="nic" id="nic" required>

        <label for="contact_number">Contact Number:</label>
        <input type="text" name="contact_number" id="contact_number" required>

        <label for="spouse_name">Spouse Name:</label>
        <input type="text" name="spouse_name" id="spouse_name">

        <button type="submit" formaction="personal_detail.php">Submit</button>
        <button type="button" class="button" onclick="window.location.href='personal_detail.php';">Employee Details</button>
    </form>
</body>
</html>