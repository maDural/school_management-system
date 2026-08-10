<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];

// Get statistics
$stats = [
    'students' => $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ?"),
    'attendance' => $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE teacher_id = ? AND date = CURDATE()"),
    'lessons' => $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE teacher_id = ?"),
    'results' => $pdo->prepare("SELECT COUNT(*) FROM results WHERE teacher_id = ?"),
    'classes' => $pdo->prepare("SELECT COUNT(DISTINCT class_name) FROM students WHERE teacher_id = ?")
];

foreach ($stats as $key => $stmt) {
    $stmt->execute([$teacher_id]);
    $stats[$key] = $stmt->fetchColumn();
}

// Get class-wise student count
$stmt = $pdo->prepare("
    SELECT class_name, COUNT(*) as count 
    FROM students 
    WHERE teacher_id = ? 
    GROUP BY class_name 
    ORDER BY class_name
");
$stmt->execute([$teacher_id]);
$class_counts = $stmt->fetchAll();

// Get recent students
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.teacher_id = ? 
    ORDER BY s.id DESC 
    LIMIT 5
");
$stmt->execute([$teacher_id]);
$recent_students = $stmt->fetchAll();

// Get today's attendance by class
$stmt = $pdo->prepare("
    SELECT class_name, 
           COUNT(*) as total,
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
           SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM attendance 
    WHERE teacher_id = ? AND date = CURDATE()
    GROUP BY class_name
");
$stmt->execute([$teacher_id]);
$today_attendance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - EduManager</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
    box-sizing: border-box;
}

body {
    background:
        radial-gradient(circle at 5% 5%, rgba(13, 148, 136, 0.07), transparent 28%),
        radial-gradient(circle at 95% 95%, rgba(59, 130, 246, 0.06), transparent 28%),
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 50%, #f1f5f9 100%);
    min-height: 100vh;
}

.container {
    max-width: 1380px;
    padding: 28px 32px;
    margin: 0 auto;
}

.content {
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 28px;
    padding: 38px;
    box-shadow:
        0 10px 30px rgba(15, 23, 42, 0.04),
        0 25px 70px rgba(15, 23, 42, 0.08);
    position: relative;
    overflow: hidden;
}

.content::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0f172a, #0d9488, #14b8a6, #3b82f6);
}

.welcome-section {
    background:
        radial-gradient(circle at 85% 20%, rgba(45, 212, 191, 0.16), transparent 30%),
        linear-gradient(135deg, #020617 0%, #0f172a 45%, #134e4a 100%);
    border-radius: 24px;
    padding: 42px 45px;
    color: white;
    margin-bottom: 35px;
    box-shadow:
        0 15px 35px rgba(15, 23, 42, 0.18),
        0 5px 15px rgba(15, 23, 42, 0.08);
    position: relative;
    overflow: hidden;
}

.welcome-section::before {
    content: "";
    position: absolute;
    top: -180px;
    right: -80px;
    width: 480px;
    height: 480px;
    background: radial-gradient(circle, rgba(45, 212, 191, 0.18) 0%, rgba(45, 212, 191, 0.06) 35%, transparent 70%);
    border-radius: 50%;
    animation: floatLight 18s ease-in-out infinite;
}

.welcome-section::after {
    content: "";
    position: absolute;
    bottom: -120px;
    left: 30%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.08), transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

@keyframes floatLight {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    50% {
        transform: translate(18px, -15px) scale(1.06);
    }
}

.welcome-section .welcome-content {
    position: relative;
    z-index: 2;
}

.welcome-section .greeting-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(45, 212, 191, 0.12);
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 10px;
    font-weight: 700;
    color: #5eead4;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    border: 1px solid rgba(45, 212, 191, 0.18);
    margin-bottom: 14px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

.welcome-section h1 {
    font-size: 38px;
    line-height: 1.15;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 10px;
    letter-spacing: -1px;
}

.welcome-section .subtitle {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 12px;
    color: rgba(255, 255, 255, 0.72);
    font-size: 13px;
}

.welcome-section .subtitle .badge-premium {
    background: rgba(255, 255, 255, 0.07);
    color: #99f6e4;
    padding: 6px 13px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.09);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: all 0.25s ease;
}

