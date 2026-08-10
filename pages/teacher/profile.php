<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get teacher data
$stmt = $pdo->prepare("
    SELECT u.*, t.department, t.phone 
    FROM users u 
    JOIN teachers t ON u.id = t.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    redirect('../../logout.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
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
        
        // Update teacher info
        $stmt = $pdo->prepare("UPDATE teachers SET department = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([$department, $phone, $user_id]);
        
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
        
        // Refresh teacher data
        $stmt = $pdo->prepare("
            SELECT u.*, t.department, t.phone 
            FROM users u 
            JOIN teachers t ON u.id = t.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ?");
$stmt->execute([$user_id]);
$total_students = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE teacher_id = ?");
$stmt->execute([$user_id]);
$total_lessons = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM results WHERE teacher_id = ?");
$stmt->execute([$user_id]);
$total_results = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE teacher_id = ? AND date = CURDATE()");
$stmt->execute([$user_id]);
$today_attendance = $stmt->fetchColumn();

// Get join date safely
$join_date = isset($teacher['created_at']) ? $teacher['created_at'] : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
:root {
    --primary: #0f172a;
    --primary-light: #1e293b;
    --teal: #0d9488;
    --teal-dark: #0f766e;
    --green: #059669;
    --success: #22c55e;
    --warning: #f59e0b;
    --danger: #ef4444;
    --text: #0f172a;
    --muted: #64748b;
    --muted-light: #94a3b8;
    --border: #e2e8f0;
    --border-light: #f1f5f9;
    --surface: #ffffff;
    --surface-soft: #f8fafc;
    --radius-sm: 12px;
    --radius-md: 16px;
    --radius-lg: 22px;
    --radius-xl: 28px;
    --shadow-sm: 0 4px 16px rgba(15, 23, 42, 0.04);
    --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.07);
    --shadow-lg: 0 20px 55px rgba(15, 23, 42, 0.09);
}

* {
    box-sizing: border-box;
}

body {
    background:
        radial-gradient(circle at 10% 10%, rgba(13, 148, 136, 0.08), transparent 28%),
        radial-gradient(circle at 90% 90%, rgba(15, 23, 42, 0.06), transparent 30%),
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
    min-height: 100vh;
    color: var(--text);
}

.container {
    width: 100%;
    max-width: 1320px;
    margin: 0 auto;
    padding: 28px 30px 50px;
}

.content {
    position: relative;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: var(--radius-xl);
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
    margin-bottom: 30px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
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
    flex-shrink: 0;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--primary), var(--teal));
    color: white;
    font-size: 24px;
    box-shadow: 0 10px 25px rgba(13, 148, 136, 0.20);
}

.page-header h2 {
    margin: 0;
    color: var(--primary);
    font-size: 28px;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: -0.7px;
}

.header-subtitle {
    margin-top: 6px;
    color: var(--muted);
    font-size: 13px;
}

.header-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    padding: 8px 16px;
    border-radius: 100px;
    background: linear-gradient(135deg, var(--teal), var(--green));
    color: white;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 7px 20px rgba(13, 148, 136, 0.18);
}

.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 18px;
    margin-bottom: 22px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 600;
    border: 1px solid transparent;
    animation: slideDown 0.35s ease;
}

.alert-icon {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.7);
}

.alert-success {
    color: #065f46;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border-color: #a7f3d0;
}

.alert-error {
    color: #991b1b;
    background: linear-gradient(135deg, #fff1f2, #fef2f2);
    border-color: #fecaca;
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

.profile-grid {
    display: grid;
    grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.7fr);
    gap: 25px;
    align-items: start;
}

.profile-summary {
    position: relative;
    overflow: hidden;
    padding: 32px 28px;
    text-align: center;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid var(--border);
    border-radius: 24px;
    box-shadow: var(--shadow-md);
}

.profile-summary::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--primary), var(--teal), var(--green));
}

.profile-summary::after {
    content: "";
    position: absolute;
    width: 180px;
    height: 180px;
    top: -100px;
    right: -80px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(13, 148, 136, 0.08), transparent 70%);
    pointer-events: none;
}

