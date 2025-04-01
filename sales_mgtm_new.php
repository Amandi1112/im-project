<?php
require('fpdf/fpdf.php');
require_once 'db_connection.php'; // Database connection file

session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database operations class
class SalesManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get filtered sales data
    public function getSalesData($filters = []) {
        $defaults = [
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-d'),
            'member_id' => null,
            'item_id' => null,
            'limit' => null,
            'page' => 1
        ];
        
        $filters = array_merge($defaults, $filters);
        
        $query = "SELECT p.purchase_id, p.purchase_date, 
                  m.id as member_id, m.full_name, m.member_id, 
                  i.item_id, i.item_name, i.item_code,
                  p.quantity, p.price_per_unit, p.total_price,
                  m.current_credit_balance
                  FROM purchases p
                  JOIN members m ON p.member_id = m.id
                  JOIN items i ON p.item_id = i.item_id
                  WHERE p.purchase_date BETWEEN ? AND ?";
        
        $params = [$filters['start_date'], $filters['end_date']];
        $types = "ss";
        
        if ($filters['member_id']) {
            $query .= " AND p.member_id = ?";
            $params[] = $filters['member_id'];
            $types .= "i";
        }
        
        if ($filters['item_id']) {
            $query .= " AND p.item_id = ?";
            $params[] = $filters['item_id'];
            $types .= "i";
        }
        
        $query .= " ORDER BY p.purchase_date DESC, p.purchase_id DESC";
        
        if ($filters['limit']) {
            $offset = ($filters['page'] - 1) * $filters['limit'];
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $filters['limit'];
            $params[] = $offset;
            $types .= "ii";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get members for dropdown
    public function getMembers() {
        $query = "SELECT id, full_name, member_id FROM members ORDER BY full_name";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get items for dropdown
    public function getItems() {
        $query = "SELECT item_id, item_name, item_code FROM items ORDER BY item_name";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get sales summary statistics
    public function getSalesSummary($startDate, $endDate) {
        $query = "SELECT 
                    COUNT(DISTINCT p.purchase_id) as transaction_count,
                    COUNT(DISTINCT p.member_id) as member_count,
                    SUM(p.quantity) as total_quantity,
                    SUM(p.total_price) as total_sales,
                    AVG(p.total_price) as avg_sale
                  FROM purchases p
                  WHERE p.purchase_date BETWEEN ? AND ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    // Get daily sales trends
    public function getDailySalesTrend($startDate, $endDate) {
        $query = "SELECT 
                    DATE(p.purchase_date) as date,
                    COUNT(p.purchase_id) as transaction_count,
                    SUM(p.total_price) as total_sales
                  FROM purchases p
                  WHERE p.purchase_date BETWEEN ? AND ?
                  GROUP BY DATE(p.purchase_date)
                  ORDER BY date";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get top selling items
    public function getTopItems($startDate, $endDate, $limit = 5) {
        $query = "SELECT 
                    i.item_id, i.item_name, i.item_code,
                    SUM(p.quantity) as total_quantity,
                    SUM(p.total_price) as total_sales
                  FROM purchases p
                  JOIN items i ON p.item_id = i.item_id
                  WHERE p.purchase_date BETWEEN ? AND ?
                  GROUP BY i.item_id
                  ORDER BY total_sales DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get member purchasing trends
    public function getTopMembers($startDate, $endDate, $limit = 5) {
        $query = "SELECT 
                    m.id as member_id, m.full_name, m.member_id,
                    COUNT(p.purchase_id) as transaction_count,
                    SUM(p.total_price) as total_spent
                  FROM purchases p
                  JOIN members m ON p.member_id = m.id
                  WHERE p.purchase_date BETWEEN ? AND ?
                  GROUP BY m.id
                  ORDER BY total_spent DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Custom PDF report generator
class SalesReport extends FPDF {
    private $title;
    private $period;
    private $logo = 'assets/logo.png';
    
    function __construct($title, $period) {
        parent::__construct('L', 'mm', 'A4');
        $this->title = $title;
        $this->period = $period;
    }
    
    function Header() {
        // Logo
        if (file_exists($this->logo)) {
            $this->Image($this->logo, 10, 10, 30);
        }
        
        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, $this->title, 0, 1, 'C');
        
        // Period
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, $this->period, 0, 1, 'C');
        
        // Line break
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 0, 'R');
    }
    
    function generateReport($data, $summary) {
        $this->AliasNbPages();
        $this->AddPage();
        
        // Report Summary
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Sales Summary', 0, 1);
        $this->Ln(5);
        
        $this->SetFont('Arial', '', 12);
        $this->Cell(60, 10, 'Total Sales:', 0, 0);
        $this->Cell(0, 10, 'Rs. ' . number_format($summary['total_sales'], 2), 0, 1);
        
        $this->Cell(60, 10, 'Transactions:', 0, 0);
        $this->Cell(0, 10, number_format($summary['transaction_count']), 0, 1);
        
        $this->Cell(60, 10, 'Active Members:', 0, 0);
        $this->Cell(0, 10, number_format($summary['member_count']), 0, 1);
        
        $this->Cell(60, 10, 'Items Sold:', 0, 0);
        $this->Cell(0, 10, number_format($summary['total_quantity']), 0, 1);
        
        $this->Cell(60, 10, 'Average Sale:', 0, 0);
        $this->Cell(0, 10, 'Rs. ' . number_format($summary['avg_sale'], 2), 0, 1);
        
        $this->Ln(15);
        
        // Detailed Transactions
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Sales Transactions', 0, 1);
        $this->Ln(5);
        
        // Table header
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(15, 10, 'ID', 1, 0, 'C', true);
        $this->Cell(25, 10, 'Date', 1, 0, 'C', true);
        $this->Cell(50, 10, 'Member', 1, 0, 'L', true);
        $this->Cell(20, 10, 'Co-op #', 1, 0, 'C', true);
        $this->Cell(50, 10, 'Item', 1, 0, 'L', true);
        $this->Cell(20, 10, 'Item Code', 1, 0, 'C', true);
        $this->Cell(15, 10, 'Qty', 1, 0, 'C', true);
        $this->Cell(25, 10, 'Unit Price', 1, 0, 'R', true);
        $this->Cell(25, 10, 'Total', 1, 1, 'R', true);
        
        // Table data
        $this->SetFont('Arial', '', 9);
        foreach ($data as $row) {
            $this->Cell(15, 10, $row['purchase_id'], 1, 0, 'C');
            $this->Cell(25, 10, $row['purchase_date'], 1, 0, 'C');
            $this->Cell(50, 10, substr($row['full_name'], 0, 20), 1, 0, 'L');
            $this->Cell(20, 10, $row['member_id'], 1, 0, 'C');
            $this->Cell(50, 10, substr($row['item_name'], 0, 25), 1, 0, 'L');
            $this->Cell(20, 10, $row['item_code'], 1, 0, 'C');
            $this->Cell(15, 10, $row['quantity'], 1, 0, 'C');
            $this->Cell(25, 10, number_format($row['price_per_unit'], 2), 1, 0, 'R');
            $this->Cell(25, 10, number_format($row['total_price'], 2), 1, 1, 'R');
        }
    }
}

// Initialize sales manager
$salesManager = new SalesManager($conn);

// Handle PDF generation
if (isset($_GET['action']) && $_GET['action'] == 'download_report') {
    $filters = [
        'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
        'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
        'member_id' => $_GET['member_id'] ?? null,
        'item_id' => $_GET['item_id'] ?? null
    ];
    
    $salesData = $salesManager->getSalesData($filters);
    $summary = $salesManager->getSalesSummary($filters['start_date'], $filters['end_date']);
    
    $title = "CO-OPERATIVE SOCIETY SALES REPORT";
    $period = "Period: " . date('j M Y', strtotime($filters['start_date'])) . 
              " to " . date('j M Y', strtotime($filters['end_date']));
    
    $pdf = new SalesReport($title, $period);
    $pdf->generateReport($salesData, $summary);
    $pdf->Output('D', 'Sales_Report_' . date('Ymd_His') . '.pdf');
    exit();
}

// Get filter parameters
$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
    'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
    'member_id' => $_GET['member_id'] ?? null,
    'item_id' => $_GET['item_id'] ?? null,
    'limit' => 20,
    'page' => $_GET['page'] ?? 1
];

// Get data for display
$salesData = $salesManager->getSalesData($filters);
$members = $salesManager->getMembers();
$items = $salesManager->getItems();
$summary = $salesManager->getSalesSummary($filters['start_date'], $filters['end_date']);
$dailyTrend = $salesManager->getDailySalesTrend($filters['start_date'], $filters['end_date']);
$topItems = $salesManager->getTopItems($filters['start_date'], $filters['end_date']);
$topMembers = $salesManager->getTopMembers($filters['start_date'], $filters['end_date']);

// Prepare data for charts
$chartDates = array_column($dailyTrend, 'date');
$chartSales = array_column($dailyTrend, 'total_sales');
$chartTransactions = array_column($dailyTrend, 'transaction_count');

$topItemNames = array_column($topItems, 'item_name');
$topItemSales = array_column($topItems, 'total_sales');

$topMemberNames = array_column($topMembers, 'full_name');
$topMemberSpending = array_column($topMembers, 'total_spent');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management | Co-operative Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .page-title {
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        
        .stat-card {
            padding: 1.5rem;
            border-radius: 0.5rem;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .stat-card .value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .label {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .stat-card.primary {
            background-color: var(--primary-color);
        }
        
        .stat-card.secondary {
            background-color: var(--secondary-color);
        }
        
        .stat-card.accent {
            background-color: var(--accent-color);
        }
        
        .stat-card.success {
            background-color: #27ae60;
        }
        
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
        }
        
        .filter-section {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            background-color: white;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        
        .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-left: none;
            border-right: none;
        }
        
        .list-group-item:first-child {
            border-top: none;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #1a252f;
            border-color: #1a252f;
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-danger {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-cart4 me-2"></i>Sales Management
        </h1>
        <div class="btn-group">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item" href="?action=download_report&<?php echo http_build_query($filters); ?>">
                        <i class="bi bi-file-pdf me-2"></i>PDF Report
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="bi bi-file-excel me-2"></i>Excel Export
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="filter-section">
        <form method="get" action="" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">From Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" 
                       value="<?php echo htmlspecialchars($filters['start_date']); ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">To Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" 
                       value="<?php echo htmlspecialchars($filters['end_date']); ?>">
            </div>
            <div class="col-md-3">
                <label for="member_id" class="form-label">Member</label>
                <select class="form-select" id="member_id" name="member_id">
                    <option value="">All Members</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['id']; ?>" 
                            <?php echo ($filters['member_id'] == $member['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['full_name'] . ' (' . $member['member_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="item_id" class="form-label">Item</label>
                <select class="form-select" id="item_id" name="item_id">
                    <option value="">All Items</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?php echo $item['item_id']; ?>" 
                            <?php echo ($filters['item_id'] == $item['item_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['item_name'] . ' (' . $item['item_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-funnel me-1"></i> Apply Filters
                </button>
                <a href="sales.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="stat-card primary">
                <div class="value">Rs. <?php echo number_format($summary['total_sales'], 2); ?></div>
                <div class="label">Total Sales</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card secondary">
                <div class="value"><?php echo number_format($summary['transaction_count']); ?></div>
                <div class="label">Transactions</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="value"><?php echo number_format($summary['member_count']); ?></div>
                <div class="label">Active Members</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card accent">
                <div class="value"><?php echo number_format($summary['total_quantity']); ?></div>
                <div class="label">Items Sold</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sales Trend</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                data-bs-toggle="dropdown">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo date('M Y', strtotime($filters['start_date'])); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">This Month</a></li>
                            <li><a class="dropdown-item" href="#">Last Month</a></li>
                            <li><a class="dropdown-item" href="#">This Quarter</a></li>
                            <li><a class="dropdown-item" href="#">This Year</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Selling Items</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 200px;">
                        <canvas id="topItemsChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($topItems as $item): ?>
                                <li class="list-group-item">
                                    <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo number_format($item['total_quantity']); ?> sold
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Members by Spending</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topMembers as $member): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                    <small class="text-muted"><?php echo $member['member_id']; ?></small>
                                </div>
                                <span class="badge bg-success rounded-pill">
                                    Rs. <?php echo number_format($member['total_spent'], 2); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Transactions</h5>
                    <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($salesData, 0, 5) as $sale): ?>
                                    <tr>
                                        <td><?php echo date('M j', strtotime($sale['purchase_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['full_name']); ?></td>
                                        <td class="fw-bold">Rs. <?php echo number_format($sale['total_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Sales Transactions</h5>
            <div>
                <a href="?action=download_report&<?php echo http_build_query($filters); ?>" 
                   class="btn btn-danger btn-sm">
                    <i class="bi bi-file-pdf me-1"></i> PDF Report
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Co-op #</th>
                            <th>Item</th>
                            <th>Item Code</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salesData as $sale): ?>
                            <tr>
                                <td><?php echo $sale['purchase_id']; ?></td>
                                <td><?php echo $sale['purchase_date']; ?></td>
                                <td><?php echo htmlspecialchars($sale['full_name']); ?></td>
                                <td><?php echo $sale['member_id']; ?></td>
                                <td><?php echo htmlspecialchars($sale['item_name']); ?></td>
                                <td><?php echo $sale['item_code']; ?></td>
                                <td><?php echo $sale['quantity']; ?></td>
                                <td>Rs. <?php echo number_format($sale['price_per_unit'], 2); ?></td>
                                <td>Rs. <?php echo number_format($sale['total_price'], 2); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-receipt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($salesData)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">No sales records found for the selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($salesData) > 0): ?>
            <nav aria-label="Page navigation" class="mt-3">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $filters['page'] <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php 
                            echo http_build_query(array_merge($filters, ['page' => $filters['page'] - 1])); 
                        ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <li class="page-item <?php echo $filters['page'] == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php 
                                echo http_build_query(array_merge($filters, ['page' => $i])); 
                            ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo count($salesData) < $filters['limit'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php 
                            echo http_build_query(array_merge($filters, ['page' => $filters['page'] + 1])); 
                        ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        flatpickr("#start_date", {
            dateFormat: "Y-m-d",
            defaultDate: "<?php echo $filters['start_date']; ?>"
        });
        
        flatpickr("#end_date", {
            dateFormat: "Y-m-d",
            defaultDate: "<?php echo $filters['end_date']; ?>"
        });

        // Sales Trend Chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendChart = new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartDates); ?>,
                datasets: [
                    {
                        label: 'Sales (Rs.)',
                        data: <?php echo json_encode($chartSales); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Transactions',
                        data: <?php echo json_encode($chartTransactions); ?>,
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.datasetIndex === 0) {
                                    label += 'Rs. ' + context.raw.toLocaleString();
                                } else {
                                    label += context.raw.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Sales (Rs.)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: 'Transactions'
                        }
                    }
                }
            }
        });

        // Top Items Chart
        const topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
        const topItemsChart = new Chart(topItemsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($topItemNames); ?>,
                datasets: [{
                    data: <?php echo json_encode($topItemSales); ?>,
                    backgroundColor: [
                        '#3498db',
                        '#2ecc71',
                        '#e74c3c',
                        '#f39c12',
                        '#9b59b6'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rs. ' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
