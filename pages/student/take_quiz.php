<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no quiz ID, redirect to available quizzes
if ($quiz_id == 0) {
    redirect('available_quizzes.php?error=No quiz selected');
}

// Get student info
$stmt = $pdo->prepare("SELECT id, class_name FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('../../logout.php');
}

$student_id = $student['id'];
$class_name = $student['class_name'];

// Get quiz details
$stmt = $pdo->prepare("
    SELECT q.*, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count
    FROM quizzes q
    WHERE q.id = ? AND q.class_name = ? AND q.is_active = 1
");
$stmt->execute([$quiz_id, $class_name]);
$quiz = $stmt->fetch();

if (!$quiz) {
    redirect('available_quizzes.php?error=Quiz not found or not available for your class');
}

// Check if quiz is available
$now = new DateTime();
$start = new DateTime($quiz['start_time']);
$end = new DateTime($quiz['end_time']);

if ($now < $start) {
    redirect('available_quizzes.php?error=This quiz has not started yet');
}

if ($now > $end) {
    redirect('available_quizzes.php?error=This quiz has expired');
}

// Check if student has already attempted this quiz
$stmt = $pdo->prepare("
    SELECT * FROM quiz_attempts 
    WHERE quiz_id = ? AND student_id = ? AND status IN ('in_progress', 'completed')
");
$stmt->execute([$quiz_id, $student_id]);
$existing_attempt = $stmt->fetch();

if ($existing_attempt && $existing_attempt['status'] == 'completed') {
    redirect('available_quizzes.php?error=You have already completed this quiz');
}

// If no attempt exists, create one
if (!$existing_attempt) {
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, start_time, status)
        VALUES (?, ?, NOW(), 'in_progress')
    ");
    $stmt->execute([$quiz_id, $student_id]);
    $attempt_id = $pdo->lastInsertId();
} else {
    $attempt_id = $existing_attempt['id'];
}

// Get quiz questions
$stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $answers = $_POST['answers'] ?? [];
    $score = 0;
    $total_marks = 0;
    
    try {
        $pdo->beginTransaction();
        
        // Delete any existing answers for this attempt (in case of re-submission)
        $stmt = $pdo->prepare("DELETE FROM quiz_answers WHERE attempt_id = ?");
        $stmt->execute([$attempt_id]);
        
        foreach ($questions as $question) {
            $selected = isset($answers[$question['id']]) ? $answers[$question['id']] : null;
            $is_correct = ($selected == $question['correct_answer']) ? 1 : 0;
            if ($is_correct) {
                $score += $question['marks'];
            }
            $total_marks += $question['marks'];
            
            // Save answer
            $stmt = $pdo->prepare("
                INSERT INTO quiz_answers (attempt_id, question_id, selected_answer, is_correct)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$attempt_id, $question['id'], $selected, $is_correct]);
        }
        
        $percentage = ($total_marks > 0) ? ($score / $total_marks) * 100 : 0;
        
        // Update attempt
        $stmt = $pdo->prepare("
            UPDATE quiz_attempts 
            SET end_time = NOW(), score = ?, total_marks = ?, percentage = ?, status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([$score, $total_marks, $percentage, $attempt_id]);
        
        // Save result to results table
        $stmt = $pdo->prepare("
            INSERT INTO results (student_id, exam_type, subject, score, total_marks, grade, exam_date, teacher_id)
            VALUES (?, 'Quiz', ?, ?, ?, ?, NOW(), ?)
        ");
        $grade = $percentage >= 80 ? 'A' : ($percentage >= 70 ? 'B' : ($percentage >= 60 ? 'C' : ($percentage >= 50 ? 'D' : 'F')));
        $stmt->execute([$student_id, $quiz['subject'], $score, $total_marks, $grade, $quiz['teacher_id']]);
        
        $pdo->commit();
        
        header("Location: quiz_result.php?attempt_id=" . $attempt_id);
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Failed to submit quiz: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - Mabaso Secondary School</title>
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
        
        .quiz-header {
            background: linear-gradient(135deg, #0f172a, #0d9488);
            border-radius: 16px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
        }
        
        .quiz-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .quiz-header .quiz-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
        
        .timer {
            background: #ef4444;
            color: white;
            padding: 8px 20px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 16px;
            display: inline-block;
            margin-bottom: 20px;
            transition: background 0.3s ease;
        }
        
        .timer.warning {
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .question-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        
        .question-container .question-number {
            font-weight: 600;
            color: #0d9488;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .question-container .question-text {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 15px;
        }
        
        .option-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .option-label:hover {
            border-color: #0d9488;
            background: #f0fdf4;
        }
        
        .option-label input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #0d9488;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .option-label .option-letter {
            font-weight: 700;
            color: #0d9488;
            min-width: 20px;
        }
        
        .option-label .option-text {
            color: #0f172a;
        }
        
        .alert {
            padding: 14px 20px;
            margin: 15px 0;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
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
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.25);
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .progress-bar .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0d9488, #059669);
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .actions .btn-premium {
                width: 100%;
                justify-content: center;
            }
            
            .quiz-header .quiz-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="quiz-header">
                <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                <div class="quiz-meta">
                    <span>📚 <?php echo htmlspecialchars($quiz['subject']); ?></span>
                    <span>⏱️ <?php echo $quiz['time_limit']; ?> minutes</span>
                    <span>📝 <?php echo count($questions); ?> questions</span>
                    <span>📊 <?php echo $quiz['total_marks']; ?> marks</span>
                </div>
            </div>
            
            <div id="timer" class="timer">⏱️ Time Remaining: <span id="timeDisplay">--:--</span></div>
            
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
            </div>
            
            <form method="POST" id="quizForm">
                <?php $index = 1; ?>
                <?php foreach($questions as $question): ?>
                    <div class="question-container" data-question="<?php echo $index; ?>">
                        <div class="question-number">Question <?php echo $index; ?> of <?php echo count($questions); ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($question['question']); ?></div>
                        
                        <?php $options = [
                            'A' => $question['option_a'],
                            'B' => $question['option_b'],
                            'C' => $question['option_c'],
                            'D' => $question['option_d']
                        ]; ?>
                        
                        <?php foreach($options as $letter => $text): ?>
                            <label class="option-label">
                                <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $letter; ?>" required>
                                <span class="option-letter"><?php echo $letter; ?>.</span>
                                <span class="option-text"><?php echo htmlspecialchars($text); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php $index++; ?>
                <?php endforeach; ?>
                
                <div class="actions">
                    <button type="submit" class="btn-premium btn-success">📤 Submit Quiz</button>
                    <a href="available_quizzes.php" class="btn-premium btn-warning">❌ Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Timer functionality
        const timeLimit = <?php echo $quiz['time_limit']; ?> * 60; // in seconds
        const startTime = new Date().getTime();
        const endTime = startTime + (timeLimit * 1000);
        const timeDisplay = document.getElementById('timeDisplay');
        const timerDiv = document.getElementById('timer');
        const progressFill = document.getElementById('progressFill');
        const totalQuestions = <?php echo count($questions); ?>;
        let timerInterval;
        
        function updateTimer() {
            const now = new Date().getTime();
            const remaining = Math.max(0, endTime - now);
            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);
            
            timeDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            // Update progress
            const elapsed = (timeLimit * 1000) - remaining;
            const progress = Math.min(100, (elapsed / (timeLimit * 1000)) * 100);
            progressFill.style.width = progress + '%';
            
            if (remaining <= 60000) {
                timerDiv.classList.add('warning');
                timerDiv.style.background = '#ef4444';
            }
            
            if (remaining <= 0) {
                clearInterval(timerInterval);
                alert('⏰ Time is up! Your quiz will be submitted automatically.');
                document.getElementById('quizForm').submit();
            }
        }
        
        timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
        
        // Auto-save progress (track answered questions)
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const questionContainer = this.closest('.question-container');
                const questionNumber = questionContainer.dataset.question;
                // You could save progress to localStorage here if needed
            });
        });
        
        // Warn before submitting
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            // Check if all questions are answered
            const totalQuestions = <?php echo count($questions); ?>;
            const answered = document.querySelectorAll('input[type="radio"]:checked').length;
            
            if (answered < totalQuestions) {
                if (!confirm(`You have answered ${answered} out of ${totalQuestions} questions. ${totalQuestions - answered} questions are unanswered. Are you sure you want to submit?`)) {
                    e.preventDefault();
                    return false;
                }
            } else {
                if (!confirm('Are you sure you want to submit this quiz?')) {
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        });
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>