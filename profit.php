<?php
// Database connection
$host = 'localhost';
$dbname = 'mywebsite';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Get date range from user (default to current month)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Query for total purchases from suppliers
$purchaseQuery = $pdo->prepare("
    SELECT SUM(ip.total_price) as total_purchase, DATE(ip.purchase_date) as date
    FROM item_purchases ip
    WHERE ip.purchase_date BETWEEN :start_date AND :end_date
    GROUP BY DATE(ip.purchase_date)
    ORDER BY ip.purchase_date
");
$purchaseQuery->execute(['start_date' => $startDate, 'end_date' => $endDate]);
$purchaseData = $purchaseQuery->fetchAll(PDO::FETCH_ASSOC);

// Query for total sales to members
$salesQuery = $pdo->prepare("
    SELECT SUM(p.total_price) as total_sales, DATE(p.purchase_date) as date
    FROM purchases p
    WHERE p.purchase_date BETWEEN :start_date AND :end_date
    GROUP BY DATE(p.purchase_date)
    ORDER BY p.purchase_date
");
$salesQuery->execute(['start_date' => $startDate, 'end_date' => $endDate]);
$salesData = $salesQuery->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for chart
$labels = [];
$purchaseAmounts = [];
$salesAmounts = [];

// Create a date range to ensure all dates are represented
$period = new DatePeriod(
    new DateTime($startDate),
    new DateInterval('P1D'),
    new DateTime($endDate . ' +1 day')
);

foreach ($period as $date) {
    $dateStr = $date->format('Y-m-d');
    $labels[] = $dateStr;
    
    // Find purchase amount for this date
    $purchaseAmount = 0;
    foreach ($purchaseData as $row) {
        if ($row['date'] == $dateStr) {
            $purchaseAmount = $row['total_purchase'];
            break;
        }
    }
    $purchaseAmounts[] = $purchaseAmount;
    
    // Find sales amount for this date
    $salesAmount = 0;
    foreach ($salesData as $row) {
        if ($row['date'] == $dateStr) {
            $salesAmount = $row['total_sales'];
            break;
        }
    }
    $salesAmounts[] = $salesAmount;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase and Sales Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>
    <style>
       :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #edf2f7;
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
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            text-align: center;
            color: var(--primary-dark);
            margin-bottom: 30px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .date-filter {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .date-filter:hover {
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        .date-filter form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .date-filter label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .date-filter input {
            padding: 8px 12px;
            border: 1px solid var(--gray-light);
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .date-filter input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .date-filter button {
            padding: 8px 20px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .date-filter button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .chart-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-box {
            flex: 1;
            min-width: 400px;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .chart-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 20px rgba(0,0,0,0.1);
        }
        
        .chart-box h2 {
            color: var(--primary-dark);
            margin-top: 0;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .summary {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-box {
            padding: 20px;
            background: white;
            border-radius: 10px;
            text-align: center;
            min-width: 200px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            flex: 1;
            max-width: 300px;
        }
        
        .summary-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        .summary-box h3 {
            color: var(--gray);
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .summary-box p {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: var(--primary-dark);
        }
        
       
        
        .purchase-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(90, 103, 216, 0.1) 100%);
        }
        
        .sales-box {
            background: linear-gradient(135deg, rgba(237, 137, 54, 0.1) 0%, rgba(221, 107, 32, 0.1) 100%);
        }
        
        .sales-box h3 {
            color: var(--warning-dark);
        }
        
        .sales-box p {
            color: var(--warning-dark);
        }
        
        @media (max-width: 768px) {
            .chart-box {
                min-width: 100%;
            }
            
            .summary-box {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color:black">Purchase and Sales Summary</h1>
        
        <div class="date-filter">
            <form method="GET">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                
                <button type="submit">Filter</button>
            </form>
        </div>
        
        <?php
        // Calculate totals
        $totalPurchases = array_sum($purchaseAmounts);
        $totalSales = array_sum($salesAmounts);
   
        ?>
        
        <div class="summary">
            <div class="summary-box purchase-box">
                <h3>Total Purchases</h3>
                <p class="purchase-value"><?php echo number_format($totalPurchases, 2); ?></p>
            </div>
            <div class="summary-box sales-box">
                <h3>Total Sales</h3>
                <p class="sales-value"><?php echo number_format($totalSales, 2); ?></p>
            </div>
            
        </div>
        
        <div class="chart-container">
            <div class="chart-box">
                <h2>Daily Purchases and Sales</h2>
                <canvas id="combinedChart"></canvas>
            </div>
            
            <div class="chart-box">
                <h2>Purchases vs Sales</h2>
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        // Animate summary boxes on load
        document.addEventListener('DOMContentLoaded', function() {
            anime({
                targets: '.summary-box',
                translateY: [20, 0],
                opacity: [0, 1],
                duration: 800,
                delay: anime.stagger(200),
                easing: 'easeOutExpo'
            });
            
            anime({
                targets: '.chart-box',
                translateY: [30, 0],
                opacity: [0, 1],
                duration: 1000,
                delay: anime.stagger(200),
                easing: 'easeOutExpo'
            });
            
            // Animate numbers in summary boxes
            const purchaseValue = document.querySelector('.purchase-value');
            const salesValue = document.querySelector('.sales-value');
            
            
            const purchaseNum = parseFloat(purchaseValue.textContent.replace(/,/g, ''));
            const salesNum = parseFloat(salesValue.textContent.replace(/,/g, ''));
            
            
            animateValue(purchaseValue, 0, purchaseNum, 1000);
            animateValue(salesValue, 0, salesNum, 1000);
            
            
            function animateValue(element, start, end, duration) {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    const value = Math.floor(progress * (end - start) + start);
                    element.innerHTML = value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            }
        });
        
        // Prepare data for JavaScript
        const labels = <?php echo json_encode($labels); ?>;
        const purchaseData = <?php echo json_encode($purchaseAmounts); ?>;
        const salesData = <?php echo json_encode($salesAmounts); ?>;
        
        // Combined Line Chart
        const combinedCtx = document.getElementById('combinedChart').getContext('2d');
        const combinedChart = new Chart(combinedCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Purchases from Suppliers',
                        data: purchaseData,
                        borderColor: 'rgba(102, 126, 234, 0.7)',
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: 'rgb(30, 35, 57)',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Sales to Members',
                        data: salesData,
                        borderColor: 'rgb(237, 137, 54)',
                        backgroundColor: 'rgba(237, 137, 54, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: 'rgb(237, 137, 54)',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Purchases and Sales',
                        font: {
                            size: 16,
                            weight: '600'
                        },
                        padding: {
                            bottom: 20
                        }
                    },
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString('en-US', {style: 'currency', currency: 'LKR'});
                            }
                        },
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('en-US', {style: 'currency', currency: 'LKR'});
                            }
                        },
                        grid: {
                            color: 'rgba(102, 126, 234, 0.7)',
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        });
        
        // Comparison Bar Chart
        const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
        const comparisonChart = new Chart(comparisonCtx, {
            type: 'bar',
            data: {
                labels: ['Purchases', 'Sales'],
                datasets: [{
                    label: 'Amount',
                    data: [<?php echo $totalPurchases; ?>, <?php echo $totalSales; ?>],
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.7)',
                        'rgba(237, 137, 54, 0.7)',
                        'rgba(72, 187, 120, 0.7)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(237, 137, 54, 1)',
                        'rgba(72, 187, 120, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Total Purchases vs Sales',
                        font: {
                            size: 16,
                            weight: '600'
                        },
                        padding: {
                            bottom: 20
                        }
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString('en-US', {style: 'currency', currency: 'LKR'});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('en-US', {style: 'currency', currency: 'LKR'});
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        });
    </script>
</body>
</html>