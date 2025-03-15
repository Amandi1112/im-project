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
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center;">Employee Personal Data Form</h2>
    <form method="POST" action="">
        <label for="full_name">Full Name:</label>
        <input type="text" name="full_name" id="full_name" required>

        <label for="email">Personal Email Address:</label>
        <input type="email" name="email" id="email" required>

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

        <label for="religion">Religion:</label>
        <input type="text" name="religion" id="religion" required>

        <label for="nic">NIC:</label>
        <input type="text" name="nic" id="nic" required>

        <label for="contact_number">Contact Number:</label>
        <input type="text" name="contact_number" id="contact_number" required>

        <label for="spouse_name">Spouse Name:</label>
        <input type="text" name="spouse_name" id="spouse_name">

        <button type="submit" formaction="personal_detail.php">Submit</button>
    </form>
</body>
</html>