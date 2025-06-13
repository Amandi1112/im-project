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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin-top: 50px;
            margin: auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 2.5rem;
            position: relative;
            padding-bottom: 15px;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(to right, #667eea, #764ba2);
            border-radius: 3px;
        }
        
        .home-btn {
            background: linear-gradient(to right, #28a745, #218838);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .home-btn:hover {
            background: linear-gradient(to right, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        tr:not(:first-child):hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        
        td {
            color: #555;
            font-size: 0.95rem;
        }
        
        .button {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
            font-size: 0.85rem;
        }
        
        .button:hover {
            background: linear-gradient(to right, #5a6fd1, #6a42a0);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }
        
        input[type="email"], select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        input[type="email"]:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <h1>User Details</h1>
            <a href="home.php" class="home-btn">Home</a>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="font-weight: bold; color: black; font-size: 20px; color:white;">Name</th>
                    <th style="font-weight: bold; color: black; font-size: 20px;">Email</th>
                    <th style="font-weight: bold; color: black; font-size: 20px;">Position</th>
                    <th style="font-weight: bold; color: black; font-size: 20px;">Actions</th>
                </tr>
            </thead>
            <tbody style="font-weight: bold; color: black; font-size: 17px;">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <form method="POST">
                            <td style="font-size: 17px; color:black;"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td style="font-size: 17px; color:black;">
                                <input type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" required style="font-size: 17px; color:black;">
                            </td>
                            <td>
                                <select name="position" required style="font-size: 17px; color:black;">
                                    <option value="clerk" <?php echo $row['position'] === 'clerk' ? 'selected' : ''; ?> style="font-size: 17px; color:black;">Clerk</option>
                                    <option value="accountant" <?php echo $row['position'] === 'accountant' ? 'selected' : ''; ?> style="font-size: 17px; color:black;">Accountant</option>
                                    <option value="admin" <?php echo $row['position'] === 'admin' ? 'selected' : ''; ?> style="font-size: 17px; color:black;">Admin</option>
                                </select>
                            </td>
                            <td>
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="update" class="button" style="font-size: 17px; color:black;">Update</button>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="no-data">No customers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Add some interactive effects with JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to table rows
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                row.style.transition = `all 0.3s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, 100);
            });
            
            // Add hover effect to buttons
            const buttons = document.querySelectorAll('.button, .home-btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', () => {
                    button.style.transform = 'translateY(-2px)';
                });
                button.addEventListener('mouseleave', () => {
                    button.style.transform = 'translateY(0)';
                });
            });
            
            // Add focus effects to form inputs
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.style.transform = 'scale(1.02)';
                });
                input.addEventListener('blur', () => {
                    input.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>