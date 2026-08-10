<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assignment_id == 0) {
    redirect('manage_assignments.php?error=No assignment selected');
}

// Get assignment details
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM students WHERE teacher_id = a.teacher_id AND class_name = a.class_name) as total_students,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'graded') as graded_count
    FROM assignments a
    WHERE a.id = ? AND a.teacher_id = ?
");
$stmt->execute([$assignment_id, $teacher_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    redirect('manage_assignments.php?error=Assignment not found');
}

// Get all submissions for this assignment with student details
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.full_name, u.email,
           a.submission_text, a.file_name, a.file_path, 
           a.submitted_at, a.marks, a.feedback, a.status,
           a.id as submission_id
    FROM assignment_submissions a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE a.assignment_id = ?
    ORDER BY a.submitted_at DESC
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();

// Get all students in the class
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.teacher_id = ? AND s.class_name = ?
    ORDER BY u.full_name ASC
");
$stmt->execute([$teacher_id, $assignment['class_name']]);
$all_students = $stmt->fetchAll();

// Handle grading submission
$grading_error = '';
$grading_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = intval($_POST['submission_id']);
    $marks = floatval($_POST['marks']);
    $feedback = trim($_POST['feedback']);
    
    if ($marks < 0 || $marks > $assignment['total_marks']) {
        $grading_error = "Marks must be between 0 and " . $assignment['total_marks'];
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE assignment_submissions 
                SET marks = ?, feedback = ?, status = 'graded'
                WHERE id = ? AND assignment_id = ?
            ");
            $stmt->execute([$marks, $feedback, $submission_id, $assignment_id]);
            
            $grading_success = "Submission graded successfully!";
            
            // Refresh submissions
            $stmt = $pdo->prepare("
                SELECT s.*, 
                       u.full_name, u.email,
                       a.submission_text, a.file_name, a.file_path, 
                       a.submitted_at, a.marks, a.feedback, a.status,
                       a.id as submission_id
                FROM assignment_submissions a
                JOIN students s ON a.student_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE a.assignment_id = ?
                ORDER BY a.submitted_at DESC
            ");
            $stmt->execute([$assignment_id]);
            $submissions = $stmt->fetchAll();
            
        } catch(Exception $e) {
            $grading_error = "Failed to grade submission: " . $e->getMessage();
        }
    }
}

// Calculate statistics
$total_students = count($all_students);
$submitted_count = count($submissions);
$not_submitted_count = $total_students - $submitted_count;
$graded_count = 0;
$total_score = 0;
$pending_count = 0;

foreach ($submissions as $sub) {
    if ($sub['status'] == 'graded') {
        $graded_count++;
        $total_score += $sub['marks'];
    } else {
        $pending_count++;
    }
}

