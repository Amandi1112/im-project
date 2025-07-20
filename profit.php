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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
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
            background: linear-gradient(120deg, #e0eafc 0%, #cfdef3 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            color: var(--dark);
            line-height: 1.6;
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 16px 32px 16px;
            position: relative;
            z-index: 1;
        }
        
        .header-card {
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(8px);
            border-radius: 22px;
            box-shadow: 0 8px 32px rgba(102,126,234,0.10), 0 1.5px 8px rgba(90,103,216,0.07);
            padding: 38px 32px 30px 32px;
            margin-bottom: 36px;
            display: flex;
            align-items: center;
            gap: 32px;
            position: relative;
            overflow: hidden;
            border: 1.5px solid #e0eafc;
        }
        .header-card .header-icon {
            font-size: 4.2rem;
            background: linear-gradient(135deg, #fff 60%, #667eea 100%);
            color: #667eea;
            border-radius: 50%;
            padding: 22px;
            box-shadow: 0 4px 16px rgba(90,103,216,0.10);
            animation: bounceIn 1.2s;
            border: 2.5px solid #e0eafc;
        }
        @keyframes bounceIn {
            0% { transform: scale(0.7); opacity: 0; }
            60% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); }
        }
        .header-card h1 {
            margin: 0 0 8px 0;
            font-size: 2.7rem;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.07);
            color: #2d3748;
        }
        .header-card p {
            margin: 0;
            font-size: 1.18rem;
            font-weight: 400;
            opacity: 0.97;
            color: #3b4252;
        }

        .date-filter {
            margin-bottom: 36px;
            padding: 24px 28px;
            background: rgba(255,255,255,0.65);
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(102,126,234,0.07);
            border: 1.5px solid #e0eafc;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: center;
            justify-content: center;
        }
        .date-filter label {
            font-weight: 600;
            color: #5a67d8;
            margin-right: 8px;
        }
        .date-filter input[type="date"] {
            padding: 8px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            background: #f7fafc;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .date-filter input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.13);
        }
        .date-filter button {
            padding: 9px 28px;
            background: linear-gradient(90deg, #667eea 0%, #5a67d8 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.08rem;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(102,126,234,0.10);
            transition: all 0.2s;
        }
        .date-filter button:hover {
            background: linear-gradient(90deg, #5a67d8 0%, #667eea 100%);
            transform: translateY(-2px) scale(1.04);
        }

        .summary {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 28px;
            margin-bottom: 38px;
            z-index: 1;
        }
        .summary-box {
            padding: 32px 24px 26px 24px;
            background: rgba(255,255,255,0.75);
            border-radius: 20px;
            text-align: center;
            min-width: 220px;
            box-shadow: 0 6px 24px rgba(102,126,234,0.10);
            transition: all 0.3s cubic-bezier(.4,2,.6,1);
            flex: 1;
            max-width: 340px;
            position: relative;
            overflow: hidden;
            border: 2px solid #e0eafc;
            backdrop-filter: blur(6px);
        }
        .summary-box:hover {
            transform: translateY(-7px) scale(1.04);
            box-shadow: 0 16px 32px rgba(102,126,234,0.15);
            border-color: #667eea33;
        }
        .summary-box .summary-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: inline-block;
            color: #fff;
            border-radius: 50%;
            padding: 13px;
            margin-top: -18px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .purchase-box .summary-icon {
            background: linear-gradient(135deg, #667eea 60%, #5a67d8 100%);
        }
        .sales-box .summary-icon {
            background: linear-gradient(135deg, #ed8936 60%, #dd6b20 100%);
        }
        .profit-box .summary-icon {
            background: linear-gradient(135deg, #48bb78 60%, #38a169 100%);
        }
        .loss-box .summary-icon {
            background: linear-gradient(135deg, #e53e3e 60%, #c53030 100%);
        }
        .summary-box h3 {
            color: #5a67d8;
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 1.13rem;
            letter-spacing: 0.5px;
        }
        .summary-box p {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0;
            color: #2d3748;
            letter-spacing: 1px;
        }
        .profit-box p {
            color: #38a169;
        }
        .loss-box p {
            color: #c53030;
        }

        .chart-container {
            display: flex;
            flex-wrap: wrap;
            gap: 36px;
            margin-bottom: 36px;
        }
        
        .chart-box {
            flex: 1;
            min-width: 380px;
            padding: 28px 18px 24px 18px;
            border-radius: 18px;
            background: rgba(255,255,255,0.82);
            box-shadow: 0 4px 18px rgba(102,126,234,0.08);
            transition: all 0.3s;
            border: 1.5px solid #e0eafc;
            backdrop-filter: blur(4px);
        }
        
        .chart-box:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 28px rgba(102,126,234,0.13);
            border-color: #667eea33;
        }
        
        .chart-box h2 {
            color: #5a67d8;
            margin-top: 0;
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: 0.5px;
        }
        
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.18);
            transition: all 0.3s;
            z-index: 1000;
            background: linear-gradient(135deg, #667eea 60%, #5a67d8 100%);
            color: #fff;
            font-size: 2rem;
            border: none;
        }
        .floating-btn:hover {
            transform: translateY(-3px) scale(1.09);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.23);
            background: linear-gradient(135deg, #5a67d8 60%, #667eea 100%);
            color: #fff;
        }

        @media (max-width: 900px) {
            .chart-container {
                flex-direction: column;
                gap: 24px;
            }
            .summary {
                flex-direction: column;
                gap: 18px;
            }
            .header-card {
                flex-direction: column;
                gap: 18px;
                text-align: center;
            }
        }
        @media (max-width: 600px) {
            .container {
                padding: 8px 2px 8px 2px;
            }
            .header-card {
                padding: 18px 8px 14px 8px;
            }
            .date-filter {
                padding: 12px 6px;
            }
            .summary-box {
                padding: 18px 8px 12px 8px;
            }
            .chart-box {
                padding: 12px 4px 10px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card animate__animated animate__fadeInDown">
            <span class="header-icon"><i class="fas fa-chart-line"></i></span>
            <div>
                <h1>Purchase & Sales Summary</h1>
                <p>Visualize your shop's daily purchases, sales, and profit trends at a glance.</p>
            </div>
        </div>
        
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
        $profit = $totalSales - $totalPurchases;
        ?>
        
        <div class="summary">
            <div class="summary-box purchase-box animate__animated animate__fadeInUp">
                <span class="summary-icon"><i class="fas fa-shopping-cart"></i></span>
                <h3>Total Purchases</h3>
                <p class="purchase-value"><?php echo number_format($totalPurchases, 2); ?></p>
            </div>
            <div class="summary-box sales-box animate__animated animate__fadeInUp">
                <span class="summary-icon"><i class="fas fa-cash-register"></i></span>
                <h3>Total Sales</h3>
                <p class="sales-value"><?php echo number_format($totalSales, 2); ?></p>
            </div>
            <div class="summary-box <?php echo ($profit >= 0) ? 'profit-box' : 'loss-box'; ?> animate__animated animate__fadeInUp">
                <span class="summary-icon"><i class="fas fa-coins"></i></span>
                <h3><?php echo ($profit >= 0) ? 'Profit' : 'Loss'; ?></h3>
                <p><?php echo number_format(abs($profit), 2); ?></p>
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