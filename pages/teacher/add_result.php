<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get all classes for this teacher
$stmt = $pdo->prepare("SELECT DISTINCT class_name FROM students WHERE teacher_id = ? AND class_name IS NOT NULL AND class_name != '' ORDER BY class_name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get selected class
$selected_class = isset($_GET['class']) ? $_GET['class'] : (isset($_POST['class_name']) ? $_POST['class_name'] : (isset($_SESSION['selected_class']) ? $_SESSION['selected_class'] : null));

// Get students for selected class
$students = [];
if ($selected_class) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.teacher_id = ? AND s.class_name = ?
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$teacher_id, $selected_class]);
    $students = $stmt->fetchAll();
}

// Save selected class to session
if ($selected_class) {
    $_SESSION['selected_class'] = $selected_class;
}

// Get existing results for the selected class to show
$existing_results = [];
if ($selected_class) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name 
        FROM results r
        JOIN students s ON r.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE r.teacher_id = ? AND s.class_name = ?
        ORDER BY r.exam_date DESC, u.full_name ASC
        LIMIT 10
    ");
    $stmt->execute([$teacher_id, $selected_class]);
    $existing_results = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $exam_type = $_POST['exam_type'];
    $subject = trim($_POST['subject']);
    $score = $_POST['score'];
    $total_marks = $_POST['total_marks'];
    $class_name = $_POST['class_name'];
    $teacher_id = $_SESSION['user_id'];
    $exam_date = date('Y-m-d'); // Auto-set to today's date
    
    // Validation
    $errors = [];
    
    if (empty($student_id)) $errors[] = "Please select a student";
    if (empty($subject)) $errors[] = "Subject is required";
    if ($score === '' || $score < 0) $errors[] = "Please enter a valid score";
    if ($total_marks === '' || $total_marks < 1) $errors[] = "Please enter valid total marks";
    if ($score > $total_marks) $errors[] = "Score cannot be greater than total marks";
    
    // Calculate grade
    $percentage = ($score / $total_marks) * 100;
    if ($percentage >= 90) $grade = 'A';
    elseif ($percentage >= 80) $grade = 'B';
    elseif ($percentage >= 70) $grade = 'C';
    elseif ($percentage >= 60) $grade = 'D';
    else $grade = 'F';
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO results (student_id, exam_type, subject, score, total_marks, grade, exam_date, teacher_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $exam_type, $subject, $score, $total_marks, $grade, $exam_date, $teacher_id]);
            
            $success = "Result added successfully! Grade: " . $grade;
            
            // Refresh results list
            $stmt = $pdo->prepare("
                SELECT r.*, u.full_name 
                FROM results r
                JOIN students s ON r.student_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE r.teacher_id = ? AND s.class_name = ?
                ORDER BY r.exam_date DESC, u.full_name ASC
                LIMIT 10
            ");
            $stmt->execute([$teacher_id, $class_name]);
            $existing_results = $stmt->fetchAll();
            
            // Reset form values
            $_POST = array();
            
        } catch(Exception $e) {
            $error = "Failed to add result: " . $e->getMessage();
        }
    }
}