.avatar-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 14px;
}

.avatar {
    width: 108px;
    height: 108px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: white;
    font-size: 42px;
    font-weight: 800;
    background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.20), transparent 30%), linear-gradient(135deg, var(--primary), var(--teal));
    border: 5px solid white;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12), 0 15px 35px rgba(15, 23, 42, 0.15);
    transition: all 0.35s ease;
}

.avatar:hover {
    transform: translateY(-4px) scale(1.04);
    box-shadow: 0 0 0 5px rgba(13, 148, 136, 0.12), 0 20px 40px rgba(15, 23, 42, 0.18);
}

.status-dot {
    position: absolute;
    right: 5px;
    bottom: 7px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--success);
    border: 4px solid white;
    box-shadow: 0 3px 10px rgba(34, 197, 94, 0.30);
}

.profile-summary h3 {
    margin: 12px 0 7px;
    color: var(--primary);
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -0.3px;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 15px;
    border-radius: 100px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    font-size: 12px;
    font-weight: 700;
}

.info-list {
    margin-top: 25px;
    padding-top: 8px;
    text-align: left;
    border-top: 1px solid var(--border);
}

.info-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 13px 2px;
    border-bottom: 1px solid var(--border-light);
    font-size: 13px;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-weight: 500;
}

.info-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #f1f5f9;
    font-size: 13px;
}

.info-value {
    color: var(--primary);
    font-weight: 700;
    text-align: right;
    word-break: break-word;
}

.info-value.highlight {
    color: var(--teal);
}

.school-identity {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 25px;
    padding: 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f8fafc, #ecfdf5);
    border: 1px solid #d1fae5;
    text-align: left;
}

.school-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 11px;
    background: white;
    box-shadow: var(--shadow-sm);
    font-size: 18px;
}

.school-name {
    color: var(--primary);
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.2px;
}

.school-label {
    margin-top: 3px;
    color: var(--muted);
    font-size: 11px;
}

.edit-profile {
    padding: 32px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 24px;
    box-shadow: var(--shadow-md);
}

.form-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 25px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border);
    color: var(--primary);
    font-size: 21px;
    font-weight: 800;
}

.title-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #ecfdf5;
    font-size: 20px;
}

.form-group {
    margin: 18px 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.required {
    color: var(--danger);
    margin-left: 2px;
}

.input-wrapper {
    position: relative;
}

.form-group input {
    width: 100%;
    min-height: 48px;
    padding: 12px 16px;
    border: 1.5px solid #dbe3ec;
    border-radius: 13px;
    background: var(--surface-soft);
    color: var(--primary);
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
}

.form-group input:hover {
    border-color: #cbd5e1;
    background: white;
}

.form-group input:focus {
    outline: none;
    border-color: var(--teal);
    background: white;
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.09), 0 5px 15px rgba(13, 148, 136, 0.05);
}

.form-group input::placeholder {
    color: var(--muted-light);
}

.form-group input:disabled {
    background: #f1f5f9;
    color: var(--muted);
    cursor: not-allowed;
}

.form-help {
    display: block;
    margin-top: 6px;
    color: var(--muted-light);
    font-size: 11px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.divider {
    margin: 30px 0 25px;
    border: none;
    border-top: 1px solid var(--border);
}

.password-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    padding: 13px 15px;
    border-radius: 13px;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    color: var(--primary);
    font-size: 15px;
    font-weight: 700;
}

.lock-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 9px;
    background: #fef3c7;
    font-size: 16px;
}

.optional-text {
    color: var(--muted-light);
    font-size: 12px;
    font-weight: 400;
}

