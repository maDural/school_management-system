<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];

// Get all results
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name, s.student_id 
    FROM results r
    JOIN students s ON r.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE r.teacher_id = ?
    ORDER BY r.exam_date DESC, r.id DESC
");
$stmt->execute([$teacher_id]);
$results = $stmt->fetchAll();

// Get statistics
$total_students = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM results WHERE teacher_id = ?");
$total_students->execute([$teacher_id]);
$total_students = $total_students->fetchColumn();

$avg_score = $pdo->prepare("SELECT AVG(score/total_marks*100) FROM results WHERE teacher_id = ?");
$avg_score->execute([$teacher_id]);
$avg_score = round($avg_score->fetchColumn(), 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Results - School Management</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
:root {
    --primary: #0f172a;
    --primary-light: #1e293b;
    --teal: #0d9488;
    --green: #059669;
    --success: #22c55e;
    --warning: #f59e0b;
    --danger: #ef4444;
    --text: #0f172a;
    --text-muted: #64748b;
    --text-light: #94a3b8;
    --border: #e2e8f0;
    --border-light: #f1f5f9;
    --surface: #ffffff;
    --surface-soft: #f8fafc;
    --radius-sm: 12px;
    --radius-md: 16px;
    --radius-lg: 22px;
    --radius-xl: 28px;
}

body {
    min-height: 100vh;
    color: var(--text);
    background:
        radial-gradient(circle at 10% 10%, rgba(13, 148, 136, 0.08), transparent 28%),
        radial-gradient(circle at 90% 90%, rgba(15, 23, 42, 0.06), transparent 30%),
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
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
    padding: 38px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: var(--radius-xl);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
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

.header-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 28px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-icon {
    width: 54px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 16px;
    color: #ffffff;
    font-size: 25px;
    background: linear-gradient(135deg, var(--primary), var(--teal));
    box-shadow: 0 10px 25px rgba(13, 148, 136, 0.20);
}

.header-section h2 {
    margin: 0;
    color: var(--text);
    font-size: 28px;
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: -0.7px;
}

.header-subtitle {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 13px;
}

.btn-premium {
    min-height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 22px;
    border: none;
    border-radius: 13px;
    color: #ffffff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.btn-premium:hover {
    transform: translateY(-2px);
}

.btn-premium:active {
    transform: translateY(0);
}

.btn-success {
    background: linear-gradient(135deg, var(--teal), var(--green));
    box-shadow: 0 8px 22px rgba(13, 148, 136, 0.22);
}

.btn-success:hover {
    box-shadow: 0 13px 32px rgba(13, 148, 136, 0.30);
}

.alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 18px;
    margin-bottom: 22px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 600;
    animation: slideDown 0.35s ease;
}

.alert-success {
    color: #065f46;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border: 1px solid #a7f3d0;
}

.alert-warning {
    color: #92400e;
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 1px solid #fde68a;
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
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 30px;
}

.stat-box {
    position: relative;
    overflow: hidden;
    padding: 24px;
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04);
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}

.stat-box::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--teal));
}

.stat-box:hover {
    transform: translateY(-4px);
    border-color: #cbd5e1;
    box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.stat-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    font-size: 21px;
}

.stat-box:nth-child(1) .stat-icon {
    background: #eff6ff;
}

.stat-box:nth-child(2) .stat-icon {
    background: #ecfdf5;
}

.stat-box:nth-child(3) .stat-icon {
    background: #fefce8;
}

.stat-number {
    color: var(--text);
    font-size: 30px;
    line-height: 1;
    font-weight: 800;
    letter-spacing: -1px;
}

.stat-label {
    margin-top: 7px;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
}

.results-section {
    margin-top: 10px;
}

.results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.results-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text);
    font-size: 18px;
    font-weight: 800;
}

.results-title-icon {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: #f1f5f9;
    font-size: 18px;
}

.results-count {
    padding: 6px 11px;
    border-radius: 100px;
    background: #f1f5f9;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 700;
}

.table-wrapper {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04);
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

thead {
    background: linear-gradient(180deg, #f8fafc, #f1f5f9);
}

th {
    padding: 15px 20px;
    color: #475569;
    text-align: left;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    white-space: nowrap;
    border-bottom: 1px solid var(--border);
}

td {
    padding: 17px 20px;
    color: #1e293b;
    font-size: 14px;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}

tbody tr {
    transition: background 0.2s ease, transform 0.2s ease;
}

tbody tr:hover {
    background: #f8fafc;
}

tbody tr:last-child td {
    border-bottom: none;
}

.student-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 190px;
}

.student-avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 12px;
    color: #ffffff;
    background: linear-gradient(135deg, var(--primary), var(--teal));
    font-size: 14px;
    font-weight: 800;
}

.student-name {
    color: var(--text);
    font-weight: 700;
}

.student-id {
    display: block;
    margin-top: 3px;
    color: var(--text-light);
    font-size: 11px;
}

.score-value {
    color: var(--text);
    font-weight: 700;
}