// Get student name for selected student
$selected_student_name = '';
if (isset($_POST['student_id']) && !empty($_POST['student_id'])) {
    foreach ($students as $s) {
        if ($s['id'] == $_POST['student_id']) {
            $selected_student_name = $s['full_name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Result - Mabaso Secondary School</title>
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
            max-width: 850px;
            margin: 0 auto;
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

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header .header-left {
            flex: 1;
        }

        .page-header .header-left h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 5px;
        }

        .page-header .header-left .subtitle {
            color: #64748b;
            font-size: 14px;
        }

        .page-header .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 100px;
            background: linear-gradient(135deg, #0d9488, #059669);
            color: white;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 7px 20px rgba(13, 148, 136, 0.18);
        }

        .class-selector {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }

        .class-selector .selector-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .class-selector .class-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .class-selector .class-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .class-selector .class-btn:hover {
            border-color: #0d9488;
            color: #0d9488;
            transform: translateY(-2px);
        }

        .class-selector .class-btn.active {
            border-color: #0d9488;
            background: linear-gradient(135deg, #0d9488, #059669);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(13, 148, 136, 0.2);
        }

        .class-selector .class-btn .count {
            background: #f1f5f9;
            color: #64748b;
            padding: 1px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 700;
        }

        .class-selector .class-btn.active .count {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
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

        .form-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
            margin-top: 20px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-title .section-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ecfdf5;
            font-size: 18px;
        }

        .section-title h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .section-title .section-sub {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 400;
        }

        .form-group {
            margin: 16px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            color: #0f172a;
            font-size: 13px;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 11px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
            color: #0f172a;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
            background: white;
        }

        .form-group input:hover,
        .form-group select:hover {
            border-color: #cbd5e1;
        }

        .form-group .input-hint {
            display: block;
            margin-top: 5px;
            color: #94a3b8;
            font-size: 11px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .btn-premium {
            padding: 12px 24px;
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-premium:hover::before {
            left: 100%;
        }

        .btn-premium:hover {
            transform: translateY(-3px);
        }

        .btn-success {
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.22);
        }

        .btn-success:hover {
            box-shadow: 0 12px 28px rgba(13, 148, 136, 0.28);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.20);
        }

        .btn-warning:hover {
            box-shadow: 0 12px 28px rgba(245, 158, 11, 0.26);
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .no-students {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .no-students .icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .no-students h4 {
            color: #0f172a;
            margin-bottom: 5px;
        }

        .no-students a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 600;
        }

        .no-students a:hover {
            text-decoration: underline;
        }

        .grade-preview {
            display: none;
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 10px;
            font-weight: 600;
        }

        .grade-preview.show {
            display: block;
        }

        .grade-preview.A {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #0d9488;
        }

        .grade-preview.B {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .grade-preview.C {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .grade-preview.D {
            background: #fed7aa;
            color: #9a3412;
            border-left: 4px solid #f97316;
        }

        .grade-preview.F {
            background: #fecaca;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .existing-results {
            margin-top: 25px;
        }

        .existing-results .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .existing-results .results-header h4 {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .existing-results .results-header .count {
            color: #94a3b8;
            font-size: 12px;
        }

        .result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .result-item .student {
            font-weight: 600;
            color: #0f172a;
        }

        .result-item .score {
            color: #0d9488;
            font-weight: 700;
        }

        .result-item .grade-badge {
            padding: 2px 10px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 12px;
        }

        .result-item .grade-badge.A {
            background: #d1fae5;
            color: #065f46;
        }

        .result-item .grade-badge.B {
            background: #dbeafe;
            color: #1e40af;
        }

        .result-item .grade-badge.C {
            background: #fef3c7;
            color: #92400e;
        }

        .result-item .grade-badge.D {
            background: #fed7aa;
            color: #9a3412;
        }

        .result-item .grade-badge.F {
            background: #fecaca;
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            .content {
                padding: 20px;
            }
            .page-header {
                flex-direction: column;
            }
            .page-header .header-left h2 {
                font-size: 22px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .actions {
                flex-direction: column;
            }
            .actions .btn-premium {
                width: 100%;
                justify-content: center;
            }
            .class-selector .class-buttons {
                flex-direction: column;
            }
            .class-selector .class-btn {
                justify-content: center;
                width: 100%;
            }
            .result-item {
                flex-direction: column;
                gap: 5px;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 17px;
            }
            .page-header .header-left h2 {
                font-size: 20px;
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
                <a href="manage_results.php">Results</a>
                <span>›</span>
                <span class="current">Add Result</span>
            </div>

            <div class="page-header">
                <div class="header-left">
                    <h2>📝 Add New Result</h2>
                    <p class="subtitle">Record an academic result for a student</p>
                </div>
                <span class="header-badge">📊 Results Management</span>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <?php foreach($errors as $err): ?>
                    <div class="alert alert-error">❌ <?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (empty($classes)): ?>
                <div class="no-students">
                    <div class="icon">👨‍🎓</div>
                    <h4>No Students Added</h4>
                    <p>You need to <a href="add_student.php">add students</a> first before you can record results.</p>
                </div>
            <?php else: ?>

                <!-- Class Selector -->
                <div class="class-selector">
                    <div class="selector-label">
                        📚 Select Class
                        <span style="color: #94a3b8; font-size: 12px; font-weight: 400;">(Choose the class to add results for)</span>
                    </div>
                    <div class="class-buttons">
                        <?php foreach($classes as $class): 
                            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ? AND class_name = ?");
                            $count_stmt->execute([$teacher_id, $class]);
                            $count = $count_stmt->fetchColumn();
                        ?>
                            <a href="?class=<?php echo urlencode($class); ?>" class="class-btn <?php echo $selected_class == $class ? 'active' : ''; ?>">
                                📚 <?php echo htmlspecialchars($class); ?>
                                <span class="count"><?php echo $count; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($selected_class && !empty($students)): ?>

                    <!-- Add Result Form -->
                    <form method="POST" id="addResultForm">
                        <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selected_class); ?>">

                        <div class="form-section">
                            <div class="section-title">
                                <div class="section-icon">👨‍🎓</div>
                                <div>
                                    <h3>Student Information</h3>
                                    <span class="section-sub">Select the student receiving this result</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Student <span class="required">*</span></label>
                                <select name="student_id" required>
                                    <option value="">Select a student</option>
                                    <?php foreach($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['student_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="input-hint">Select the student receiving this result.</span>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-title">
                                <div class="section-icon">📋</div>
                                <div>
                                    <h3>Assessment Details</h3>
                                    <span class="section-sub">Exam type and subject information</span>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Exam Type <span class="required">*</span></label>
                                    <select name="exam_type" required>
                                        <option value="Quiz" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] == 'Quiz') ? 'selected' : ''; ?>>Quiz</option>
                                        <option value="Midterm" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] == 'Midterm') ? 'selected' : ''; ?>>Midterm</option>
                                        <option value="Final" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] == 'Final') ? 'selected' : ''; ?>>Final</option>
                                        <option value="Assignment" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] == 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
                                        <option value="Project" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] == 'Project') ? 'selected' : ''; ?>>Project</option>
                                        <option value="Test" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] == 'Test') ? 'selected' : ''; ?>>Test</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Subject <span class="required">*</span></label>
                                    <input type="text" name="subject" required placeholder="e.g., Mathematics" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-title">
                                <div class="section-icon">📊</div>
                                <div>
                                    <h3>Marks & Score</h3>
                                    <span class="section-sub">Enter the student's achieved score and total marks</span>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Score <span class="required">*</span></label>
                                    <input type="number" name="score" step="0.01" required min="0" placeholder="e.g., 75" value="<?php echo isset($_POST['score']) ? htmlspecialchars($_POST['score']) : ''; ?>" oninput="calculateGrade()">
                                    <span class="input-hint">Enter the student's achieved score.</span>
                                </div>

                                <div class="form-group">
                                    <label>Total Marks <span class="required">*</span></label>
                                    <input type="number" name="total_marks" step="0.01" required min="1" placeholder="e.g., 100" value="<?php echo isset($_POST['total_marks']) ? htmlspecialchars($_POST['total_marks']) : ''; ?>" oninput="calculateGrade()">
                                    <span class="input-hint">Enter the maximum available marks.</span>
                                </div>
                            </div>

                            <div class="grade-preview" id="gradePreview">
                                <span id="gradeText">Grade: A</span>
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn-premium btn-success">💾 Add Result</button>
                            <a href="manage_results.php" class="btn-premium btn-warning">❌ Cancel</a>
                        </div>
                    </form>

                    <!-- Recent Results -->
                    <?php if (!empty($existing_results)): ?>
                        <div class="existing-results">
                            <div class="results-header">
                                <h4>📋 Recent Results for <?php echo htmlspecialchars($selected_class); ?></h4>
                                <span class="count">Last 10 results</span>
                            </div>
                            <?php foreach($existing_results as $result): ?>
                                <div class="result-item">
                                    <span class="student"><?php echo htmlspecialchars($result['full_name']); ?></span>
                                    <span><?php echo htmlspecialchars($result['subject']); ?></span>
                                    <span class="score"><?php echo $result['score']; ?> / <?php echo $result['total_marks']; ?></span>
                                    <span class="grade-badge <?php echo $result['grade']; ?>"><?php echo $result['grade']; ?></span>
                                    <span style="color: #94a3b8; font-size: 11px;"><?php echo date('M d, Y', strtotime($result['exam_date'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($selected_class && empty($students)): ?>
                    <div class="no-students">
                        <div class="icon">👨‍🎓</div>
                        <h4>No Students in <?php echo htmlspecialchars($selected_class); ?></h4>
                        <p>Add students to this class first before adding results.</p>
                        <a href="add_student.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #0d9488; color: white; border-radius: 10px; text-decoration: none;">➕ Add Student</a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

    <script>
        function calculateGrade() {
            const score = document.getElementById('score');
            const total = document.getElementById('total_marks');
            const preview = document.getElementById('gradePreview');
            const gradeText = document.getElementById('gradeText');

            if (score && total && score.value && total.value && parseFloat(score.value) >= 0 && parseFloat(total.value) > 0) {
                const s = parseFloat(score.value);
                const t = parseFloat(total.value);
                const percentage = (s / t) * 100;

                let grade = '';
                let gradeClass = '';

                if (percentage >= 90) { grade = 'A'; gradeClass = 'A'; }
                else if (percentage >= 80) { grade = 'B'; gradeClass = 'B'; }
                else if (percentage >= 70) { grade = 'C'; gradeClass = 'C'; }
                else if (percentage >= 60) { grade = 'D'; gradeClass = 'D'; }
                else { grade = 'F'; gradeClass = 'F'; }

                gradeText.textContent = 'Grade: ' + grade + ' (' + percentage.toFixed(1) + '%)';
                preview.className = 'grade-preview show ' + gradeClass;
            } else {
                preview.className = 'grade-preview';
            }
        }
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>