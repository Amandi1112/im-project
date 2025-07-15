<?php
// Database connection
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

// Function to get top customers by purchase amount
function getTopCustomers($conn, $limit = 5) {
    $sql = "SELECT m.id, m.full_name, m.bank_membership_number, 
                   SUM(p.total_price) as total_spent, 
                   COUNT(p.purchase_id) as purchase_count 
            FROM members m 
            JOIN purchases p ON m.id = p.member_id 
            GROUP BY m.id 
            ORDER BY total_spent DESC 
            LIMIT ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $topCustomers = [];
    while ($row = $result->fetch_assoc()) {
        $topCustomers[] = $row;
    }
    
    return $topCustomers;
}

// Function to get credit limit history
function getCreditLimitHistory($conn) {
    // This assumes you have a credit_limit_history table
    // If not, you'd need to track this separately or use timestamps from the members table
    
    $sql = "SELECT DATE_FORMAT(last_updated, '%Y-%m') as month, 
                   AVG(credit_limit) as avg_credit_limit
            FROM members 
            GROUP BY DATE_FORMAT(last_updated, '%Y-%m')
            ORDER BY month ASC";
            
    $result = $conn->query($sql);
    
    $creditHistory = [];
    while ($row = $result->fetch_assoc()) {
        $creditHistory[] = $row;
    }
    
    return $creditHistory;
}

// Function to get top selling items
function getTopSellingItems($conn, $limit = 5) {
    $sql = "SELECT i.item_id, i.item_name, 
                   SUM(p.quantity) as total_quantity, 
                   SUM(p.total_price) as total_revenue
            FROM items i 
            JOIN purchases p ON i.item_id = p.item_id 
            GROUP BY i.item_id 
            ORDER BY total_quantity DESC 
            LIMIT ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $topItems = [];
    while ($row = $result->fetch_assoc()) {
        $topItems[] = $row;
    }
    
    return $topItems;
}

// Function to get monthly sales data
function getMonthlySales($conn, $months = 12) {
    $sql = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') as month,
                   SUM(total_price) as monthly_sales
            FROM purchases
            WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(purchase_date, '%Y-%m')
            ORDER BY month ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $months);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monthlySales = [];
    while ($row = $result->fetch_assoc()) {
        $monthlySales[] = $row;
    }
    
    return $monthlySales;
}

// Function to get total members count
function getTotalMembers($conn) {
    $sql = "SELECT COUNT(*) as total FROM members";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'];
}

// Function to get total sales
function getTotalSales($conn) {
    $sql = "SELECT SUM(total_price) as total FROM purchases";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0; // Return 0 if null
}

// Function to get average credit utilization
function getAvgCreditUtilization($conn) {
    $sql = "SELECT AVG(current_credit_balance / credit_limit * 100) as avg_utilization 
            FROM members 
            WHERE credit_limit > 0";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['avg_utilization'] ?? 0; // Return 0 if null
}

// Get all the required data
$topCustomers = getTopCustomers($conn);
$creditHistory = getCreditLimitHistory($conn);
$topItems = getTopSellingItems($conn);
$monthlySales = getMonthlySales($conn);
$totalMembers = getTotalMembers($conn);
$totalSales = getTotalSales($conn);
$avgCreditUtilization = getAvgCreditUtilization($conn);

// Prepare data for charts
$monthLabels = [];
$salesData = [];
foreach ($monthlySales as $data) {
    $monthLabels[] = $data['month'];
    $salesData[] = $data['monthly_sales'];
}

$creditMonths = [];
$creditData = [];
foreach ($creditHistory as $data) {
    $creditMonths[] = $data['month'];
    $creditData[] = $data['avg_credit_limit'];
}

$customerNames = [];
$customerSpending = [];
foreach ($topCustomers as $customer) {
    $customerNames[] = $customer['full_name'];
    $customerSpending[] = $customer['total_spent'];
}

