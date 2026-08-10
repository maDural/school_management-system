<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get student data with class and teacher
$stmt = $pdo->prepare("
    SELECT u.*, s.student_id, s.class_name, s.teacher_id, 
           t.full_name as teacher_name
    FROM users u 
    JOIN students s ON u.id = s.user_id 
    LEFT JOIN users t ON s.teacher_id = t.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('../../logout.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        $pdo->beginTransaction();
        
        // Check if email already exists for other users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already exists for another user");
        }
        
        // Update user info
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $user_id]);
        
        // Update password if provided
        if (!empty($current_password) && !empty($new_password)) {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception("Password must be at least 6 characters");
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
        }
        
        $pdo->commit();
        $success = "Profile updated successfully!";
        
        // Refresh session data
        $_SESSION['full_name'] = $full_name;
        
        // Refresh student data
        $stmt = $pdo->prepare("
            SELECT u.*, s.student_id, s.class_name, s.teacher_id, 
                   t.full_name as teacher_name
            FROM users u 
            JOIN students s ON u.id = s.user_id 
            LEFT JOIN users t ON s.teacher_id = t.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_attendance,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
    FROM attendance 
    WHERE student_id = (SELECT id FROM students WHERE user_id = ?)
");
$stmt->execute([$user_id]);
$attendance_stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_results,
           AVG(score/total_marks*100) as avg_score
    FROM results 
    WHERE student_id = (SELECT id FROM students WHERE user_id = ?)
");
$stmt->execute([$user_id]);
$result_stats = $stmt->fetch();

$total_attendance = $attendance_stats['total_attendance'] ?? 0;
$present_days = $attendance_stats['present_days'] ?? 0;
$absent_days = $attendance_stats['absent_days'] ?? 0;
$late_days = $attendance_stats['late_days'] ?? 0;
$attendance_rate = $total_attendance > 0 ? round(($present_days / $total_attendance) * 100, 1) : 0;
$avg_score = round($result_stats['avg_score'] ?? 0, 1);
$total_results = $result_stats['total_results'] ?? 0;

$teacher_name = $student['teacher_name'] ?? 'Not assigned';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EduManager</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
body {
    background:
        radial-gradient(circle at 8% 8%, rgba(13, 148, 136, 0.08), transparent 28%),
        radial-gradient(circle at 92% 92%, rgba(15, 23, 42, 0.06), transparent 30%),
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
    min-height: 100vh;
    color: #1e293b;
}

.container {
    width: 100%;
    max-width: 1380px;
    margin: 0 auto;
    padding: 28px 30px 45px;
}

.profile-content {
    position: relative;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 28px;
    padding: 35px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.07), 0 2px 10px rgba(15, 23, 42, 0.03);
}

.profile-content::before {
    content: "";
    position: absolute;
    top: -180px;
    right: -120px;
    width: 430px;
    height: 430px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(13, 148, 136, 0.09) 0%, rgba(13, 148, 136, 0.02) 45%, transparent 72%);
    pointer-events: none;
}

.profile-content::after {
    content: "";
    position: absolute;
    bottom: -230px;
    left: -150px;
    width: 460px;
    height: 460px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(15, 23, 42, 0.045) 0%, transparent 70%);
    pointer-events: none;
}

.profile-header {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 28px;
    padding: 24px 26px;
    border-radius: 22px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #0d9488 100%);
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.profile-header::after {
    content: "";
    position: absolute;
    width: 250px;
    height: 250px;
    right: -90px;
    top: -140px;
    border-radius: 50%;
    background: rgba(45, 212, 191, 0.13);
    pointer-events: none;
}

.profile-header-content {
    position: relative;
    z-index: 1;
}

.profile-header h2 {
    margin: 0;
    color: #ffffff;
    font-size: 28px;
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -0.6px;
}

.profile-header p {
    margin: 8px 0 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 13px;
    font-weight: 500;
}

.school-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 12px;
    padding: 5px 12px;
    border-radius: 50px;
    background: rgba(45, 212, 191, 0.12);
    border: 1px solid rgba(45, 212, 191, 0.18);
    color: #99f6e4;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.alert {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    margin: 0 0 18px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 600;
    animation: alertSlide 0.3s ease;
}

