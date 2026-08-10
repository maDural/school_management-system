<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];

// Get student info with class and teacher details
// Added u.created_at as user_created_at to fix the date issue
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, u.created_at as user_created_at,
           t.full_name as teacher_name
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN users t ON s.teacher_id = t.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('../../logout.php');
}

// Get join date safely - FIXED: This handles the missing created_at column
$join_date = isset($student['user_created_at']) ? $student['user_created_at'] : (isset($student['created_at']) ? $student['created_at'] : date('Y-m-d'));

// Get statistics - filter by student's class
$stats = [
    'attendance' => $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'present'"),
    'absent' => $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'absent'"),
    'results' => $pdo->prepare("SELECT COUNT(*) FROM results WHERE student_id = ?"),
    'lessons' => $pdo->prepare("
        SELECT COUNT(*) FROM lessons 
        WHERE teacher_id = ? 
        AND (class_name = ? OR class_name IS NULL OR class_name = '')
    ")
];

foreach ($stats as $key => $stmt) {
    if ($key == 'lessons') {
        $stmt->execute([$student['teacher_id'], $student['class_name']]);
    } else {
        $stmt->execute([$student['id']]);
    }
    $stats[$key] = $stmt->fetchColumn();
}

$attendance_rate = $stats['attendance'] + $stats['absent'] > 0 ? 
    round(($stats['attendance'] / ($stats['attendance'] + $stats['absent'])) * 100, 1) : 0;

// Get recent results
$stmt = $pdo->prepare("
    SELECT * FROM results 
    WHERE student_id = ? 
    ORDER BY exam_date DESC 
    LIMIT 3
");
$stmt->execute([$student['id']]);
$recent_results = $stmt->fetchAll();

// Get upcoming lessons for this student's class
$stmt = $pdo->prepare("
    SELECT * FROM lessons 
    WHERE teacher_id = ? 
    AND (class_name = ? OR class_name IS NULL OR class_name = '')
    AND lesson_date >= CURDATE()
    ORDER BY lesson_date ASC 
    LIMIT 3
");
$stmt->execute([$student['teacher_id'], $student['class_name']]);
$upcoming_lessons = $stmt->fetchAll();

// Get class attendance summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM attendance 
    WHERE student_id = ?
");
$stmt->execute([$student['id']]);
$attendance_summary = $stmt->fetch();

// Get available quizzes for this student
$stmt = $pdo->prepare("
    SELECT q.*, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ?) as attempt_count
    FROM quizzes q
    WHERE q.class_name = ? 
    AND q.is_active = 1
    AND q.start_time <= NOW()
    AND q.end_time >= NOW()
    ORDER BY q.end_time ASC
");
$stmt->execute([$student['id'], $student['class_name']]);
$available_quizzes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduManager</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
:root {
    --primary: #0f172a;
    --primary-light: #1e293b;
    --teal: #0d9488;
    --teal-dark: #0f766e;
    --teal-light: #14b8a6;
    --blue: #2563eb;
    --purple: #7c3aed;
    --gold: #f59e0b;
    --danger: #ef4444;
    --text: #0f172a;
    --text-soft: #475569;
    --text-muted: #94a3b8;
    --surface: #ffffff;
    --surface-soft: #f8fafc;
    --border: #e2e8f0;
    --radius-sm: 12px;
    --radius-md: 18px;
    --radius-lg: 24px;
    --radius-xl: 30px;
    --shadow-sm: 0 4px 15px rgba(15, 23, 42, 0.05);
    --shadow-md: 0 12px 35px rgba(15, 23, 42, 0.08);
    --shadow-lg: 0 20px 55px rgba(15, 23, 42, 0.12);
    --transition: all 0.35s cubic-bezier(.165, .84, .44, 1);
}

body {
    background:
        radial-gradient(circle at 10% 10%, rgba(13, 148, 136, 0.08), transparent 30%),
        radial-gradient(circle at 90% 90%, rgba(37, 99, 235, 0.06), transparent 30%),
        linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    min-height: 100vh;
    color: var(--text);
}

.container {
    width: 100%;
    max-width: 1380px;
    margin: 0 auto;
    padding: 30px;
}

.content {
    position: relative;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: var(--radius-xl);
    padding: 38px;
    box-shadow: var(--shadow-lg);
}

.content::before,
.content::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
}

.content::before {
    width: 520px;
    height: 520px;
    top: -280px;
    right: -180px;
    background: radial-gradient(circle, rgba(20, 184, 166, 0.07), transparent 70%);
}

.content::after {
    width: 450px;
    height: 450px;
    bottom: -250px;
    left: -180px;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.05), transparent 70%);
}

