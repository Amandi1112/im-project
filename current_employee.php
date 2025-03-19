<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mywebsite"; // Replace with your actual database name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'];
    $email = $_POST['email'];
    $position = $_POST['position'];

    $sql = "UPDATE users SET email = ?, position = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $email, $position, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Record updated successfully.');</script>";
    } else {
        echo "<script>alert('Error updating record: " . $conn->error . "');</script>";
    }
    $stmt->close();
}

// Fetch customer data
$sql = "SELECT id, name, email, position FROM users";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details</title>
    
    <style>
        h1 {
            text-align: center;
            font-size: 3em;
            color:rgb(77, 27, 2);
            text-shadow: 2px 2px 5px rgba(236, 110, 110, 0.5);
        }
        .home-btn {
            background-color:rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            position: absolute;
            top: 0;
            right: 0;
            margin: 10px;
        }
        .home-btn:hover {
            background-color: #f28252;
        }
        .header-container {
            position: relative;
            margin-bottom: 30px;
        }
    </style>
</head>
<body style="background-image: url('images/background60.jpg'); background-size: cover; background-repeat: no-repeat; background-attachment: fixed;"></body>
    <h1>Customer Details</h1>
    <div style="display: flex; justify-content: flex-end; margin-right: 20px;">
        <button class = 'home-btn' style="background-color:rgb(246, 157, 122);">
            <a href="home.php" style="text-decoration: none; color: black;">Home</a>
        </button>
    </div>
    <div style="display: flex; justify-content: center; align-items: center; margin-top: 20px;">
        <table border="1" style="width: 90%; font-size: 1.2em; height: auto; margin: 20px auto; table-layout: fixed; background-color:rgb(66, 31, 2);">
            <thead>
            <tr>
            <th style="background-color: lightgray;">ID</th>
            <th style="background-color: lightgray;">Name</th>
            <th style="background-color: lightgray;">Email</th>
            <th style="background-color: lightgray;">Position</th>
            <th style="background-color: lightgray;">Actions</th>
            </tr>
            </thead>
            <tbody style="color: white;">
            <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <form method="POST">
                <td style="color: white;"><?php echo $row['id']; ?></td>
                <td style="color: white; font-size: 0.7em;"><?php echo $row['name']; ?></td>
                <td>
                <input type="email" name="email" value="<?php echo $row['email']; ?>" required style="border: none; outline: none; background: transparent; width: 100%; font-size: 0.7em; color: white;">
                </td>
                <td>
                <select name="position" required style="width: 100%; color: white; background-color: transparent;">
                <option value="client" <?php echo $row['position'] === 'client' ? 'selected' : ''; ?>>Client</option>
                <option value="accountant" <?php echo $row['position'] === 'accountant' ? 'selected' : ''; ?>>Accountant</option>
                </select>
                </td>
                <td>
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <button type="submit" name="update" style="width: 100%; color: black; background-color:rgb(246, 157, 122);">Update</button>
                </td>
                </form>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr>
            <td colspan="5" style="color: white;">No customers found.</td>
            </tr>
            <?php endif; ?>
            </tbody></tbody>
            <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <form method="POST">
                <td><?php echo $row['id']; ?></td>
                <td style="font-size: 0.7em;"><?php echo $row['name']; ?></td>
                <td>
                <input type="email" name="email" value="<?php echo $row['email']; ?>" required style="border: none; outline: none; background: transparent; width: 100%; font-size: 0.7em;">
                </td>
                <td>
                <select name="position" required style="width: 100%;">
                <option value="client" <?php echo $row['position'] === 'client' ? 'selected' : ''; ?>>Client</option>
                <option value="accountant" <?php echo $row['position'] === 'accountant' ? 'selected' : ''; ?>>Accountant</option>
                </select>
                </td>
                <td>
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <button type="submit" name="update" style="background-color: black; color: white;">Update</button>
                </td>
                </form>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr>
            <td colspan="5">No customers found.</td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
$conn->close();
?>