.welcome-section .subtitle .badge-premium:hover {
    background: rgba(45, 212, 191, 0.13);
    border-color: rgba(45, 212, 191, 0.2);
    transform: translateY(-1px);
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 18px;
    margin: 30px 0;
}

.card-premium {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 25px 18px;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    box-shadow: 0 3px 15px rgba(15, 23, 42, 0.035);
    border: 1px solid #eef2f7;
    position: relative;
    overflow: hidden;
}

.card-premium::before {
    content: "";
    position: absolute;
    top: 0;
    left: 20%;
    right: 20%;
    height: 2px;
    border-radius: 100px;
    background: transparent;
    transition: all 0.3s ease;
}

.card-premium:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 35px rgba(15, 23, 42, 0.09), 0 5px 15px rgba(15, 23, 42, 0.04);
    border-color: #e2e8f0;
}

.card-premium:hover::before {
    left: 25%;
    right: 25%;
}

.card-premium .card-icon-wrapper {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 14px;
    font-size: 23px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
    transition: transform 0.3s ease;
}

.card-premium:hover .card-icon-wrapper {
    transform: translateY(-2px) scale(1.05);
}

.card-premium:nth-child(1) .card-icon-wrapper {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
}

.card-premium:nth-child(2) .card-icon-wrapper {
    background: linear-gradient(135deg, #ccfbf1, #99f6e4);
}

.card-premium:nth-child(3) .card-icon-wrapper {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
}

.card-premium:nth-child(4) .card-icon-wrapper {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
}

.card-premium:nth-child(5) .card-icon-wrapper {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
}

.card-premium h3 {
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin: 0 0 5px;
}

.card-premium .number {
    font-size: 32px;
    line-height: 1.15;
    font-weight: 800;
    margin: 6px 0;
    letter-spacing: -1.2px;
}

.card-premium:nth-child(1) .number {
    color: #0f172a;
}

.card-premium:nth-child(2) .number {
    color: #0d9488;
}

.card-premium:nth-child(3) .number {
    color: #f59e0b;
}

.card-premium:nth-child(4) .number {
    color: #ef4444;
}

.card-premium:nth-child(5) .number {
    color: #3b82f6;
}

.card-premium .label {
    color: #94a3b8;
    font-size: 11px;
}

.quick-actions-premium {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    margin: 30px 0 42px;
}

.btn-premium {
    min-height: 48px;
    padding: 13px 16px;
    border-radius: 13px;
    font-weight: 700;
    font-size: 12px;
    transition: transform 0.25s ease, box-shadow 0.25s ease, filter 0.25s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    color: #ffffff;
    white-space: nowrap;
}

.btn-premium:hover {
    transform: translateY(-3px);
    filter: brightness(1.04);
}

.btn-premium:active {
    transform: translateY(-1px);
}

.btn-premium-primary {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    box-shadow: 0 7px 20px rgba(15, 23, 42, 0.22);
}

.btn-premium-primary:hover {
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.28);
}

.btn-premium-secondary {
    background: linear-gradient(135deg, #0d9488, #059669);
    box-shadow: 0 7px 20px rgba(13, 148, 136, 0.22);
}

.btn-premium-secondary:hover {
    box-shadow: 0 12px 28px rgba(13, 148, 136, 0.30);
}

.btn-premium-tertiary {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 7px 20px rgba(245, 158, 11, 0.20);
}

.btn-premium-tertiary:hover {
    box-shadow: 0 12px 28px rgba(245, 158, 11, 0.28);
}

.btn-premium-quaternary {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    box-shadow: 0 7px 20px rgba(239, 68, 68, 0.20);
}

.btn-premium-quaternary:hover {
    box-shadow: 0 12px 28px rgba(239, 68, 68, 0.28);
}

.btn-premium-quinary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 7px 20px rgba(59, 130, 246, 0.20);
}

.btn-premium-quinary:hover {
    box-shadow: 0 12px 28px rgba(59, 130, 246, 0.28);
}

.btn-info {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    box-shadow: 0 7px 20px rgba(124, 58, 237, 0.20);
}

.btn-info:hover {
    box-shadow: 0 12px 28px rgba(124, 58, 237, 0.28);
}

