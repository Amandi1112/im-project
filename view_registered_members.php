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

        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM members {$whereClause}");

        if (!empty($searchTerm)) {
            $stmt->bindValue(':search', "%{$searchTerm}%", PDO::PARAM_STR);
        }

        if (!empty($filterValue)) {
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
                full_name LIKE :search OR
                bank_membership_number LIKE :search OR
                coop_number LIKE :search OR
                nic LIKE :search OR
                occupation LIKE :search
            )";
        }

        if (!empty($filterColumn) && !empty($filterValue)) {
            $whereClauses[] = "{$filterColumn} = :filter";
        }

        return !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    }

    // Fetch members with pagination and search
    public function getMembers($page = 1, $perPage = 10, $searchTerm = '', $filterColumn = '', $filterValue = '', $sortColumn = 'id', $sortOrder = 'DESC') {
        // Validate sort column to prevent SQL injection
        $allowedColumns = ['id', 'full_name', 'coop_number', 'age', 'monthly_income', 'registration_date'];
        $sortColumn = in_array($sortColumn, $allowedColumns) ? $sortColumn : 'id';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Build dynamic WHERE clause
        $whereClause = $this->buildWhereClause($searchTerm, $filterColumn, $filterValue);

        // Prepare SQL
        $stmt = $this->conn->prepare("
            SELECT
                id, full_name, bank_membership_number, coop_number,
                address, nic, date_of_birth, age, telephone_number,
                occupation, monthly_income, credit_limit, registration_date
            FROM members
            {$whereClause}
            ORDER BY {$sortColumn} {$sortOrder}
            LIMIT :limit OFFSET :offset
        ");

        // Bind parameters
        if (!empty($searchTerm)) {
            $stmt->bindValue(':search', "%{$searchTerm}%", PDO::PARAM_STR);
        }

        if (!empty($filterValue)) {
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
    
        $stmt = $this->conn->prepare("
            SELECT
                full_name, bank_membership_number, coop_number,
                address, nic, date_of_birth, age, telephone_number,
                occupation, monthly_income, credit_limit,
                DATE(registration_date) as registration_date
            FROM members
            {$whereClause}
            ORDER BY registration_date DESC
        ");

        if (!empty($searchTerm)) {
            $stmt->bindValue(':search', "%{$searchTerm}%", PDO::PARAM_STR);
        }

        if (!empty($filterValue)) {
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

        // Create PDF with improved styling
        $pdf = new FPDF('L', 'mm', 'A4'); // Landscape orientation
        $pdf->AddPage();
        
        // Set document properties
        $pdf->SetTitle('Members List');
        $pdf->SetAuthor('T&C co-op city Shop-Karawita');
        $pdf->SetCreator('Member Management System');
        
        // Add logo and header
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'T&C co-op city Shop-Karawita', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Members List - ' . date('F j, Y'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Set column widths (total width ~280mm for landscape A4)
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
        
        // Table header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(59, 130, 246); // Blue-500 color
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
        
        // Table data
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0);
        $fill = false;
        
        foreach ($members as $member) {
            $pdf->SetFillColor($fill ? 240 : 255); // Alternate row colors
            $pdf->Cell($colWidths['name'], 7, $member['full_name'], 1, 0, 'L', $fill);
            $pdf->Cell($colWidths['bank'], 7, $member['bank_membership_number'], 1, 0, 'L', $fill);
            $pdf->Cell($colWidths['coop'], 7, $member['coop_number'], 1, 0, 'C', $fill);
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
        
        // Summary footer
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, 'Total Members: ' . count($members), 0, 1, 'L');
        
        // Footer
        $pdf->SetY(-15);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 10, 'Page ' . $pdf->PageNo(), 0, 0, 'C');
        
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Members</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-800">Registered Members</h2>
                <div class="flex space-x-2">
                    <a href="member.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        + Add New Member
                    </a>
                    <button id="exportPdfBtn" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Export PDF
                    </button>
                </div>
            </div>

            <div class="p-4">
                <form id="searchForm" class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input type="text" name="search" placeholder="Search Members"
                        class="border rounded px-3 py-2 col-span-2 md:col-span-1">

                    <select name="filter_column"
                        class="border rounded px-3 py-2 col-span-2 md:col-span-1">
                        <option value="">Select Filter</option>
                        <option value="nic">Nic</option>
                        <option value="telephone_number">Telephone Number</option>
                    </select>

                    <input type="text" name="filter_value" placeholder="Filter Value"
                        class="border rounded px-3 py-2 col-span-2 md:col-span-1">

                    <button type="submit" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">
                        Search
                    </button>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full bg-white">
                        <thead>
                            <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                <th class="py-3 px-6 text-left">Coop Number</th>
                                <th class="py-3 px-6 text-left">Full Name</th>
                                <th class="py-3 px-6 text-left">NIC</th>
                                <th class="py-3 px-6 text-left">Age</th>
                                <th class="py-3 px-6 text-left">Occupation</th>
                                <th class="py-3 px-6 text-left">Monthly Income</th>
                                <th class="py-3 px-6 text-left">Credit Limit</th>
                                <th class="py-3 px-6 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody">
                            <!-- Members will be dynamically loaded here -->
                        </tbody>
                    </table>
                </div>

                <div id="pagination" class="mt-4 flex justify-center">
                    <!-- Pagination links will be dynamically loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Member Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg">
            <h2 class="text-2xl font-bold mb-4">Edit Member</h2>
            <form id="editMemberForm">
                <input type="hidden" name="id" id="editMemberId">
                <div class="mb-4">
                    <label for="editFullName" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="editFullName" name="full_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="editBankMembershipNumber" class="block text-sm font-medium text-gray-700">Bank Membership Number</label>
                    <input type="text" id="editBankMembershipNumber" name="bank_membership_number" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="editAddress" class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea id="editAddress" name="address" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"></textarea>
                </div>
                <div class="mb-4">
                    <label for="editNic" class="block text-sm font-medium text-gray-700">NIC</label>
                    <input type="text" id="editNic" name="nic" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="editDateOfBirth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                    <input type="date" id="editDateOfBirth" name="date_of_birth" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="editTelephoneNumber" class="block text-sm font-medium text-gray-700">Telephone Number</label>
                    <input type="tel" id="editTelephoneNumber" name="telephone_number" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="editOccupation" class="block text-sm font-medium text-gray-700">Occupation</label>
                    <input type="text" id="editOccupation" name="occupation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="editMonthlyIncome" class="block text-sm font-medium text-gray-700">Monthly Income</label>
                    <input type="number" id="editMonthlyIncome" name="monthly_income" required step="0.01"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="mb-4">
                    <label for="editCreditLimit" class="block text-sm font-medium text-gray-700">Credit Limit</label>
                    <input type="number" id="editCreditLimit" name="credit_limit" required step="0.01"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div class="text-center">
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Save Changes
                    </button>
                    <button type="button" id="closeEditModal" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-left">${member.coop_number}</td>
                                <td class="py-3 px-6 text-left">${member.full_name}</td>
                                <td class="py-3 px-6 text-left">${member.nic}</td>
                                <td class="py-3 px-6 text-left">${member.age}</td>
                                <td class="py-3 px-6 text-left">${member.occupation || 'N/A'}</td>
                                <td class="py-3 px-6 text-left">${member.monthly_income}</td>
                                <td class="py-3 px-6 text-left">${member.credit_limit || '0.00'}</td>
                                <td class="py-3 px-6 text-center">
                                    <button class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 edit-btn" data-id="${member.id}">
                                        Edit
                                    </button>
                                    <button class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 delete-btn" data-id="${member.id}">
                                        Delete
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
                            <button class="page-btn mx-1 px-3 py-1 ${i === page ? 'bg-indigo-500 text-white' : 'bg-gray-200'} rounded">${i}</button>
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
                    $('#editModal').removeClass('hidden');
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
                        $('#editModal').addClass('hidden');
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
            $('#editModal').addClass('hidden');
        });
    });
    </script>
</body>
</html>