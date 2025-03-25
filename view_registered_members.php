<?php
// Database Configuration
$DB_HOST = 'localhost';
$DB_NAME = 'mywebsite';
$DB_USER = 'root';
$DB_PASS = '';

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
                occupation, monthly_income, registration_date 
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

    // Generate CSV export for members
    public function exportMembersToCsv($searchTerm = '', $filterColumn = '', $filterValue = '') {
        $whereClause = $this->buildWhereClause($searchTerm, $filterColumn, $filterValue);
        
        $stmt = $this->conn->prepare("
            SELECT 
                full_name, bank_membership_number, coop_number, 
                address, nic, date_of_birth, age, telephone_number, 
                occupation, monthly_income, registration_date 
            FROM members 
            {$whereClause}
            ORDER BY id DESC
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
}

// Start output buffering
ob_start();

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $memberManager = new MemberManager($pdo);

    // CSV Export Handler
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $searchTerm = $_GET['search'] ?? '';
        $filterColumn = $_GET['filter_column'] ?? '';
        $filterValue = $_GET['filter_value'] ?? '';

        $members = $memberManager->exportMembersToCsv($searchTerm, $filterColumn, $filterValue);

        // Prepare CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="members_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'Full Name', 'Bank Membership Number', 'Coop Number', 
            'Address', 'NIC', 'Date of Birth', 'Age', 'Telephone', 
            'Occupation', 'Monthly Income', 'Registration Date'
        ]);

        // Write data rows
        foreach ($members as $member) {
            fputcsv($output, array_values($member));
        }

        fclose($output);
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
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Ensure output buffering is cleared
ob_end_flush();
?>
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
                    <button id="exportCsvBtn" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Export CSV
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
                        <option value="occupation">Occupation</option>
                        <option value="age">Age</option>
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
                                <td class="py-3 px-6 text-center">
                                    <button class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                        View Details
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

        // CSV Export
        $('#exportCsvBtn').click(function() {
            const search = $('[name="search"]').val();
            const filterColumn = $('[name="filter_column"]').val();
            const filterValue = $('[name="filter_value"]').val();

            window.location.href = `?export=csv&search=${search}&filter_column=${filterColumn}&filter_value=${filterValue}`;
        });
    });
    </script>
</body>
</html>
<?php
// Handle AJAX Request for Members
if (isset($_GET['page'])) {
    try {
        $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $memberManager = new MemberManager($pdo);

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
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>