$itemNames = [];
$itemQuantities = [];
foreach ($topItems as $item) {
    $itemNames[] = $item['item_name'];
    $itemQuantities[] = $item['total_quantity'];
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Store Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #48bb78;
            --danger: #e53e3e;
            --danger-dark: #c53030;
            --success: #48bb78;
            --success-dark: #38a169;
            --warning: #ed8936;
            --warning-dark: #dd6b20;
            --info: #4299e1;
            --info-dark: #3182ce;
            --light: #f7fafc;
            --dark: #2d3748;
            --gray: #718096;
            --gray-light: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
           font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(120deg, #e0eafc 0%, #cfdef3 100%);
            color: var(--dark);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            position: relative;
            overflow-x: hidden;
        }
        body::before, body::after {
            content: '';
            position: fixed;
            z-index: 0;
            border-radius: 50%;
            filter: blur(8px);
        }
        body::before {
            top: -120px;
            left: -120px;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, #667eea 0%, #fff 80%);
            opacity: 0.13;
        }
        body::after {
            bottom: -120px;
            right: -120px;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, #ed8936 0%, #fff 80%);
            opacity: 0.11;
        }
                .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 16px 32px 16px;
            position: relative;
            z-index: 1;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 36px;
            padding-bottom: 18px;
            border-bottom: 1.5px solid var(--gray-light);
            background: rgba(255,255,255,0.55);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(102,126,234,0.10), 0 1.5px 8px rgba(90,103,216,0.07);
            padding: 28px 32px 18px 32px;
            gap: 18px;
            position: relative;
            overflow: hidden;
            border: 1.5px solid #e0eafc;
        }
        
        .dashboard-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: #2d3748;
            letter-spacing: 1.2px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        
        .date {
            font-size: 1.1rem;
            color: #5a67d8;
            font-weight: 600;
            background: #f7fafc;
            padding: 8px 18px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(102,126,234,0.07);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 36px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.75);
            border-radius: 16px;
            padding: 28px 24px 22px 24px;
            box-shadow: 0 6px 24px rgba(102,126,234,0.10);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            border: 2px solid #e0eafc;
            backdrop-filter: blur(6px);
            transition: all 0.3s cubic-bezier(.4,2,.6,1);
        }
        
        .stat-card:hover {
            transform: translateY(-7px) scale(1.04);
            box-shadow: 0 16px 32px rgba(102,126,234,0.15);
            border-color: #667eea33;
        }
        
        .stat-title {
            font-size: 1.08rem;
            color: #5a67d8;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 2.1rem;
            font-weight: 800;
            margin-bottom: 5px;
            color: #2d3748;
            letter-spacing: 1px;
        }
        
        .stat-icon {
            align-self: flex-end;
            font-size: 2.7rem;
            margin-top: -38px;
            color: #667eea;
            opacity: 0.18;
            transition: color 0.3s;
        }
        
        .stat-card:hover .stat-icon {
            color: #ed8936;
            opacity: 0.23;
        }
        
        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 28px;
            margin-bottom: 36px;
        }
        
        .chart-card {
            background: rgba(255,255,255,0.82);
            border-radius: 18px;
            padding: 28px 18px 24px 18px;
            box-shadow: 0 4px 18px rgba(102,126,234,0.08);
            border: 1.5px solid #e0eafc;
            backdrop-filter: blur(4px);
            transition: all 0.3s;
        }
        
        .chart-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 28px rgba(102,126,234,0.13);
            border-color: #667eea33;
        }
        
        .chart-title {
            font-size: 1.18rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #5a67d8;
            letter-spacing: 0.5px;
        }
        
        .table-container {
            background: rgba(255,255,255,0.85);
            border-radius: 14px;
            padding: 24px 12px 18px 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow-x: auto;
            border: 1.5px solid #e0eafc;
        }
        
        .table-title {
            font-size: 1.13rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #5a67d8;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 700;
            color: #2d3748;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-primary {
            background-color: #e3f2fd;
            color: var(--primary);
        }
        
        .badge-success {
            background-color: #e8f5e9;
            color: var(--secondary);
        }
        
        .footer {
            text-align: center;
            padding: 20px 0 10px 0;
            color: #95a5a6;
            font-size: 15px;
            background: transparent;
        }
        
        @media (max-width: 900px) {
            .chart-container {
                grid-template-columns: 1fr;
                gap: 18px;
            }
            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
            }
            .dashboard-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                padding: 18px 8px 14px 8px;
            }
        }
        @media (max-width: 600px) {
            .container {
                padding: 8px 2px 8px 2px;
            }
            .table-container {
                padding: 10px 2px 8px 2px;
            }
            .chart-card {
                padding: 12px 4px 10px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Member Purchase Summary</h1>
            <div class="date"><?php echo date('F d, Y'); ?></div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-title">Total Members</div>
                <div class="stat-value"><?php echo number_format($totalMembers); ?></div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Total Sales</div>
                <div class="stat-value">Rs.<?php echo number_format($totalSales, 2); ?></div>
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            </div>
            
            
        </div>
        
        <div class="chart-container">
            <div class="chart-card">
                <div class="chart-title">Monthly Sales</div>
                <canvas id="salesChart"></canvas>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">Credit Limit History</div>
                <canvas id="creditChart"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-card">
                <div class="chart-title">Top Customers</div>
                <canvas id="customersChart"></canvas>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">Top Selling Items</div>
                <canvas id="itemsChart"></canvas>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-title">Top 5 Customers by Purchase Amount</div>
            <table>
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Full Name</th>
                        <th>Membership Number</th>
                        <th>Total Spent</th>
                        <th>Purchase Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topCustomers as $customer): ?>
                    <tr>
                        <td><?php echo $customer['id']; ?></td>
                        <td><?php echo $customer['full_name']; ?></td>
                        <td><span class="badge badge-primary"><?php echo $customer['bank_membership_number']; ?></span></td>
                        <td>Rs.<?php echo number_format($customer['total_spent'], 2); ?></td>
                        <td><?php echo number_format($customer['purchase_count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-container">
            <div class="table-title">Top 5 Selling Items</div>
            <table>
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Item Name</th>
                        <th>Total Quantity Sold</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topItems as $item): ?>
                    <tr>
                        <td><?php echo $item['item_id']; ?></td>
                        <td><?php echo $item['item_name']; ?></td>
                        <td><?php echo number_format($item['total_quantity']); ?></td>
                        <td>Rs.<?php echo number_format($item['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>© <?php echo date('Y'); ?> Cooperative Store Management System</p>
        </div>
    </div>
       <a href="home.php" class="btn btn-primary floating-btn animate__animated animate__fadeInUp">
        <i class="fas fa-home"></i>
    </a>
    
    <script>
        // Monthly Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthLabels); ?>,
                datasets: [{
                    label: 'Monthly Sales (Rs.)',
                    data: <?php echo json_encode($salesData); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs.' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Credit Limit History Chart
        const creditCtx = document.getElementById('creditChart').getContext('2d');
        const creditChart = new Chart(creditCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($creditMonths); ?>,
                datasets: [{
                    label: 'Average Credit Limit (Rs.)',
                    data: <?php echo json_encode($creditData); ?>,
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs.' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Top Customers Chart
        const customersCtx = document.getElementById('customersChart').getContext('2d');
        const customersChart = new Chart(customersCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($customerNames); ?>,
                datasets: [{
                    label: 'Total Spent (Rs.)',
                    data: <?php echo json_encode($customerSpending); ?>,
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.7)',
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(155, 89, 182, 0.7)',
                        'rgba(243, 156, 18, 0.7)',
                        'rgba(231, 76, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgba(52, 152, 219, 1)',
                        'rgba(46, 204, 113, 1)',
                        'rgba(155, 89, 182, 1)',
                        'rgba(243, 156, 18, 1)',
                        'rgba(231, 76, 60, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs.' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Top Items Chart
        const itemsCtx = document.getElementById('itemsChart').getContext('2d');
        const itemsChart = new Chart(itemsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($itemNames); ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?php echo json_encode($itemQuantities); ?>,
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.7)',
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(155, 89, 182, 0.7)',
                        'rgba(243, 156, 18, 0.7)',
                        'rgba(231, 76, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgba(52, 152, 219, 1)',
                        'rgba(46, 204, 113, 1)',
                        'rgba(155, 89, 182, 1)',
                        'rgba(243, 156, 18, 1)',
                        'rgba(231, 76, 60, 1)'
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
    </script>
</body>
</html>