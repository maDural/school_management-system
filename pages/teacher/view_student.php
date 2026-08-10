<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    redirect('manage_students.php?error=No student selected');
}

// Get student details
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, u.username, u.created_at
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ? AND s.teacher_id = ?
");
$stmt->execute([$student_id, $teacher_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('manage_students.php?error=Student not found');
}

// Get attendance records
$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE student_id = ? 
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute([$student_id]);
$attendance = $stmt->fetchAll();

// Get results
$stmt = $pdo->prepare("
    SELECT * FROM results 
    WHERE student_id = ? 
    ORDER BY exam_date DESC
");
$stmt->execute([$student_id]);
$results = $stmt->fetchAll();

// Get assignment submissions
$stmt = $pdo->prepare("
    SELECT a.*, 
           asub.submission_text, asub.file_name, asub.file_path, 
           asub.submitted_at, asub.marks, asub.feedback, asub.status
    FROM assignment_submissions asub
    JOIN assignments a ON asub.assignment_id = a.id
    WHERE asub.student_id = ?
    ORDER BY asub.submitted_at DESC
");
$stmt->execute([$student_id]);
$assignments = $stmt->fetchAll();

// Calculate statistics
$total_attendance = count($attendance);
$present = 0;
$absent = 0;
$late = 0;

foreach ($attendance as $record) {
    if ($record['status'] == 'present') $present++;
    elseif ($record['status'] == 'absent') $absent++;
    elseif ($record['status'] == 'late') $late++;
}

$attendance_rate = $total_attendance > 0 ? round(($present / $total_attendance) * 100, 1) : 0;

$total_results = count($results);
$avg_score = 0;
$best_grade = 'F';
$grades = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

foreach ($results as $result) {
    $score_percent = ($result['score'] / $result['total_marks']) * 100;
    $avg_score += $score_percent;
    $grades[$result['grade']]++;
    if ($result['grade'] < $best_grade) $best_grade = $result['grade'];
}

$avg_score = $total_results > 0 ? round($avg_score / $total_results, 1) : 0;

// Get teacher info
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(13, 148, 136, 0.08), transparent 35%),
                radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.05), transparent 35%),
                linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1280px;
            padding: 20px 30px;
            margin: 0 auto;
        }

        .content {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            padding: 35px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
            position: relative;
            overflow: hidden;
        }

        .content::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(13, 148, 136, 0.03), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .content::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(15, 23, 42, 0.03), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: #94a3b8;
            font-size: 13px;
        }

        .breadcrumb a {
            color: #64748b;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #0d9488;
        }

        .breadcrumb .current {
            color: #0f172a;
            font-weight: 600;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .header-section .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .student-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #0f172a, #0d9488);
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.2);
        }

        .header-section .header-left h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 5px;
        }

        .header-section .header-left .meta-info {
            color: #64748b;
            font-size: 14px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .header-section .header-left .meta-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-premium {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }

        .btn-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-premium:hover::before {
            left: 100%;
        }

        .btn-premium:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.18);
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin: 20px 0 30px;
        }

        .stat-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
        }

        .stat-box .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
        }

        .stat-box .stat-label {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }

        .stat-box .stat-icon {
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
        }

        .info-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-card .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .info-card .info-row:last-child {
            border-bottom: none;
        }

        .info-card .info-row .label {
            color: #64748b;
        }

        .info-card .info-row .value {
            font-weight: 600;
            color: #0f172a;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .section-header .count {
            color: #64748b;
            font-size: 14px;
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 10px;
        }

        table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 700;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            font-size: 13px;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-info {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-graded {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-submitted {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .grade-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 13px;
        }

        .grade-a { background: #d1fae5; color: #065f46; }
        .grade-b { background: #dbeafe; color: #1e40af; }
        .grade-c { background: #fef3c7; color: #92400e; }
        .grade-d { background: #fed7aa; color: #9a3412; }
        .grade-f { background: #fecaca; color: #991b1b; }

        .no-data {
            text-align: center;
            padding: 30px 20px;
            color: #94a3b8;
        }

        .no-data .icon {
            font-size: 36px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            .content {
                padding: 20px;
            }
            .header-section {
                flex-direction: column;
            }
            .header-section .header-left {
                flex-wrap: wrap;
            }
            .header-section .header-left h2 {
                font-size: 22px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .info-card .info-row {
                flex-direction: column;
                gap: 4px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header-section .header-left h2 {
                font-size: 20px;
            }
            .student-avatar-large {
                width: 60px;
                height: 60px;
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="content">

            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>›</span>
                <a href="manage_students.php">Students</a>
                <span>›</span>
                <span class="current">Student Profile</span>
            </div>

            <!-- Header -->
            <div class="header-section">
                <div class="header-left">
                    <div class="student-avatar-large">
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <div class="meta-info">
                            <span>🆔 <?php echo htmlspecialchars($student['student_id']); ?></span>
                            <span>🏫 <?php echo htmlspecialchars($student['class_name']); ?></span>
                            <span>📧 <?php echo htmlspecialchars($student['email']); ?></span>
                            <span>👤 @<?php echo htmlspecialchars($student['username']); ?></span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="manage_students.php" class="btn-premium btn-primary">
                        ← Back
                    </a>
                    <a href="add_result.php?student=<?php echo $student['id']; ?>" class="btn-premium btn-secondary">
                        📝 Add Result
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-icon">📋</span>
                    <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
                <div class="stat-box">
                    <span class="stat-icon">📊</span>
                    <div class="stat-number"><?php echo $total_results; ?></div>
                    <div class="stat-label">Total Results</div>
                </div>
                <div class="stat-box">
                    <span class="stat-icon">📈</span>
                    <div class="stat-number"><?php echo $avg_score; ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-box">
                    <span class="stat-icon">🏆</span>
                    <div class="stat-number"><?php echo $best_grade; ?></div>
                    <div class="stat-label">Best Grade</div>
                </div>
            </div>

            <!-- Student Information -->
            <div class="info-grid">
                <div class="info-card">
                    <h4>👤 Personal Information</h4>
                    <div class="info-row">
                        <span class="label">Full Name</span>
                        <span class="value"><?php echo htmlspecialchars($student['full_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Student ID</span>
                        <span class="value"><?php echo htmlspecialchars($student['student_id']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Username</span>
                        <span class="value"><?php echo htmlspecialchars($student['username']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Class</span>
                        <span class="value"><?php echo htmlspecialchars($student['class_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Joined</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h4>📊 Academic Summary</h4>
                    <div class="info-row">
                        <span class="label">Total Results</span>
                        <span class="value"><?php echo $total_results; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Average Score</span>
                        <span class="value"><?php echo $avg_score; ?>%</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Best Grade</span>
                        <span class="value">
                            <span class="grade-badge grade-<?php echo strtolower($best_grade); ?>">
                                <?php echo $best_grade; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Attendance Rate</span>
                        <span class="value"><?php echo $attendance_rate; ?>%</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Grade Distribution</span>
                        <span class="value">
                            <?php 
                            $grade_labels = [];
                            foreach ($grades as $g => $count) {
                                if ($count > 0) {
                                    $grade_labels[] = $g . ': ' . $count;
                                }
                            }
                            echo !empty($grade_labels) ? implode(' | ', $grade_labels) : 'No grades yet';
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Attendance Records -->
            <div class="section-header">
                <h3>📋 Recent Attendance</h3>
                <span class="count"><?php echo count($attendance); ?> records</span>
            </div>

            <?php if (count($attendance) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Class</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($attendance as $record): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['class_name']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            if ($record['status'] == 'present') echo 'badge-success';
                                            elseif ($record['status'] == 'absent') echo 'badge-danger';
                                            else echo 'badge-warning';
                                        ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="icon">📋</div>
                    <p>No attendance records found.</p>
                </div>
            <?php endif; ?>

            <!-- Results -->
            <div class="section-header">
                <h3>📊 Results</h3>
                <span class="count"><?php echo $total_results; ?> results</span>
            </div>

            <?php if (count($results) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Exam Type</th>
                                <th>Score</th>
                                <th>Grade</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($results as $result): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($result['subject']); ?></strong></td>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($result['exam_type']); ?></span></td>
                                    <td><?php echo $result['score']; ?> / <?php echo $result['total_marks']; ?></td>
                                    <td>
                                        <span class="grade-badge grade-<?php echo strtolower($result['grade']); ?>">
                                            <?php echo $result['grade']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($result['exam_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="icon">📊</div>
                    <p>No results found for this student.</p>
                </div>
            <?php endif; ?>

            <!-- Assignment Submissions -->
            <div class="section-header">
                <h3>📤 Assignment Submissions</h3>
                <span class="count"><?php echo count($assignments); ?> submissions</span>
            </div>

            <?php if (count($assignments) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Assignment</th>
                                <th>Subject</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($assignments as $assignment): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($assignment['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($assignment['subject']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($assignment['submitted_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            if ($assignment['status'] == 'graded') echo 'badge-graded';
                                            elseif ($assignment['status'] == 'submitted') echo 'badge-submitted';
                                            else echo 'badge-pending';
                                        ?>">
                                            <?php echo ucfirst($assignment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($assignment['status'] == 'graded'): ?>
                                            <?php echo $assignment['marks']; ?> / <?php echo $assignment['total_marks']; ?>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="icon">📤</div>
                    <p>No assignment submissions found.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>