@keyframes alertSlide {
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
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.profile-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(280px, 0.85fr) minmax(0, 1.65fr);
    gap: 24px;
    align-items: start;
}

.profile-summary {
    background: #ffffff;
    border: 1px solid #e8edf3;
    border-radius: 22px;
    padding: 30px 26px;
    box-shadow: 0 6px 25px rgba(15, 23, 42, 0.045);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.profile-summary:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
}

.profile-identity {
    text-align: center;
    padding-bottom: 22px;
    border-bottom: 1px solid #eef2f7;
}

.avatar-wrapper {
    position: relative;
    display: inline-flex;
    margin-bottom: 15px;
}

.avatar {
    width: 108px;
    height: 108px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f172a, #1e293b 45%, #0d9488);
    color: #ffffff;
    font-size: 42px;
    font-weight: 800;
    border: 5px solid #ffffff;
    box-shadow: 0 10px 30px rgba(13, 148, 136, 0.2), 0 0 0 4px rgba(13, 148, 136, 0.12);
    transition: transform 0.3s ease;
}

.avatar:hover {
    transform: scale(1.05);
}

.status-dot {
    position: absolute;
    right: 4px;
    bottom: 6px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #22c55e;
    border: 3px solid #ffffff;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
}

.profile-summary h3 {
    margin: 0 0 7px;
    color: #0f172a;
    font-size: 21px;
    font-weight: 800;
}

.student-id {
    display: block;
    margin-bottom: 12px;
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 50px;
    background: linear-gradient(135deg, #ecfdf5, #ccfbf1);
    color: #047857;
    border: 1px solid #bbf7d0;
    font-size: 12px;
    font-weight: 700;
}

.profile-info {
    margin-top: 20px;
}

.profile-info-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    color: #0f172a;
    font-size: 14px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item {
    display: grid;
    grid-template-columns: 105px minmax(0, 1fr);
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item .label {
    color: #64748b;
    font-weight: 600;
}

.info-item .value {
    color: #0f172a;
    font-weight: 700;
    text-align: right;
    overflow-wrap: anywhere;
}

.edit-profile {
    background: #ffffff;
    border: 1px solid #e8edf3;
    border-radius: 22px;
    padding: 30px;
    box-shadow: 0 6px 25px rgba(15, 23, 42, 0.045);
}

.form-heading {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 24px;
}

.form-heading-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: linear-gradient(135deg, #ecfdf5, #ccfbf1);
    font-size: 20px;
}

.form-heading h3 {
    margin: 0 0 4px;
    color: #0f172a;
    font-size: 20px;
    font-weight: 800;
}

.form-heading p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 7px;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
}

.required {
    color: #ef4444;
}

.form-control {
    width: 100%;
    box-sizing: border-box;
    padding: 13px 15px;
    border: 1.5px solid #dbe3ec;
    border-radius: 12px;
    background: #f8fafc;
    color: #0f172a;
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
}

.form-control:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}

.form-control:focus {
    outline: none;
    border-color: #0d9488;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1);
}

.form-control::placeholder {
    color: #94a3b8;
}

.form-control[readonly] {
    background: #f1f5f9;
    color: #64748b;
    cursor: not-allowed;
}

.form-help {
    margin-top: 6px;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1.4;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.password-section {
    margin-top: 26px;
    padding-top: 24px;
    border-top: 1px solid #e8edf3;
}

.password-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}

.password-icon {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    font-size: 18px;
}

.password-header h4 {
    margin: 0;
    color: #0f172a;
    font-size: 16px;
    font-weight: 800;
}

.optional {
    margin-left: 5px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 500;
}

.password-note {
    margin: -8px 0 18px 48px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 26px;
    padding-top: 22px;
    border-top: 1px solid #eef2f7;
}

