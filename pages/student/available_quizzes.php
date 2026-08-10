<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, t.full_name as teacher_name
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

$student_id = $student['id'];
$class_name = $student['class_name'];

// Get available quizzes for this student
$stmt = $pdo->prepare("
    SELECT q.*, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ?) as attempt_count,
           (SELECT percentage FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 1) as score_percentage
    FROM quizzes q
    WHERE q.class_name = ? 
    AND q.is_active = 1
    ORDER BY q.end_time ASC
");
$stmt->execute([$student_id, $student_id, $class_name]);
$quizzes = $stmt->fetchAll();

// Separate quizzes into active and past
$active_quizzes = [];
$past_quizzes = [];
$now = new DateTime();

foreach ($quizzes as $quiz) {
    $end = new DateTime($quiz['end_time']);
    if ($now <= $end) {
        $active_quizzes[] = $quiz;
    } else {
        $past_quizzes[] = $quiz;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Quizzes - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1280px;
            padding: 20px 30px;
        }
        
        .content {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            padding: 35px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
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
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 25px 0 15px;
        }
        
        .quiz-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .quiz-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            transform: translateX(5px);
            border-color: #0d9488;
        }
        
        .quiz-card .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .quiz-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        
        .quiz-card .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .quiz-card .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .quiz-card .status-badge.expired {
            background: #fef2f2;
            color: #991b1b;
        }
        
        .quiz-card .status-badge.completed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .quiz-card .quiz-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 12px 0;
        }
        
        .quiz-card .quiz-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 14px;
        }
        
        .quiz-card .quiz-meta .meta-item strong {
            color: #0f172a;
        }
        
        .quiz-card .quiz-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn-premium {
            padding: 10px 24px;
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
        }
        
        .btn-premium:hover {
            transform: translateY(-2px) scale(1.02);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.25);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            box-shadow: 0 8px 25px rgba(100, 116, 139, 0.25);
        }
        
        .btn-sm {
            padding: 6px 16px;
            font-size: 12px;
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
        
        .score-display {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 14px;
        }
        
        .score-display.pass {
            background: #d1fae5;
            color: #065f46;
        }
        
        .score-display.fail {
            background: #fef2f2;
            color: #991b1b;
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
            
            .quiz-card .quiz-header {
                flex-direction: column;
            }
            
            .quiz-card .quiz-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .quiz-card .quiz-actions .btn-premium {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <div class="header-section">
                <h2>📝 Available Quizzes</h2>
                <span class="class-badge">🏫 Class <?php echo htmlspecialchars($class_name); ?></span>
            </div>
            
            <div class="subtitle">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                <span>•</span>
                <span>👨‍🏫 Teacher: <strong><?php echo htmlspecialchars($student['teacher_name'] ?? 'Not Assigned'); ?></strong></span>
            </div>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error" style="padding: 14px 20px; margin: 15px 0; border-radius: 12px; font-weight: 500; border-left: 4px solid; background: #fef2f2; color: #991b1b; border-left-color: #ef4444;">
                    ❌ <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($active_quizzes) && empty($past_quizzes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <h3>No Quizzes Available</h3>
                    <p>There are no quizzes available for your class at the moment. Check back later!</p>
                </div>
            <?php else: ?>
                
                <?php if (!empty($active_quizzes)): ?>
                    <div class="section-title">🟢 Active Quizzes</div>
                    <?php foreach($active_quizzes as $quiz): 
                        $attempted = $quiz['attempt_count'] > 0;
                        $start = new DateTime($quiz['start_time']);
                        $end = new DateTime($quiz['end_time']);
                        $now = new DateTime();
                        $can_take = ($now >= $start && $now <= $end && !$attempted);
                    ?>
                    <div class="quiz-card">
                        <div class="quiz-header">
                            <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                            <?php if ($attempted): ?>
                                <span class="status-badge completed">✅ Completed</span>
                            <?php else: ?>
                                <span class="status-badge active">🟢 Active</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="quiz-meta">
                            <span class="meta-item">📚 <strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject']); ?></span>
                            <span class="meta-item">📝 <strong>Questions:</strong> <?php echo $quiz['question_count']; ?></span>
                            <span class="meta-item">📊 <strong>Total Marks:</strong> <?php echo $quiz['total_marks']; ?></span>
                            <span class="meta-item">⏱️ <strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> min</span>
                            <span class="meta-item">📅 <strong>Ends:</strong> <?php echo date('M d, Y h:i A', strtotime($quiz['end_time'])); ?></span>
                        </div>
                        
                        <?php if ($attempted): ?>
                            <div class="quiz-actions">
                                <span class="score-display <?php echo ($quiz['score_percentage'] ?? 0) >= 50 ? 'pass' : 'fail'; ?>">
                                    Score: <?php echo round($quiz['score_percentage'] ?? 0, 1); ?>%
                                </span>
                                <span style="color: #64748b; font-size: 14px;">✅ You have already completed this quiz</span>
                            </div>
                        <?php elseif ($can_take): ?>
                            <div class="quiz-actions">
                                <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-premium btn-success">📝 Start Quiz</a>
                            </div>
                        <?php else: ?>
                            <div class="quiz-actions">
                                <span style="color: #f59e0b; font-size: 14px;">⏰ This quiz has not started yet</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($past_quizzes)): ?>
                    <div class="section-title" style="margin-top: 30px;">📜 Past Quizzes</div>
                    <?php foreach($past_quizzes as $quiz): 
                        $attempted = $quiz['attempt_count'] > 0;
                    ?>
                    <div class="quiz-card" style="opacity: 0.8;">
                        <div class="quiz-header">
                            <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                            <span class="status-badge expired">⏰ Expired</span>
                        </div>
                        
                        <div class="quiz-meta">
                            <span class="meta-item">📚 <strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject']); ?></span>
                            <span class="meta-item">📝 <strong>Questions:</strong> <?php echo $quiz['question_count']; ?></span>
                            <span class="meta-item">📊 <strong>Total Marks:</strong> <?php echo $quiz['total_marks']; ?></span>
                            <span class="meta-item">⏱️ <strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> min</span>
                            <span class="meta-item">📅 <strong>Ended:</strong> <?php echo date('M d, Y h:i A', strtotime($quiz['end_time'])); ?></span>
                        </div>
                        
                        <?php if ($attempted): ?>
                            <div class="quiz-actions">
                                <span class="score-display <?php echo ($quiz['score_percentage'] ?? 0) >= 50 ? 'pass' : 'fail'; ?>">
                                    Score: <?php echo round($quiz['score_percentage'] ?? 0, 1); ?>%
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="quiz-actions">
                                <span style="color: #94a3b8; font-size: 14px;">❌ This quiz has expired</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="dashboard.php" class="btn-premium btn-primary">← Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>