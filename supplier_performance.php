<?php
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "mywebsite";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include FPDF library
require('fpdf/fpdf.php');

// Start session to store messages
session_start();

// Function to validate input data
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get suppliers for dropdown
$suppliers_query = "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name";
$suppliers_result = $conn->query($suppliers_query);

// Initialize variables
$supplier_id = $start_date = $end_date = "";
$error = $success = "";

// Get messages from session if they exist
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate'])) {
    // Validate inputs
    $supplier_id = validateInput($_POST['supplier_id']);
    $start_date = validateInput($_POST['start_date']);
    $end_date = validateInput($_POST['end_date']);
    
    // Check for empty fields
    if (empty($supplier_id) || empty($start_date) || empty($end_date)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else if (strtotime($end_date) < strtotime($start_date)) {
        $_SESSION['error'] = "End date cannot be earlier than start date";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        // Generate PDF report
        generatePerformanceReport($conn, $supplier_id, $start_date, $end_date);
    }
}

// Function to calculate supplier metrics
function calculateSupplierMetrics($conn, $supplier_id, $start_date, $end_date) {
    // Get supplier name
    $supplier_query = "SELECT supplier_name FROM supplier WHERE supplier_id = ?";
    $stmt = $conn->prepare($supplier_query);
    $stmt->bind_param("s", $supplier_id);
    $stmt->execute();
    $supplier_result = $stmt->get_result();
    $supplier_name = $supplier_result->fetch_assoc()['supplier_name'];
    
    // Total purchases
    $total_purchases_query = "SELECT COUNT(*) as total_count, SUM(total_price) as total_value 
                             FROM items 
                             WHERE supplier_id = ? AND purchase_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($total_purchases_query);
    $stmt->bind_param("sss", $supplier_id, $start_date, $end_date);
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_purchases = $total_row['total_count'];
    $total_value = $total_row['total_value'];
    
    // Average price per unit
    $avg_price_query = "SELECT AVG(price_per_unit) as avg_price 
                        FROM items 
                        WHERE supplier_id = ? AND purchase_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($avg_price_query);
    $stmt->bind_param("sss", $supplier_id, $start_date, $end_date);
    $stmt->execute();
    $avg_result = $stmt->get_result();
    $avg_price = $avg_result->fetch_assoc()['avg_price'];
    
    // Category distribution
    $category_dist_query = "SELECT c.category_name, COUNT(*) as count, SUM(i.total_price) as value
                           FROM items i
                           JOIN categories c ON i.category_id = c.category_id
                           WHERE i.supplier_id = ? AND i.purchase_date BETWEEN ? AND ?
                           GROUP BY c.category_name
                           ORDER BY count DESC";
    $stmt = $conn->prepare($category_dist_query);
    $stmt->bind_param("sss", $supplier_id, $start_date, $end_date);
    $stmt->execute();
    $category_result = $stmt->get_result();
    $categories = [];
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    // Monthly trend
    $monthly_trend_query = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') as month, 
                           COUNT(*) as count, SUM(total_price) as value
                           FROM items 
                           WHERE supplier_id = ? AND purchase_date BETWEEN ? AND ?
                           GROUP BY DATE_FORMAT(purchase_date, '%Y-%m')
                           ORDER BY month";
    $stmt = $conn->prepare($monthly_trend_query);
    $stmt->bind_param("sss", $supplier_id, $start_date, $end_date);
    $stmt->execute();
    $monthly_result = $stmt->get_result();
    $monthly_trend = [];
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_trend[] = $row;
    }
    
    // Recent purchases
    $recent_purchases_query = "SELECT i.item_id, i.item_name, c.category_name, 
                              i.quantity, i.price_per_unit, i.total_price, i.purchase_date 
                              FROM items i 
                              JOIN categories c ON i.category_id = c.category_id 
                              WHERE i.supplier_id = ? AND i.purchase_date BETWEEN ? AND ?
                              ORDER BY i.purchase_date DESC LIMIT 10";
    $stmt = $conn->prepare($recent_purchases_query);
    $stmt->bind_param("sss", $supplier_id, $start_date, $end_date);
    $stmt->execute();
    $recent_result = $stmt->get_result();
    $recent_purchases = [];
    while ($row = $recent_result->fetch_assoc()) {
        $recent_purchases[] = $row;
    }
    
    return [
        'supplier_name' => $supplier_name,
        'total_purchases' => $total_purchases,
        'total_value' => $total_value,
        'avg_price' => $avg_price,
        'categories' => $categories,
        'monthly_trend' => $monthly_trend,
        'recent_purchases' => $recent_purchases,
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
}

