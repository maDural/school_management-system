<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];

// Get student info with class
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('../../logout.php');
}

$student_id = $student['id'];

// Get results
$stmt = $pdo->prepare("
    SELECT * FROM results 
    WHERE student_id = ? 
    ORDER BY exam_date DESC
");
$stmt->execute([$student_id]);
$results = $stmt->fetchAll();

// Calculate statistics
$total_subjects = count(array_unique(array_column($results, 'subject')));
$total_exams = count($results);
$avg_score = 0;
$best_grade = 'F';
$grades = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

foreach ($results as $result) {
    $score_percent = ($result['score'] / $result['total_marks']) * 100;
    $avg_score += $score_percent;
    $grades[$result['grade']]++;
    if ($result['grade'] < $best_grade) $best_grade = $result['grade'];
}

$avg_score = $total_exams > 0 ? round($avg_score / $total_exams, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - EduManager</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        body { background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%); min-height: 100vh; }
        .container { max-width: 1280px; padding: 20px 30px; }
        
        .content {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            padding: 35px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
        }
        
        .content h2 { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 5px; }
        .content .subtitle { color: #64748b; font-size: 14px; margin-bottom: 25px; }
        
        .class-badge {
            display: inline-block;
            background: #f1f5f9;
            padding: 4px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            margin-left: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .stat-box .stat-number {
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-box .stat-label {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .stat-box:nth-child(1) .stat-number { color: #0f172a; }
        .stat-box:nth-child(2) .stat-number { color: #0d9488; }
        .stat-box:nth-child(3) .stat-number { color: #f59e0b; }
        .stat-box:nth-child(4) .stat-number { color: #ef4444; }
        
        .table-responsive { overflow-x: auto; margin-top: 20px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }
        
        thead { background: #f8fafc; }
        th { padding: 15px 20px; text-align: left; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        tr:hover { background: #f8fafc; }
        
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fecaca; color: #991b1b; }
        
        @media (max-width: 768px) {
            .container { padding: 10px 15px; }
            .content { padding: 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 15px; }
            .content h2 { font-size: 22px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <h2>📊 My Results</h2>
            <p class="subtitle">
                Results for <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                <span class="class-badge">🏫 Class <?php echo htmlspecialchars($student['class_name']); ?></span>
            </p>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $total_exams; ?></div>
                    <div class="stat-label">Total Exams</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $avg_score; ?>%</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $best_grade; ?></div>
                    <div class="stat-label">Best Grade</div>
                </div>
            </div>
            
            <?php if (count($results) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>📚 Subject</th>
                                <th>📋 Exam Type</th>
                                <th>📊 Score</th>
                                <th>Grade</th>
                                <th>📅 Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($results as $result): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($result['subject']); ?></strong></td>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($result['exam_type']); ?></span></td>
                                <td><?php echo $result['score']; ?> / <?php echo $result['total_marks']; ?></td>
                                <td>
                                    <span class="badge <?php 
                                        if ($result['grade'] == 'A') echo 'badge-success';
                                        elseif ($result['grade'] == 'F') echo 'badge-danger';
                                        else echo 'badge-warning';
                                    ?>">
                                        <?php echo $result['grade']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($result['exam_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="background: #fef3c7; color: #92400e; border-left-color: #f59e0b; padding: 14px 20px; border-radius: 12px; border-left: 4px solid;">
                    No results available yet for your class.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>