.score-total {
    color: var(--text-muted);
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 27px;
    padding: 5px 11px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.badge-primary {
    color: #1e40af;
    background: #dbeafe;
    border: 1px solid #bfdbfe;
}

.badge-success {
    color: #047857;
    background: #d1fae5;
    border: 1px solid #a7f3d0;
}

.badge-warning {
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
}

.badge-danger {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.grade-badge {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 800;
}

.grade-a {
    color: #047857;
    background: #d1fae5;
}

.grade-b {
    color: #1d4ed8;
    background: #dbeafe;
}

.grade-c {
    color: #92400e;
    background: #fef3c7;
}

.grade-d {
    color: #c2410c;
    background: #ffedd5;
}

.grade-f {
    color: #991b1b;
    background: #fee2e2;
}

.date-cell {
    color: var(--text-muted);
    font-size: 13px;
    white-space: nowrap;
}

.empty-state {
    padding: 65px 25px;
    text-align: center;
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 20px;
}

.empty-icon {
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    border-radius: 20px;
    background: #f1f5f9;
    font-size: 32px;
}

.empty-state h3 {
    margin: 0 0 7px;
    color: var(--text);
    font-size: 18px;
    font-weight: 800;
}

.empty-state p {
    max-width: 420px;
    margin: 0 auto;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.6;
}

@media (max-width: 1000px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 800px) {
    .container {
        padding: 15px 15px 35px;
    }
    .content {
        padding: 25px;
        border-radius: 23px;
    }
    .header-section {
        align-items: flex-start;
    }
    .header-section h2 {
        font-size: 23px;
    }
    .header-icon {
        width: 46px;
        height: 46px;
        font-size: 21px;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .stat-box {
        padding: 20px;
    }
    .results-header {
        align-items: flex-start;
        gap: 12px;
    }
}

@media (max-width: 600px) {
    .content {
        padding: 19px;
    }
    .header-section {
        flex-direction: column;
        gap: 15px;
    }
    .header-left {
        width: 100%;
    }
    .header-section .btn-premium {
        width: 100%;
    }
    .header-section h2 {
        font-size: 21px;
    }
    .header-subtitle {
        font-size: 12px;
    }
    .results-header {
        flex-direction: column;
    }
    .results-count {
        align-self: flex-start;
    }
    .table-wrapper {
        border-radius: 15px;
    }
    .empty-state {
        padding: 45px 20px;
    }
}

@media (max-width: 420px) {
    .container {
        padding-left: 10px;
        padding-right: 10px;
    }
    .content {
        padding: 15px;
        border-radius: 19px;
    }
    .stat-number {
        font-size: 27px;
    }
}

.btn-premium:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(13, 148, 136, 0.25);
    outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
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
            <div class="header-left">
                <div class="header-icon">📊</div>
                <div>
                    <h2>Manage Results</h2>
                    <div class="header-subtitle">View, monitor and manage student academic results</div>
                </div>
            </div>
            <a href="add_result.php" class="btn-premium btn-success">
                <span>➕</span>
                <span>Add New Result</span>
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span>Result added successfully!</span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-top">
                    <div>
                        <div class="stat-number"><?php echo count($results); ?></div>
                        <div class="stat-label">Total Results</div>
                    </div>
                    <div class="stat-icon">📊</div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-top">
                    <div>
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div class="stat-label">Students with Results</div>
                    </div>
                    <div class="stat-icon">👨‍🎓</div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-top">
                    <div>
                        <div class="stat-number"><?php echo $avg_score; ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    <div class="stat-icon">📈</div>
                </div>
            </div>
        </div>

        <div class="results-section">
            <div class="results-header">
                <div class="results-title">
                    <span class="results-title-icon">📋</span>
                    <span>Student Results</span>
                </div>
                <span class="results-count">
                    <?php echo count($results); ?>
                    <?php echo count($results) === 1 ? 'Result' : 'Results'; ?>
                </span>
            </div>

            <?php if (count($results) > 0): ?>

                <div class="table-wrapper">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Exam Type</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <?php
                                        $grade = strtoupper(trim($result['grade']));
                                        switch ($grade) {
                                            case 'A': $grade_class = 'grade-a'; break;
                                            case 'B': $grade_class = 'grade-b'; break;
                                            case 'C': $grade_class = 'grade-c'; break;
                                            case 'D': $grade_class = 'grade-d'; break;
                                            default: $grade_class = 'grade-f'; break;
                                        }
                                        $student_name = $result['full_name'] ?? 'Unknown Student';
                                        $student_initial = strtoupper(substr(trim($student_name), 0, 1));
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="student-cell">
                                                <div class="student-avatar"><?php echo htmlspecialchars($student_initial); ?></div>
                                                <div>
                                                    <div class="student-name"><?php echo htmlspecialchars($student_name); ?></div>
                                                    <span class="student-id">ID: <?php echo htmlspecialchars($result['student_id']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($result['subject']); ?></strong></td>
                                        <td><span class="badge badge-primary"><?php echo htmlspecialchars($result['exam_type']); ?></span></td>
                                        <td>
                                            <span class="score-value"><?php echo htmlspecialchars($result['score']); ?></span>
                                            <span class="score-total">/ <?php echo htmlspecialchars($result['total_marks']); ?></span>
                                        </td>
                                        <td><span class="grade-badge <?php echo $grade_class; ?>"><?php echo htmlspecialchars($grade); ?></span></td>
                                        <td><span class="date-cell"><?php echo date('M d, Y', strtotime($result['exam_date'])); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>

                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <h3>No Results Yet</h3>
                    <p>No student results have been recorded yet. Add your first result to start tracking academic performance.</p>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>