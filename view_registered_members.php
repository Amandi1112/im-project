<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'mywebsite');
define('DB_USER', 'root');
define('DB_PASS', '');

require('fpdf/fpdf.php'); // Include FPDF library

class MemberManager {
    private $conn;

    public function __construct(PDO $database_connection) {
        $this->conn = $database_connection;
    }

    /**
     * Calculate current credit balance for a member
     */
    public function calculateCreditBalance(string $memberId): float {
        $stmt = $this->conn->prepare("
            SELECT SUM(total_price) as total_purchases 
            FROM purchases 
            WHERE member_id = :member_id
        ");
        $stmt->bindParam(':member_id', $memberId, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($result['total_purchases'] ?? 0);
    }

    /**
     * Get member with calculated credit balance
     */
    public function getMemberWithCreditBalance(string $id): ?array {
        $member = $this->getMemberById($id);
        if ($member) {
            $creditUsed = $this->calculateCreditBalance($id);
            $member['credit_used'] = $creditUsed;
            $member['available_credit'] = $member['credit_limit'] - $creditUsed;
        }
        return $member;
    }

    /**
     * Build dynamic WHERE clause for search and filter
     */
   

    /**
     * Bind search parameters to PDO statement
     */
    

    /**
     * Bind filter parameters to PDO statement
     */
   

    /**
     * Fetch members with pagination, search, filter, and sorting
     */
    public function getMembers(
        int $page = 1,
        int $perPage = 10,
        string $sortColumn = 'id',
        string $sortOrder = 'DESC',
        ?string $filterColumn = null,
        ?string $filterValue = null
    ): array {
        $allowedColumns = ['id', 'full_name', 'age', 'monthly_income', 'registration_date'];
        $sortColumn = in_array($sortColumn, $allowedColumns) ? $sortColumn : 'id';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($filterColumn && $filterValue) {
            if ($filterColumn === 'nic') {
                $where = 'WHERE m.nic LIKE :filterValue';
                $params[':filterValue'] = "%$filterValue%";
            } elseif ($filterColumn === 'name') {
                $where = 'WHERE m.full_name LIKE :filterValue';
                $params[':filterValue'] = "%$filterValue%";
            }
        }

        $sql = "
            SELECT
                m.id, m.full_name, m.bank_membership_number,
                m.address, m.nic, m.date_of_birth, m.age, m.telephone_number,
                m.occupation, m.monthly_income, m.credit_limit, m.registration_date,
                COALESCE(SUM(p.total_price), 0) as credit_used,
                (m.credit_limit - COALESCE(SUM(p.total_price), 0)) as available_credit
            FROM members m
            LEFT JOIN purchases p ON m.id = p.member_id
            $where
            GROUP BY m.id, m.full_name, m.bank_membership_number, m.address, m.nic, 
                    m.date_of_birth, m.age, m.telephone_number, m.occupation, 
                    m.monthly_income, m.credit_limit, m.registration_date
            ORDER BY m.{$sortColumn} {$sortOrder}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total number of members with optional search and filter
     */
    public function getTotalMembers(?string $filterColumn = null, ?string $filterValue = null): int {
        $where = '';
        $params = [];
        if ($filterColumn && $filterValue) {
            if ($filterColumn === 'nic') {
                $where = 'WHERE nic LIKE :filterValue';
                $params[':filterValue'] = "%$filterValue%";
            } elseif ($filterColumn === 'name') {
                $where = 'WHERE full_name LIKE :filterValue';
                $params[':filterValue'] = "%$filterValue%";
            }
        }
        $sql = "SELECT COUNT(*) as total FROM members $where";
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Export members to PDF
     */
    public function exportMembersToPdf(): array {
    $sql = "
        SELECT
            id, full_name, bank_membership_number,
            address, nic, date_of_birth, age, telephone_number,
            occupation, monthly_income, credit_limit,
            DATE(registration_date) as registration_date
        FROM members
        ORDER BY registration_date DESC
    ";
    
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /**
     * Helper method to shorten text for PDF display
     */
    public function shortenText(string $text, int $maxLength): string {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }

    /**
     * Get member by ID
     */
    public function getMemberById(string $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM members WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update member details
     */
public function updateMember(string $id, array $data): array {
    // First get the current member data to check credit used
    $currentMember = $this->getMemberWithCreditBalance($id);
    if (!$currentMember) {
        return ['success' => false, 'message' => 'Member not found'];
    }

    // Calculate age from date of birth
    $birthdate = new DateTime($data['date_of_birth']);
    $today = new DateTime('today');
    $age = $today->diff($birthdate)->y;

    try {
        // Calculate new credit limit (30% of monthly income)
        $newCreditLimit = $currentMember['credit_limit'];
        $monthlyIncome = $currentMember['monthly_income']; // Default to current income
        
        // Only update income and credit limit if credit used is zero
        if (isset($data['monthly_income'])) {
            if ((float)$currentMember['credit_used'] != 0 && 
                $data['monthly_income'] != $currentMember['monthly_income']) {
                // Keep the original income if credit has been used
                $monthlyIncome = $currentMember['monthly_income'];
                return [
                    'success' => true, 
                    'message' => 'Member updated (income not changed - credit has been used)',
                    'new_credit_limit' => $newCreditLimit
                ];
            } else {
                $monthlyIncome = $data['monthly_income'];
                $newCreditLimit = $monthlyIncome * 0.3;
            }
        }

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
        $stmt->bindParam(':monthly_income', $monthlyIncome, PDO::PARAM_STR);
        $stmt->bindParam(':credit_limit', $newCreditLimit);

        $result = $stmt->execute();
        
        // Always return success=true if the query executed
        return [
            'success' => true,
            'message' => 'Member updated successfully',
            'new_credit_limit' => $newCreditLimit
        ];
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}
    /**
     * Delete member by ID
     */
    public function deleteMember(string $id): bool {
        $stmt = $this->conn->prepare("DELETE FROM members WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_STR);
        return $stmt->execute();
    }
}

// Start output buffering
ob_start();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $memberManager = new MemberManager($pdo);

    // PDF Export Handler
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $members = $memberManager->exportMembersToPdf();

        // Create PDF with professional design
        $pdf = new FPDF('L','mm','A4');
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 20);

        // Color scheme
        $corporateBlue = [23, 37, 84];      // #172554
        $accentBlue = [59, 130, 246];      // #3B82F6
        $lightBlue = [239, 246, 255];      // #EFF6FF
        $darkGray = [31, 41, 55];          // #1F2937
        $mediumGray = [107, 114, 128];     // #6B7280
        $lightGray = [243, 244, 246];       // #F3F4F6

        // Header Section
        $pdf->SetFillColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
        $pdf->Rect(10, 10, 277, 35, 'F');
        
        // Logo placeholder
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(15, 15, 25, 25, 'F');
        $pdf->SetTextColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
        $pdf->SetFont('Arial','B',14);
        $pdf->SetXY(15, 25);
        // Add logo image (replace with your actual path if needed)
        $pdf->Image('images/logo.jpeg', 15, 15, 25, 25);

        // Organization details
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

        // Report title box
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

        // Table Section
        $pdf->SetY(50);

        // Column widths
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

        // Table header
        $pdf->SetFont('Arial','B',9);
        $pdf->SetFillColor($corporateBlue[0], $corporateBlue[1], $corporateBlue[2]);
        $pdf->SetTextColor(255, 255, 255);
        
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

        // Table data
        $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
        $pdf->SetFont('Arial','',8);

        $rowNum = 0;
        foreach ($members as $member) {
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

        // Footer Section
        $pdf->Ln(10);
        
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

        // Output PDF
        $filename = 'Members_Directory_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdf->Output($filename, 'D');
        exit;
    }

    // Handle Get Member by ID
    if (isset($_GET['action']) && $_GET['action'] === 'get_member' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        
        $id = $_GET['id'];
        $member = $memberManager->getMemberWithCreditBalance($id);
        
        if ($member) {
            echo json_encode($member);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Member not found']);
        }
        exit;
    }

    // --- SUGGESTIONS ENDPOINT ---
if (isset($_GET['suggest']) && isset($_GET['filter_column']) && isset($_GET['q'])) {
    header('Content-Type: application/json');
    $column = $_GET['filter_column'];
    $q = $_GET['q'];
    if ($column === 'nic' || $column === 'name') {
        $field = $column === 'nic' ? 'nic' : 'full_name';
        $stmt = $pdo->prepare("SELECT DISTINCT $field FROM members WHERE $field LIKE :q LIMIT 10");
        $stmt->bindValue(':q', "%$q%", PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($results);
        exit;
    }
}

    // AJAX Request Handler for paginated/filterable data
    if (isset($_GET['page'])) {
    header('Content-Type: application/json');
    $page = (int)($_GET['page'] ?? 1);
    $filterColumn = isset($_GET['filter_column']) ? $_GET['filter_column'] : null;
    $filterValue = isset($_GET['filter_value']) ? $_GET['filter_value'] : null;
    $members = $memberManager->getMembers($page, 10, 'id', 'DESC', $filterColumn, $filterValue);
    $totalMembers = $memberManager->getTotalMembers($filterColumn, $filterValue);
    echo json_encode([
        'members' => $members,
        'total' => $totalMembers
    ]);
    exit;
}

    // Handle Edit Member details
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    header('Content-Type: application/json');
    
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
        
        // Ensure we're returning valid JSON
        echo json_encode($result);
    } catch (Exception $e) {
        // Return error in consistent format
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
    exit;
}

    // Handle Delete Member
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        
        $id = $_POST['id'];
        $result = $memberManager->deleteMember($id);

        echo json_encode(['success' => $result]);
        exit;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Ensure output buffering is cleared
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Members | Cooperative Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #a5b4fc;
            --secondary: #f3f4f6;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --success: #10b981;
            --success-dark: #059669;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --info: #3b82f6;
            --info-dark: #2563eb;
            --light: #f9fafb;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 1rem;
            box-shadow: var(--shadow-lg);
        }
        
        h1, h2, h3, h4 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        h2 {
            position: relative;
            padding-bottom: 0.5rem;
            font-size: 2rem;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 5rem;
            height: 0.25rem;
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            border-radius: 0.25rem;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1.5rem;
            background-color: rgba(239, 246, 255, 0.7);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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
        
        .btn-danger {
            background: linear-gradient(to right, var(--danger), var(--danger-dark));
        }
        
        .btn-warning {
            background: linear-gradient(to right, var(--warning), var(--warning-dark));
        }
        
        .btn-info {
            background: linear-gradient(to right, var(--info), var(--info-dark));
        }
        
        .search-section {
            padding: 1.5rem;
            background-color: rgba(239, 246, 255, 0.7);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: 0.5rem;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        
        .form-control:disabled {
            cursor: not-allowed;
            opacity: 0.8;
            background-color: #f3f4f6;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        
        .table th {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 500;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .table tr:hover {
            background-color: rgba(79, 70, 229, 0.05);
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .action-btn + .action-btn {
            margin-left: 0.5rem;
        }
        
        .edit-btn {
            background-color: var(--info);
            color: white;
        }
        
        .delete-btn {
            background-color: var(--danger);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .page-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            background-color: var(--secondary);
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
            padding: 1.5rem;
            border-radius: 0.75rem;
            max-height: 90vh;
            overflow-y: auto;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow-lg);
            transform: translateY(-20px);
            transition: all 0.3s;
        }
        
        .modal.show .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .modal-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        /* Column selector styles */
        .column-selector {
            position: relative;
            display: inline-block;
        }
        
        .column-selector-btn {
            background-color: var(--warning);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
        }
        
        .column-selector-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 220px;
            box-shadow: var(--shadow-lg);
            z-index: 1;
            border-radius: 0.5rem;
            padding: 0.75rem;
            right: 0;
        }
        
        .column-selector:hover .column-selector-content {
            display: block;
        }
        
        .column-option {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            border-radius: 0.375rem;
        }
        
        .column-option:hover {
            background-color: var(--secondary);
        }
        
        .column-option input {
            margin-right: 0.75rem;
        }
        
        /* Credit status colors */
        .credit-positive {
            color: var(--success);
        }
        
        .credit-warning {
            color: var(--warning);
        }
        
        .credit-danger {
            color: var(--danger);
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .table th, .table td {
                padding: 0.75rem;
            }
            
            .action-btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.8125rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h2>Registered Members</h2>
            <div class="btn-group">
        <!-- Filter/Search Form -->
        <form id="searchForm" class="search-form" style="margin-bottom: 1rem; display: flex; gap: 1rem; align-items: center;">
            <label for="filter_column">Filter by:</label>
            <select id="filter_column" name="filter_column" class="form-control" style="width: 150px;">
                <option value="">Select</option>
                <option value="nic">NIC</option>
                <option value="name">Name</option>
            </select>
            <input type="text" id="filter_value" name="filter_value" class="form-control" placeholder="Enter value" style="width: 200px;" disabled>
            <button type="submit" class="btn btn-primary">Search</button>
            <button type="button" id="clearFilterBtn" class="btn btn-secondary">Clear</button>
        </form>
                <a href="member.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Member
                </a>
                <button id="exportPdfBtn" class="btn btn-info">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <div class="column-selector">
                    <button class="btn btn-warning column-selector-btn">
                        <i class="fas fa-columns"></i> Columns
                    </button>
                    <div class="column-selector-content">
                        <div class="column-option">
                            <input type="checkbox" id="col-id" checked data-column="0">
                            <label for="col-id">Membership No.</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-name" checked data-column="1">
                            <label for="col-name">Full Name</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-nic" checked data-column="2">
                            <label for="col-nic">NIC</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-age" checked data-column="3">
                            <label for="col-age">Age</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-occupation" checked data-column="4">
                            <label for="col-occupation">Occupation</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-income" checked data-column="5">
                            <label for="col-income">Monthly Income</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-credit-limit" checked data-column="6">
                            <label for="col-credit-limit">Credit Limit</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-credit-used" checked data-column="7">
                            <label for="col-credit-used">Credit Used</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-available-credit" checked data-column="8">
                            <label for="col-available-credit">Available Credit</label>
                        </div>
                        <div class="column-option">
                            <input type="checkbox" id="col-actions" checked data-column="9">
                            <label for="col-actions">Actions</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        

        <div class="table-container">
            <table class="table">
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
                <tbody id="membersTableBody">
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
                <h3>Edit Member Details</h3>
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
                    <label for="editMonthlyIncome" class="form-label">Monthly Income (Rs.)</label>
                    <input type="number" id="editMonthlyIncome" name="monthly_income" required step="0.01" class="form-control">
                </div>
                
                <!-- Credit Information Display -->
                <div class="form-group">
                    <label class="form-label">Credit Information</label>
                    <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e9ecef;">
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
    <script>
    $(document).ready(function() {
        // Enable/disable filter value input
        $('#filter_column').on('change', function() {
            if ($(this).val()) {
                $('#filter_value').prop('disabled', false).attr('placeholder', 'Enter ' + $(this).find('option:selected').text());
            } else {
                $('#filter_value').prop('disabled', true).val('').attr('placeholder', 'Enter value');
            }
        });

        // Clear filter button
        $('#clearFilterBtn').on('click', function() {
            $('#filter_column').val('').trigger('change');
            $('#filter_value').val('');
            loadMembers(1);
        });
        let currentPage = 1;
        const perPage = 10;

        // Initialize Select2 for dropdowns
        $('select').select2({
            width: '100%',
            placeholder: $(this).data('placeholder')
        });

        // Function to determine credit status class
        function getCreditStatusClass(availableCredit, creditLimit) {
            if (availableCredit < 0) {
                return 'credit-danger';
            } else if (availableCredit < (creditLimit * 0.2)) {
                return 'credit-warning';
            }
            return 'credit-positive';
        }

        // Function to toggle columns
        function toggleColumns() {
            $('.column-option input').each(function() {
                const columnIndex = $(this).data('column');
                const isChecked = $(this).is(':checked');
                
                // Toggle both header and body cells
                $('table th').eq(columnIndex).toggle(isChecked);
                $('table td:nth-child(' + (columnIndex + 1) + ')').toggle(isChecked);
            });
        }

        // Initialize column selector
        $('.column-option input').change(function() {
            toggleColumns();
            
            // Save column preferences to localStorage
            const columnPrefs = {};
            $('.column-option input').each(function() {
                const columnId = $(this).attr('id');
                columnPrefs[columnId] = $(this).is(':checked');
            });
            localStorage.setItem('columnPreferences', JSON.stringify(columnPrefs));
        });

        // Load saved column preferences
        const savedPrefs = localStorage.getItem('columnPreferences');
        if (savedPrefs) {
            const columnPrefs = JSON.parse(savedPrefs);
            for (const [id, isChecked] of Object.entries(columnPrefs)) {
                $(`#${id}`).prop('checked', isChecked);
            }
            toggleColumns(); // Apply saved preferences
        }

        // Function to load members
        function loadMembers(page = 1) {
            const filterColumn = $('#filter_column').val();
            const filterValue = $('#filter_value').val();
            $.ajax({
                url: window.location.href,
                method: 'GET',
                data: {
                    page: page,
                    filter_column: filterColumn,
                    filter_value: filterValue
                },
                dataType: 'json',
                success: function(response) {
                    currentPage = page;
                    const tableBody = $('#membersTableBody');
                    tableBody.empty();

                    response.members.forEach(member => {
                        // Determine credit status
                        const creditStatusClass = getCreditStatusClass(
                            member.available_credit, 
                            member.credit_limit
                        );

                        tableBody.append(`
                            <tr>
                                <td>${member.id}</td>
                                <td>${member.full_name}</td>
                                <td>${member.nic}</td>
                                <td>${member.age}</td>
                                <td>${member.occupation || 'N/A'}</td>
                                <td>Rs. ${parseFloat(member.monthly_income).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                <td>Rs. ${parseFloat(member.credit_limit).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                <td>Rs. ${parseFloat(member.credit_used).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                <td class="${creditStatusClass}">Rs. ${parseFloat(member.available_credit).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
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
                    
                    // Previous button
                    if (page > 1) {
                        pagination.append(`
                            <button class="page-btn" data-page="${page - 1}">
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                        `);
                    }
                    
                    // Page numbers
                    const maxVisiblePages = 5;
                    let startPage = Math.max(1, page - Math.floor(maxVisiblePages / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                    
                    if (endPage - startPage + 1 < maxVisiblePages) {
                        startPage = Math.max(1, endPage - maxVisiblePages + 1);
                    }
                    
                    if (startPage > 1) {
                        pagination.append(`
                            <button class="page-btn" data-page="1">1</button>
                            ${startPage > 2 ? '<span>...</span>' : ''}
                        `);
                    }
                    
                    for (let i = startPage; i <= endPage; i++) {
                        pagination.append(`
                            <button class="page-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>
                        `);
                    }
                    
                    if (endPage < totalPages) {
                        pagination.append(`
                            ${endPage < totalPages - 1 ? '<span>...</span>' : ''}
                            <button class="page-btn" data-page="${totalPages}">${totalPages}</button>
                        `);
                    }
                    
                    // Next button
                    if (page < totalPages) {
                        pagination.append(`
                            <button class="page-btn" data-page="${page + 1}">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        `);
                    }

                    // Pagination click handler
                    $('.page-btn').click(function() {
                        const pageNum = $(this).data('page');
                        loadMembers(pageNum);
                    });
                    
                    // Apply column visibility preferences
                    toggleColumns();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading members:', error);
                    alert('Failed to load members. Please try again.');
                }
            });
        }

        // Initial load
        loadMembers();

        // Search form submission
        $('#searchForm').submit(function(e) {
            e.preventDefault();
            loadMembers(1);
        });

        // PDF Export
        $('#exportPdfBtn').click(function() {
            window.location.href = '?export=pdf';
        });

        // Edit Member Modal
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
            
            // Set form values
            $('#editMemberId').val(member.id);
            $('#editFullName').val(member.full_name);
            $('#editBankMembershipNumber').val(member.bank_membership_number);
            $('#editAddress').val(member.address);
            $('#editNic').val(member.nic);
            $('#editDateOfBirth').val(member.date_of_birth);
            $('#editTelephoneNumber').val(member.telephone_number);
            $('#editOccupation').val(member.occupation);
            $('#editMonthlyIncome').val(member.monthly_income);
            
            // Disable income field if credit used isn't zero
            if (parseFloat(member.credit_used || 0) !== 0) {
                $('#editMonthlyIncome').prop('disabled', true)
                    .attr('title', 'Cannot update income when credit has been used')
                    .css('background-color', '#f3f4f6');
            } else {
                $('#editMonthlyIncome').prop('disabled', false)
                    .removeAttr('title')
                    .css('background-color', '');
            }
            
            // Display credit information
            $('#displayCreditLimit').text(parseFloat(member.credit_limit || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#displayCreditUsed').text(parseFloat(member.credit_used || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#displayAvailableCredit').text(parseFloat(member.available_credit || member.credit_limit || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            
            $('#editModal').addClass('show');
        },
        error: function(xhr, status, error) {
            console.error('Error loading member:', error);
            alert('Error loading member data. Please try again.');
        }
    });
});

        // Edit Member Form Submission
// Edit Member Form Submission
$('#editMemberForm').submit(function(e) {
    e.preventDefault();
    
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: $(this).serialize() + '&action=edit',
        dataType: 'json',
        success: function(response) {
            // First check if response is valid
            if (!response) {
                console.error('Empty response from server');
                alert('Update may have succeeded, but no response from server. Please refresh to confirm.');
                return;
            }
            
            if (response.success) {
                $('#editModal').removeClass('show');
                loadMembers(currentPage);
                
                let message = response.message || 'Member updated successfully!';
                if (response.new_credit_limit !== undefined) {
                    message += `\nNew credit limit: Rs. ${parseFloat(response.new_credit_limit).toLocaleString()}`;
                }
                alert(message);
            } else {
                alert(response.message || 'Update failed. Please try again.');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
            
            // Check if we actually got a response despite the error
            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Sometimes updates succeed but return error status
                        $('#editModal').removeClass('show');
                        loadMembers(currentPage);
                        alert(response.message || 'Update succeeded (please refresh to confirm)');
                        return;
                    }
                } catch (e) {
                    console.error('Failed to parse response:', e);
                }
            }
            
            alert('Update Successfull');
        },
        complete: function() {
            submitBtn.prop('disabled', false).html(originalBtnText);
        }
    });
});

        // Delete Member
        $(document).on('click', '.delete-btn', function() {
            const memberId = $(this).data('id');
            
            if (confirm('Are you sure you want to delete this member? This action cannot be undone.')) {
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
                            
                            // Show success message
                            alert('Member deleted successfully!');
                        } else {
                            alert('Failed to delete member');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error deleting member:', error);
                        alert('Failed to delete member. Please try again.');
                    }
                });
            }
        });

        // Close Edit Modal
        $('#closeEditModal').click(function() {
            $('#editModal').removeClass('show');
        });

        // Close modal when clicking outside
        $(window).click(function(e) {
            if ($(e.target).hasClass('modal')) {
                $('#editModal').removeClass('show');
            }
        });

        // Autocomplete suggestions for filter_value
        let suggestionXHR = null;
        $('#filter_value').on('input', function() {
            const filterColumn = $('#filter_column').val();
            const query = $(this).val();
            if (!filterColumn || !query) {
                closeSuggestions();
                return;
            }
            // Abort previous request if any
            if (suggestionXHR) suggestionXHR.abort();
            suggestionXHR = $.ajax({
                url: window.location.href,
                method: 'GET',
                data: {
                    suggest: 1,
                    filter_column: filterColumn,
                    q: query
                },
                dataType: 'json',
                success: function(suggestions) {
                    showSuggestions(suggestions);
                }
            });
        });

        // Show suggestions dropdown
        function showSuggestions(suggestions) {
            closeSuggestions();
            if (!suggestions || suggestions.length === 0) return;
            const $input = $('#filter_value');
            const $list = $('<ul id="suggestionList"></ul>').css({
                position: 'absolute',
                zIndex: 9999,
                background: '#fff',
                border: '1px solid #ccc',
                borderRadius: '0.25rem',
                width: $input.outerWidth(),
                left: $input.offset().left,
                top: $input.offset().top + $input.outerHeight(),
                listStyle: 'none',
                margin: 0,
                padding: '0.25rem 0',
                maxHeight: '200px',
                overflowY: 'auto'
            });
            suggestions.forEach(function(s) {
                $list.append('<li class="suggestion-item" style="padding:0.5rem;cursor:pointer;">' + s + '</li>');
            });
            $('body').append($list);
            $('.suggestion-item').on('mousedown', function(e) {
                e.preventDefault();
                $input.val($(this).text());
                closeSuggestions();
            });
        }
        function closeSuggestions() {
            $('#suggestionList').remove();
        }
        $('#filter_value').on('blur', function() {
            setTimeout(closeSuggestions, 150);
        });
        $('#filter_value').on('focus', function() {
            if ($(this).val()) {
                $(this).trigger('input');
            }
        });
    });
    </script>
</body>
</html>