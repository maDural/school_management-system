<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$teacher_id = $_SESSION['user_id'];

// Get quiz details
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$quiz_id, $teacher_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    redirect('manage_quizzes.php');
}

// Get all attempts for this quiz with student details
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name, s.student_id
    FROM quiz_attempts a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE a.quiz_id = ?
    ORDER BY a.percentage DESC
");
$stmt->execute([$quiz_id]);
$attempts = $stmt->fetchAll();

// Calculate statistics
$total_students = count($attempts);
$avg_score = 0;
$pass_count = 0;
$fail_count = 0;

foreach ($attempts as $attempt) {
    $avg_score += $attempt['percentage'];
    if ($attempt['percentage'] >= 50) {
        $pass_count++;
    } else {
        $fail_count++;
    }
}
$avg_score = $total_students > 0 ? round($avg_score / $total_students, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
    background:
        radial-gradient(circle at 10% 10%, rgba(13, 148, 136, 0.08), transparent 28%),
        radial-gradient(circle at 90% 90%, rgba(15, 23, 42, 0.06), transparent 30%),
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
    min-height: 100vh;
    color: #1e293b;
}

.container {
    max-width: 1380px;
    padding: 28px 30px 40px;
}

.content {
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 28px;
    padding: 35px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.07), 0 2px 10px rgba(15, 23, 42, 0.03);
    position: relative;
    overflow: hidden;
}

.content::before {
    content: "";
    position: absolute;
    top: -180px;
    right: -120px;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(13, 148, 136, 0.08) 0%, rgba(13, 148, 136, 0.02) 45%, transparent 72%);
    border-radius: 50%;
    pointer-events: none;
}

.content::after {
    content: "";
    position: absolute;
    bottom: -220px;
    left: -140px;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(15, 23, 42, 0.05) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.header-section {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
    padding: 24px 26px;
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.98) 55%, rgba(13, 148, 136, 0.96));
    border-radius: 22px;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.header-section::before {
    content: "";
    position: absolute;
    width: 240px;
    height: 240px;
    right: -80px;
    top: -130px;
    border-radius: 50%;
    background: rgba(45, 212, 191, 0.13);
    pointer-events: none;
}

.header-section > div {
    position: relative;
    z-index: 1;
}

.header-section h2 {
    margin: 0;
    font-size: 28px;
    line-height: 1.2;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.6px;
}

.header-section p {
    color: rgba(255, 255, 255, 0.72) !important;
    margin: 9px 0 0 !important;
    font-size: 13px;
    font-weight: 500;
}

.btn-premium {
    position: relative;
    z-index: 2;
    padding: 12px 20px;
    min-height: 44px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 13px;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #ffffff;
    white-space: nowrap;
}

.btn-premium:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: rgba(255, 255, 255, 0.10);
    border-color: rgba(255, 255, 255, 0.16);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.btn-primary:hover {
    background: rgba(255, 255, 255, 0.17);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.stats-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin: 24px 0 30px;
}

.stat-box {
    position: relative;
    overflow: hidden;
    background: #ffffff;
    padding: 22px 20px;
    border-radius: 18px;
    border: 1px solid #e8edf3;
    text-align: center;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.045);
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}

.stat-box::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 3px;
}