.content > * {
    position: relative;
    z-index: 1;
}

.welcome-section {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 85% 15%, rgba(45, 212, 191, 0.20), transparent 28%),
        linear-gradient(135deg, #020617 0%, #0f172a 45%, #134e4a 100%);
    border-radius: var(--radius-lg);
    padding: 44px 48px;
    margin-bottom: 28px;
    box-shadow: 0 25px 55px rgba(15, 23, 42, 0.20), inset 0 1px 0 rgba(255,255,255,0.08);
}

.welcome-section::before {
    content: "";
    position: absolute;
    width: 360px;
    height: 360px;
    right: -120px;
    top: -150px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(45, 212, 191, 0.16), transparent 68%);
    animation: floatLight 12s ease-in-out infinite;
}

.welcome-section::after {
    content: "";
    position: absolute;
    width: 280px;
    height: 280px;
    left: -120px;
    bottom: -180px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.10), transparent 68%);
    animation: floatLight 16s ease-in-out infinite reverse;
}

@keyframes floatLight {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    50% {
        transform: translate(20px, -15px) scale(1.06);
    }
}

.welcome-content {
    position: relative;
    z-index: 2;
}

.greeting-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 15px;
    background: rgba(45, 212, 191, 0.13);
    border: 1px solid rgba(45, 212, 191, 0.25);
    color: #5eead4;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    backdrop-filter: blur(10px);
    margin-bottom: 15px;
}

.welcome-section h1 {
    margin: 0 0 12px;
    color: #ffffff;
    font-size: clamp(30px, 4vw, 42px);
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: -1.2px;
    text-shadow: 0 4px 25px rgba(0,0,0,0.15);
}