// Function to generate PDF report
function generatePerformanceReport($conn, $supplier_id, $start_date, $end_date) {
    // Get metrics
    $metrics = calculateSupplierMetrics($conn, $supplier_id, $start_date, $end_date);
    
    // Create PDF
    class PerformanceReportPDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, 'Supplier Performance Report', 0, 1, 'C');
            $this->Ln(5);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    
    $pdf = new PerformanceReportPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Supplier info
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Supplier: ' . $metrics['supplier_name'], 0, 1);
    $pdf->Cell(0, 6, 'Report Period: ' . date('Y-m-d', strtotime($metrics['start_date'])) . ' to ' . date('Y-m-d', strtotime($metrics['end_date'])), 0, 1);
    $pdf->Cell(0, 6, 'Report Generated: ' . date('Y-m-d'), 0, 1);
    $pdf->Ln(5);
    
    // Summary section
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Performance Summary', 0, 1, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(120, 8, 'Total Number of Purchases:', 0, 0);
    $pdf->Cell(70, 8, $metrics['total_purchases'], 0, 1);
    $pdf->Cell(120, 8, 'Total Purchase Value:', 0, 0);
    $pdf->Cell(70, 8, '$' . number_format($metrics['total_value'], 2), 0, 1);
    $pdf->Cell(120, 8, 'Average Price Per Unit:', 0, 0);
    $pdf->Cell(70, 8, '$' . number_format($metrics['avg_price'], 2), 0, 1);
    $pdf->Ln(5);
    
    // Category distribution
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Category Distribution', 0, 1, 'L', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 8, 'Category', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Number of Items', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Total Value', 1, 0, 'C');
    $pdf->Cell(40, 8, '% of Total', 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    foreach ($metrics['categories'] as $category) {
        $percentage = ($category['value'] / $metrics['total_value']) * 100;
        $pdf->Cell(70, 8, $category['category_name'], 1, 0);
        $pdf->Cell(40, 8, $category['count'], 1, 0, 'C');
        $pdf->Cell(40, 8, '$' . number_format($category['value'], 2), 1, 0, 'R');
        $pdf->Cell(40, 8, number_format($percentage, 2) . '%', 1, 1, 'R');
    }
    $pdf->Ln(5);
    
    // Monthly trend
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Monthly Purchase Trend', 0, 1, 'L', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 8, 'Month', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Number of Items', 1, 0, 'C');
    $pdf->Cell(50, 8, 'Total Value', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Avg Value/Item', 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    foreach ($metrics['monthly_trend'] as $month) {
        $avg_value = $month['count'] > 0 ? $month['value'] / $month['count'] : 0;
        $pdf->Cell(60, 8, date('F Y', strtotime($month['month'] . '-01')), 1, 0);
        $pdf->Cell(40, 8, $month['count'], 1, 0, 'C');
        $pdf->Cell(50, 8, '$' . number_format($month['value'], 2), 1, 0, 'R');
        $pdf->Cell(40, 8, '$' . number_format($avg_value, 2), 1, 1, 'R');
    }
    $pdf->Ln(5);
    
    // Recent purchases
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Recent Purchases', 0, 1, 'L', true);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(20, 8, 'Item ID', 1, 0, 'C');
    $pdf->Cell(50, 8, 'Item Name', 1, 0, 'C');
    $pdf->Cell(35, 8, 'Category', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Quantity', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Price/Unit', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Total', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Date', 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    foreach ($metrics['recent_purchases'] as $purchase) {
        $pdf->Cell(20, 8, $purchase['item_id'], 1, 0);
        $pdf->Cell(50, 8, $purchase['item_name'], 1, 0);
        $pdf->Cell(35, 8, $purchase['category_name'], 1, 0);
        $pdf->Cell(20, 8, $purchase['quantity'], 1, 0, 'C');
        $pdf->Cell(25, 8, '$' . number_format($purchase['price_per_unit'], 2), 1, 0, 'R');
        $pdf->Cell(25, 8, '$' . number_format($purchase['total_price'], 2), 1, 0, 'R');
        $pdf->Cell(25, 8, date('Y-m-d', strtotime($purchase['purchase_date'])), 1, 1, 'C');
    }
    
    // Performance evaluation
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Performance Evaluation', 0, 1, 'L', true);
    
    // Determine supplier rating based on metrics
    $rating = calculateSupplierRating($metrics);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, 'Overall Rating: ' . $rating['overall_rating'] . ' out of 5', 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 8, 'Price Performance: ' . $rating['price_rating'] . ' out of 5 - ' . $rating['price_comment']);
    $pdf->Ln(2);
    $pdf->MultiCell(0, 8, 'Diversity of Supply: ' . $rating['diversity_rating'] . ' out of 5 - ' . $rating['diversity_comment']);
    $pdf->Ln(2);
    $pdf->MultiCell(0, 8, 'Consistency: ' . $rating['consistency_rating'] . ' out of 5 - ' . $rating['consistency_comment']);
    $pdf->Ln(5);
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, 'Recommendations:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 8, $rating['recommendations']);
    
    // Output PDF
    $filename = 'supplier_performance_' . $supplier_id . '_' . date('Ymd') . '.pdf';
    $pdf->Output('D', $filename);
    exit();
}

