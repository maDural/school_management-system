<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle quiz deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $quiz_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$quiz_id, $teacher_id]);
        $message = "Quiz deleted successfully!";
    } catch(Exception $e) {
        $error = "Failed to delete quiz.";
    }
}

// Get all quizzes for this teacher
$stmt = $pdo->prepare("
    SELECT q.*, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
           (SELECT COUNT(DISTINCT student_id) FROM quiz_attempts WHERE quiz_id = q.id) as student_count
    FROM quizzes q
    WHERE q.teacher_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$teacher_id]);
$quizzes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quizzes - Mabaso Secondary School</title>
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
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.25);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        }
        
        .alert {
            padding: 14px 20px;
            margin: 15px 0;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
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
        
        .quiz-card .status-badge.upcoming {
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
        
        .quiz-card .quiz-actions .btn-sm {
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
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <div class="header-section">
                <h2>📝 Manage Quizzes</h2>
                <a href="create_quiz.php" class="btn-premium btn-success">➕ Create New Quiz</a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (count($quizzes) > 0): ?>
                <?php foreach($quizzes as $quiz): 
                    $now = new DateTime();
                    $start = new DateTime($quiz['start_time']);
                    $end = new DateTime($quiz['end_time']);
                    $status = 'active';
                    $status_class = 'active';
                    
                    if ($now < $start) {
                        $status = 'upcoming';
                        $status_class = 'upcoming';
                    } elseif ($now > $end) {
                        $status = 'expired';
                        $status_class = 'expired';
                    }
                ?>
                <div class="quiz-card">
                    <div class="quiz-header">
                        <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>
                    
                    <div class="quiz-meta">
                        <span class="meta-item">📚 <strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject']); ?></span>
                        <span class="meta-item">🏫 <strong>Class:</strong> <?php echo htmlspecialchars($quiz['class_name']); ?></span>
                        <span class="meta-item">📝 <strong>Questions:</strong> <?php echo $quiz['question_count']; ?></span>
                        <span class="meta-item">👨‍🎓 <strong>Students:</strong> <?php echo $quiz['student_count']; ?></span>
                        <span class="meta-item">⏱️ <strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> min</span>
                        <span class="meta-item">📊 <strong>Total Marks:</strong> <?php echo $quiz['total_marks']; ?></span>
                    </div>
                    
                    <div class="quiz-meta">
                        <span class="meta-item">📅 <strong>Starts:</strong> <?php echo date('M d, Y h:i A', strtotime($quiz['start_time'])); ?></span>
                        <span class="meta-item">⏰ <strong>Ends:</strong> <?php echo date('M d, Y h:i A', strtotime($quiz['end_time'])); ?></span>
                    </div>
                    
                    <?php if (!empty($quiz['description'])): ?>
                        <p style="color: #475569; margin: 10px 0;"><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                    <?php endif; ?>
                    
                    <div class="quiz-actions">
                        <a href="view_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-premium btn-primary btn-sm">👁️ View</a>
                        <a href="quiz_results.php?id=<?php echo $quiz['id']; ?>" class="btn-premium btn-warning btn-sm">📊 Results</a>
                        <a href="?delete=<?php echo $quiz['id']; ?>" class="btn-premium btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this quiz?')">🗑️ Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <h3>No Quizzes Created</h3>
                    <p>You haven't created any quizzes yet. Click the button above to create your first quiz.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>