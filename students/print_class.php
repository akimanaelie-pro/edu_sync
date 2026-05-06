<?php
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/config/database.php';

$db = db();

$classId = $_GET['class_id'] ?? -1;
$className = $_GET['class_name'] ?? 'Unknown Class';

if ($classId == -1) {
    die('Invalid class ID');
}

if ($classId == 0) {
    $students = $db->select("
        SELECT s.*, u.first_name, u.last_name, u.email, u.phone
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = 0 OR s.class_id IS NULL
        ORDER BY u.first_name, u.last_name
    ");
    $className = 'Unassigned Students';
} else {
    $students = $db->select("
        SELECT s.*, u.first_name, u.last_name, u.email, u.phone
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ?
        ORDER BY u.first_name, u.last_name
    ", [$classId]);
}

$schoolName = SITE_NAME ?? 'School Management System';
$date = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List - <?= htmlspecialchars($className) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 24pt;
            color: #4f46e5;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 16pt;
            color: #6366f1;
            margin-bottom: 10px;
        }

        .header .meta {
            font-size: 10pt;
            color: #666;
        }

        .info-box {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .info-item {
            font-size: 10pt;
        }

        .info-item strong {
            color: #4f46e5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background: #4f46e5;
            color: white;
        }

        th {
            padding: 10px 8px;
            text-align: left;
            font-size: 10pt;
            font-weight: bold;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10pt;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .student-id {
            font-family: 'Courier New', monospace;
            background: #e0e7ff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9pt;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14pt;
            z-index: 1000;
        }

        .print-btn:hover {
            background: #6366f1;
        }

        @media print {
            .print-btn {
                display: none;
            }

            .a4-page {
                margin: 0;
                padding: 15mm;
                page-break-after: always;
            }
        }

        @media screen {
            body {
                background: #e5e7eb;
                padding: 20px;
            }

            .a4-page {
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print
    </button>

    <div class="a4-page">
        <div class="header">
            <h1><?= htmlspecialchars($schoolName) ?></h1>
            <h2>Student List - <?= htmlspecialchars($className) ?></h2>
            <div class="meta">
                Generated on <?= $date ?> | Total Students: <?= count($students) ?>
            </div>
        </div>

        <div class="info-box">
            <div class="info-item">
                <strong>Class:</strong> <?= htmlspecialchars($className) ?>
            </div>
            <div class="info-item">
                <strong>Total Students:</strong> <?= count($students) ?>
            </div>
            <div class="info-item">
                <strong>Date:</strong> <?= $date ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">Name</th>
                    <th style="width: 20%;">Student ID</th>
                    <th style="width: 25%;">Email</th>
                    <th style="width: 15%;">Phone</th>
                    <th style="width: 10%;">Gender</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #666;">
                        No students found in this class
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($students as $index => $student): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong></td>
                        <td><span class="student-id"><?= htmlspecialchars($student['student_id']) ?></span></td>
                        <td><?= htmlspecialchars($student['email']) ?></td>
                        <td><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></td>
                        <td><?= ucfirst($student['gender'] ?? 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <p>Printed from School Management System on <?= date('F d, Y \a\t h:i A') ?></p>
            <p style="margin-top: 5px;">This is an official document generated by the system</p>
        </div>
    </div>

    <script>
        // Auto print on load (optional - comment out if not needed)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>