.password-toggle {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 9px;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.password-toggle:hover {
    background: #f1f5f9;
    color: var(--teal);
}

.has-toggle input {
    padding-right: 52px;
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
    padding-top: 22px;
    border-top: 1px solid var(--border-light);
}

.btn-premium {
    min-height: 46px;
    padding: 12px 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    border-radius: 13px;
    cursor: pointer;
    color: white;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-premium:hover {
    transform: translateY(-2px);
}

.btn-premium:active {
    transform: translateY(0);
}

.btn-premium:focus-visible {
    outline: 3px solid rgba(13, 148, 136, 0.25);
    outline-offset: 3px;
}

.btn-success {
    background: linear-gradient(135deg, var(--teal), var(--green));
    box-shadow: 0 8px 22px rgba(13, 148, 136, 0.20);
}

.btn-success:hover {
    box-shadow: 0 12px 30px rgba(13, 148, 136, 0.30);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 8px 22px rgba(245, 158, 11, 0.20);
}

.btn-warning:hover {
    box-shadow: 0 12px 30px rgba(245, 158, 11, 0.28);
}

.stats-section {
    margin-top: 45px;
    padding-top: 30px;
    border-top: 1px solid var(--border);
}

.stats-header {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 22px;
    color: var(--primary);
    font-size: 20px;
    font-weight: 800;
}

.stats-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #eff6ff;
    font-size: 20px;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}

.card {
    position: relative;
    overflow: hidden;
    padding: 24px 20px;
    text-align: center;
    background: white;
    border: 1px solid var(--border);
    border-radius: 20px;
    box-shadow: var(--shadow-sm);
    transition: all 0.35s ease;
}

.card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--teal));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    border-color: #cbd5e1;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.09);
}

.card:hover::before {
    opacity: 1;
}

.card-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    border-radius: 15px;
    background: #f1f5f9;
    font-size: 25px;
}

.card:nth-child(1) .card-icon {
    background: #eff6ff;
}

.card:nth-child(2) .card-icon {
    background: #ecfdf5;
}

.card:nth-child(3) .card-icon {
    background: #fefce8;
}

.card:nth-child(4) .card-icon {
    background: #f0fdf4;
}

.card h3 {
    margin: 0 0 5px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
}

.card .number {
    margin-top: 4px;
    color: var(--primary);
    font-size: 32px;
    font-weight: 800;
    letter-spacing: -1px;
}

.card .label {
    margin-top: 5px;
    color: var(--muted-light);
    font-size: 12px;
}