.stat-box:nth-child(1)::before {
    background: linear-gradient(90deg, #0f172a, #334155);
}

.stat-box:nth-child(2)::before {
    background: linear-gradient(90deg, #0d9488, #2dd4bf);
}

.stat-box:nth-child(3)::before {
    background: linear-gradient(90deg, #22c55e, #4ade80);
}

.stat-box:nth-child(4)::before {
    background: linear-gradient(90deg, #ef4444, #fb7185);
}

.stat-box:hover {
    transform: translateY(-5px);
    border-color: #dbe3ec;
    box-shadow: 0 14px 35px rgba(15, 23, 42, 0.09);
}

.stat-box .stat-number {
    font-size: 31px;
    line-height: 1;
    font-weight: 800;
    letter-spacing: -1px;
    margin-bottom: 9px;
}

.stat-box .stat-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.stat-box:nth-child(1) .stat-number {
    color: #0f172a;
}

.stat-box:nth-child(2) .stat-number {
    color: #0d9488;
}

.stat-box:nth-child(3) .stat-number {
    color: #16a34a;
}

.stat-box:nth-child(4) .stat-number {
    color: #dc2626;
}

.table-responsive {
    position: relative;
    z-index: 1;
    overflow-x: auto;
    margin-top: 8px;
    border: 1px solid #e8edf3;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.05);
    scrollbar-width: thin;
}

.table-responsive::-webkit-scrollbar {
    height: 7px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 20px;
}

table {
    width: 100%;
    min-width: 850px;
    border-collapse: separate;
    border-spacing: 0;
    background: #ffffff;
}

thead {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
}

th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 700;
    color: #475569;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

th:first-child {
    border-top-left-radius: 18px;
}

th:last-child {
    border-top-right-radius: 18px;
}

td {
    padding: 16px 20px;
    color: #1e293b;
    font-size: 14px;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap;
    transition: background 0.2s ease;
}

tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    background: #f8fffe;
}

tbody tr:last-child td {
    border-bottom: none;
}

td strong {
    color: #0f172a;
    font-weight: 700;
}

td:nth-child(3) {
    font-weight: 700;
    color: #334155;
}

td:nth-child(4) {
    font-weight: 800;
    color: #0d9488;
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 90px;
    padding: 6px 13px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
    border: 1px solid transparent;
}

.badge-success {
    background: #ecfdf5;
    color: #047857;
    border-color: #bbf7d0;
}

.badge-danger {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fecaca;
}

.badge-warning {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}

.empty-state {
    position: relative;
    z-index: 1;
    text-align: center;
    padding: 65px 30px;
    border: 1px dashed #cbd5e1;
    border-radius: 18px;
    background: linear-gradient(135deg, #f8fafc, #ffffff);
}

.empty-state .empty-icon {
    width: 68px;
    height: 68px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    background: linear-gradient(135deg, #e2e8f0, #f1f5f9);
    font-size: 30px;
}

.empty-state h3 {
    margin: 0 0 7px;
    color: #0f172a;
    font-size: 17px;
    font-weight: 700;
}

.empty-state p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.btn-premium:focus-visible {
    outline: 3px solid rgba(45, 212, 191, 0.35);
    outline-offset: 3px;
}

@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .container {
        padding: 12px 15px 25px;
    }
    .content {
        padding: 18px;
        border-radius: 22px;
    }
    .header-section {
        flex-direction: column;
        align-items: stretch;
        padding: 22px;
        border-radius: 18px;
    }
    .header-section h2 {
        font-size: 23px;
    }
    .header-section p {
        line-height: 1.6;
    }
    .btn-premium {
        width: 100%;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .stat-box {
        padding: 19px 12px;
    }
    .stat-box .stat-number {
        font-size: 26px;
    }
    .stat-box .stat-label {
        font-size: 10px;
    }
    .table-responsive {
        border-radius: 14px;
    }
    th, td {
        padding: 13px 15px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .header-section h2 {
        font-size: 21px;
    }
    .empty-state {
        padding: 45px 20px;
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

        <div class="header-section">
            <div>
                <h2>📊 Quiz Results</h2>
                <p>
                    <?php echo htmlspecialchars($quiz['title']); ?>
                    •
                    <?php echo htmlspecialchars($quiz['subject']); ?>
                    •
                    <?php echo htmlspecialchars($quiz['class_name']); ?>
                </p>
            </div>
            <a href="manage_quizzes.php" class="btn-premium btn-primary">← Back to Quizzes</a>
        </div>

        <div class="stats-grid">

            <div class="stat-box">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?php echo $avg_score; ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?php echo $pass_count; ?></div>
                <div class="stat-label">✅ Passed</div>
            </div>

            <div class="stat-box">
                <div class="stat-number"><?php echo $fail_count; ?></div>
                <div class="stat-label">❌ Failed</div>
            </div>

        </div>

        <?php if ($total_students > 0): ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($attempts as $attempt): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($attempt['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($attempt['student_id']); ?></td>
                            <td><?php echo $attempt['score']; ?> / <?php echo $attempt['total_marks']; ?></td>
                            <td><?php echo round($attempt['percentage'], 1); ?>%</td>
                            <td>
                                <span class="badge <?php echo $attempt['percentage'] >= 50 ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $attempt['percentage'] >= 50 ? '✅ Passed' : '❌ Failed'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y h:i A', strtotime($attempt['end_time'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <h3>No Quiz Attempts Yet</h3>
                <p>No students have attempted this quiz yet.</p>
            </div>

        <?php endif; ?>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>