.btn-premium {
    min-height: 44px;
    padding: 11px 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid transparent;
    border-radius: 12px;
    color: #ffffff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
}

.btn-premium:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.2);
}

.btn-primary:hover {
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.28);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 8px 22px rgba(245, 158, 11, 0.2);
}

.btn-warning:hover {
    box-shadow: 0 12px 30px rgba(245, 158, 11, 0.28);
}

.btn-premium:focus-visible {
    outline: 3px solid rgba(45, 212, 191, 0.35);
    outline-offset: 3px;
}

.statistics-section {
    position: relative;
    z-index: 1;
    margin-top: 32px;
}

.statistics-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 17px;
}

.statistics-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: linear-gradient(135deg, #ecfdf5, #ccfbf1);
    font-size: 20px;
}

.statistics-header h3 {
    margin: 0 0 3px;
    color: #0f172a;
    font-size: 19px;
    font-weight: 800;
}

.statistics-header p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 17px;
}

.stat-card {
    position: relative;
    overflow: hidden;
    padding: 22px 20px;
    background: #ffffff;
    border: 1px solid #e8edf3;
    border-radius: 18px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.045);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.stat-card:nth-child(1)::before {
    background: linear-gradient(90deg, #0d9488, #2dd4bf);
}

.stat-card:nth-child(2)::before {
    background: linear-gradient(90deg, #22c55e, #4ade80);
}

.stat-card:nth-child(3)::before {
    background: linear-gradient(90deg, #6366f1, #818cf8);
}

.stat-card:nth-child(4)::before {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 35px rgba(15, 23, 42, 0.09);
}

.stat-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 15px;
}

.stat-card-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: #f8fafc;
    font-size: 19px;
}

.stat-card:nth-child(1) .stat-card-icon {
    background: #ecfdf5;
}

.stat-card:nth-child(2) .stat-card-icon {
    background: #f0fdf4;
}

.stat-card:nth-child(3) .stat-card-icon {
    background: #eef2ff;
}

.stat-card:nth-child(4) .stat-card-icon {
    background: #fffbeb;
}

.stat-card-label {
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
}

.stat-number {
    margin: 0;
    color: #0f172a;
    font-size: 30px;
    line-height: 1;
    font-weight: 800;
    letter-spacing: -1px;
}

.stat-description {
    margin-top: 7px;
    color: #94a3b8;
    font-size: 11px;
}

@media (max-width: 1100px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .profile-summary {
        display: grid;
        grid-template-columns: 230px minmax(0, 1fr);
        gap: 25px;
        text-align: left;
    }
    .profile-identity {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding-right: 25px;
        padding-bottom: 0;
        border-right: 1px solid #eef2f7;
        border-bottom: none;
    }
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .container {
        padding: 12px 15px 28px;
    }
    .profile-content {
        padding: 18px;
        border-radius: 22px;
    }
    .profile-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 22px;
        border-radius: 18px;
    }
    .profile-header h2 {
        font-size: 23px;
    }
    .profile-grid {
        gap: 18px;
    }
    .profile-summary {
        display: block;
        padding: 24px 20px;
        text-align: center;
    }
    .profile-identity {
        display: block;
        padding-right: 0;
        padding-bottom: 20px;
        border-right: none;
        border-bottom: 1px solid #eef2f7;
    }
    .profile-info {
        text-align: left;
    }
    .edit-profile {
        padding: 22px 20px;
    }
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn-premium {
        width: 100%;
    }
    .dashboard-cards {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .stat-card {
        padding: 18px 15px;
    }
    .stat-number {
        font-size: 26px;
    }
}

@media (max-width: 480px) {
    .profile-header h2 {
        font-size: 21px;
    }
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    .info-item {
        grid-template-columns: 1fr;
        gap: 3px;
    }
    .info-item .value {
        text-align: left;
    }
    .avatar {
        width: 94px;
        height: 94px;
        font-size: 36px;
    }
    .password-note {
        margin-left: 0;
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

    <div class="profile-content">

        <header class="profile-header">
            <div class="profile-header-content">
                <h2>👤 My Profile</h2>
                <p>Manage your personal information and account security.</p>
                <span class="school-badge">🎓 Mabaso Secondary School</span>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <span>✅</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <span>❌</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-grid">

            <section class="profile-summary">
                <div class="profile-identity">
                    <div class="avatar-wrapper">
                        <div class="avatar" aria-label="Student avatar">
                            <?php echo strtoupper(substr(trim($student['full_name']), 0, 1)); ?>
                        </div>
                        <span class="status-dot" title="Active student" aria-label="Active student"></span>
                    </div>
                    <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
                    <span class="student-id">Student ID: <?php echo htmlspecialchars($student['student_id']); ?></span>
                    <span class="role-badge">🎓 Student</span>
                </div>

                <div class="profile-info">
                    <div class="profile-info-title"><span>📋</span> Account Information</div>
                    <div class="info-item">
                        <span class="label">Student ID</span>
                        <span class="value"><?php echo htmlspecialchars($student['student_id']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Class</span>
                        <span class="value"><?php echo htmlspecialchars($student['class_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Teacher</span>
                        <span class="value"><?php echo htmlspecialchars($teacher_name); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Username</span>
                        <span class="value"><?php echo htmlspecialchars($student['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Joined</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                    </div>
                </div>
            </section>

            <section class="edit-profile">
                <div class="form-heading">
                    <div class="form-heading-icon">✏️</div>
                    <div>
                        <h3>Edit Profile</h3>
                        <p>Keep your personal information up to date.</p>
                    </div>
                </div>

                <form method="POST" id="profileForm" onsubmit="return validateForm('profileForm')" novalidate>

                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" placeholder="Enter your full name" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" placeholder="Enter your email address" autocomplete="email" required>
                    </div>

                    <div class="password-section">
                        <div class="password-header">
                            <div class="password-icon">🔐</div>
                            <h4>Change Password <span class="optional">(Optional)</span></h4>
                        </div>

                        <p class="password-note">Only complete these fields if you want to change your current password.</p>

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter your current password" autocomplete="current-password">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Minimum 6 characters" autocomplete="new-password" minlength="6">
                                <div class="form-help">Use at least 6 characters.</div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter your new password" autocomplete="new-password" minlength="6">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-premium btn-primary">💾 <span>Update Profile</span></button>
                        <button type="reset" class="btn-premium btn-warning">↺ <span>Reset Changes</span></button>
                    </div>

                </form>
            </section>

        </div>

        <section class="statistics-section">
            <div class="statistics-header">
                <div class="statistics-icon">📊</div>
                <div>
                    <h3>Your Academic Overview</h3>
                    <p>A quick look at your attendance and academic progress.</p>
                </div>
            </div>

            <div class="dashboard-cards">

                <article class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-card-icon">✅</div>
                        <span class="stat-card-label">Attendance</span>
                    </div>
                    <p class="stat-number"><?php echo $attendance_rate; ?>%</p>
                    <div class="stat-description">Overall attendance rate</div>
                </article>

                <article class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-card-icon">📅</div>
                        <span class="stat-card-label">Present</span>
                    </div>
                    <p class="stat-number"><?php echo $present_days; ?></p>
                    <div class="stat-description">Days present</div>
                </article>

                <article class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-card-icon">📊</div>
                        <span class="stat-card-label">Results</span>
                    </div>
                    <p class="stat-number"><?php echo $total_results; ?></p>
                    <div class="stat-description">Recorded academic results</div>
                </article>

                <article class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-card-icon">📈</div>
                        <span class="stat-card-label">Average</span>
                    </div>
                    <p class="stat-number"><?php echo $avg_score; ?>%</p>
                    <div class="stat-description">Overall average score</div>
                </article>

            </div>
        </section>

    </div>

</div>

<?php include '../../includes/footer.php'; ?>