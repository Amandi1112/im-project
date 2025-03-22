<?php
require('fpdf/fpdf.php'); // Make sure to provide the correct path to fpdf.php

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Supplier Balance Report', 0, 1, 'C');
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

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

// Fetch due amounts for each supplier
$dueAmounts = [];
$sql = "
    SELECT
        s.supplier_id,
        COALESCE(SUM(i.total_price), 0) - COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = s.supplier_id), 0) AS balance_due
    FROM
        supplier s
    LEFT JOIN
        items i ON s.supplier_id = i.supplier_id
    GROUP BY
        s.supplier_id
    HAVING
        balance_due > 0;
";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $dueAmounts[] = $row;
    }
}

// Fetch all payments
$payments = [];
$sql = "
    SELECT
        sp.supplier_id,
        sp.amount,
        sp.payment_date
    FROM
        supplier_payments sp
    JOIN
        supplier s ON sp.supplier_id = s.supplier_id
    ORDER BY
        sp.payment_date DESC;
";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
}

// Handle form submission for adding payments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    $supplier_id = $_POST['supplier_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];

    $sql = "INSERT INTO supplier_payments (supplier_id, amount, payment_date) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sds', $supplier_id, $amount, $payment_date);

    if ($stmt->execute()) {
        $message = "success|New payment added successfully";
        // Refresh the payments list
        header("Refresh:0");
    } else {
        $message = "error|Error: " . $stmt->error;
    }
}

// Handle form submission for generating the report
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_report'])) {
    $sql = "
        SELECT
            s.supplier_id,
            COALESCE(SUM(i.total_price), 0) AS total_purchased,
            COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = s.supplier_id), 0) AS total_paid,
            COALESCE(SUM(i.total_price), 0) - COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = s.supplier_id), 0) AS balance_due
        FROM
            supplier s
        LEFT JOIN
            items i ON s.supplier_id = i.supplier_id
        GROUP BY
            s.supplier_id;
    ";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Create new PDF document
        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);

        // Add table header
        $pdf->Cell(40, 10, 'Supplier ID', 1);
        $pdf->Cell(45, 10, 'Total Purchased', 1);
        $pdf->Cell(45, 10, 'Total Paid', 1);
        $pdf->Cell(45, 10, 'Balance Due', 1);
        $pdf->Ln();

        // Add table data
        $pdf->SetFont('Arial', '', 12);
        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(40, 10, $row['supplier_id'], 1);
            $pdf->Cell(45, 10, '$' . number_format($row['total_purchased'], 2), 1);
            $pdf->Cell(45, 10, '$' . number_format($row['total_paid'], 2), 1);
            $pdf->Cell(45, 10, '$' . number_format($row['balance_due'], 2), 1);
            $pdf->Ln();
        }

        // Output the PDF
        $pdf->Output('D', 'supplier_balance_report_' . date('Y-m-d') . '.pdf');
    } else {
        $message = "error|No data found to generate the report";
    }
}

// Prevent resubmission
echo '<script>
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>';

// Fetch suppliers for the dropdown
$suppliers = [];
$sql = "SELECT supplier_id, supplier_name FROM supplier";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Payment Management</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background:url("images/background60.jpg");
            font-family: 'Arial', sans-serif;
            margin: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin-top:100px;
            margin: auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: #d5731846;
    /* Changed color */
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: none;
            padding: 15px;
            border-radius: 5px;
            font-size: 16px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn-custom {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-custom:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .due-table, .payment-table {
            margin-top: 20px;
        }
        .btn {
            background-color: rgb(135, 74, 0);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            margin: 0 5px; /* Space between buttons */
            width:100%;
        }
        .btn:hover {
            background-color: #f28252;
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
<h1 class="my-4 text-center" style="text-shadow: 2px 2px 5px lightblue; font-size: 30px; background-color: white;">Supplier Payment Management</h1>
        
        <br>
        <div class="container">
        <h2 class="my-4" style="text-shadow: 2px 2px 5px lightblue; font-size: 18px; font-weight:bold; text-align: center;">Payment History</h2>
        <table class="table table-bordered payment-table" style="background-color: white;">
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Amount Paid</th>
                    <th>Payment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo $payment['supplier_id']; ?></td>
                        <td><?php echo '$' . number_format($payment['amount'], 2); ?></td>
                        <td><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <br>
        <div class="container">
<h2 class="my-4 text-center" style="text-shadow: 2px 2px 5px lightblue; font-size: 18px; font-weight:bold; text-align: left;">Generate Balance Report</h2>
        <form method="post" action="">
            <button type="submit" name="generate_report" class="btn">Generate Report</button>
        </form>
            <div>
        
    
    </div>
    </div>
    <br>
    <div class="nav-btn-container">
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.display = 'block';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 5000); // Increased display time to 5 seconds
            }

            const paymentForm = document.getElementById('paymentForm');
            paymentForm.addEventListener('submit', function(event) {
                const amount = document.getElementById('amount').value;
                if (amount <= 0) {
                    event.preventDefault();
                    alert('Amount must be greater than zero.');
                }
            });
        });
    </script>
</body>
</html>
