<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get all classes for this teacher
$stmt = $pdo->prepare("SELECT DISTINCT class_name FROM students WHERE teacher_id = ? ORDER BY class_name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get selected class from URL or session
$selected_class = isset($_GET['class']) ? $_GET['class'] : (isset($_SESSION['selected_class']) ? $_SESSION['selected_class'] : null);

// If no class selected and there are classes, select the first one
if (empty($selected_class) && !empty($classes)) {
    $selected_class = $classes[0];
}

// Save selected class to session
if ($selected_class) {
    $_SESSION['selected_class'] = $selected_class;
}

// Get students for selected class only
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'] ?? $today;
    $class_name = $_POST['class_name'];
    $attendance = $_POST['attendance'] ?? [];
    $teacher_id = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($attendance as $student_id => $status) {
            if ($status) {
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, class_name, date, status, teacher_id) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");
                $stmt->execute([$student_id, $class_name, $date, $status, $teacher_id]);
            }
        }
        
        $pdo->commit();
        $success = "✅ Attendance recorded successfully for " . htmlspecialchars($class_name) . "!";
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Get today's attendance for selected class
$today_attendance = [];
if ($selected_class) {
    $stmt = $pdo->prepare("
        SELECT student_id, status FROM attendance 
        WHERE teacher_id = ? AND date = ? AND class_name = ?
    ");
    $stmt->execute([$teacher_id, $today, $selected_class]);
    $today_attendance = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Get attendance summary for selected class
$class_attendance_summary = [];
if ($selected_class) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM attendance 
        WHERE teacher_id = ? AND class_name = ? AND date = ?
    ");
    $stmt->execute([$teacher_id, $selected_class, $today]);
    $class_attendance_summary = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance - EduManager</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            background:
                radial-gradient(circle at 8% 8%, rgba(13, 148, 136, 0.08), transparent 28%),
                radial-gradient(circle at 92% 92%, rgba(15, 23, 42, 0.07), transparent 30%),
                linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            min-height: 100vh;
            color: #0f172a;
        }

        .container {
            max-width: 1320px;
            padding: 28px 30px 50px;
            margin: 0 auto;
        }

        .content {
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 30px;
            padding: 38px;
            box-shadow: 0 25px 70px rgba(15, 23, 42, 0.08), 0 5px 20px rgba(15, 23, 42, 0.04);
        }

        .content::before,
        .content::after {
            content: "";
            position: absolute;
            pointer-events: none;
            border-radius: 50%;
            z-index: 0;
        }

        .content::before {
            width: 520px;
            height: 520px;
            top: -300px;
            right: -180px;
            background: radial-gradient(circle, rgba(13, 148, 136, 0.08) 0%, rgba(13, 148, 136, 0.02) 45%, transparent 72%);
        }

        .content::after {
            width: 450px;
            height: 450px;
            bottom: -280px;
            left: -200px;
            background: radial-gradient(circle, rgba(15, 23, 42, 0.05) 0%, transparent 70%);
        }

        .content > * {
            position: relative;
            z-index: 1;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
            padding-bottom: 22px;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header-icon {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: linear-gradient(135deg, #0f172a, #0d9488);
            color: #ffffff;
            font-size: 25px;
            box-shadow: 0 10px 25px rgba(13, 148, 136, 0.20);
        }

        .page-header h2 {
            margin: 0;
            font-size: 28px;
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.7px;
            color: #0f172a;
        }

        .page-header .header-subtitle {
            margin-top: 6px;
            color: #64748b;
            font-size: 13px;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 100px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            font-size: 12px;
            font-weight: 700;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 18px;
            margin: 0 0 22px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid transparent;
            animation: slideDown 0.35s ease;
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

        .alert-success {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: linear-gradient(135deg, #fff1f2, #fef2f2);
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            color: #92400e;
            border-color: #fde68a;
        }

        .alert-info {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .class-selector {
            position: relative;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.04);
        }

        .class-selector::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 22px 22px 0 0;
            background: linear-gradient(90deg, #0f172a, #0d9488, #059669);
        }

        .class-selector .label {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 14px;
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
        }

        .class-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .class-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 13px;
            border: 1.5px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .class-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(13, 148, 136, 0.05), transparent);
            transition: left 0.5s ease;
        }

        .class-btn:hover::before {
            left: 100%;
        }

        .class-btn:hover {
            transform: translateY(-3px);
            border-color: #0d9488;
            color: #0d9488;
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.12);
            background: #f0fdfa;
        }

        .class-btn.active {
            color: #ffffff;
            border-color: #0d9488;
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.22);
            transform: translateY(-2px);
        }

        .class-btn.active:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(13, 148, 136, 0.30);
        }

        .class-btn .count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 10px;
            margin-left: 4px;
            border-radius: 100px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .class-btn.active .count {
            background: rgba(255, 255, 255, 0.20);
            color: #ffffff;
        }

        .class-btn:hover .count {
            background: #d1fae5;
            color: #065f46;
        }

        .class-btn.active:hover .count {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        .summary-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin: 25px 0;
        }

        .summary-item {
            position: relative;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 22px 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04);
            transition: all 0.3s ease;
        }

        .summary-item:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.10);
        }

        .summary-item::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #cbd5e1;
            transition: height 0.3s ease;
        }

        .summary-item:hover::before {
            height: 4px;
        }

        .summary-item.present::before {
            background: #0d9488;
        }

        .summary-item.absent::before {
            background: #ef4444;
        }

        .summary-item.late::before {
            background: #f59e0b;
        }

        .summary-item.total::before {
            background: #0f172a;
        }

        .summary-item .summary-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            border-radius: 13px;
            background: #f1f5f9;
            font-size: 20px;
            transition: all 0.3s ease;
        }

        .summary-item:hover .summary-icon {
            transform: scale(1.05) rotate(-3deg);
        }

        .summary-item.present .summary-icon {
            background: #ecfdf5;
        }

        .summary-item.absent .summary-icon {
            background: #fef2f2;
        }

        .summary-item.late .summary-icon {
            background: #fffbeb;
        }

        .summary-item.total .summary-icon {
            background: #f1f5f9;
        }

        .summary-item .number {
            color: #0f172a;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.8px;
            transition: all 0.3s ease;
        }

        .summary-item:hover .number {
            transform: scale(1.05);
        }

        .summary-item .label {
            margin-top: 5px;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
        }

        .summary-item.present .number {
            color: #0d9488;
        }

        .summary-item.absent .number {
            color: #ef4444;
        }

        .summary-item.late .number {
            color: #d97706;
        }

        .attendance-form {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 26px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.05);
        }

        .form-section-title {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 20px;
            padding-bottom: 17px;
            border-bottom: 1px solid #e2e8f0;
            color: #0f172a;
            font-size: 18px;
            font-weight: 800;
        }

        .form-section-title .title-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: #ecfdf5;
            font-size: 19px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin: 15px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .form-group input {
            width: 100%;
            min-height: 48px;
            padding: 12px 16px;
            border: 1.5px solid #dbe3ec;
            border-radius: 13px;
            background: #f8fafc;
            color: #0f172a;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.25s ease;
            box-sizing: border-box;
        }

        .form-group input:hover {
            border-color: #cbd5e1;
            background: #ffffff;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0d9488;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.09), 0 5px 15px rgba(13, 148, 136, 0.05);
        }

        .form-group input[readonly] {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        .table-card {
            margin-top: 20px;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 17px 20px;
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            border-bottom: 1px solid #e2e8f0;
        }

        .table-header-title {
            color: #0f172a;
            font-size: 14px;
            font-weight: 800;
        }

        .table-header-subtitle {
            color: #94a3b8;
            font-size: 12px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
            background: #ffffff;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 14px 20px;
            text-align: left;
            color: #475569;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        td {
            padding: 15px 20px;
            color: #1e293b;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
            transform: scale(1.01);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .student-name {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .student-avatar {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: linear-gradient(135deg, #0f172a, #0d9488);
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            transition: all 0.3s ease;
        }

        tr:hover .student-avatar {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);
        }

        .student-details strong {
            display: block;
            color: #0f172a;
            font-size: 13px;
            font-weight: 750;
        }

        .student-details small {
            display: block;
            margin-top: 2px;
            color: #94a3b8;
            font-size: 11px;
        }

        .attendance-option {
            text-align: center;
        }

        .attendance-option label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .attendance-option label:hover {
            background: #f1f5f9;
            transform: scale(1.1);
        }

        input[type="radio"] {
            width: 19px;
            height: 19px;
            margin: 0;
            cursor: pointer;
            accent-color: #0d9488;
            transition: all 0.2s ease;
        }

        input[type="radio"]:hover {
            transform: scale(1.15);
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 22px;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }

        .btn-premium {
            min-height: 46px;
            padding: 12px 24px;
            border: none;
            border-radius: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
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

        .btn-premium:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.20);
        }

        .btn-primary:hover {
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.28);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 22px rgba(245, 158, 11, 0.20);
        }

        .btn-warning:hover {
            box-shadow: 0 12px 35px rgba(245, 158, 11, 0.28);
        }

        .no-students {
            padding: 55px 25px;
            text-align: center;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
        }

        .no-students .icon {
            width: 72px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            border-radius: 20px;
            background: #f1f5f9;
            font-size: 35px;
        }

        .no-students h3 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 19px;
            font-weight: 800;
        }

        .no-students p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 1100px) {
            .summary-box {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 12px 15px 30px;
            }
            .content {
                padding: 22px;
                border-radius: 22px;
            }
            .page-header {
                align-items: flex-start;
            }
            .page-header h2 {
                font-size: 22px;
            }
            .page-header-icon {
                width: 45px;
                height: 45px;
                font-size: 21px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .attendance-form {
                padding: 20px;
            }
            .summary-box {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .class-buttons {
                flex-direction: column;
            }
            .class-btn {
                justify-content: center;
                width: 100%;
                box-sizing: border-box;
            }
            .actions {
                flex-direction: column;
            }
            .actions .btn-premium {
                width: 100%;
            }
            .table-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 520px) {
            .content {
                padding: 17px;
            }
            .page-header {
                flex-direction: column;
                gap: 12px;
            }
            .page-header-left {
                width: 100%;
            }
            .header-badge {
                align-self: flex-start;
            }
            .summary-box {
                grid-template-columns: 1fr;
            }
            .attendance-form {
                padding: 16px;
            }
            .summary-item {
                padding: 18px 15px;
            }
            .summary-item .number {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
    <div class="content">

        <div class="page-header">
            <div class="page-header-left">
                <div class="page-header-icon">📋</div>
                <div>
                    <h2>Take Attendance</h2>
                    <div class="header-subtitle">Record daily attendance for your students</div>
                </div>
            </div>
            <span class="header-badge">📅 <?php echo date('M d, Y'); ?></span>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <span>❌</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($classes)): ?>
            <div class="alert alert-warning">
                <span>⚠️</span>
                <span>
                    You don't have any students yet.
                    <a href="add_student.php" style="color:#0d9488;font-weight:700;text-decoration:none;">Add your first student</a>
                    to start taking attendance.
                </span>
            </div>
        <?php else: ?>

            <div class="class-selector">
                <span class="label">📚 Select Class</span>
                <div class="class-buttons">
                    <?php foreach ($classes as $class): ?>
                        <?php
                        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ? AND class_name = ?");
                        $count_stmt->execute([$teacher_id, $class]);
                        $count = $count_stmt->fetchColumn();
                        ?>
                        <a href="?class=<?php echo urlencode($class); ?>" class="class-btn <?php echo $selected_class == $class ? 'active' : ''; ?>">
                            📚 <?php echo htmlspecialchars($class); ?>
                            <span class="count"><?php echo $count; ?> <?php echo $count == 1 ? 'student' : 'students'; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($selected_class && !empty($students)): ?>

                <div class="summary-box">
                    <div class="summary-item present">
                        <div class="summary-icon">✅</div>
                        <div class="number"><?php echo $class_attendance_summary['present'] ?? 0; ?></div>
                        <div class="label">Present</div>
                    </div>

                    <div class="summary-item absent">
                        <div class="summary-icon">❌</div>
                        <div class="number"><?php echo $class_attendance_summary['absent'] ?? 0; ?></div>
                        <div class="label">Absent</div>
                    </div>

                    <div class="summary-item late">
                        <div class="summary-icon">⏰</div>
                        <div class="number"><?php echo $class_attendance_summary['late'] ?? 0; ?></div>
                        <div class="label">Late</div>
                    </div>

                    <div class="summary-item total">
                        <div class="summary-icon">👨‍🎓</div>
                        <div class="number"><?php echo count($students); ?></div>
                        <div class="label">Total Students</div>
                    </div>
                </div>

                <div class="attendance-form">
                    <div class="form-section-title">
                        <span class="title-icon">📝</span>
                        <span>Daily Attendance</span>
                    </div>

                    <form method="POST" id="attendanceForm" onsubmit="return validateForm('attendanceForm')">
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Attendance Date</label>
                                <input type="date" name="date" value="<?php echo $today; ?>" required>
                            </div>

                            <div class="form-group">
                                <label>🏫 Selected Class</label>
                                <input type="text" name="class_name" value="<?php echo htmlspecialchars($selected_class); ?>" readonly>
                            </div>
                        </div>

                        <div class="table-card">
                            <div class="table-header">
                                <div>
                                    <div class="table-header-title">👨‍🎓 Student Attendance</div>
                                    <div class="table-header-subtitle">Select one attendance status for each student</div>
                                </div>
                                <span class="header-badge"><?php echo count($students); ?> Students</span>
                            </div>

                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th style="text-align:center;">✅ Present</th>
                                            <th style="text-align:center;">❌ Absent</th>
                                            <th style="text-align:center;">⏰ Late</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td>
                                                    <div class="student-name">
                                                        <div class="student-avatar">
                                                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="student-details">
                                                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                                            <small><?php echo htmlspecialchars($student['student_id']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td class="attendance-option">
                                                    <label>
                                                        <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="present"
                                                            <?php echo isset($today_attendance[$student['id']]) && $today_attendance[$student['id']] == 'present' ? 'checked' : ''; ?>>
                                                    </label>
                                                </td>

                                                <td class="attendance-option">
                                                    <label>
                                                        <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="absent"
                                                            <?php echo isset($today_attendance[$student['id']]) && $today_attendance[$student['id']] == 'absent' ? 'checked' : ''; ?>>
                                                    </label>
                                                </td>

                                                <td class="attendance-option">
                                                    <label>
                                                        <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="late"
                                                            <?php echo isset($today_attendance[$student['id']]) && $today_attendance[$student['id']] == 'late' ? 'checked' : ''; ?>>
                                                    </label>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn-premium btn-primary">💾 Save Attendance</button>
                            <button type="reset" class="btn-premium btn-warning">↺ Reset</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($selected_class && empty($students)): ?>

                <div class="no-students">
                    <div class="icon">👨‍🎓</div>
                    <h3>No Students in <?php echo htmlspecialchars($selected_class); ?></h3>
                    <p>Add students to this class to start taking attendance.</p>
                    <a href="add_student.php" class="btn-premium btn-success" style="margin-top:20px;">➕ Add Student</a>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>