.welcome-section .subtitle {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.info-item,
.badge-premium,
.class-badge-premium,
.teacher-badge-premium {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border-radius: 100px;
    white-space: nowrap;
}

.info-item {
    color: rgba(255,255,255,0.78);
    font-size: 13px;
}

.badge-premium {
    padding: 6px 13px;
    color: #5eead4;
    background: rgba(45, 212, 191, 0.12);
    border: 1px solid rgba(45, 212, 191, 0.18);
    font-size: 12px;
    font-weight: 700;
}

.class-badge-premium {
    padding: 6px 14px;
    color: #fcd34d;
    background: rgba(251, 191, 36, 0.12);
    border: 1px solid rgba(251, 191, 36, 0.18);
    font-size: 12px;
    font-weight: 700;
}

.teacher-badge-premium {
    padding: 6px 14px;
    color: #93c5fd;
    background: rgba(96, 165, 250, 0.12);
    border: 1px solid rgba(96, 165, 250, 0.18);
    font-size: 12px;
    font-weight: 600;
}

.class-info-card {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 22px 25px;
    margin-bottom: 25px;
    background: linear-gradient(135deg, rgba(248,250,252,0.95), rgba(241,245,249,0.95));
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}

.class-info-card > div {
    padding: 4px 15px;
}

.class-info-card > div:not(:last-child) {
    border-right: 1px solid var(--border);
}

.class-label,
.teacher-label {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 5px;
}

.class-value {
    color: var(--primary);
    font-size: 21px;
    font-weight: 800;
}

.teacher-value {
    color: var(--teal);
    font-size: 16px;
    font-weight: 700;
}

.quiz-banner {
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 22px 26px;
    margin-bottom: 30px;
    color: #fff;
    background:
        radial-gradient(circle at 85% 20%, rgba(255,255,255,0.18), transparent 25%),
        linear-gradient(135deg, #2563eb, #7c3aed);
    border-radius: var(--radius-md);
    box-shadow: 0 15px 35px rgba(79,70,229,0.20);
}

.quiz-banner::after {
    content: "✦";
    position: absolute;
    right: 25%;
    top: 10px;
    font-size: 90px;
    color: rgba(255,255,255,0.05);
    transform: rotate(15deg);
}

.quiz-title {
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 8px;
}

.quiz-info {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    font-size: 12px;
    opacity: 0.88;
}

.btn-quiz {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    color: #fff;
    background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.28);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    backdrop-filter: blur(10px);
    transition: var(--transition);
}

.btn-quiz:hover {
    background: rgba(255,255,255,0.24);
    transform: translateY(-2px);
    color: #fff;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin: 28px 0;
}

.card-premium {
    position: relative;
    overflow: hidden;
    background: rgba(255,255,255,0.96);
    border: 1px solid rgba(226,232,240,0.9);
    border-radius: var(--radius-md);
    padding: 25px 20px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.card-premium::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    opacity: 0;
    transition: opacity .3s ease;
}

.card-premium:hover {
    transform: translateY(-7px);
    box-shadow: 0 20px 45px rgba(15,23,42,0.10);
    border-color: rgba(13,148,136,0.15);
}

.card-premium:hover::before {
    opacity: 1;
}

.card-premium:nth-child(1)::before {
    background: linear-gradient(90deg, #0f172a, #334155);
}

.card-premium:nth-child(2)::before {
    background: linear-gradient(90deg, #0d9488, #14b8a6);
}

.card-premium:nth-child(3)::before {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.card-premium:nth-child(4)::before {
    background: linear-gradient(90deg, #3b82f6, #6366f1);
}

.card-icon-wrapper {
    width: 58px;
    height: 58px;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 17px;
    font-size: 26px;
    transition: var(--transition);
}

.card-premium:hover .card-icon-wrapper {
    transform: scale(1.08) rotate(-3deg);
}

.card-premium:nth-child(1) .card-icon-wrapper {
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
}

.card-premium:nth-child(2) .card-icon-wrapper {
    background: linear-gradient(135deg, #ccfbf1, #99f6e4);
}

.card-premium:nth-child(3) .card-icon-wrapper {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
}

.card-premium:nth-child(4) .card-icon-wrapper {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
}

.card-premium h3 {
    margin: 0 0 6px;
    color: var(--text-soft);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

.card-premium .number {
    margin: 3px 0;
    font-size: 36px;
    line-height: 1.2;
    font-weight: 850;
    letter-spacing: -1px;
}

.card-premium:nth-child(1) .number {
    color: #0f172a;
}

.card-premium:nth-child(2) .number {
    color: var(--teal);
}

.card-premium:nth-child(3) .number {
    color: var(--gold);
}

.card-premium:nth-child(4) .number {
    color: var(--blue);
    font-size: 27px;
}

.card-premium .label {
    color: var(--text-muted);
    font-size: 12px;
}

.quick-actions-premium {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin: 30px 0 40px;
}

.btn-premium {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 52px;
    padding: 14px 20px;
    border: 0;
    border-radius: 14px;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: var(--transition);
}

.btn-premium::before {
    content: "";
    position: absolute;
    width: 0;
    height: 0;
    left: 50%;
    top: 50%;
    border-radius: 50%;
    background: rgba(255,255,255,0.14);
    transform: translate(-50%, -50%);
    transition: width .5s ease, height .5s ease;
}

.btn-premium:hover::before {
    width: 400px;
    height: 400px;
}

.btn-premium span,
.btn-premium {
    isolation: isolate;
}

.btn-premium:hover {
    transform: translateY(-3px);
}

.btn-premium-primary {
    background: linear-gradient(135deg, #0f172a, #334155);
    box-shadow: 0 8px 25px rgba(15,23,42,.18);
}

.btn-premium-secondary {
    background: linear-gradient(135deg, #0d9488, #059669);
    box-shadow: 0 8px 25px rgba(13,148,136,.18);
}

.btn-premium-tertiary {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 8px 25px rgba(245,158,11,.18);
}

.btn-premium-quaternary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 8px 25px rgba(59,130,246,.18);
}

.btn-premium-primary:hover {
    box-shadow: 0 14px 35px rgba(15,23,42,.25);
}

.btn-premium-secondary:hover {
    box-shadow: 0 14px 35px rgba(13,148,136,.25);
}

.btn-premium-tertiary:hover {
    box-shadow: 0 14px 35px rgba(245,158,11,.25);
}

.btn-premium-quaternary:hover {
    box-shadow: 0 14px 35px rgba(59,130,246,.25);
}

.section-header-premium {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 40px 0 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}

.section-header-premium h3 {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: var(--primary);
    font-size: 19px;
    font-weight: 800;
    letter-spacing: -.3px;
}

.section-header-premium h3::first-letter {
    color: var(--teal);
}

.view-all-premium {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--teal);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: var(--transition);
}

.view-all-premium:hover {
    color: var(--teal-dark);
    gap: 9px;
}

.results-grid-premium {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.result-card-premium {
    position: relative;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 24px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.result-card-premium::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 4px;
    opacity: 0;
    transition: opacity .3s ease;
}

.result-card-premium:nth-child(1)::before {
    background: linear-gradient(90deg, #0f172a, #334155);
}

.result-card-premium:nth-child(2)::before {
    background: linear-gradient(90deg, #0d9488, #14b8a6);
}

.result-card-premium:nth-child(3)::before {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.result-card-premium:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
}

.result-card-premium:hover::before {
    opacity: 1;
}

.result-subject {
    color: var(--primary);
    font-size: 16px;
    font-weight: 800;
}

.result-exam {
    margin-top: 3px;
    margin-bottom: 12px;
    color: var(--text-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .6px;
}

.result-score {
    margin: 8px 0;
    color: var(--primary);
    font-size: 31px;
    font-weight: 850;
    letter-spacing: -.7px;
}

.result-grade-premium {
    display: inline-flex;
    padding: 5px 16px;
    margin: 5px 0 10px;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 800;
}

.result-grade-premium.A {
    color: #065f46;
    background: #d1fae5;
}

.result-grade-premium.B {
    color: #1e40af;
    background: #dbeafe;
}

.result-grade-premium.C {
    color: #92400e;
    background: #fef3c7;
}

.result-grade-premium.D {
    color: #9a3412;
    background: #fed7aa;
}

.result-grade-premium.F {
    color: #991b1b;
    background: #fecaca;
}

.result-date {
    color: var(--text-muted);
    font-size: 11px;
}

.lessons-list-premium {
    display: grid;
    gap: 12px;
}

.lesson-item-premium {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 22px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 15px;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.lesson-item-premium:hover {
    transform: translateX(5px);
    border-color: rgba(13,148,136,.20);
    box-shadow: var(--shadow-md);
}

.lesson-info {
    min-width: 0;
}

.lesson-title {
    color: var(--primary);
    font-size: 15px;
    font-weight: 750;
    margin-bottom: 6px;
}

.lesson-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    color: var(--text-muted);
    font-size: 11px;
}

.lesson-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.lesson-date-premium {
    flex-shrink: 0;
    padding: 8px 14px;
    color: #475569;
    background: #f1f5f9;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.empty-state-premium {
    padding: 50px 20px;
    text-align: center;
    background: linear-gradient(135deg, #fafafa, #f8fafc);
    border: 1px dashed #cbd5e1;
    border-radius: var(--radius-md);
}

.empty-icon {
    display: inline-flex;
    width: 70px;
    height: 70px;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    background: #f1f5f9;
    border-radius: 50%;
    font-size: 30px;
}

.empty-state-premium h4 {
    margin: 0 0 7px;
    color: var(--text-soft);
    font-size: 17px;
    font-weight: 750;
}

.empty-state-premium p {
    margin: 0;
    color: var(--text-muted);
    font-size: 13px;
}

.footer-premium {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 42px;
    padding-top: 22px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 11px;
}

.footer-premium span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.footer-premium strong {
    color: var(--text-soft);
    font-weight: 700;
}

@media (max-width: 1100px) {
    .container {
        padding: 20px;
    }
    .content {
        padding: 28px;
    }
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .quick-actions-premium {
        grid-template-columns: repeat(2, 1fr);
    }
    .results-grid-premium {
        grid-template-columns: repeat(2, 1fr);
    }
    .footer-premium {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    .content {
        padding: 15px;
        border-radius: 20px;
    }
    .welcome-section {
        padding: 30px 24px;
        border-radius: 20px;
    }
    .welcome-section h1 {
        font-size: 29px;
    }
    .welcome-section .subtitle {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }
    .class-info-card {
        grid-template-columns: 1fr;
        gap: 15px;
        padding: 18px;
    }
    .class-info-card > div {
        padding: 0;
    }
    .class-info-card > div:not(:last-child) {
        border-right: 0;
        border-bottom: 1px solid var(--border);
        padding-bottom: 15px;
    }
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    .card-premium {
        padding: 20px 12px;
    }
    .card-premium .number {
        font-size: 29px;
    }
    .card-premium:nth-child(4) .number {
        font-size: 23px;
    }
    .quick-actions-premium {
        grid-template-columns: 1fr;
    }
    .results-grid-premium {
        grid-template-columns: 1fr;
    }
    .quiz-banner {
        align-items: flex-start;
        flex-direction: column;
    }
    .lesson-item-premium {
        align-items: flex-start;
        flex-direction: column;
    }
    .lesson-date-premium {
        align-self: flex-start;
    }
    .footer-premium {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 7px;
    }
    .content {
        padding: 11px;
    }
    .welcome-section {
        padding: 25px 19px;
    }
    .welcome-section h1 {
        font-size: 25px;
    }
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    .card-premium .number {
        font-size: 34px;
    }
    .card-premium:nth-child(4) .number {
        font-size: 26px;
    }
    .section-header-premium {
        align-items: flex-start;
        gap: 10px;
        flex-direction: column;
    }
    .quiz-info {
        flex-direction: column;
        gap: 6px;
    }
    .lesson-meta {
        flex-direction: column;
        gap: 6px;
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

.btn-premium:focus-visible,
.btn-quiz:focus-visible,
.view-all-premium:focus-visible {
    outline: 3px solid rgba(20,184,166,.35);
    outline-offset: 3px;
}
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">

    <div class="content">

        <div class="welcome-section">
            <div class="welcome-content">
                <div class="greeting-badge">✦ Welcome Back</div>
                <h1>Hello, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                <div class="subtitle">
                    <span class="info-item">📅 <?php echo date('l, F d, Y'); ?></span>
                    <span class="badge-premium">✦ Student</span>
                    <span class="class-badge-premium">🏫 Class <?php echo htmlspecialchars($student['class_name']); ?></span>
                    <span class="teacher-badge-premium">👨‍🏫 <?php echo htmlspecialchars($student['teacher_name'] ?? 'Not Assigned'); ?></span>
                </div>
            </div>
        </div>

        <div class="class-info-card">
            <div>
                <div class="class-label">📚 Your Class</div>
                <div class="class-value"><?php echo htmlspecialchars($student['class_name']); ?></div>
            </div>
            <div>
                <div class="teacher-label">👨‍🏫 Your Teacher</div>
                <div class="teacher-value"><?php echo htmlspecialchars($student['teacher_name'] ?? 'Not Assigned'); ?></div>
            </div>
            <div>
                <div class="teacher-label">🆔 Your Student ID</div>
                <div class="teacher-value" style="font-size:14px; color:#475569;"><?php echo htmlspecialchars($student['student_id']); ?></div>
            </div>
        </div>

        <?php if (count($available_quizzes) > 0): ?>
            <?php $quiz = $available_quizzes[0]; ?>
            <div class="quiz-banner">
                <div>
                    <div class="quiz-title">📝 <?php echo htmlspecialchars($quiz['title']); ?></div>
                    <div class="quiz-info">
                        <span>📚 <?php echo htmlspecialchars($quiz['subject']); ?></span>
                        <span>⏱️ <?php echo $quiz['time_limit']; ?> minutes</span>
                        <span>📝 <?php echo $quiz['question_count']; ?> questions</span>
                        <span>📊 <?php echo $quiz['total_marks']; ?> marks</span>
                        <?php if ($quiz['attempt_count'] > 0): ?>
                            <span style="color:#fde68a;">✅ Attempted</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($quiz['attempt_count'] == 0): ?>
                    <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-quiz">📝 Take Quiz</a>
                <?php else: ?>
                    <span style="position:relative;z-index:2;background:rgba(255,255,255,.15);padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;">✅ Completed</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-cards">
            <div class="card-premium">
                <div class="card-icon-wrapper">📋</div>
                <h3>Attendance</h3>
                <div class="number"><?php echo $attendance_rate; ?>%</div>
                <div class="label">Attendance Rate</div>
            </div>
            <div class="card-premium">
                <div class="card-icon-wrapper">📊</div>
                <h3>Results</h3>
                <div class="number"><?php echo $stats['results']; ?></div>
                <div class="label">Total Results</div>
            </div>
            <div class="card-premium">
                <div class="card-icon-wrapper">📚</div>
                <h3>Lessons</h3>
                <div class="number"><?php echo $stats['lessons']; ?></div>
                <div class="label">Total Lessons</div>
            </div>
            <div class="card-premium">
                <div class="card-icon-wrapper">🏫</div>
                <h3>Class</h3>
                <div class="number" style="font-size:27px; color:#2563eb;"><?php echo htmlspecialchars($student['class_name']); ?></div>
                <div class="label">Your Class</div>
            </div>
        </div>

        <div class="quick-actions-premium">
            <a href="results.php" class="btn-premium btn-premium-primary">📊 <span>View Results</span></a>
            <a href="lessons.php" class="btn-premium btn-premium-secondary">📚 <span>View Lessons</span></a>
            <a href="assignments.php" class="btn-premium btn-info">📝 Assignments</a>
            <a href="attendance.php" class="btn-premium btn-premium-tertiary">📋 <span>My Attendance</span></a>
            <a href="profile.php" class="btn-premium btn-premium-quaternary">👤 <span>My Profile</span></a>
        </div>

        <div class="section-header-premium">
            <h3>✦ Recent Results</h3>
            <a href="results.php" class="view-all-premium">View All →</a>
        </div>

        <?php if (count($recent_results) > 0): ?>
            <div class="results-grid-premium">
                <?php foreach($recent_results as $result): ?>
                    <div class="result-card-premium">
                        <div class="result-subject"><?php echo htmlspecialchars($result['subject']); ?></div>
                        <div class="result-exam"><?php echo htmlspecialchars($result['exam_type']); ?></div>
                        <div class="result-score"><?php echo $result['score']; ?> / <?php echo $result['total_marks']; ?></div>
                        <div class="result-grade-premium <?php echo htmlspecialchars($result['grade']); ?>">Grade: <?php echo htmlspecialchars($result['grade']); ?></div>
                        <div class="result-date">📅 <?php echo date('M d, Y', strtotime($result['exam_date'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state-premium">
                <div class="empty-icon">📊</div>
                <h4>No Results Yet</h4>
                <p>Your results will appear here once your teacher adds them.</p>
            </div>
        <?php endif; ?>

        <div class="section-header-premium">
            <h3>✦ Upcoming Lessons</h3>
            <a href="lessons.php" class="view-all-premium">View All →</a>
        </div>

        <?php if (count($upcoming_lessons) > 0): ?>
            <div class="lessons-list-premium">
                <?php foreach($upcoming_lessons as $lesson): ?>
                    <div class="lesson-item-premium">
                        <div class="lesson-info">
                            <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                            <div class="lesson-meta">
                                <span>📚 <?php echo htmlspecialchars($lesson['subject']); ?></span>
                                <?php if (!empty($lesson['class_name'])): ?>
                                    <span>🏫 <?php echo htmlspecialchars($lesson['class_name']); ?></span>
                                <?php else: ?>
                                    <span>📚 All Classes</span>
                                <?php endif; ?>
                                <span>🕒 <?php echo date('h:i A', strtotime($lesson['lesson_date'] ?? 'now')); ?></span>
                            </div>
                        </div>
                        <div class="lesson-date-premium">📅 <?php echo date('M d, Y', strtotime($lesson['lesson_date'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state-premium">
                <div class="empty-icon">📚</div>
                <h4>No Upcoming Lessons</h4>
                <p>Check back later for new lessons from your teacher.</p>
            </div>
        <?php endif; ?>

        <div class="footer-premium">
            <span>🆔 Student ID: <strong><?php echo htmlspecialchars($student['student_id']); ?></strong></span>
            <span>📧 Email: <strong><?php echo htmlspecialchars($student['email']); ?></strong></span>
            <span>🏫 Class: <strong><?php echo htmlspecialchars($student['class_name']); ?></strong></span>
            <span>📅 Member since: <strong><?php echo date('M d, Y', strtotime($join_date)); ?></strong></span>
        </div>

    </div>

</div>

<?php include '../../includes/footer.php'; ?>