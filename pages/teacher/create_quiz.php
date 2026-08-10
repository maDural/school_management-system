<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT DISTINCT class_name FROM students WHERE teacher_id = ? ORDER BY class_name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $subject = trim($_POST['subject']);
    $class_name = $_POST['class_name'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $questions = $_POST['questions'] ?? [];

    $errors = [];

    if (empty($title)) $errors[] = "Quiz title is required";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($class_name)) $errors[] = "Class is required";
    if (empty($start_time)) $errors[] = "Start time is required";
    if (empty($end_time)) $errors[] = "End time is required";
    if (strtotime($start_time) >= strtotime($end_time)) $errors[] = "End time must be after start time";
    if (count($questions) < 1) $errors[] = "Please add at least one question";

    foreach ($questions as $index => $q) {
        if (empty($q['question'])) $errors[] = "Question " . ($index + 1) . " is empty";
        if (empty($q['correct'])) $errors[] = "Question " . ($index + 1) . " must have a correct answer";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $total_marks = 0;
            foreach ($questions as $q) {
                $total_marks += intval($q['marks'] ?? 1);
            }

            $stmt = $pdo->prepare("
                INSERT INTO quizzes (teacher_id, title, description, subject, class_name, total_marks, start_time, end_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$teacher_id, $title, $description, $subject, $class_name, $total_marks, $start_time, $end_time]);
            $quiz_id = $pdo->lastInsertId();

            foreach ($questions as $q) {
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer, marks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $quiz_id,
                    $q['question'],
                    $q['option_a'],
                    $q['option_b'],
                    $q['option_c'],
                    $q['option_d'],
                    $q['correct'],
                    intval($q['marks'] ?? 1)
                ]);
            }

            $pdo->commit();
            $success = "Quiz created successfully!";

            header("Refresh: 2; URL=manage_quizzes.php");

        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Failed to create quiz: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(13, 148, 136, 0.08), transparent 35%),
                linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 1280px;
            padding: 30px;
            margin: 0 auto;
        }

        .content {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 25px 70px rgba(15, 23, 42, 0.09), 0 4px 15px rgba(15, 23, 42, 0.04);
        }

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }

        .page-header-content {
            flex: 1;
        }

        .page-icon {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: linear-gradient(135deg, #ccfbf1, #d1fae5);
            font-size: 25px;
            margin-bottom: 15px;
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.12);
        }

        .content h2 {
            font-size: 30px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 8px;
            letter-spacing: -0.5px;
        }

        .content .subtitle {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .alert {
            padding: 15px 18px;
            margin: 15px 0;
            border-radius: 14px;
            font-weight: 500;
            border: 1px solid transparent;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.5;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .form-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .form-section:hover {
            border-color: #cbd5e1;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: #f0fdfa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .section-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 750;
            color: #0f172a;
        }

        .section-header p {
            margin: 3px 0 0;
            color: #94a3b8;
            font-size: 12px;
        }

        .form-group {
            margin: 0 0 18px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 650;
            color: #0f172a;
            font-size: 13px;
        }

        .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 13px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.25s ease;
            background: #f8fafc;
            color: #0f172a;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.5;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #94a3b8;
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.10);
            background: #ffffff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .questions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .question-counter {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

        .question-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
            margin: 15px 0;
            position: relative;
            transition: all 0.3s ease;
        }

        .question-box:hover {
            border-color: #cbd5e1;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.04);
            transform: translateX(4px);
        }

        .question-box .question-number {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            background: #ccfbf1;
            color: #0f766e;
            border-radius: 100px;
            margin-bottom: 18px;
            font-size: 12px;
            font-weight: 750;
        }

        .question-box .remove-question {
            position: absolute;
            top: 17px;
            right: 17px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff1f2;
            color: #e11d48;
            border: 1px solid #fecdd3;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.25s ease;
        }

        .question-box .remove-question:hover {
            background: #ffe4e6;
            transform: translateY(-2px) scale(1.05);
        }

        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }

        .options-grid .option-input {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .options-grid .option-input label {
            width: 30px;
            min-width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            background: #e2e8f0;
            color: #475569;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
        }

        .options-grid .option-input input {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            background: #ffffff;
            min-width: 0;
        }

        .options-grid .option-input input:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.08);
        }

        .correct-answer-select {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .correct-answer-select label {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .correct-answer-select select,
        .correct-answer-select input[type="number"] {
            padding: 9px 11px;
            border: 2px solid #e2e8f0;
            border-radius: 9px;
            font-size: 13px;
            background: #ffffff;
            color: #0f172a;
        }

        .correct-answer-select select:focus,
        .correct-answer-select input[type="number"]:focus {
            outline: none;
            border-color: #0d9488;
        }

        .correct-answer-select input[type="number"] {
            width: 75px;
        }

        .btn-premium {
            padding: 12px 20px;
            min-height: 44px;
            border-radius: 12px;
            font-weight: 650;
            font-size: 13px;
            transition: all 0.25s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #ffffff;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .btn-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-premium:hover::before {
            left: 100%;
        }

        .btn-premium:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #334155);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
        }

        .btn-primary:hover {
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.24);
        }

        .btn-success {
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.22);
        }

        .btn-success:hover {
            box-shadow: 0 12px 28px rgba(13, 148, 136, 0.28);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.20);
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .empty-questions {
            text-align: center;
            padding: 35px 20px;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            color: #64748b;
        }

        .empty-questions .icon {
            font-size: 38px;
            margin-bottom: 10px;
        }

        .empty-questions p {
            margin: 0;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .content {
                padding: 22px;
                border-radius: 22px;
            }
            .page-header {
                margin-bottom: 22px;
            }
            .content h2 {
                font-size: 24px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .options-grid {
                grid-template-columns: 1fr;
            }
            .questions-header {
                align-items: stretch;
                flex-direction: column;
            }
            .questions-header .btn-premium {
                width: 100%;
            }
            .actions {
                flex-direction: column;
            }
            .actions .btn-premium {
                width: 100%;
            }
            .form-section {
                padding: 17px;
            }
            .question-box {
                padding: 17px;
            }
            .correct-answer-select {
                align-items: flex-start;
                flex-direction: column;
            }
            .correct-answer-select select,
            .correct-answer-select input[type="number"] {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 17px;
            }
            .page-icon {
                width: 46px;
                height: 46px;
            }
            .content h2 {
                font-size: 22px;
            }
            .section-header {
                align-items: flex-start;
            }
            .question-box .remove-question {
                top: 14px;
                right: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="content">

            <div class="page-header">
                <div class="page-header-content">
                    <div class="page-icon">📝</div>
                    <h2>Create New Quiz</h2>
                    <p class="subtitle">
                        Build an interactive multiple-choice quiz for your students.
                        Add questions, answers, marks and a scheduled availability period.
                    </p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <?php foreach($errors as $err): ?>
                    <div class="alert alert-error">❌ <?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" id="quizForm">

                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">📌</div>
                        <div>
                            <h3>Quiz Information</h3>
                            <p>Enter the basic details of your quiz</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Quiz Title <span class="required">*</span></label>
                        <input type="text" name="title" required maxlength="150" placeholder="e.g. Mathematics Term 2 Assessment" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" maxlength="1000" placeholder="Enter a short description or instructions for students..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📚 Subject <span class="required">*</span></label>
                            <input type="text" name="subject" required placeholder="e.g. Mathematics">
                        </div>

                        <div class="form-group">
                            <label>🏫 Class <span class="required">*</span></label>
                            <select name="class_name" required>
                                <option value="">Select Class</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">⏰</div>
                        <div>
                            <h3>Quiz Schedule</h3>
                            <p>Choose when students can access the quiz</p>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📅 Start Time <span class="required">*</span></label>
                            <input type="datetime-local" name="start_time" id="start_time" required>
                        </div>

                        <div class="form-group">
                            <label>⏰ End Time <span class="required">*</span></label>
                            <input type="datetime-local" name="end_time" id="end_time" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">📋</div>
                        <div style="flex: 1;">
                            <h3>Quiz Questions</h3>
                            <p>Add multiple-choice questions and mark the correct answers</p>
                        </div>
                    </div>

                    <div class="questions-header">
                        <div class="question-counter">
                            <span id="questionCount">0</span> question(s)
                        </div>
                        <button type="button" class="btn-premium btn-primary" onclick="addQuestion()">➕ Add Question</button>
                    </div>

                    <div id="questionsContainer"></div>
                </div>

                <div class="actions">
                    <a href="manage_quizzes.php" class="btn-premium btn-warning">❌ Cancel</a>
                    <button type="submit" class="btn-premium btn-success" id="createQuizButton">💾 Create Quiz</button>
                </div>

            </form>

        </div>
    </div>

    <script>
        let questionCount = 0;
        const container = document.getElementById('questionsContainer');
        const countDisplay = document.getElementById('questionCount');

        function updateQuestionCount() {
            const questions = container.querySelectorAll('.question-box');
            questionCount = questions.length;
            countDisplay.textContent = questionCount;
        }

        function addQuestion() {
            const id = Date.now();
            const questionHTML = `
                <div class="question-box" data-question-id="${id}">
                    <button type="button" class="remove-question" onclick="removeQuestion(this)" title="Remove question" aria-label="Remove question">✕</button>
                    <div class="question-number">Question</div>

                    <div class="form-group">
                        <label>Question <span class="required">*</span></label>
                        <input type="text" name="questions[${id}][question]" required maxlength="500" placeholder="Enter your question...">
                    </div>

                    <div class="options-grid">
                        <div class="option-input">
                            <label>A</label>
                            <input type="text" name="questions[${id}][option_a]" required maxlength="255" placeholder="Option A">
                        </div>
                        <div class="option-input">
                            <label>B</label>
                            <input type="text" name="questions[${id}][option_b]" required maxlength="255" placeholder="Option B">
                        </div>
                        <div class="option-input">
                            <label>C</label>
                            <input type="text" name="questions[${id}][option_c]" required maxlength="255" placeholder="Option C">
                        </div>
                        <div class="option-input">
                            <label>D</label>
                            <input type="text" name="questions[${id}][option_d]" required maxlength="255" placeholder="Option D">
                        </div>
                    </div>

                    <div class="correct-answer-select">
                        <label>✅ Correct Answer</label>
                        <select name="questions[${id}][correct]" required>
                            <option value="">Select answer</option>
                            <option value="A">A — Option A</option>
                            <option value="B">B — Option B</option>
                            <option value="C">C — Option C</option>
                            <option value="D">D — Option D</option>
                        </select>
                        <label>Marks</label>
                        <input type="number" name="questions[${id}][marks]" value="1" min="1" max="100" required>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', questionHTML);
            renumberQuestions();

            const questions = container.querySelectorAll('.question-box');
            const newQuestion = questions[questions.length - 1];

            if (newQuestion) {
                newQuestion.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const input = newQuestion.querySelector('input[name*="[question]"]');
                if (input) {
                    setTimeout(() => input.focus(), 350);
                }
            }
        }

        function removeQuestion(button) {
            const question = button.closest('.question-box');
            if (!question) return;

            const totalQuestions = container.querySelectorAll('.question-box').length;
            if (totalQuestions <= 1) {
                alert('A quiz must contain at least one question.');
                return;
            }

            if (!confirm('Remove this question?')) return;
            question.remove();
            renumberQuestions();
        }

        function renumberQuestions() {
            const questions = container.querySelectorAll('.question-box');
            questions.forEach((question, index) => {
                const number = question.querySelector('.question-number');
                if (number) {
                    number.textContent = `Question ${index + 1}`;
                }
            });
            updateQuestionCount();
        }

        function formatLocalDateTime(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        function setDefaultTimes() {
            const now = new Date();
            const startDate = new Date(now.getTime() + 30 * 60000);
            const endDate = new Date(now.getTime() + 90 * 60000);

            const startInput = document.getElementById('start_time');
            const endInput = document.getElementById('end_time');

            if (startInput && !startInput.value) {
                startInput.value = formatLocalDateTime(startDate);
            }
            if (endInput && !endInput.value) {
                endInput.value = formatLocalDateTime(endDate);
            }

            if (startInput && endInput) {
                startInput.addEventListener('change', function() {
                    endInput.min = this.value;
                    if (endInput.value && endInput.value < this.value) {
                        endInput.value = '';
                    }
                });
            }
        }

        document.getElementById('quizForm').addEventListener('submit', function(event) {
            const questions = container.querySelectorAll('.question-box');
            if (questions.length === 0) {
                event.preventDefault();
                alert('Please add at least one question before creating the quiz.');
                return;
            }

            const startInput = document.getElementById('start_time');
            const endInput = document.getElementById('end_time');

            if (startInput.value && endInput.value && endInput.value <= startInput.value) {
                event.preventDefault();
                alert('The quiz end time must be after the start time.');
                endInput.focus();
                return;
            }

            const submitButton = document.getElementById('createQuizButton');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '⏳ Creating Quiz...';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            setDefaultTimes();
            if (container.querySelectorAll('.question-box').length === 0) {
                addQuestion();
            }
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>