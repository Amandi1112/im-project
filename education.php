<?php
// Sample data for educational backgrounds of employees
$employees = [
    [
        'name' => 'John Doe',
        'position' => 'Software Engineer',
        'education' => 'Bachelor of Science in Computer Science',
        'university' => 'University of California, Berkeley',
        'year' => 2015
    ],
    [
        'name' => 'Jane Smith',
        'position' => 'Data Analyst',
        'education' => 'Master of Science in Data Analytics',
        'university' => 'Massachusetts Institute of Technology',
        'year' => 2018
    ],
    [
        'name' => 'Alice Johnson',
        'position' => 'Project Manager',
        'education' => 'Bachelor of Business Administration',
        'university' => 'Harvard University',
        'year' => 2012
    ],
    [
        'name' => 'Bob Brown',
        'position' => 'UI/UX Designer',
        'education' => 'Bachelor of Arts in Graphic Design',
        'university' => 'Rhode Island School of Design',
        'year' => 2016
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Educational Backgrounds</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>Employee Educational Backgrounds</h1>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Position</th>
                <th>Education</th>
                <th>University</th>
                <th>Graduation Year</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $employee): ?>
                <tr>
                    <td><?= htmlspecialchars($employee['name']) ?></td>
                    <td><?= htmlspecialchars($employee['position']) ?></td>
                    <td><?= htmlspecialchars($employee['education']) ?></td>
                    <td><?= htmlspecialchars($employee['university']) ?></td>
                    <td><?= htmlspecialchars($employee['year']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>