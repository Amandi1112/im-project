<?php
// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "mywebsite";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Process date filter form
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); // Default to first day of current month
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d'); // Default to today

// Initialize variables
$totalSales = 0;
$totalItems = 0;
$categorySales = [];
$dailySales = [];

// Get total sales for the period
$sql = "SELECT SUM(total_price) as total_sales, SUM(quantity) as total_items 
        FROM customer_transactions 
        WHERE transaction_date BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);
    $totalSales = $row['total_sales'] ?? 0;
    $totalItems = $row['total_items'] ?? 0;
}

// Get sales by category
$sql = "SELECT c.category_name, SUM(ct.total_price) as category_total 
        FROM customer_transactions ct
        JOIN items i ON ct.item_id = i.item_id
        JOIN categories c ON i.category_id = c.category_id
        WHERE ct.transaction_date BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
        GROUP BY c.category_id
        ORDER BY category_total DESC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categorySales[$row['category_name']] = $row['category_total'];
    }
}

// Get daily sales for the period
$sql = "SELECT DATE(transaction_date) as sale_date, SUM(total_price) as daily_total 
        FROM customer_transactions 
        WHERE transaction_date BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
        GROUP BY DATE(transaction_date)
        ORDER BY sale_date";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dailySales[$row['sale_date']] = $row['daily_total'];
    }
}

// Get top selling items
$sql = "SELECT i.item_name, SUM(ct.quantity) as total_quantity, SUM(ct.total_price) as total_revenue
        FROM customer_transactions ct
        JOIN items i ON ct.item_id = i.item_id
        WHERE ct.transaction_date BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
        GROUP BY ct.item_id
        ORDER BY total_quantity DESC
        LIMIT 5";
$topItems = [];
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $topItems[] = $row;
    }
}

// Prepare data for charts
$categoryLabels = json_encode(array_keys($categorySales));
$categoryData = json_encode(array_values($categorySales));

$dateLabels = json_encode(array_keys($dailySales));
$dateData = json_encode(array_values($dailySales));

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Summary Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color:rgb(219, 141, 52);
            --secondary-color:rgb(80, 64, 44);
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color:rgb(94, 76, 52);
        }
        
        body {
            background-color: #f5f8fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-header {
            background-color: var(--secondary-color);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .stats-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .stats-label {
            color: #7f8c8d;
            font-size: 1rem;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .date-filter {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .table-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        .report-title {
            margin-bottom: 0;
        }
        
        .date-range {
            color: #7f8c8d;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                background-color: white;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="report-title">Sales Summary Report</h1>
                    <p class="date-range">From: <?php echo date('F d, Y', strtotime($startDate)); ?> - To: <?php echo date('F d, Y', strtotime($endDate)); ?></p>
                </div>
                <div class="no-print">
                    <button class="btn btn-light" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="btn btn-light ms-2" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>
        
        <div class="date-filter no-print">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="stats-card text-center">
                    <div class="stats-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stats-value"><?php echo number_format($totalSales, 2); ?></div>
                    <div class="stats-label">Total Sales (Rs.)</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card text-center">
                    <div class="stats-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stats-value"><?php echo number_format($totalItems); ?></div>
                    <div class="stats-label">Items Sold</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Sales by Category</h5>
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Daily Sales Trend</h5>
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <h5>Top Selling Items</h5>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th class="text-end">Quantity Sold</th>
                        <th class="text-end">Revenue (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td class="text-end"><?php echo number_format($item['total_quantity']); ?></td>
                        <td class="text-end"><?php echo number_format($item['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topItems)): ?>
                    <tr>
                        <td colspan="3" class="text-center">No data available for selected period</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        // Chart.js configurations
        document.addEventListener('DOMContentLoaded', function() {
            // Category Sales Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo $categoryLabels; ?>,
                    datasets: [{
                        data: <?php echo $categoryData; ?>,
                        backgroundColor: [
                            '#3498db', '#2ecc71', '#f1c40f', '#e74c3c', '#9b59b6',
                            '#34495e', '#1abc9c', '#d35400', '#7f8c8d'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
            
            // Daily Sales Trend Chart
            const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
            const salesTrendChart = new Chart(salesTrendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $dateLabels; ?>,
                    datasets: [{
                        label: 'Daily Sales',
                        data: <?php echo $dateData; ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'Rs. ' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
        
        // Export to PDF function
        function exportToPDF() {
            window.jsPDF = window.jspdf.jsPDF;
            
            const doc = new jsPDF('p', 'mm', 'a4');
            const pageWidth = doc.internal.pageSize.getWidth();
            
            doc.setFontSize(18);
            doc.text('Sales Summary Report', pageWidth / 2, 15, { align: 'center' });
            
            doc.setFontSize(12);
            const dateRange = `From: <?php echo date('F d, Y', strtotime($startDate)); ?> - To: <?php echo date('F d, Y', strtotime($endDate)); ?>`;
            doc.text(dateRange, pageWidth / 2, 25, { align: 'center' });
            
            doc.setFontSize(14);
            doc.text('Total Sales: Rs. <?php echo number_format($totalSales, 2); ?>', 20, 40);
            doc.text('Items Sold: <?php echo number_format($totalItems); ?>', 20, 50);
            
            // Add charts using html2canvas
            html2canvas(document.querySelector("#categoryChart")).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                doc.addImage(imgData, 'PNG', 15, 65, 90, 60);
                
                html2canvas(document.querySelector("#salesTrendChart")).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    doc.addImage(imgData, 'PNG', 15, 130, 180, 60);
                    
                    // Add top selling items
                    doc.setFontSize(14);
                    doc.text('Top Selling Items', 20, 200);
                    
                    doc.setFontSize(10);
                    doc.text('Item Name', 20, 210);
                    doc.text('Quantity Sold', 120, 210);
                    doc.text('Revenue (Rs.)', 160, 210);
                    
                    doc.setLineWidth(0.1);
                    doc.line(20, 212, 190, 212);
                    
                    let yPos = 220;
                    <?php foreach ($topItems as $index => $item): ?>
                        doc.text('<?php echo addslashes(htmlspecialchars($item['item_name'])); ?>', 20, yPos);
                        doc.text('<?php echo number_format($item['total_quantity']); ?>', 120, yPos, { align: 'right' });
                        doc.text('<?php echo number_format($item['total_revenue'], 2); ?>', 160, yPos, { align: 'right' });
                        yPos += 10;
                    <?php endforeach; ?>
                    
                    doc.save('sales_summary_report.pdf');
                });
            });
        }
    </script>
</body>
</html>