.section-header-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 38px 0 18px;
    padding: 0 2px 14px;
    border-bottom: 1px solid #e8edf3;
}

.section-header-premium h3 {
    font-size: 18px;
    font-weight: 750;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.3px;
}

.section-header-premium .view-all {
    color: #0d9488;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 8px;
    transition: all 0.25s ease;
}

.section-header-premium .view-all:hover {
    color: #059669;
    background: #f0fdfa;
}

.class-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 15px;
}

.class-card {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 17px;
    padding: 21px 18px;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    position: relative;
}

.class-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.07);
    border-color: #5eead4;
}

.class-card .class-name {
    font-size: 19px;
    font-weight: 750;
    color: #0f172a;
}

.class-card .student-count {
    color: #64748b;
    font-size: 13px;
    margin-top: 6px;
}

.class-card .attendance-status {
    margin-top: 12px;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.table-responsive {
    overflow-x: auto;
    margin-top: 15px;
    border-radius: 16px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
}

thead {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
}

th {
    padding: 14px 20px;
    text-align: left;
    font-weight: 700;
    color: #475569;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 15px 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    font-size: 13px;
    transition: background 0.2s ease;
}

tbody tr {
    transition: background 0.2s ease;
}

tbody tr:hover {
    background: #f8fafc;
}

tbody tr:last-child td {
    border-bottom: none;
}

td strong {
    color: #0f172a;
    font-weight: 700;
}

.attendance-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 6px;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.04);
}

.attendance-dot.present {
    background: #0d9488;
}

.attendance-dot.absent {
    background: #ef4444;
}

.attendance-dot.late {
    background: #f59e0b;
}

