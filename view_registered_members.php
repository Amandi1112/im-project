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

    // Calculate current credit balance for a member
        public function calculateCreditBalance($memberId) {
            $stmt = $this->conn->prepare("
                SELECT SUM(total_price) as total_purchases 
                FROM purchases 
                WHERE member_id = :member_id
            ");
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalPurchases = $result['total_purchases'] ?? 0;
            
            return $totalPurchases;
        }

        // Get member with calculated credit balance
        public function getMemberWithCreditBalance($id) {
            $member = $this->getMemberById($id);
            if ($member) {
                $creditUsed = $this->calculateCreditBalance($id);
                $member['credit_used'] = $creditUsed;
                $member['available_credit'] = $member['credit_limit'] - $creditUsed;
            }
            return $member;
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
                    m.id, m.full_name, m.bank_membership_number,
                    m.address, m.nic, m.date_of_birth, m.age, m.telephone_number,
                    m.occupation, m.monthly_income, m.credit_limit, m.registration_date,
                    COALESCE(SUM(p.total_price), 0) as credit_used,
                    (m.credit_limit - COALESCE(SUM(p.total_price), 0)) as available_credit
                FROM members m
                LEFT JOIN purchases p ON m.id = p.member_id
                {$whereClause}
                GROUP BY m.id, m.full_name, m.bank_membership_number, m.address, m.nic, 
                        m.date_of_birth, m.age, m.telephone_number, m.occupation, 
                        m.monthly_income, m.credit_limit, m.registration_date
                ORDER BY m.{$sortColumn} {$sortOrder}
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
        $stmt = $this->conn->prepare("SELECT * FROM members WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update member details
        // Update the existing updateMember method to remove credit_limit parameter
                public function updateMember($id, $data) {
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
                    monthly_income = :monthly_income
                WHERE id = :id LIMIT 1
            ");

            $stmt->bindParam(':id', $id, PDO::PARAM_STR);
            $stmt->bindParam(':full_name', $data['full_name'], PDO::PARAM_STR);
            $stmt->bindParam(':bank_membership_number', $data['bank_membership_number'], PDO::PARAM_STR);
            $stmt->bindParam(':address', $data['address'], PDO::PARAM_STR);
            $stmt->bindParam(':nic', $data['nic'], PDO::PARAM_STR);
            $stmt->bindParam(':date_of_birth', $data['date_of_birth'], PDO::PARAM_STR);
            $stmt->bindParam(':age', $age, PDO::PARAM_INT);
            $stmt->bindParam(':telephone_number', $data['telephone_number'], PDO::PARAM_STR);
            $stmt->bindParam(':occupation', $data['occupation'], PDO::PARAM_STR);
            $stmt->bindParam(':monthly_income', $data['monthly_income'], PDO::PARAM_STR);

            return $stmt->execute();
        }

    // Delete member by ID
    public function deleteMember($id) {
        $stmt = $this->conn->prepare("DELETE FROM members WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        return $stmt->execute();
    }
}

// Start output buffering
ob_start();

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $memberManager = new MemberManager($pdo);

    // PDF Export Handler - PROFESSIONAL VERSION
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
        $searchTerm = $_GET['search'] ?? '';
        $filterColumn = $_GET['filter_column'] ?? '';
        $filterValue = $_GET['filter_value'] ?? '';

        $members = $memberManager->exportMembersToPdf($searchTerm, $filterColumn, $filterValue);

        // Create Professional PDF with Corporate Design
        $pdf = new FPDF('L','mm','A4');
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 20);

        // ========== PROFESSIONAL COLOR SCHEME ========== //
        $corporateBlue = array(23, 37, 84);      // #172554 - Deep professional blue
        $accentBlue = array(59, 130, 246);       // #3B82F6 - Modern blue accent
        $lightBlue = array(239, 246, 255);       // #EFF6FF - Very light blue
        $darkGray = array(31, 41, 55);           // #1F2937 - Professional dark gray
        $mediumGray = array(107, 114, 128);      // #6B7280 - Medium gray
        $lightGray = array(243, 244, 246);       // #F3F4F6 - Light gray background
        $successGreen = array(16, 185, 129);     // #10B981 - Professional green
        $warningOrange = array(245, 158, 11);    // #F59E0B - Professional orange

        // ========== HEADER SECTION WITH LOGO SPACE ========== //
        // Main header background with gradient effect
        $pdf->SetFillColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
        $pdf->Rect(10, 10, 277, 35, 'F');
        
        // Logo placeholder (you can add actual logo here)
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(15, 15, 25, 25, 'F');
        $pdf->SetTextColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
        $pdf->SetFont('Arial','B',14);
        $pdf->SetXY(15, 25);
        $pdf->Cell(25, 8, 'LOGO', 0, 0, 'C');

        // Organization name and details
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial','B',18);
        $pdf->SetXY(45, 15);
        $pdf->Cell(0, 8, 'PAHALA KARAWITA COOPERATIVE SOCIETY', 0, 1, 'L');
        
        $pdf->SetFont('Arial','',11);
        $pdf->SetXY(45, 23);
        $pdf->Cell(0, 5, 'Karawita, Ratnapura, Sri Lanka', 0, 1, 'L');
        
        $pdf->SetFont('Arial','',10);
        $pdf->SetXY(45, 28);
        $pdf->Cell(0, 4, 'Tel: +94 11 2345678 | Email: co_op@sanasa.com | Reg No: CO-OP/2024/001', 0, 1, 'L');
        
        $pdf->SetFont('Arial','',9);
        $pdf->SetXY(45, 33);
        $pdf->Cell(0, 4, 'Established 1995 | Licensed by Department of Cooperative Development', 0, 1, 'L');

        // Report title box with modern design
        $pdf->SetFillColor($accentBlue[0], $accentBlue[1], $accentBlue[2]);
        $pdf->Rect(200, 12, 82, 31, 'F');
        
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial','B',14);
        $pdf->SetXY(200, 16);
        $pdf->Cell(82, 8, 'MEMBERS DIRECTORY', 0, 1, 'C');
        
        $pdf->SetFont('Arial','',10);
        $pdf->SetXY(200, 24);
        $pdf->Cell(82, 5, 'Generated: ' . date('F j, Y'), 0, 1, 'C');
        $pdf->SetXY(200, 29);
        $pdf->Cell(82, 5, 'Time: ' . date('g:i A'), 0, 1, 'C');
        $pdf->SetXY(200, 34);
        $pdf->Cell(82, 5, 'Total Records: ' . count($members), 0, 1, 'C');

        // ========== REPORT SUMMARY SECTION ========== //
        // ========== PROFESSIONAL TABLE SECTION ========== //
        $pdf->SetY(50);

        // Professional column widths for landscape A4
        $colWidths = [
            'coop_no' => 20,
            'name' => 28,
            'nic' => 28,
            'address' => 45,
            'phone' => 25,
            'age' => 12,
            'occupation' => 30,
            'income' => 28,
            'credit' => 28,
            'reg_date' => 24
        ];

        // Table header with professional styling
        $pdf->SetFont('Arial','B',9);
        $pdf->SetFillColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
        $pdf->SetTextColor(255, 255, 255);
        
        // Header row
        $pdf->Cell($colWidths['coop_no'], 10, 'RegNo.', 1, 0, 'C', true);
        $pdf->Cell($colWidths['name'], 10, 'Full Name', 1, 0, 'C', true);
        $pdf->Cell($colWidths['nic'], 10, 'NIC Number', 1, 0, 'C', true);
        $pdf->Cell($colWidths['address'], 10, 'Address', 1, 0, 'C', true);
        $pdf->Cell($colWidths['phone'], 10, 'Contact No.', 1, 0, 'C', true);
        $pdf->Cell($colWidths['age'], 10, 'Age', 1, 0, 'C', true);
        $pdf->Cell($colWidths['occupation'], 10, 'Occupation', 1, 0, 'C', true);
        $pdf->Cell($colWidths['income'], 10, 'Monthly Income', 1, 0, 'C', true);
        $pdf->Cell($colWidths['credit'], 10, 'Credit Limit', 1, 0, 'C', true);
        $pdf->Cell($colWidths['reg_date'], 10, 'Reg. Date', 1, 1, 'C', true);

        // Table data with professional alternating colors
        $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
        $pdf->SetFont('Arial','',8);

        $rowNum = 0;
        foreach ($members as $member) {
            // Check if we need a new page
            if ($pdf->GetY() > 180) {
                $pdf->AddPage();
                
                // Repeat header on new page
                $pdf->SetFont('Arial','B',9);
                $pdf->SetFillColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
                $pdf->SetTextColor(255, 255, 255);
                
                $pdf->Cell($colWidths['coop_no'], 10, 'Coop No.', 1, 0, 'C', true);
                $pdf->Cell($colWidths['name'], 10, 'Full Name', 1, 0, 'C', true);
                $pdf->Cell($colWidths['nic'], 10, 'NIC Number', 1, 0, 'C', true);
                $pdf->Cell($colWidths['address'], 10, 'Address', 1, 0, 'C', true);
                $pdf->Cell($colWidths['phone'], 10, 'Phone Number', 1, 0, 'C', true);
                $pdf->Cell($colWidths['age'], 10, 'Age', 1, 0, 'C', true);
                $pdf->Cell($colWidths['occupation'], 10, 'Occupation', 1, 0, 'C', true);
                $pdf->Cell($colWidths['income'], 10, 'Monthly Income', 1, 0, 'C', true);
                $pdf->Cell($colWidths['credit'], 10, 'Credit Limit', 1, 0, 'C', true);
                $pdf->Cell($colWidths['reg_date'], 10, 'Reg. Date', 1, 1, 'C', true);
                
                $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
                $pdf->SetFont('Arial','',8);
            }

            $fill = ($rowNum % 2 == 0);
            $pdf->SetFillColor($fill ? $lightGray[0] : 255, $fill ? $lightGray[1] : 255, $fill ? $lightGray[2] : 255);
            
            $pdf->Cell($colWidths['coop_no'], 8, str_pad($member['id'], 4, '0', STR_PAD_LEFT), 1, 0, 'C', $fill);
            $pdf->Cell($colWidths['name'], 8, $memberManager->shortenText($member['full_name'], 20), 1, 0, 'L', $fill);
            $pdf->Cell($colWidths['nic'], 8, $member['nic'], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths['address'], 8, $memberManager->shortenText($member['address'], 30), 1, 0, 'L', $fill);
            $pdf->Cell($colWidths['phone'], 8, $member['telephone_number'], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths['age'], 8, $member['age'], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths['occupation'], 8, $memberManager->shortenText($member['occupation'] ?? 'N/A', 18), 1, 0, 'L', $fill);
            $pdf->Cell($colWidths['income'], 8, 'Rs. ' . number_format($member['monthly_income'], 0), 1, 0, 'R', $fill);
            $pdf->Cell($colWidths['credit'], 8, 'Rs. ' . number_format($member['credit_limit'], 0), 1, 0, 'R', $fill);
            $pdf->Cell($colWidths['reg_date'], 8, date('Y-m-d', strtotime($member['registration_date'])), 1, 1, 'C', $fill);
            
            $rowNum++;
        }

        // ========== PROFESSIONAL FOOTER ========== //
        $pdf->Ln(10);
        
        // Footer information box
        $pdf->SetFillColor($lightBlue[0], $lightBlue[1], $lightBlue[2]);
        $pdf->Rect(10, $pdf->GetY(), 277, 25, 'F');
        
        $pdf->SetTextColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
        $pdf->SetFont('Arial','B',10);
        $pdf->SetXY(15, $pdf->GetY() + 3);
        $pdf->Cell(0, 6, 'REPORT CERTIFICATION', 0, 1, 'L');
        
        $pdf->SetFont('Arial','',8);
        $currentY = $pdf->GetY();
        $pdf->SetXY(15, $currentY);
        $pdf->Cell(0, 4, 'This report contains ' . count($members) . ' member records as of ' . date('F j, Y \a\t g:i A'), 0, 1, 'L');
        $pdf->SetXY(15, $currentY + 5);
        $pdf->Cell(0, 4, 'Generated by: Cooperative Management System', 0, 1, 'L');
        $pdf->SetXY(15, $currentY + 10);
        $pdf->Cell(0, 4, 'Authorized by: Clerk, Pahala Karawita Cooperative Society', 0, 1, 'L');
        
        // Signature lines
        $pdf->SetXY(15, $currentY + 40);
        $pdf->Cell(80, 4, 'Clerk Signature: ________________________', 0, 0, 'L');
        $pdf->Cell(80, 4, 'Authorized Officer Signature: ________________________', 0, 1, 'L');

        // Page footer
        $pdf->SetY(-15);
        $pdf->SetFont('Arial','I',8);
        $pdf->SetTextColor($mediumGray[0], $mediumGray[1], $mediumGray[2]);
        $pdf->Cell(0, 5, 'Pahala Karawita Cooperative Society - Members Directory Report', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Page ' . $pdf->PageNo() . ' | Generated on ' . date('Y-m-d H:i:s') . ' | Confidential Document', 0, 0, 'C');

        // Output the professional PDF
        $filename = 'Members_Directory_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdf->Output($filename, 'D');
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
    try {
        $id = $_POST['id'];
        $data = [
            'full_name' => $_POST['full_name'],
            'bank_membership_number' => $_POST['bank_membership_number'],
            'address' => $_POST['address'],
            'nic' => $_POST['nic'],
            'date_of_birth' => $_POST['date_of_birth'],
            'telephone_number' => $_POST['telephone_number'],
            'occupation' => $_POST['occupation'],
            'monthly_income' => $_POST['monthly_income']
        ];

        $result = $memberManager->updateMember($id, $data);

        echo json_encode(['success' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type for AJAX requests early
if (isset($_GET['page']) || (isset($_POST['action']) && in_array($_POST['action'], ['edit', 'delete'])) || (isset($_GET['action']) && $_GET['action'] === 'get_member')) {
    header('Content-Type: application/json');
}

// Ensure output buffering is cleared
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
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
                        <th>Membership No.</th>
                        <th>Full Name</th>
                        <th>NIC</th>
                        <th>Age</th>
                        <th>Occupation</th>
                        <th>Monthly Income</th>
                        <th>Credit Limit</th>
                        <th>Credit Used</th>
                        <th>Available Credit</th>
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
            <!-- Credit information display (read-only) -->
            <div class="form-group">
                <label class="form-label">Credit Information</label>
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #e9ecef;">
                    <p><strong>Credit Limit:</strong> Rs. <span id="displayCreditLimit">0.00</span></p>
                    <p><strong>Credit Used:</strong> Rs. <span id="displayCreditUsed">0.00</span></p>
                    <p><strong>Available Credit:</strong> Rs. <span id="displayAvailableCredit">0.00</span></p>
                </div>
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
                const tableBody = $('#membersTableBody');
                tableBody.empty();

                response.members.forEach(member => {
                    // Color coding for available credit
                    let creditColor = 'green';
                    if (member.available_credit < 0) {
                        creditColor = 'red';
                    } else if (member.available_credit < (member.credit_limit * 0.2)) {
                        creditColor = 'orange';
                    }

                    tableBody.append(`
                        <tr>
                            <td>${member.id}</td>
                            <td>${member.full_name}</td>
                            <td>${member.nic}</td>
                            <td>${member.age}</td>
                            <td>${member.occupation || 'N/A'}</td>
                            <td>Rs. ${parseFloat(member.monthly_income).toLocaleString()}</td>
                            <td>Rs. ${parseFloat(member.credit_limit).toLocaleString()}</td>
                            <td>Rs. ${parseFloat(member.credit_used).toLocaleString()}</td>
                            <td style="color: ${creditColor}; font-weight: bold;">Rs. ${parseFloat(member.available_credit).toLocaleString()}</td>
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
    
    // Create URL parameters
    let params = new URLSearchParams();
    params.append('export', 'pdf');
    if (search) params.append('search', search);
    if (filterColumn) params.append('filter_column', filterColumn);
    if (filterValue) params.append('filter_value', filterValue);
    
    window.location.href = `?${params.toString()}`;
});

        $(document).on('click', '.edit-btn', function() {
        const memberId = $(this).data('id');
        
        $('#editMemberForm')[0].reset();
        
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { 
                action: 'get_member',
                id: memberId
            },
            dataType: 'json',
            success: function(member) {
                if (member.error) {
                    alert(member.error);
                    return;
                }
                
                // Set form values (excluding credit_limit)
                $('#editMemberId').val(member.id);
                $('#editFullName').val(member.full_name);
                $('#editBankMembershipNumber').val(member.bank_membership_number);
                $('#editAddress').val(member.address);
                $('#editNic').val(member.nic);
                $('#editDateOfBirth').val(member.date_of_birth);
                $('#editTelephoneNumber').val(member.telephone_number);
                $('#editOccupation').val(member.occupation);
                $('#editMonthlyIncome').val(member.monthly_income);
                
                // Display credit information (read-only)
                $('#displayCreditLimit').text(parseFloat(member.credit_limit || 0).toLocaleString());
                $('#displayCreditUsed').text(parseFloat(member.credit_used || 0).toLocaleString());
                $('#displayAvailableCredit').text(parseFloat(member.available_credit || member.credit_limit || 0).toLocaleString());
                
                $('#editModal').addClass('show');
            },
            error: function(xhr, status, error) {
                console.error('Error details:', xhr.responseText);
                alert('Error loading member data: ' + error);
            }
        });
    });

        // Updated form submission (removes credit_limit from data)
            $('#editMemberForm').submit(function(e) {
    e.preventDefault();
    const formData = $(this).serialize() + '&action=edit';
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#editModal').removeClass('show');
                loadMembers(currentPage, 
                    $('#searchForm [name="search"]').val(),
                    $('#searchForm [name="filter_column"]').val(),
                    $('#searchForm [name="filter_value"]').val());
                alert('Member updated successfully!');
            } else {
                alert('Failed to update member: ' + (response.error || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error details:', xhr.responseText);
            // Check if response is HTML (error page)
            if (xhr.responseText.includes('<br />') || xhr.responseText.includes('<html>')) {
                alert('Server error occurred. Check browser console for details.');
                console.log('Server returned HTML instead of JSON:', xhr.responseText);
            } else {
                alert('Failed to update member: ' + error);
            }
        }
    });
});

        // Delete Member
        $(document).on('click', '.delete-btn', function() {
    const memberId = $(this).data('id');
    
    if (confirm('Are you sure you want to delete this member?')) {
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { 
                action: 'delete',
                id: memberId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Reload the current page of members
                    loadMembers(currentPage, 
                        $('#searchForm [name="search"]').val(),
                        $('#searchForm [name="filter_column"]').val(),
                        $('#searchForm [name="filter_value"]').val());
                } else {
                    alert('Failed to delete member');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error details:', xhr.responseText);
                alert('Failed to delete member: ' + error);
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