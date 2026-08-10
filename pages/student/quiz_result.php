<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];
$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;

if ($attempt_id == 0) {
    redirect('dashboard.php');
}

// Get student info
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'];

// Get attempt details with quiz info
$stmt = $pdo->prepare("
    SELECT a.*, q.title, q.subject, q.class_name,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as total_questions
    FROM quiz_attempts a
    JOIN quizzes q ON a.quiz_id = q.id
    WHERE a.id = ? AND a.student_id = ?
");
$stmt->execute([$attempt_id, $student_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    redirect('dashboard.php');
}

// Get answers with correct answers
$stmt = $pdo->prepare("
    SELECT qa.*, qq.question, qq.correct_answer, qq.marks
    FROM quiz_answers qa
    JOIN quiz_questions qq ON qa.question_id = qq.id
    WHERE qa.attempt_id = ?
");
$stmt->execute([$attempt_id]);
$answers = $stmt->fetchAll();
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
            max-width: 800px;
            margin: 0 auto;
        }
        
        .result-header {
            text-align: center;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        
        .result-header.pass {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        }
        
        .result-header.fail {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
        }
        
        .result-header .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 36px;
            font-weight: 800;
            color: white;
        }
        
        .result-header .score-circle.pass {
            background: linear-gradient(135deg, #0d9488, #059669);
        }
        
        .result-header .score-circle.fail {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .result-header h2 {
            font-size: 28px;
            font-weight: 800;
        }
        
        .result-header .result-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .result-header .result-meta .meta-item {
            color: #475569;
            font-size: 14px;
        }
        
        .result-header .result-meta .meta-item strong {
            color: #0f172a;
        }
        
        .answer-review {
            margin-top: 25px;
        }
        
        .answer-item {
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        
        .answer-item.correct {
            border-left: 4px solid #0d9488;
            background: #f0fdf4;
        }
        
        .answer-item.incorrect {
            border-left: 4px solid #ef4444;
            background: #fef2f2;
        }
        
        .answer-item .answer-question {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 5px;
        }
        
        .answer-item .answer-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #64748b;
        }
        
        .answer-item .answer-details .correct-answer {
            color: #0d9488;
            font-weight: 600;
        }
        
        .answer-item .answer-details .wrong-answer {
            color: #ef4444;
            font-weight: 600;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.25);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.25);
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .result-header .result-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .actions .btn-premium {
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
            <?php 
                $percentage = round($attempt['percentage'], 1);
                $passed = $percentage >= 50;
                $grade = $percentage >= 80 ? 'A' : ($percentage >= 70 ? 'B' : ($percentage >= 60 ? 'C' : ($percentage >= 50 ? 'D' : 'F')));
            ?>
            
            <div class="result-header <?php echo $passed ? 'pass' : 'fail'; ?>">
                <div class="score-circle <?php echo $passed ? 'pass' : 'fail'; ?>">
                    <?php echo $percentage; ?>%
                </div>
                <h2><?php echo $passed ? '🎉 Congratulations!' : '😅 Better Luck Next Time!'; ?></h2>
                <p style="color: #475569; font-size: 16px;">
                    You scored <strong><?php echo $attempt['score']; ?></strong> out of <strong><?php echo $attempt['total_marks']; ?></strong> marks
                    <span style="display: inline-block; margin-left: 10px; padding: 4px 16px; border-radius: 100px; font-weight: 700; <?php echo $passed ? 'background: #d1fae5; color: #065f46;' : 'background: #fef2f2; color: #991b1b;'; ?>">
                        Grade: <?php echo $grade; ?>
                    </span>
                </p>
                <div class="result-meta">
                    <span class="meta-item">📚 <strong><?php echo htmlspecialchars($attempt['subject']); ?></strong></span>
                    <span class="meta-item">📝 <strong><?php echo $attempt['total_questions']; ?></strong> questions</span>
                    <span class="meta-item">✅ <strong><?php echo $attempt['score']; ?></strong> correct</span>
                    <span class="meta-item">❌ <strong><?php echo $attempt['total_marks'] - $attempt['score']; ?></strong> incorrect</span>
                </div>
            </div>
            
            <div class="answer-review">
                <h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 15px;">📋 Answer Review</h3>
                <?php foreach($answers as $index => $answer): ?>
                    <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <div class="answer-question">Q<?php echo $index + 1; ?>. <?php echo htmlspecialchars($answer['question']); ?></div>
                        <div class="answer-details">
                            <span>Your answer: 
                                <span class="<?php echo $answer['is_correct'] ? 'correct-answer' : 'wrong-answer'; ?>">
                                    <?php echo $answer['selected_answer'] ? 'Option ' . htmlspecialchars($answer['selected_answer']) : 'Not answered'; ?>
                                </span>
                            </span>
                            <?php if (!$answer['is_correct']): ?>
                                <span>Correct answer: <span class="correct-answer">Option <?php echo htmlspecialchars($answer['correct_answer']); ?></span></span>
                            <?php endif; ?>
                            <span>Marks: <?php echo $answer['is_correct'] ? '+' . $answer['marks'] : '0'; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="actions">
                <a href="available_quizzes.php" class="btn-premium btn-primary">📝 View All Quizzes</a>
                <a href="dashboard.php" class="btn-premium btn-success">🏠 Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>