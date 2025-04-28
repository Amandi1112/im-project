<?php
// Database Configuration
$DB_HOST = 'localhost';
$DB_NAME = 'mywebsite';
$DB_USER = 'root';
$DB_PASS = '';

require('fpdf/fpdf.php'); // Include FPDF library

class MemberManager {
    private $conn;

    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }

    // Get total number of members
    // Replace your getTotalMembers method with this:
public function getTotalMembers($searchTerm = '', $filterColumn = '', $filterValue = '') {
    $whereClause = $this->buildWhereClause($searchTerm, $filterColumn, $filterValue);
    
    $sql = "SELECT COUNT(*) as total FROM members {$whereClause}";
    $stmt = $this->conn->prepare($sql);
    
    if (!empty($searchTerm)) {
        $searchParam = "%{$searchTerm}%";
        $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search4', $searchParam, PDO::PARAM_STR);
        $stmt->bindValue(':search5', $searchParam, PDO::PARAM_STR);
    }
    
    if (!empty($filterColumn) && !empty($filterValue)) {
        $stmt->bindValue(':filter', $filterValue);
    }
    
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

    // Build dynamic WHERE clause for search and filter
    private function buildWhereClause($searchTerm = '', $filterColumn = '', $filterValue = '') {
        $whereClauses = [];
        
        if (!empty($searchTerm)) {
            $whereClauses[] = "(
                full_name LIKE :search1 OR
                bank_membership_number LIKE :search2 OR
                id LIKE :search3 OR
                nic LIKE :search4 OR
                occupation LIKE :search5
            )";
        }
        
        if (!empty($filterColumn) && !empty($filterValue)) {
            $whereClauses[] = "{$filterColumn} = :filter";
        }
        
        return !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    }

    // Fetch members with pagination and search
    public function getMembers($page = 1, $perPage = 10, $searchTerm = '', $filterColumn = '', $filterValue = '', $sortColumn = 'id', $sortOrder = 'DESC') {
        $allowedColumns = ['id', 'full_name', 'age', 'monthly_income', 'registration_date'];
        $sortColumn = in_array($sortColumn, $allowedColumns) ? $sortColumn : 'id';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        
        $offset = ($page - 1) * $perPage;
        $whereClause = $this->buildWhereClause($searchTerm, $filterColumn, $filterValue);
        
        $sql = "
            SELECT
                id, full_name, bank_membership_number,
                address, nic, date_of_birth, age, telephone_number,
                occupation, monthly_income, credit_limit, registration_date
            FROM members
            {$whereClause}
            ORDER BY {$sortColumn} {$sortOrder}
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($searchTerm)) {
            $searchParam = "%{$searchTerm}%";
            $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search4', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search5', $searchParam, PDO::PARAM_STR);
        }
        
        if (!empty($filterColumn) && !empty($filterValue)) {
            $stmt->bindValue(':filter', $filterValue);
        }
        
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Export members to PDF
    public function exportMembersToPdf($searchTerm = '', $filterColumn = '', $filterValue = '') {
        $whereClause = $this->buildWhereClause($searchTerm, $filterColumn, $filterValue);
        
        $sql = "
            SELECT
                id, full_name, bank_membership_number,
                address, nic, date_of_birth, age, telephone_number,
                occupation, monthly_income, credit_limit,
                DATE(registration_date) as registration_date
            FROM members
            {$whereClause}
            ORDER BY registration_date DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($searchTerm)) {
            $searchParam = "%{$searchTerm}%";
            $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search3', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search4', $searchParam, PDO::PARAM_STR);
            $stmt->bindValue(':search5', $searchParam, PDO::PARAM_STR);
        }
        
        if (!empty($filterColumn) && !empty($filterValue)) {
            $stmt->bindValue(':filter', $filterValue);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Helper method to shorten text for PDF display
    public function shortenText($text, $maxLength) {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }

    // Get member by ID
    public function getMemberById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM members WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update member details
    public function updateMember($id, $data) {
        // Calculate new age based on updated date of birth
        $birthdate = new DateTime($data['date_of_birth']);
        $today = new DateTime('today');
        $age = $today->diff($birthdate)->y;

        $stmt = $this->conn->prepare("
            UPDATE members SET
                full_name = :full_name,
                bank_membership_number = :bank_membership_number,
                address = :address,
                nic = :nic,
                date_of_birth = :date_of_birth,
                age = :age,
                telephone_number = :telephone_number,
                occupation = :occupation,
                monthly_income = :monthly_income,
                credit_limit = :credit_limit
            WHERE id = :id
        ");

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':full_name', $data['full_name']);
        $stmt->bindParam(':bank_membership_number', $data['bank_membership_number']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':nic', $data['nic']);
        $stmt->bindParam(':date_of_birth', $data['date_of_birth']);
        $stmt->bindParam(':age', $age, PDO::PARAM_INT);
        $stmt->bindParam(':telephone_number', $data['telephone_number']);
        $stmt->bindParam(':occupation', $data['occupation']);
        $stmt->bindParam(':monthly_income', $data['monthly_income']);
        $stmt->bindParam(':credit_limit', $data['credit_limit']);

        return $stmt->execute();
    }

    // Delete member by ID
    public function deleteMember($id) {
        $stmt = $this->conn->prepare("DELETE FROM members WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

// Start output buffering
ob_start();

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $memberManager = new MemberManager($pdo);

    // PDF Export Handler
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
        $searchTerm = $_GET['search'] ?? '';
        $filterColumn = $_GET['filter_column'] ?? '';
        $filterValue = $_GET['filter_value'] ?? '';

        $members = $memberManager->exportMembersToPdf($searchTerm, $filterColumn, $filterValue);

        // Create PDF with professional design in landscape
$pdf = new FPDF('L','mm','A4');
$pdf->AddPage();

// ========== COLOR SCHEME (Same as invoice) ========== //
$primaryColor = array(102, 126, 234);   // #667eea
$primaryDark = array(90, 103, 216);    // #5a67d8
$secondaryColor = array(237, 242, 247); // #edf2f7
$dangerColor = array(229, 62, 62);      // #e53e3e
$successColor = array(72, 187, 120);    // #48bb78
$warningColor = array(237, 137, 54);    // #ed8936
$infoColor = array(66, 153, 225);       // #4299e1
$lightColor = array(247, 250, 252);     // #f7fafc
$darkColor = array(45, 55, 72);         // #2d3748
$grayColor = array(113, 128, 150);      // #718096
$grayLight = array(226, 232, 240);      // #e2e8f0

// ========== HEADER SECTION ========== //
// Header with primary color background
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Rect(10, 10, 277, 20, 'F');

// Shop name
$pdf->SetTextColor(255);
$pdf->SetFont('Helvetica','B',16);
$pdf->SetXY(15, 12);
$pdf->Cell(0,8,'T&C CO-OP CITY SHOP - KARAWITA',0,1,'L');

// Report info box
$pdf->SetFillColor($primaryDark[0], $primaryDark[1], $primaryDark[2]);
$pdf->Rect(200, 12, 80, 16, 'F');
$pdf->SetFont('Helvetica','B',12);
$pdf->SetXY(200, 12);
$pdf->Cell(80,8,'MEMBERS LIST',0,1,'C');
$pdf->SetFont('Helvetica','',10);
$pdf->SetXY(200, 18);
$pdf->Cell(80,6,date('F j, Y'),0,1,'C');

// Shop contact info
$pdf->SetTextColor(255);
$pdf->SetFont('Helvetica','',9);
$pdf->SetXY(15, 22);
$pdf->Cell(0,5,'Karawita | Tel: +94 11 2345678 | Email: info@tccoop.lk',0,1,'L');

// ========== TABLE SECTION ========== //
$pdf->SetY(40);

// Column widths (total width ~280mm for landscape A4)
$colWidths = [
    'name' => 40,
    'bank' => 15,
    'coop' => 20,
    'address' => 45,
    'nic' => 25,
    'age' => 10,
    'phone' => 25,
    'occupation' => 20,
    'income' => 25,
    'credit_limit' => 25,
    'reg_date' => 30
];

// Table header with primary color
$pdf->SetFont('Helvetica','B',10);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255);
$pdf->Cell($colWidths['name'], 8, 'Full Name', 1, 0, 'C', true);
$pdf->Cell($colWidths['bank'], 8, 'Bank ID', 1, 0, 'C', true);
$pdf->Cell($colWidths['coop'], 8, 'Coop No.', 1, 0, 'C', true);
$pdf->Cell($colWidths['address'], 8, 'Address', 1, 0, 'C', true);
$pdf->Cell($colWidths['nic'], 8, 'NIC', 1, 0, 'C', true);
$pdf->Cell($colWidths['age'], 8, 'Age', 1, 0, 'C', true);
$pdf->Cell($colWidths['phone'], 8, 'Phone', 1, 0, 'C', true);
$pdf->Cell($colWidths['occupation'], 8, 'Occupation', 1, 0, 'C', true);
$pdf->Cell($colWidths['income'], 8, 'Income', 1, 0, 'C', true);
$pdf->Cell($colWidths['credit_limit'], 8, 'Credit Limit', 1, 0, 'C', true);
$pdf->Cell($colWidths['reg_date'], 8, 'Reg. Date', 1, 1, 'C', true);

// Table data with alternate row colors
$pdf->SetTextColor($darkColor[0], $darkColor[1], $darkColor[2]);
$pdf->SetFont('Helvetica','',9);

$fill = false;
foreach ($members as $member) {
    $pdf->SetFillColor($fill ? $grayLight[0] : 255); // Alternate row colors
    $pdf->Cell($colWidths['name'], 7, $member['full_name'], 1, 0, 'L', $fill);
    $pdf->Cell($colWidths['bank'], 7, $member['bank_membership_number'], 1, 0, 'L', $fill);
    $pdf->Cell($colWidths['coop'], 7, $member['id'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidths['address'], 7, $memberManager->shortenText($member['address'], 30), 1, 0, 'L', $fill);
    $pdf->Cell($colWidths['nic'], 7, $member['nic'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidths['age'], 7, $member['age'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidths['phone'], 7, $member['telephone_number'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidths['occupation'], 7, $member['occupation'] ?? 'N/A', 1, 0, 'L', $fill);
    $pdf->Cell($colWidths['income'], 7, number_format($member['monthly_income'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($colWidths['credit_limit'], 7, number_format($member['credit_limit'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($colWidths['reg_date'], 7, date('Y-m-d', strtotime($member['registration_date'])), 1, 1, 'C', $fill);
    $fill = !$fill;
}

// ========== FOOTER SECTION ========== //
// Summary
$pdf->Ln(5);
$pdf->SetFont('Helvetica','I',10);
$pdf->Cell(0, 8, 'Total Members: ' . count($members), 0, 1, 'L');

// Footer
$pdf->SetY(-15);
$pdf->SetFont('Helvetica','I',8);
$pdf->SetTextColor($grayColor[0], $grayColor[1], $grayColor[2]);
$pdf->Cell(0,10,'Page ' . $pdf->PageNo(),0,0,'C');

// Output the PDF
$pdf->Output('Members_Export_' . date('Y-m-d') . '.pdf', 'D');
exit;
    }

    // Handle Get Member by ID
    if (isset($_GET['action']) && $_GET['action'] === 'get_member' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $member = $memberManager->getMemberById($id);
        
        if ($member) {
            header('Content-Type: application/json');
            echo json_encode($member);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Member not found']);
        }
        exit;
    }

    // AJAX Request Handler
    if (isset($_GET['page'])) {
        $page = $_GET['page'] ?? 1;
        $searchTerm = $_GET['search'] ?? '';
        $filterColumn = $_GET['filter_column'] ?? '';
        $filterValue = $_GET['filter_value'] ?? '';

        $members = $memberManager->getMembers($page, 10, $searchTerm, $filterColumn, $filterValue);
        $totalMembers = $memberManager->getTotalMembers($searchTerm, $filterColumn, $filterValue);

        header('Content-Type: application/json');
        echo json_encode([
            'members' => $members,
            'total' => $totalMembers
        ]);
        exit;
    }

    // Handle Edit Member
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = $_POST['id'];
        $data = [
            'full_name' => $_POST['full_name'],
            'bank_membership_number' => $_POST['bank_membership_number'],
            'address' => $_POST['address'],
            'nic' => $_POST['nic'],
            'date_of_birth' => $_POST['date_of_birth'],
            'telephone_number' => $_POST['telephone_number'],
            'occupation' => $_POST['occupation'],
            'monthly_income' => $_POST['monthly_income'],
            'credit_limit' => $_POST['credit_limit']
        ];

        $result = $memberManager->updateMember($id, $data);

        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit;
    }

    // Handle Delete Member
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $result = $memberManager->deleteMember($id);

        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit;
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Ensure output buffering is cleared
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<ty>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Members</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        
        .container {
            max-width: 1800px;
            margin: 20px auto;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        h1, h2, h3, h4 {
            color: var(--dark);
            font-weight: 600;
        }
        
        h2 {
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 3px;
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            border-radius: 3px;
        }
        
        .header-section {
            background-color: rgba(237, 242, 247, 0.7);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .btn {
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-dark), var(--primary));
        }
        
        .btn-success {
            background: linear-gradient(to right, var(--success), var(--success-dark));
        }
        
        .btn-success:hover {
            background: linear-gradient(to right, var(--success-dark), var(--success));
        }
        
        .btn-danger {
            background: linear-gradient(to right, var(--danger), var(--danger-dark));
        }
        
        .btn-danger:hover {
            background: linear-gradient(to right, var(--danger-dark), var(--danger));
        }
        
        .btn-warning {
            background: linear-gradient(to right, var(--warning), var(--warning-dark));
        }
        
        .btn-warning:hover {
            background: linear-gradient(to right, var(--warning-dark), var(--warning));
        }
        
        .btn-info {
            background: linear-gradient(to right, var(--info), var(--info-dark));
        }
        
        .btn-info:hover {
            background: linear-gradient(to right, var(--info-dark), var(--info));
        }
        
        .search-section {
            background-color: rgba(237, 242, 247, 0.7);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .table th {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 500;
            padding: 12px 15px;
            text-align: left;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .table tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .action-btn + .action-btn {
            margin-left: 8px;
        }
        
        .edit-btn {
            background-color: var(--info);
            color: white;
        }
        
        .edit-btn:hover {
            background-color: var(--info-dark);
        }
        
        .delete-btn {
            background-color: var(--danger);
            color: white;
        }
        
        .delete-btn:hover {
            background-color: var(--danger-dark);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .page-btn {
            padding: 8px 12px;
            border-radius: 4px;
            background-color: var(--gray-light);
            color: var(--dark);
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .page-btn:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .page-btn.active {
            background-color: var(--primary);
            color: white;
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal.show {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            max-height: 90vh;
        overflow-y: auto;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            transition: all 0.3s;
        }
        
        .modal.show .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .modal-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .table th, .table td {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .action-btn {
                padding: 5px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 35px;">Registered Members</h2>
            <div class="btn-group">
                <a href="member.php" class="btn btn-success" style="font-size: 15px;">
                    <i class="fas fa-plus"></i> Add New Member
                </a>
                <button id="exportPdfBtn" class="btn btn-info" style="font-size: 15px;">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>

        <div class="search-section">
            <form id="searchForm" class="search-form">
                <div class="form-group" style="font-size: 17px; font-weight: bold;">
                    <input type="text" name="search" placeholder="Search Members" class="form-control">
                </div>
                <div class="form-group">
                    <select name="filter_column" class="form-control">
                        <option value="">Select Filter</option>
                        <option value="nic">NIC</option>
                        <option value="telephone_number">Telephone Number</option>
                    </select>
                </div>
              
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="font-size: 15px;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="table" style="font-size:20px;">
                <thead>
                    <tr>
                        <th>Coop Number</th>
                        <th>Full Name</th>
                        <th>NIC</th>
                        <th>Age</th>
                        <th>Occupation</th>
                        <th>Monthly Income</th>
                        <th>Credit Limit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="membersTableBody" style="font-weight: bold;">
                    <!-- Members will be dynamically loaded here -->
                </tbody>
            </table>
        </div>

        <div id="pagination" class="pagination">
            <!-- Pagination links will be dynamically loaded here -->
        </div>
    </div>

    <!-- Edit Member Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Member</h3>
            </div>
            <form id="editMemberForm">
                <input type="hidden" name="id" id="editMemberId">
                <div class="form-group">
                    <label for="editFullName" class="form-label">Full Name</label>
                    <input type="text" id="editFullName" name="full_name" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="editBankMembershipNumber" class="form-label">Bank Membership Number</label>
                    <input type="text" id="editBankMembershipNumber" name="bank_membership_number" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="editAddress" class="form-label">Address</label>
                    <textarea id="editAddress" name="address" required class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="editNic" class="form-label">NIC</label>
                    <input type="text" id="editNic" name="nic" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="editDateOfBirth" class="form-label">Date of Birth</label>
                    <input type="date" id="editDateOfBirth" name="date_of_birth" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="editTelephoneNumber" class="form-label">Telephone Number</label>
                    <input type="tel" id="editTelephoneNumber" name="telephone_number" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="editOccupation" class="form-label">Occupation</label>
                    <input type="text" id="editOccupation" name="occupation" class="form-control">
                </div>
                <div class="form-group">
                    <label for="editMonthlyIncome" class="form-label">Monthly Income</label>
                    <input type="number" id="editMonthlyIncome" name="monthly_income" required step="0.01" class="form-control">
                </div>
                <div class="form-group">
                    <label for="editCreditLimit" class="form-label">Credit Limit</label>
                    <input type="number" id="editCreditLimit" name="credit_limit" required step="0.01" class="form-control">
                </div>
                <div class="modal-footer">
                    <button type="button" id="closeEditModal" class="btn btn-danger">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
    $(document).ready(function() {
        let currentPage = 1;
        const perPage = 10;

        // Function to load members
        function loadMembers(page = 1, search = '', filterColumn = '', filterValue = '') {
            $.ajax({
                url: '',
                method: 'GET',
                data: {
                    page: page,
                    search: search,
                    filter_column: filterColumn,
                    filter_value: filterValue
                },
                dataType: 'json',
                success: function(response) {
                    // Populate table
                    const tableBody = $('#membersTableBody');
                    tableBody.empty();

                    response.members.forEach(member => {
                        tableBody.append(`
                            <tr>
                                <td>${member.id}</td>
                                <td>${member.full_name}</td>
                                <td>${member.nic}</td>
                                <td>${member.age}</td>
                                <td>${member.occupation || 'N/A'}</td>
                                <td>${member.monthly_income}</td>
                                <td>${member.credit_limit || '0.00'}</td>
                                <td>
                                    <button class="action-btn edit-btn" data-id="${member.id}">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="action-btn delete-btn" data-id="${member.id}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        `);
                    });

                    // Pagination
                    const pagination = $('#pagination');
                    pagination.empty();

                    const totalPages = Math.ceil(response.total / perPage);
                    for (let i = 1; i <= totalPages; i++) {
                        pagination.append(`
                            <button class="page-btn ${i === page ? 'active' : ''}">${i}</button>
                        `);
                    }

                    // Pagination click handler
                    $('.page-btn').click(function() {
                        const pageNum = parseInt($(this).text());
                        loadMembers(pageNum, $('#searchForm [name="search"]').val(),
                            $('#searchForm [name="filter_column"]').val(),
                            $('#searchForm [name="filter_value"]').val());
                    });
                },
                error: function() {
                    alert('Failed to load members');
                }
            });
        }

        // Initial load
        loadMembers();

        // Search form submission
        $('#searchForm').submit(function(e) {
            e.preventDefault();
            const search = $('[name="search"]').val();
            const filterColumn = $('[name="filter_column"]').val();
            const filterValue = $('[name="filter_value"]').val();
            loadMembers(1, search, filterColumn, filterValue);
        });

        // PDF Export
        $('#exportPdfBtn').click(function() {
            const search = $('[name="search"]').val();
            const filterColumn = $('[name="filter_column"]').val();
            const filterValue = $('[name="filter_value"]').val();

            window.location.href = `?export=pdf&search=${search}&filter_column=${filterColumn}&filter_value=${filterValue}`;
        });

        // Edit Member
        $(document).on('click', '.edit-btn', function() {
            const memberId = $(this).data('id');
            $.ajax({
                url: window.location.href,
                method: 'GET',
                data: { 
                    id: memberId,
                    action: 'get_member'
                },
                dataType: 'json',
                success: function(member) {
                    if (member.error) {
                        alert(member.error);
                        return;
                    }
                    $('#editMemberId').val(member.id);
                    $('#editFullName').val(member.full_name);
                    $('#editBankMembershipNumber').val(member.bank_membership_number);
                    $('#editAddress').val(member.address);
                    $('#editNic').val(member.nic);
                    $('#editDateOfBirth').val(member.date_of_birth);
                    $('#editTelephoneNumber').val(member.telephone_number);
                    $('#editOccupation').val(member.occupation);
                    $('#editMonthlyIncome').val(member.monthly_income);
                    $('#editCreditLimit').val(member.credit_limit || '0.00');
                    $('#editModal').addClass('show');
                },
                error: function(xhr, status, error) {
                    alert('Error loading member data: ' + error);
                    console.error(xhr.responseText);
                }
            });
        });

        // Save Edited Member
        $('#editMemberForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize() + '&action=edit';
            $.ajax({
                url: '',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editModal').removeClass('show');
                        loadMembers(currentPage, $('#searchForm [name="search"]').val(),
                            $('#searchForm [name="filter_column"]').val(),
                            $('#searchForm [name="filter_value"]').val());
                    } else {
                        alert('Failed to update member');
                    }
                },
                error: function() {
                    alert('Failed to update member');
                }
            });
        });

        // Delete Member
        $(document).on('click', '.delete-btn', function() {
            const memberId = $(this).data('id');
            if (confirm('Are you sure you want to delete this member?')) {
                $.ajax({
                    url: '',
                    method: 'POST',
                    data: { id: memberId, action: 'delete' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadMembers(currentPage, $('#searchForm [name="search"]').val(),
                                $('#searchForm [name="filter_column"]').val(),
                                $('#searchForm [name="filter_value"]').val());
                        } else {
                            alert('Failed to delete member');
                        }
                    },
                    error: function() {
                        alert('Failed to delete member');
                    }
                });
            }
        });

        // Close Edit Modal
        $('#closeEditModal').click(function() {
            $('#editModal').removeClass('show');
        });
    });
    </script>
</body>
</html>