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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            font-family:'Poppins','sans-serif';
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .dashboard-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
        }
        
        .stat-title {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-icon {
            align-self: flex-end;
            font-size: 32px;
            margin-top: -50px;
            color: var(--primary);
            opacity: 0.2;
        }
        
        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .table-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        .table-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
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
            padding: 20px 0;
            color: #95a5a6;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
            
            <div class="stat-card">
                <div class="stat-title">Avg Credit Utilization</div>
                <div class="stat-value"><?php echo number_format($avgCreditUtilization, 1); ?>%</div>
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Monthly Growth</div>
                <?php 
                    $growth = 0;
                    if (count($salesData) >= 2) {
                        $current = end($salesData);
                        $previous = prev($salesData);
                        if ($previous > 0) {
                            $growth = (($current - $previous) / $previous) * 100;
                        }
                    }
                ?>
                <div class="stat-value" style="color: <?php echo $growth >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                    <?php echo $growth >= 0 ? '+' : ''; ?><?php echo number_format($growth, 1); ?>%
                </div>
                <div class="stat-icon">
                    <i class="fas <?php echo $growth >= 0 ? 'fa-chart-line' : 'fa-chart-line'; ?>"></i>
                </div>
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