@media (max-width: 1200px) {
    .dashboard-cards {
        grid-template-columns: repeat(3, 1fr);
    }
    .quick-actions-premium {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .container {
        padding: 12px 15px;
    }
    .content {
        padding: 22px;
        border-radius: 22px;
    }
    .welcome-section {
        padding: 30px 25px;
        border-radius: 20px;
    }
    .welcome-section h1 {
        font-size: 28px;
        letter-spacing: -0.6px;
    }
    .welcome-section .subtitle {
        gap: 8px;
    }
    .dashboard-cards {
        grid-template-columns: 1fr 1fr;
        gap: 13px;
    }
    .card-premium {
        padding: 21px 14px;
    }
    .card-premium .number {
        font-size: 26px;
    }
    .quick-actions-premium {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .btn-premium {
        min-height: 46px;
        font-size: 12px;
    }
    .class-grid {
        grid-template-columns: 1fr 1fr;
    }
    .section-header-premium h3 {
        font-size: 17px;
    }
    th, td {
        padding: 13px 15px;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 8px;
    }
    .content {
        padding: 16px;
        border-radius: 18px;
    }
    .welcome-section {
        padding: 25px 20px;
        margin-bottom: 22px;
    }
    .welcome-section h1 {
        font-size: 24px;
    }
    .welcome-section .greeting-badge {
        font-size: 9px;
        padding: 5px 12px;
    }
    .welcome-section .subtitle {
        flex-direction: column;
        align-items: flex-start;
    }
    .dashboard-cards {
        grid-template-columns: 1fr;
        gap: 11px;
        margin: 22px 0;
    }
    .card-premium {
        display: grid;
        grid-template-columns: 52px 1fr;
        grid-template-rows: auto auto;
        column-gap: 14px;
        text-align: left;
        align-items: center;
        padding: 17px;
    }
    .card-premium .card-icon-wrapper {
        grid-row: span 2;
        margin: 0;
    }
    .card-premium h3 {
        margin: 0;
    }
    .card-premium .number {
        margin: 2px 0;
        font-size: 24px;
    }
    .card-premium .label {
        grid-column: 2;
    }
    .quick-actions-premium {
        grid-template-columns: 1fr;
        margin: 22px 0 30px;
    }
    .btn-premium {
        width: 100%;
    }
    .class-grid {
        grid-template-columns: 1fr;
    }
    .section-header-premium {
        margin-top: 28px;
    }
    .section-header-premium h3 {
        font-size: 16px;
    }
    .section-header-premium .view-all {
        font-size: 12px;
    }
    .table-responsive {
        border-radius: 12px;
    }
    table {
        min-width: 600px;
    }
    th, td {
        padding: 12px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="greeting-badge">✦ Welcome Back</div>
                    <h1>Hello, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                    <div class="subtitle">
                        <span>📅 <?php echo date('l, F d, Y'); ?></span>
                        <span class="badge-premium">👨‍🏫 Teacher</span>
                        <span class="badge-premium">📚 <?php echo $stats['classes']; ?> Class<?php echo $stats['classes'] > 1 ? 'es' : ''; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="dashboard-cards">
                <div class="card-premium">
                    <div class="card-icon-wrapper">👨‍🎓</div>
                    <h3>Students</h3>
                    <div class="number"><?php echo $stats['students']; ?></div>
                    <div class="label">Total Students</div>
                </div>
                <div class="card-premium">
                    <div class="card-icon-wrapper">✅</div>
                    <h3>Attendance</h3>
                    <div class="number"><?php echo $stats['attendance']; ?></div>
                    <div class="label">Present Today</div>
                </div>
                <div class="card-premium">
                    <div class="card-icon-wrapper">📚</div>
                    <h3>Lessons</h3>
                    <div class="number"><?php echo $stats['lessons']; ?></div>
                    <div class="label">Total Lessons</div>
                </div>
                <div class="card-premium">
                    <div class="card-icon-wrapper">📊</div>
                    <h3>Results</h3>
                    <div class="number"><?php echo $stats['results']; ?></div>
                    <div class="label">Total Results</div>
                </div>
                <div class="card-premium">
                    <div class="card-icon-wrapper">🏫</div>
                    <h3>Classes</h3>
                    <div class="number"><?php echo $stats['classes']; ?></div>
                    <div class="label">Your Classes</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions-premium">
                <a href="add_student.php" class="btn-premium btn-premium-primary">➕ Add Student</a>
                <a href="attendance.php" class="btn-premium btn-premium-secondary">📋 Take Attendance</a>
                <a href="manage_quizzes.php" class="btn-premium btn-premium-quinary">📝 Manage Quizzes</a>
                <a href="manage_assignments.php" class="btn-premium btn-info">📝 Assignments</a>
                <a href="add_result.php" class="btn-premium btn-premium-tertiary">📊 Add Results</a>
                <a href="add_lesson.php" class="btn-premium btn-premium-quaternary">📖 Add Lesson</a>
            </div>
            
            <!-- Classes Overview -->
            <?php if (!empty($class_counts)): ?>
            <div class="section-header-premium">
                <h3>📚 Your Classes</h3>
                <a href="manage_students.php" class="view-all">View All →</a>
            </div>
            <div class="class-grid">
                <?php foreach($class_counts as $class): 
                    // Get today's attendance for this class
                    $att_stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
                        FROM attendance 
                        WHERE teacher_id = ? AND class_name = ? AND date = CURDATE()
                    ");
                    $att_stmt->execute([$teacher_id, $class['class_name']]);
                    $att_data = $att_stmt->fetch();
                    
                    $present_count = $att_data['present'] ?? 0;
                    $total_count = $att_data['total'] ?? 0;
                    $attendance_percent = $total_count > 0 ? round(($present_count / $total_count) * 100) : 0;
                ?>
                    <div class="class-card">
                        <div class="class-name">📚 <?php echo htmlspecialchars($class['class_name']); ?></div>
                        <div class="student-count">👨‍🎓 <?php echo $class['count']; ?> Students</div>
                        <?php if ($total_count > 0): ?>
                            <div class="attendance-status">
                                <span class="badge <?php echo $attendance_percent >= 80 ? 'badge-success' : ($attendance_percent >= 50 ? 'badge-warning' : 'badge-info'); ?>">
                                    <?php echo $attendance_percent; ?>% Today
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="attendance-status">
                                <span class="badge badge-info">No attendance yet</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Recent Students -->
            <?php if (count($recent_students) > 0): ?>
            <div class="section-header-premium">
                <h3>👨‍🎓 Recent Students</h3>
                <a href="manage_students.php" class="view-all">View All →</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_students as $student): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><span class="badge badge-info">📚 <?php echo htmlspecialchars($student['class_name']); ?></span></td>
                            <td><span class="badge badge-success">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>