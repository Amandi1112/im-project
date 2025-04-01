<?php
// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mywebsite';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if this is an AJAX request for chart data
if (isset($_GET['action']) && $_GET['action'] === 'get_purchase_trends') {
    header('Content-Type: application/json');
    
    // Get purchase trends for last 30 days
    $query = "SELECT 
                DATE(purchase_date) as purchase_day,
                COUNT(*) as purchase_count
              FROM purchases
              WHERE purchase_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
              GROUP BY DATE(purchase_date)
              ORDER BY purchase_day ASC";
    
    $result = $conn->query($query);
    
    $dates = [];
    $counts = [];
    
    // Fill in all days (even those with no purchases)
    $startDate = new DateTime('-30 days');
    $endDate = new DateTime();
    
    for ($date = $startDate; $date <= $endDate; $date->modify('+1 day')) {
        $dateStr = $date->format('Y-m-d');
        $dates[] = $dateStr;
        $counts[$dateStr] = 0; // Initialize with 0
    }
    
    // Update counts with actual data
    while ($row = $result->fetch_assoc()) {
        $counts[$row['purchase_day']] = (int)$row['purchase_count'];
    }
    
    // Prepare response
    $response = [
        'dates' => $dates,
        'counts' => array_values($counts)
    ];
    
    echo json_encode($response);
    $conn->close();
    exit;
}

// Function to get purchase statistics
function getPurchaseStats($conn) {
    $stats = [];
    
    // Total purchases
    $result = $conn->query("SELECT COUNT(*) as total_purchases FROM purchases");
    $stats['total_purchases'] = $result->fetch_assoc()['total_purchases'];
    
    // Total revenue
    $result = $conn->query("SELECT SUM(total_price) as total_revenue FROM purchases");
    $stats['total_revenue'] = $result->fetch_assoc()['total_revenue'];
    
    // Average purchase value
    $stats['avg_purchase'] = $stats['total_purchases'] > 0 ? $stats['total_revenue'] / $stats['total_purchases'] : 0;
    
    return $stats;
}

// Function to get loyal customers (recent and frequent buyers)
function getLoyalCustomers($conn, $limit = 10) {
    $query = "SELECT 
                m.id,
                m.full_name,
                
                m.telephone_number,
                COUNT(p.purchase_id) as purchase_count,
                SUM(p.total_price) as total_spent,
                MAX(p.purchase_date) as last_purchase_date,
                DATEDIFF(CURRENT_DATE, MAX(p.purchase_date)) as days_since_last_purchase
              FROM members m
              JOIN purchases p ON m.id = p.member_id
              GROUP BY m.id, m.full_name, m.telephone_number
              ORDER BY purchase_count DESC, days_since_last_purchase ASC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    return $customers;
}

// Function to get recent purchases
function getRecentPurchases($conn, $limit = 10) {
    $query = "SELECT 
                p.purchase_id,
                p.purchase_date,
                m.full_name,
              
                i.item_name,
                p.quantity,
                p.total_price
              FROM purchases p
              JOIN members m ON p.member_id = m.id
              JOIN items i ON p.item_id = i.item_id
              ORDER BY p.purchase_date DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchases = [];
    while ($row = $result->fetch_assoc()) {
        $purchases[] = $row;
    }
    
    return $purchases;
}

// Get data for dashboard
$stats = getPurchaseStats($conn);
$loyalCustomers = getLoyalCustomers($conn);
$recentPurchases = getRecentPurchases($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Purchase Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .stat {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-primary {
            background-color: #3498db;
            color: white;
        }
        .badge-success {
            background-color: #2ecc71;
            color: white;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        .refresh-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }
        .refresh-btn:hover {
            background-color: #2980b9;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f8f9fa;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
        }
        .tab.active {
            background-color: #3498db;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Customer Purchase Performance Analysis</h1>
            <p>Track customer purchases and identify loyal customers</p>
        </div>

        <button class="refresh-btn" onclick="refreshData()">Refresh Data</button>

        <div class="dashboard">
            <div class="card">
                <h2>Total Purchases</h2>
                <div class="stat"><?php echo number_format($stats['total_purchases']); ?></div>
                <div class="stat-label">All-time purchases</div>
            </div>
            <div class="card">
                <h2>Total Revenue</h2>
                <div class="stat">Rs. <?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Generated from purchases</div>
            </div>
            <div class="card">
                <h2>Average Purchase</h2>
                <div class="stat">Rs. <?php echo number_format($stats['avg_purchase'], 2); ?></div>
                <div class="stat-label">Per transaction</div>
            </div>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('loyal')">Loyal Customers</div>
            <div class="tab" onclick="switchTab('recent')">Recent Purchases</div>
            <div class="tab" onclick="switchTab('trends')">Purchase Trends</div>
        </div>

        <div id="loyal" class="tab-content active">
            <div class="card">
                <h2>Top Loyal Customers</h2>
                <p>Customers with most purchases and recent activity</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Co-op Number</th>
                            <th>Phone</th>
                            <th>Purchases</th>
                            <th>Total Spent</th>
                            <th>Last Purchase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loyalCustomers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['id']); ?></td>
                            <td><?php echo htmlspecialchars($customer['telephone_number']); ?></td>
                            <td><?php echo $customer['purchase_count']; ?></td>
                            <td>Rs. <?php echo number_format($customer['total_spent'], 2); ?></td>
                            <td>
                                <?php 
                                echo htmlspecialchars($customer['last_purchase_date']);
                                echo ' <span class="badge ';
                                echo $customer['days_since_last_purchase'] <= 30 ? 'badge-success' : 'badge-primary';
                                echo '">';
                                echo $customer['days_since_last_purchase'] . ' days ago';
                                echo '</span>';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="recent" class="tab-content">
            <div class="card">
                <h2>Recent Purchases</h2>
                <p>Latest transactions in the system</p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Co-op Number</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPurchases as $purchase): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($purchase['purchase_date']); ?></td>
                            <td><?php echo htmlspecialchars($purchase['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($purchase['member_id']); ?></td>
                            <td><?php echo htmlspecialchars($purchase['item_name']); ?></td>
                            <td><?php echo $purchase['quantity']; ?></td>
                            <td>Rs. <?php echo number_format($purchase['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="trends" class="tab-content">
            <div class="card">
                <h2>Purchase Trends</h2>
                <p>Daily purchase volume over the last 30 days</p>
                
                <div class="chart-container">
                    <canvas id="purchaseTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Deactivate all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Activate selected tab and content
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab[onclick="switchTab('${tabId}')"]`).classList.add('active');
            
            // If showing trends tab, render chart
            if (tabId === 'trends') {
                renderPurchaseTrendChart();
            }
        }

        // Refresh data
        function refreshData() {
            location.reload();
        }

        // Auto-refresh every 5 minutes
        setTimeout(refreshData, 5 * 60 * 1000);

        // Purchase trend chart
        function renderPurchaseTrendChart() {
            fetch('?action=get_purchase_trends')
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('purchaseTrendChart').getContext('2d');
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.dates,
                            datasets: [{
                                label: 'Daily Purchases',
                                data: data.counts,
                                backgroundColor: 'rgba(52, 152, 219, 0.2)',
                                borderColor: 'rgba(52, 152, 219, 1)',
                                borderWidth: 2,
                                tension: 0.1,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Number of Purchases'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Date'
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'Purchases: ' + context.parsed.y;
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error fetching trend data:', error);
                });
        }

        // Initialize the chart when the page loads if on trends tab
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('trends').classList.contains('active')) {
                renderPurchaseTrendChart();
            }
        });
    </script>
</body>
</html>