<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$errors = [];
$error = null;

$stmt = $pdo->prepare("
    SELECT DISTINCT class_name
    FROM students
    WHERE teacher_id = ?
      AND class_name IS NOT NULL
      AND class_name != ''
    ORDER BY class_name
");
$stmt->execute([$teacher_id]);
$existing_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $full_name  = trim($_POST['full_name'] ?? '');
    $password   = $_POST['password'] ?? '';
    $student_id = trim($_POST['student_id'] ?? '');
    $class_name = trim($_POST['class_name'] ?? '');

    if ($username === '') {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $errors[] = "Username may only contain letters, numbers, dots, underscores, and hyphens.";
    }

    if ($email === '') {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if ($full_name === '') {
        $errors[] = "Full name is required.";
    } elseif (strlen($full_name) < 2) {
        $errors[] = "Full name must be at least 2 characters.";
    }

    if ($student_id === '') {
        $errors[] = "Student ID is required.";
    }

    if ($password === '') {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if ($class_name === '') {
        $errors[] = "Class name is required.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            if ($stmt->fetch()) {
                throw new Exception("Student ID already exists.");
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                throw new Exception("Username or email already exists.");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, role)
                VALUES (?, ?, ?, ?, 'student')
            ");
            $stmt->execute([$username, $email, $hashed_password, $full_name]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO students (user_id, student_id, class_name, teacher_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $student_id, $class_name, $teacher_id]);

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO class_assignments (teacher_id, class_name)
                VALUES (?, ?)
            ");
            $stmt->execute([$teacher_id, $class_name]);

            $pdo->commit();
            header("Location: manage_students.php?success=1");
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - EduManager</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            box-sizing: border-box;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(13, 148, 136, 0.08), transparent 35%),
                linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #0f172a;
        }

        .container {
            max-width: 1280px;
            padding: 30px;
            margin: 0 auto;
        }

        .content {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            padding: 35px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08), 0 4px 15px rgba(15, 23, 42, 0.03);
            max-width: 760px;
            margin: 20px auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .content h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 8px;
            letter-spacing: -0.5px;
        }

        .content .subtitle {
            color: #64748b;
            font-size: 14px;
            margin: 0;
            line-height: 1.6;
        }

        .alert {
            padding: 14px 18px;
            margin: 15px 0;
            border-radius: 14px;
            font-weight: 500;
            border-left: 4px solid;
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

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border-left-color: #10b981;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left-color: #3b82f6;
        }

        .info-box {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 1px solid #bbf7d0;
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .info-box .icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .info-box .text {
            color: #065f46;
            font-size: 13px;
            line-height: 1.7;
        }

        .info-box .text strong {
            font-weight: 700;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin: 10px 0 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 650;
            color: #0f172a;
            font-size: 13px;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-group input {
            width: 100%;
            padding: 13px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 13px;
            font-size: 14px;
            color: #0f172a;
            transition: border-color 0.25s ease, box-shadow 0.25s ease, background 0.25s ease, transform 0.25s ease;
            background: #f8fafc;
        }

        .form-group input:hover {
            border-color: #cbd5e1;
            background: #ffffff;
            transform: translateY(-1px);
        }

        .form-group input:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.10);
            background: #ffffff;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .password-requirements {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
        }

        .existing-class-wrapper {
            margin-top: 8px;
        }

        .existing-class-label {
            display: block;
            color: #64748b;
            font-size: 11px;
            margin-bottom: 7px;
        }

        .existing-classes {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .existing-class-tag {
            background: #f1f5f9;
            color: #334155;
            padding: 5px 11px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .existing-class-tag:hover {
            background: #ecfdf5;
            border-color: #99f6e4;
            color: #0f766e;
            transform: translateY(-2px);
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
            padding-top: 8px;
        }

        .btn-premium {
            padding: 13px 22px;
            border-radius: 13px;
            font-weight: 650;
            font-size: 13px;
            transition: transform 0.25s ease, box-shadow 0.25s ease, filter 0.25s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #ffffff;
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
            filter: brightness(1.03);
        }

        .btn-premium:active {
            transform: translateY(0);
        }

        .btn-success {
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.25);
        }

        .btn-success:hover {
            box-shadow: 0 12px 35px rgba(13, 148, 136, 0.32);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.22);
        }

        .btn-warning:hover {
            box-shadow: 0 12px 35px rgba(245, 158, 11, 0.30);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            .content {
                padding: 22px;
                border-radius: 22px;
                margin: 10px auto;
            }
            .content h2 {
                font-size: 23px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .actions {
                flex-direction: column;
            }
            .actions .btn-premium {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 18px;
            }
            .info-box {
                padding: 13px;
            }
            .content h2 {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="content">
            <div class="page-header">
                <h2>➕ Add New Student</h2>
                <p class="subtitle">Create a student account and assign them to one of your classes.</p>
            </div>

            <div class="info-box">
                <span class="icon">📝</span>
                <div class="text">
                    <strong>Students are organized by class.</strong><br>
                    You can create multiple classes and assign each student to the appropriate class.
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-error">❌ <?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" id="addStudentForm" onsubmit="return validateForm('addStudentForm')" autocomplete="off">

                <div class="form-row">
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" required minlength="3" maxlength="50" autocomplete="username" placeholder="Choose username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" required maxlength="150" autocomplete="email" placeholder="Enter email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" required maxlength="100" autocomplete="name" placeholder="Enter full name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Student ID <span class="required">*</span></label>
                        <input type="text" name="student_id" required maxlength="50" placeholder="e.g. STU001" value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" required minlength="6" autocomplete="new-password" placeholder="Minimum 6 characters">
                        <div class="password-requirements">🔒 Password must contain at least 6 characters.</div>
                    </div>

                    <div class="form-group">
                        <label>Class Name <span class="required">*</span></label>
                        <input type="text" name="class_name" required maxlength="50" placeholder="e.g. 12C, 11A, 10B" value="<?php echo isset($_POST['class_name']) ? htmlspecialchars($_POST['class_name']) : ''; ?>">

                        <?php if (!empty($existing_classes)): ?>
                            <div class="existing-class-wrapper">
                                <span class="existing-class-label">Your existing classes:</span>
                                <div class="existing-classes">
                                    <?php foreach ($existing_classes as $class): ?>
                                        <span class="existing-class-tag">📚 <?php echo htmlspecialchars($class); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn-premium btn-success">➕ Add Student</button>
                    <a href="manage_students.php" class="btn-premium btn-warning">❌ Cancel</a>
                </div>

            </form>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>