// Function to calculate supplier rating
function calculateSupplierRating($metrics) {
    // Price rating - based on average price compared to overall average (mock data for example)
    $overall_avg = 20.00; // This would come from a query of all suppliers
    $price_ratio = $metrics['avg_price'] / $overall_avg;
    
    if ($price_ratio < 0.8) {
        $price_rating = 5;
        $price_comment = "Excellent pricing, significantly below average market price.";
    } elseif ($price_ratio < 0.9) {
        $price_rating = 4;
        $price_comment = "Good pricing, below average market price.";
    } elseif ($price_ratio < 1.1) {
        $price_rating = 3;
        $price_comment = "Average pricing, within market range.";
    } elseif ($price_ratio < 1.2) {
        $price_rating = 2;
        $price_comment = "Higher than average pricing.";
    } else {
        $price_rating = 1;
        $price_comment = "Significantly higher prices than market average.";
    }
    
    // Diversity rating - based on number of different categories
    $category_count = count($metrics['categories']);
    
    if ($category_count >= 5) {
        $diversity_rating = 5;
        $diversity_comment = "Excellent variety of products across many categories.";
    } elseif ($category_count >= 4) {
        $diversity_rating = 4;
        $diversity_comment = "Good variety of products across several categories.";
    } elseif ($category_count >= 3) {
        $diversity_rating = 3;
        $diversity_comment = "Moderate variety of products.";
    } elseif ($category_count >= 2) {
        $diversity_rating = 2;
        $diversity_comment = "Limited variety of products.";
    } else {
        $diversity_rating = 1;
        $diversity_comment = "Very limited product range.";
    }
    
    // Consistency rating - based on variance in monthly purchases
    $monthly_values = array_column($metrics['monthly_trend'], 'value');
    
    if (count($monthly_values) > 1) {
        $avg_monthly = array_sum($monthly_values) / count($monthly_values);
        $sum_squared_diff = 0;
        
        foreach ($monthly_values as $value) {
            $sum_squared_diff += pow($value - $avg_monthly, 2);
        }
        
        $variance = $sum_squared_diff / count($monthly_values);
        $std_dev = sqrt($variance);
        $coefficient_of_variation = $std_dev / $avg_monthly;
        
        if ($coefficient_of_variation < 0.1) {
            $consistency_rating = 5;
            $consistency_comment = "Highly consistent supply pattern with minimal fluctuations.";
        } elseif ($coefficient_of_variation < 0.2) {
            $consistency_rating = 4;
            $consistency_comment = "Good consistency in supply with minor fluctuations.";
        } elseif ($coefficient_of_variation < 0.3) {
            $consistency_rating = 3;
            $consistency_comment = "Moderate consistency with some fluctuations.";
        } elseif ($coefficient_of_variation < 0.4) {
            $consistency_rating = 2;
            $consistency_comment = "Inconsistent supply pattern with significant fluctuations.";
        } else {
            $consistency_rating = 1;
            $consistency_comment = "Very inconsistent supply pattern with major fluctuations.";
        }
    } else {
        $consistency_rating = 3;
        $consistency_comment = "Insufficient data to evaluate consistency.";
    }
    
    // Overall rating
    $overall_rating = round(($price_rating + $diversity_rating + $consistency_rating) / 3, 1);
    
    // Recommendations
    if ($overall_rating >= 4) {
        $recommendations = "This is a high-performing supplier and should be considered for increased business volume. Consider negotiating a long-term contract to secure favorable terms.";
    } elseif ($overall_rating >= 3) {
        $recommendations = "This supplier performs adequately. Monitor specific improvement areas noted in the report and schedule regular performance reviews.";
    } elseif ($overall_rating >= 2) {
        $recommendations = "This supplier needs improvement in several areas. Schedule a supplier review meeting to discuss concerns and establish an improvement plan.";
    } else {
        $recommendations = "This supplier's performance is below expectations. Consider reducing order volumes and exploring alternative suppliers while monitoring for improvements.";
    }
    
    return [
        'price_rating' => $price_rating,
        'price_comment' => $price_comment,
        'diversity_rating' => $diversity_rating,
        'diversity_comment' => $diversity_comment,
        'consistency_rating' => $consistency_rating,
        'consistency_comment' => $consistency_comment,
        'overall_rating' => $overall_rating,
        'recommendations' => $recommendations
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Performance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background:url("images/background60.jpg");
            margin: 0;
            padding: 20px;
            font-weight: bold;
        }
        .container {
            background-color: #d5731846;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0d6efd;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 500;
        }
        .alert {
            margin-top: 20px;
        }
        .report-form {
            background-color:rgb(255, 245, 241);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
            margin: 0 5px;
        }
        .home-btn:hover {
            background-color: #f28252;
        }
        .nav-btn-container {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; font-weight: bold; color: white; font-size: 2em; text-shadow: 2px 2px 5px lightblue;">Supplier Performance Report</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="report-form">
                    <h3>Generate Supplier Performance Report</h3>
                    <p>Select a supplier and date range to generate a comprehensive performance report.</p>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="supplier_id" class="form-label">Supplier</label>
                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php while($supplier = $suppliers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['supplier_id']; ?>" <?php if($supplier_id == $supplier['supplier_id']) echo "selected"; ?>>
                                            <?php echo $supplier['supplier_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" name="generate" class="btn btn-primary">Generate PDF Report</button>
                                <button type="reset" class="btn btn-secondary">Reset</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h3>What's Included in the Report</h3>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Comprehensive Supplier Analysis</h5>
                        <ul>
                            <li><strong>Performance Summary:</strong> Total purchases, value, and average pricing</li>
                            <li><strong>Category Distribution:</strong> Breakdown of purchases by category</li>
                            <li><strong>Monthly Trends:</strong> Purchase patterns over time</li>
                            <li><strong>Recent Purchases:</strong> Detailed view of latest transactions</li>
                            <li><strong>Performance Rating:</strong> Evaluation of pricing, diversity, and consistency</li>
                            <li><strong>Recommendations:</strong> Actionable insights for supplier management</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <br><br>
    <div class="nav-btn-container">
        <a href="purchase_management.php" class="home-btn">Back to Purchase Management</a>
        <a href="home.php" class="home-btn">Back to Home Page</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>