$avg_score = $graded_count > 0 ? round($total_score / $graded_count, 1) : 0;
$submission_rate = $total_students > 0 ? round(($submitted_count / $total_students) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submissions - Mabaso Secondary School</title>
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
            flex: 1;
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
            margin-top: 5px;
        }

        .header-section .header-left .meta-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-open {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-closed {
            background: #fef2f2;
            color: #991b1b;
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

        .btn-success {
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.22);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.20);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.18);
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }

        .alert {
            padding: 14px 18px;
            margin: 15px 0;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #0d9488;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
            font-size: 28px;
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

        .filter-section {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            align-items: center;
        }

        .filter-section .filter-label {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
        }

        .filter-btn {
            padding: 6px 16px;
            border-radius: 100px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .filter-btn:hover {
            border-color: #0d9488;
            color: #0d9488;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #0d9488, #059669);
            color: #ffffff;
            border-color: #0d9488;
        }

        .submission-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .submission-item:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border-color: #cbd5e1;
        }

        .submission-item .submission-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .submission-item .submission-left {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .submission-item .submission-right {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .submission-item .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .submission-item .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: white;
            background: linear-gradient(135deg, #0f172a, #0d9488);
        }

        .submission-item .student-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 15px;
        }

        .submission-item .student-email {
            font-size: 12px;
            color: #94a3b8;
        }

        .submission-item .submission-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 13px;
            color: #64748b;
        }

        .submission-item .submission-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .submission-item .submission-status {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 100px;
            display: inline-block;
        }

        .submission-item .submission-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .submission-item .submission-status.submitted {
            background: #dbeafe;
            color: #1e40af;
        }

        .submission-item .submission-status.graded {
            background: #d1fae5;
            color: #065f46;
        }

        .submission-item .submission-file {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            color: #0f172a;
            text-decoration: none;
            transition: all 0.3s ease;
            width: fit-content;
        }

        .submission-item .submission-file:hover {
            background: #f0fdf4;
            border-color: #0d9488;
        }

        .submission-item .submission-text {
            color: #475569;
            font-size: 14px;
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #0d9488;
        }

        .submission-item .grade-form {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }

        .submission-item .grade-form .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .submission-item .grade-form .form-group {
            flex: 1;
            min-width: 120px;
            margin: 0;
        }

        .submission-item .grade-form .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }

        .submission-item .grade-form .form-group input,
        .submission-item .grade-form .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .submission-item .grade-form .form-group input:focus,
        .submission-item .grade-form .form-group textarea:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
        }

        .submission-item .grade-form .form-group textarea {
            min-height: 50px;
            resize: vertical;
        }

        .submission-item .grade-form .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .submission-item .grade-display {
            padding: 10px 14px;
            background: #f0fdf4;
            border-radius: 8px;
            border-left: 3px solid #0d9488;
        }

        .submission-item .grade-display .grade-label {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
        }

        .submission-item .grade-display .grade-value {
            color: #0d9488;
            font-weight: 700;
            font-size: 16px;
        }

        .submission-item .grade-display .feedback-text {
            color: #475569;
            margin-top: 5px;
        }

        .no-submissions {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .no-submissions .icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .no-submissions h4 {
            color: #0f172a;
            margin-bottom: 5px;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        @media (max-width: 1024px) {
            .submission-item .submission-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
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
            .header-section .header-left h2 {
                font-size: 22px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .assignment-details .detail-row {
                flex-direction: column;
                gap: 4px;
            }
            .assignment-details .detail-row .label {
                min-width: auto;
            }
            .submission-item .submission-grid {
                grid-template-columns: 1fr;
            }
            .submission-item .grade-form .form-row {
                flex-direction: column;
            }
            .submission-item .grade-form .form-actions {
                flex-direction: column;
            }
            .submission-item .grade-form .form-actions .btn-premium {
                width: 100%;
                justify-content: center;
            }
            .submission-item .submission-meta {
                flex-direction: column;
                gap: 5px;
            }
            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            .bulk-actions {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header-section .header-left h2 {
                font-size: 20px;
            }
            .submission-item .student-info {
                flex-wrap: wrap;
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
                <a href="manage_assignments.php">Assignments</a>
                <span>›</span>
                <span class="current">Grade Submissions</span>
            </div>

            <div class="header-section">
                <div class="header-left">
                    <h2>📊 Grade Submissions</h2>
                    <div class="meta-info">
                        <span>📌 <?php echo htmlspecialchars($assignment['title']); ?></span>
                        <span>📚 <?php echo htmlspecialchars($assignment['subject']); ?></span>
                        <span>🏫 <?php echo htmlspecialchars($assignment['class_name']); ?></span>
                        <span>
                            <span class="badge <?php echo strtotime($assignment['due_date']) > time() ? 'badge-open' : 'badge-closed'; ?>">
                                <?php echo strtotime($assignment['due_date']) > time() ? '🟢 Open' : '🔴 Closed'; ?>
                            </span>
                        </span>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="manage_assignments.php" class="btn-premium btn-primary">
                        ← Back
                    </a>
                    <a href="view_assignment.php?id=<?php echo $assignment_id; ?>" class="btn-premium btn-secondary">
                        👁️ View Assignment
                    </a>
                </div>
            </div>

            <?php if ($grading_success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($grading_success); ?></div>
            <?php endif; ?>

            <?php if ($grading_error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($grading_error); ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-icon">📤</span>
                    <div class="stat-number"><?php echo $submitted_count; ?></div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-box">
                    <span class="stat-icon">✅</span>
                    <div class="stat-number"><?php echo $graded_count; ?></div>
                    <div class="stat-label">Graded</div>
                </div>
                <div class="stat-box">
                    <span class="stat-icon">⏳</span>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-box">
                    <span class="stat-icon">📊</span>
                    <div class="stat-number"><?php echo $avg_score; ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-box">
                    <span class="stat-icon">📈</span>
                    <div class="stat-number"><?php echo $submission_rate; ?>%</div>
                    <div class="stat-label">Submission Rate</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <span class="filter-label">📋 Filter:</span>
                <a href="grade_submissions.php?id=<?php echo $assignment_id; ?>" class="filter-btn <?php echo !isset($_GET['filter']) ? 'active' : ''; ?>">All</a>
                <a href="?id=<?php echo $assignment_id; ?>&filter=pending" class="filter-btn <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'pending') ? 'active' : ''; ?>">⏳ Pending</a>
                <a href="?id=<?php echo $assignment_id; ?>&filter=graded" class="filter-btn <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'graded') ? 'active' : ''; ?>">✅ Graded</a>
                <span style="margin-left: auto; color: #94a3b8; font-size: 13px;">
                    <?php echo $pending_count; ?> pending submissions
                </span>
            </div>

            <!-- Submissions -->
            <?php if (count($submissions) > 0): ?>
                <?php 
                $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
                foreach($submissions as $submission):
                    if ($filter == 'pending' && $submission['status'] == 'graded') continue;
                    if ($filter == 'graded' && $submission['status'] != 'graded') continue;
                ?>
                    <div class="submission-item">
                        <div class="submission-grid">
                            <!-- Left Column - Student Info & Submission Details -->
                            <div class="submission-left">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($submission['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($submission['full_name']); ?></div>
                                        <div class="student-email"><?php echo htmlspecialchars($submission['email']); ?></div>
                                    </div>
                                </div>

                                <div class="submission-meta">
                                    <span>🆔 <?php echo htmlspecialchars($submission['student_id']); ?></span>
                                    <span>📤 <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></span>
                                    <span>
                                        <span class="submission-status <?php echo $submission['status']; ?>">
                                            <?php echo ucfirst($submission['status']); ?>
                                        </span>
                                    </span>
                                </div>

                                <?php if (!empty($submission['submission_text'])): ?>
                                    <div class="submission-text">
                                        <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($submission['file_path']) && !empty($submission['file_name'])): ?>
                                    <a href="../../<?php echo htmlspecialchars($submission['file_path']); ?>" class="submission-file" download>
                                        📎 <?php echo htmlspecialchars($submission['file_name']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Right Column - Grading -->
                            <div class="submission-right">
                                <?php if ($submission['status'] == 'graded'): ?>
                                    <div class="grade-display">
                                        <div class="grade-label">📊 Grade:</div>
                                        <div class="grade-value"><?php echo $submission['marks']; ?> / <?php echo $assignment['total_marks']; ?></div>
                                        <?php if (!empty($submission['feedback'])): ?>
                                            <div class="feedback-text">💬 <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                                        <button onclick="editGrade(<?php echo $submission['submission_id']; ?>, <?php echo $submission['marks']; ?>, '<?php echo addslashes($submission['feedback']); ?>')" class="btn-premium btn-warning btn-sm">
                                            ✏️ Edit Grade
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="grade-form">
                                        <form method="POST" id="gradeForm_<?php echo $submission['submission_id']; ?>">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                                            <div class="form-row">
                                                <div class="form-group" style="flex: 1;">
                                                    <label>📊 Marks (out of <?php echo $assignment['total_marks']; ?>)</label>
                                                    <input type="number" name="marks" min="0" max="<?php echo $assignment['total_marks']; ?>" step="0.5" required placeholder="Enter marks">
                                                </div>
                                                <div class="form-group" style="flex: 2;">
                                                    <label>💬 Feedback</label>
                                                    <textarea name="feedback" rows="2" placeholder="Provide feedback..."></textarea>
                                                </div>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" name="grade_submission" class="btn-premium btn-success btn-sm">
                                                    💾 Save Grade
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-submissions">
                    <div class="icon">📭</div>
                    <h4>No Submissions Yet</h4>
                    <p>No students have submitted this assignment yet.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function editGrade(submissionId, currentMarks, currentFeedback) {
            // Find the submission item
            const items = document.querySelectorAll('.submission-item');
            let targetItem = null;
            
            items.forEach(item => {
                const form = item.querySelector('form');
                if (form && form.querySelector('input[name="submission_id"]')) {
                    const id = form.querySelector('input[name="submission_id"]').value;
                    if (parseInt(id) === submissionId) {
                        targetItem = item;
                    }
                }
            });
            
            if (!targetItem) return;
            
            // Find the grade display and replace with form
            const gradeDisplay = targetItem.querySelector('.grade-display');
            const editBtn = targetItem.querySelector('.btn-warning');
            
            if (gradeDisplay && editBtn) {
                // Hide grade display and edit button
                gradeDisplay.style.display = 'none';
                editBtn.style.display = 'none';
                
                // Show the form
                const form = targetItem.querySelector('.grade-form');
                if (form) {
                    form.style.display = 'block';
                    // Pre-fill values
                    const marksInput = form.querySelector('input[name="marks"]');
                    const feedbackTextarea = form.querySelector('textarea[name="feedback"]');
                    if (marksInput) marksInput.value = currentMarks;
                    if (feedbackTextarea) feedbackTextarea.value = currentFeedback;
                }
            }
        }
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>