@media (max-width: 1100px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .profile-summary {
        width: 100%;
        max-width: 620px;
        margin: 0 auto;
    }
    .dashboard-cards {
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
        width: 44px;
        height: 44px;
        font-size: 20px;
    }
    .edit-profile {
        padding: 22px;
    }
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .dashboard-cards {
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn-premium {
        width: 100%;
    }
    .profile-summary .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    .profile-summary .info-value {
        text-align: left;
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
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    .profile-summary,
    .edit-profile {
        padding: 20px;
    }
    .school-identity {
        align-items: flex-start;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
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

        <header class="page-header">
            <div class="page-header-left">
                <div class="page-header-icon" aria-hidden="true">👤</div>
                <div>
                    <h2>My Profile</h2>
                    <div class="header-subtitle">Manage your Mabaso Secondary School account</div>
                </div>
            </div>
            <span class="header-badge">👨‍🏫 Teacher</span>
        </header>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <span class="alert-icon" aria-hidden="true">✓</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <span class="alert-icon" aria-hidden="true">!</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-grid">

            <aside class="profile-summary">
                <div class="avatar-wrapper">
                    <div class="avatar" aria-label="Profile avatar">
                        <?php echo strtoupper(substr(trim($teacher['full_name']), 0, 1)); ?>
                    </div>
                    <span class="status-dot" title="Active" aria-label="Active"></span>
                </div>
                <h3><?php echo htmlspecialchars($teacher['full_name']); ?></h3>
                <span class="role-badge">👨‍🏫 Teacher</span>

                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label"><span class="info-icon">👤</span> Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($teacher['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><span class="info-icon">📧</span> Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($teacher['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><span class="info-icon">🏛️</span> Department</span>
                        <span class="info-value highlight"><?php echo htmlspecialchars($teacher['department'] ?? 'Not set'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><span class="info-icon">📞</span> Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($teacher['phone'] ?? 'Not set'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><span class="info-icon">📅</span> Joined</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($join_date)); ?></span>
                    </div>
                </div>

                <div class="school-identity">
                    <div class="school-icon">🏫</div>
                    <div>
                        <div class="school-name">Mabaso Secondary School</div>
                        <div class="school-label">Teacher Management Portal</div>
                    </div>
                </div>
            </aside>

            <section class="edit-profile">
                <div class="form-title">
                    <span class="title-icon">✏️</span>
                    <span>Edit Profile</span>
                </div>

                <form method="POST" id="profileForm" onsubmit="return validateForm('profileForm')" novalidate>

                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required autocomplete="name" placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required autocomplete="email" placeholder="Enter your email address">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($teacher['department'] ?? ''); ?>" autocomplete="organization-title" placeholder="e.g. Mathematics">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>" autocomplete="tel" placeholder="e.g. +27 00 000 0000">
                        </div>
                    </div>

                    <hr class="divider">

                    <div class="password-section-title">
                        <span class="lock-icon">🔑</span>
                        <span>Change Password <span class="optional-text">(Optional)</span></span>
                    </div>

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-wrapper has-toggle">
                            <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)" aria-label="Show current password" title="Show password">👁️</button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="input-wrapper has-toggle">
                                <input type="password" id="new_password" name="new_password" minlength="6" placeholder="Minimum 6 characters" autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)" aria-label="Show new password" title="Show password">👁️</button>
                            </div>
                            <small class="form-help">Use at least 6 characters.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-wrapper has-toggle">
                                <input type="password" id="confirm_password" name="confirm_password" minlength="6" placeholder="Confirm your new password" autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)" aria-label="Show confirmation password" title="Show password">👁️</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-premium btn-success">💾 <span>Update Profile</span></button>
                        <button type="reset" class="btn-premium btn-warning">↺ <span>Reset</span></button>
                    </div>

                </form>
            </section>

        </div>

        <section class="stats-section">
            <div class="stats-header">
                <span class="stats-icon">📊</span>
                <span>Your Statistics</span>
            </div>

            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-icon">👨‍🎓</div>
                    <h3>Students</h3>
                    <div class="number"><?php echo (int)$total_students; ?></div>
                    <div class="label">Total Students</div>
                </div>
                <div class="card">
                    <div class="card-icon">📚</div>
                    <h3>Lessons</h3>
                    <div class="number"><?php echo (int)$total_lessons; ?></div>
                    <div class="label">Total Lessons</div>
                </div>
                <div class="card">
                    <div class="card-icon">📊</div>
                    <h3>Results</h3>
                    <div class="number"><?php echo (int)$total_results; ?></div>
                    <div class="label">Total Results</div>
                </div>
                <div class="card">
                    <div class="card-icon">✅</div>
                    <h3>Attendance</h3>
                    <div class="number"><?php echo (int)$today_attendance; ?></div>
                    <div class="label">Today's Attendance</div>
                </div>
            </div>
        </section>

    </div>
</div>

<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        if (!input) return;
        if (input.type === "password") {
            input.type = "text";
            button.textContent = "🙈";
            button.setAttribute("aria-label", "Hide password");
            button.setAttribute("title", "Hide password");
        } else {
            input.type = "password";
            button.textContent = "👁️";
            button.setAttribute("aria-label", "Show password");
            button.setAttribute("title", "Show password");
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("profileForm");
        if (!form) return;

        form.addEventListener("submit", function(event) {
            const newPassword = document.getElementById("new_password");
            const confirmPassword = document.getElementById("confirm_password");
            const currentPassword = document.getElementById("current_password");

            if (newPassword.value.trim() !== "" || confirmPassword.value.trim() !== "") {
                if (currentPassword.value.trim() === "") {
                    event.preventDefault();
                    alert("Please enter your current password before changing it.");
                    currentPassword.focus();
                    return false;
                }
                if (newPassword.value.length < 6) {
                    event.preventDefault();
                    alert("Your new password must contain at least 6 characters.");
                    newPassword.focus();
                    return false;
                }
                if (newPassword.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert("The new password and confirmation password do not match.");
                    confirmPassword.focus();
                    return false;
                }
            }
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>