<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];

// Get student info
$stmt = $pdo->prepare("SELECT id, class_name, teacher_id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('../../logout.php');
}

$student_id = $student['id'];
$class_name = $student['class_name'];
$teacher_id = $student['teacher_id'];

// Get all assignments for this student's class
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submission_status,
           (SELECT submitted_at FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submitted_at,
           (SELECT marks FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as marks,
           (SELECT feedback FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as feedback,
           (SELECT file_name FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submitted_file_name,
           (SELECT file_path FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submitted_file_path
    FROM assignments a
    WHERE a.class_name = ? AND a.teacher_id = ?
    ORDER BY a.due_date ASC
");
$stmt->execute([$student_id, $student_id, $student_id, $student_id, $student_id, $student_id, $class_name, $teacher_id]);
$assignments = $stmt->fetchAll();

// Handle assignment submission
$submission_error = '';
$submission_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $submission_text = trim($_POST['submission_text'] ?? '');
    $file_name = null;
    $file_path = null;
    
    // Check if assignment exists and is still open
    $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND class_name = ? AND teacher_id = ?");
    $stmt->execute([$assignment_id, $class_name, $teacher_id]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        $submission_error = "Assignment not found.";
    } else {
        $now = new DateTime();
        $due = new DateTime($assignment['due_date']);
        if ($now > $due) {
            $submission_error = "This assignment has expired. You can no longer submit.";
        }
    }
    
    // Check if already submitted
    if (empty($submission_error)) {
        $stmt = $pdo->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
        $stmt->execute([$assignment_id, $student_id]);
        if ($stmt->rowCount() > 0) {
            $submission_error = "You have already submitted this assignment.";
        }
    }
    
    // Handle file upload
    if (empty($submission_error)) {
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
            $allowed_types = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png',
                'image/gif',
                'text/plain'
            ];
            $max_size = 10 * 1024 * 1024;
            
            $file_type = $_FILES['submission_file']['type'];
            $file_size = $_FILES['submission_file']['size'];
            $file_tmp = $_FILES['submission_file']['tmp_name'];
            $file_original_name = $_FILES['submission_file']['name'];
            
            if (!in_array($file_type, $allowed_types)) {
                $submission_error = "File type not allowed. Please upload PDF, Word, PowerPoint, Excel, images, or text files.";
            } elseif ($file_size > $max_size) {
                $submission_error = "File size exceeds 10MB limit.";
            } else {
                $upload_dir = '../../uploads/submissions/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($file_original_name, PATHINFO_EXTENSION);
                $file_name = time() . '_' . $student_id . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $file_path = 'uploads/submissions/' . $file_name;
                } else {
                    $submission_error = "Failed to upload file. Please try again.";
                    $file_name = null;
                    $file_path = null;
                }
            }
        }
    }
    
    if (empty($submission_error)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_name, file_path, status, submitted_at)
                VALUES (?, ?, ?, ?, ?, 'submitted', NOW())
            ");
            $stmt->execute([$assignment_id, $student_id, $submission_text, $file_name, $file_path]);
            
            $submission_success = "Assignment submitted successfully!";
            
            // Refresh page to show updated status
            header("Refresh: 2; URL=assignments.php?success=1");
            exit();
            
        } catch(Exception $e) {
            $submission_error = "Failed to submit assignment: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Mabaso Secondary School</title>
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

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-section h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
        }

        .header-section .class-badge {
            display: inline-block;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 6px 20px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
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

        .assignment-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .assignment-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            transform: translateX(5px);
            border-color: #0d9488;
        }

        .assignment-card .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }

        .assignment-card .assignment-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .assignment-card .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .assignment-card .status-badge.open {
            background: #d1fae5;
            color: #065f46;
        }

        .assignment-card .status-badge.submitted {
            background: #dbeafe;
            color: #1e40af;
        }

        .assignment-card .status-badge.graded {
            background: #fef3c7;
            color: #92400e;
        }

        .assignment-card .status-badge.closed {
            background: #fef2f2;
            color: #991b1b;
        }

        .assignment-card .assignment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 10px 0;
        }

        .assignment-card .assignment-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 14px;
        }

        .assignment-card .assignment-meta .meta-item strong {
            color: #0f172a;
        }

        .assignment-card .assignment-description {
            color: #475569;
            margin: 12px 0;
            line-height: 1.7;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 3px solid #0d9488;
        }

        .assignment-card .file-attachment {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #0f172a;
            transition: all 0.3s ease;
            margin: 8px 0;
        }

        .assignment-card .file-attachment:hover {
            background: #f0fdf4;
            border-color: #0d9488;
            transform: translateY(-2px);
        }

        .assignment-card .submitted-file {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            color: #065f46;
            margin: 8px 0;
        }

        .marks-display {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 14px;
        }

        .marks-display.pass {
            background: #d1fae5;
            color: #065f46;
        }

        .marks-display.fail {
            background: #fef2f2;
            color: #991b1b;
        }

        .feedback-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 10px;
            border-left: 3px solid #0d9488;
        }

        .feedback-box .feedback-label {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
        }

        .feedback-box .feedback-text {
            color: #475569;
            font-size: 14px;
            margin-top: 4px;
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

        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.18);
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
            border-radius: 24px 24px 0 0;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .modal-header .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.3s ease;
            padding: 0 8px;
        }

        .modal-header .modal-close:hover {
            color: #ef4444;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-body .assignment-info-modal {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .modal-body .assignment-info-modal .info-row {
            display: flex;
            padding: 5px 0;
            font-size: 14px;
        }

        .modal-body .assignment-info-modal .info-row .label {
            color: #64748b;
            min-width: 100px;
        }

        .modal-body .assignment-info-modal .info-row .value {
            color: #0f172a;
            font-weight: 600;
        }

        .form-group {
            margin: 18px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
        }

        .form-group label .optional {
            font-weight: 400;
            color: #94a3b8;
            font-size: 12px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
            background: white;
        }

        .file-upload-wrapper {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            background: #fafafa;
            cursor: pointer;
            position: relative;
        }

        .file-upload-wrapper:hover {
            border-color: #0d9488;
            background: #f0fdf4;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-wrapper .upload-icon {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
        }

        .file-upload-wrapper .upload-text {
            font-size: 14px;
            color: #64748b;
        }

        .file-upload-wrapper .upload-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 5px;
        }

        .file-preview {
            display: none;
            margin-top: 10px;
            padding: 10px 16px;
            background: #f1f5f9;
            border-radius: 8px;
            align-items: center;
            gap: 10px;
        }

        .file-preview.show {
            display: flex;
        }

        .file-preview .remove-file {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #ef4444;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            background: #f8fafc;
            border-radius: 0 0 24px 24px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fafafa;
            border-radius: 16px;
            border: 2px dashed #e2e8f0;
        }

        .empty-state .empty-icon {
            font-size: 56px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #0f172a;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #94a3b8;
            font-size: 16px;
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
                align-items: flex-start;
            }
            .header-section h2 {
                font-size: 22px;
            }
            .assignment-card .assignment-header {
                flex-direction: column;
            }
            .assignment-card .assignment-meta {
                flex-direction: column;
                gap: 8px;
            }
            .modal-content {
                max-height: 95vh;
            }
            .modal-footer {
                flex-direction: column;
            }
            .modal-footer .btn-premium {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            .assignment-card .assignment-title {
                font-size: 17px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="content">

            <div class="header-section">
                <h2>📝 Assignments</h2>
                <span class="class-badge">🏫 Class <?php echo htmlspecialchars($class_name); ?></span>
            </div>

            <div class="subtitle">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                <span style="margin: 0 10px;">•</span>
                <span>📚 <?php echo count($assignments); ?> assignments</span>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">✅ Assignment submitted successfully!</div>
            <?php endif; ?>

            <?php if ($submission_success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($submission_success); ?></div>
            <?php endif; ?>

            <?php if ($submission_error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($submission_error); ?></div>
            <?php endif; ?>

            <?php if (count($assignments) > 0): ?>
                <?php foreach($assignments as $assignment):
                    $now = new DateTime();
                    $due = new DateTime($assignment['due_date']);
                    $is_overdue = $now > $due;
                    $status = $assignment['submission_status'] ?? 'pending';
                    $is_graded = $status == 'graded';

                    if ($is_graded) {
                        $status_label = 'Graded';
                        $status_class = 'graded';
                    } elseif ($status == 'submitted') {
                        $status_label = 'Submitted';
                        $status_class = 'submitted';
                    } elseif ($is_overdue) {
                        $status_label = 'Closed';
                        $status_class = 'closed';
                    } else {
                        $status_label = 'Open';
                        $status_class = 'open';
                    }
                ?>
                <div class="assignment-card">
                    <div class="assignment-header">
                        <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </div>

                    <div class="assignment-meta">
                        <span class="meta-item">📚 <strong>Subject:</strong> <?php echo htmlspecialchars($assignment['subject']); ?></span>
                        <span class="meta-item">📊 <strong>Total Marks:</strong> <?php echo $assignment['total_marks']; ?></span>
                        <span class="meta-item">📅 <strong>Due:</strong> <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?></span>
                    </div>

                    <?php if (!empty($assignment['description'])): ?>
                        <div class="assignment-description">
                            <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($assignment['file_path']) && !empty($assignment['file_name'])): ?>
                        <a href="../../<?php echo htmlspecialchars($assignment['file_path']); ?>" class="file-attachment" download>
                            📎 <?php echo htmlspecialchars($assignment['file_name']); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($status == 'submitted' && !empty($assignment['submitted_file_name'])): ?>
                        <div class="submitted-file">
                            📤 Submitted: <?php echo htmlspecialchars($assignment['submitted_file_name']); ?>
                            <span style="font-size: 12px; color: #64748b;">
                                on <?php echo date('M d, Y h:i A', strtotime($assignment['submitted_at'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($status == 'graded'): ?>
                        <div style="margin: 10px 0;">
                            <span class="marks-display <?php echo ($assignment['marks'] ?? 0) >= ($assignment['total_marks'] * 0.5) ? 'pass' : 'fail'; ?>">
                                Score: <?php echo $assignment['marks'] ?? 0; ?> / <?php echo $assignment['total_marks']; ?>
                            </span>
                        </div>
                        <?php if (!empty($assignment['feedback'])): ?>
                            <div class="feedback-box">
                                <div class="feedback-label">📝 Teacher's Feedback:</div>
                                <div class="feedback-text"><?php echo nl2br(htmlspecialchars($assignment['feedback'])); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div style="margin-top: 15px;">
                        <?php if ($status == 'graded'): ?>
                            <span class="btn-premium btn-secondary btn-sm">✅ Completed</span>
                        <?php elseif ($status == 'submitted'): ?>
                            <span class="btn-premium btn-secondary btn-sm">⏳ Awaiting Grading</span>
                        <?php elseif ($is_overdue): ?>
                            <span style="color: #ef4444; font-size: 14px; font-weight: 600;">⏰ This assignment has closed</span>
                        <?php else: ?>
                            <button onclick="openSubmitModal(<?php echo $assignment['id']; ?>)" class="btn-premium btn-success btn-sm">
                                📤 Submit Assignment
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <h3>No Assignments</h3>
                    <p>There are no assignments for your class at the moment.</p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="dashboard.php" class="btn-premium btn-primary">← Back to Dashboard</a>
            </div>

        </div>
    </div>

    <!-- Submit Assignment Modal -->
    <div class="modal-overlay" id="submitModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📤 Submit Assignment</h3>
                <button class="modal-close" onclick="closeSubmitModal()">✕</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="submitForm">
                <div class="modal-body">
                    <div class="assignment-info-modal" id="assignmentInfo">
                        <div class="info-row">
                            <span class="label">📌 Title</span>
                            <span class="value" id="modalTitle">Loading...</span>
                        </div>
                        <div class="info-row">
                            <span class="label">📚 Subject</span>
                            <span class="value" id="modalSubject">Loading...</span>
                        </div>
                        <div class="info-row">
                            <span class="label">📊 Marks</span>
                            <span class="value" id="modalMarks">Loading...</span>
                        </div>
                        <div class="info-row">
                            <span class="label">⏰ Due Date</span>
                            <span class="value" id="modalDue">Loading...</span>
                        </div>
                    </div>

                    <input type="hidden" name="assignment_id" id="assignmentId" value="">

                    <div class="form-group">
                        <label>📝 Description <span class="optional">(Optional)</span></label>
                        <textarea name="submission_text" id="submissionText" rows="4" placeholder="Add any comments or notes about your submission..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>📎 Attach File <span class="optional">(Optional)</span></label>
                        <div style="position: relative;">
                            <div class="file-upload-wrapper" id="modalFileUploadWrapper">
                                <span class="upload-icon">📄</span>
                                <div class="upload-text">Click to upload or drag and drop</div>
                                <div class="upload-hint">PDF, Word, PowerPoint, Excel, Images, Text (Max 10MB)</div>
                                <input type="file" name="submission_file" id="modalFileInput" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                            </div>
                            <div class="file-preview" id="modalFilePreview">
                                <span id="modalFileIcon">📄</span>
                                <span id="modalFileName" style="flex: 1;">file.pdf</span>
                                <button type="button" class="remove-file" onclick="removeModalFile()">✕</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-premium btn-secondary" onclick="closeSubmitModal()">Cancel</button>
                    <button type="submit" name="submit_assignment" class="btn-premium btn-success">📤 Submit Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSubmitModal(assignmentId) {
            const modal = document.getElementById('submitModal');
            const assignmentIdInput = document.getElementById('assignmentId');

            // Set assignment ID
            assignmentIdInput.value = assignmentId;

            // Get assignment details from the page
            const cards = document.querySelectorAll('.assignment-card');
            let assignmentData = null;

            cards.forEach(card => {
                // Find the assignment ID from the button
                const btn = card.querySelector('button[onclick*="openSubmitModal"]');
                if (btn) {
                    const btnId = btn.getAttribute('onclick').match(/\d+/);
                    if (btnId && parseInt(btnId[0]) === assignmentId) {
                        // Extract data from the card
                        const title = card.querySelector('.assignment-title')?.textContent || 'Unknown';
                        const subject = card.querySelector('.meta-item')?.textContent?.replace('📚 Subject:', '').trim() || 'Unknown';
                        const marks = card.querySelector('.meta-item:nth-child(2)')?.textContent?.replace('📊 Total Marks:', '').trim() || 'Unknown';
                        const due = card.querySelector('.meta-item:nth-child(3)')?.textContent?.replace('📅 Due:', '').trim() || 'Unknown';

                        assignmentData = { title, subject, marks, due };
                    }
                }
            });

            // Set modal info
            if (assignmentData) {
                document.getElementById('modalTitle').textContent = assignmentData.title;
                document.getElementById('modalSubject').textContent = assignmentData.subject;
                document.getElementById('modalMarks').textContent = assignmentData.marks;
                document.getElementById('modalDue').textContent = assignmentData.due;
            }

            // Reset file upload
            resetModalFile();

            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSubmitModal() {
            document.getElementById('submitModal').classList.remove('active');
            document.body.style.overflow = '';
            resetModalFile();
            document.getElementById('submissionText').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('submitModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSubmitModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSubmitModal();
            }
        });

        // File upload handling for modal
        const modalFileInput = document.getElementById('modalFileInput');
        const modalFilePreview = document.getElementById('modalFilePreview');
        const modalFileName = document.getElementById('modalFileName');
        const modalFileIcon = document.getElementById('modalFileIcon');
        const modalUploadWrapper = document.getElementById('modalFileUploadWrapper');

        modalFileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('File size exceeds 10MB limit.');
                    this.value = '';
                    return;
                }
                modalFileName.textContent = file.name;
                modalFileIcon.textContent = getFileIcon(file.name);
                modalFilePreview.classList.add('show');
                modalUploadWrapper.style.display = 'none';
            }
        });

        function removeModalFile() {
            modalFileInput.value = '';
            modalFilePreview.classList.remove('show');
            modalUploadWrapper.style.display = 'block';
        }

        function resetModalFile() {
            removeModalFile();
            document.getElementById('submissionText').value = '';
        }

        function getFileIcon(filename) {
            const extension = filename.split('.').pop().toLowerCase();
            const icons = {
                pdf: '📕',
                doc: '📘',
                docx: '📘',
                ppt: '📙',
                pptx: '📙',
                xls: '📗',
                xlsx: '📗',
                jpg: '🖼️',
                jpeg: '🖼️',
                png: '🖼️',
                gif: '🖼️',
                txt: '📄'
            };
            return icons[extension] || '📄';
        }

        // Drag and drop for modal
        modalUploadWrapper.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        modalUploadWrapper.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        modalUploadWrapper.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                modalFileInput.files = files;
                modalFileInput.dispatchEvent(new Event('change'));
            }
        });

        // Form validation
        document.getElementById('submitForm').addEventListener('submit', function(e) {
            const file = modalFileInput.files[0];
            const text = document.getElementById('submissionText').value.trim();

            if (!file && !text) {
                e.preventDefault();
                alert('Please either upload a file or add a description.');
                return false;
            }
            return true;
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>