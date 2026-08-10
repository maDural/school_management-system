<?php
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('isLoggedIn') && isLoggedIn()) {
    if (function_exists('isTeacher') && isTeacher()) {
        header('Location: teacher/dashboard.php');
        exit;
    } else {
        header('Location: student/dashboard.php');
        exit;
    }
}

$errors = [];
$error = '';
$username = '';
$email = '';
$full_name = '';
$department = '';
$phone = '';
$nat_emis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $nat_emis = trim($_POST['nat_emis'] ?? '');

    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($department === '') {
        $errors[] = 'Department is required.';
    }

    if ($nat_emis === '') {
        $errors[] = 'School NatEmis code is required.';
    } elseif ($nat_emis !== '500415029') {
        $errors[] = 'Invalid NatEmis code. Please enter the correct school code.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);

            if ($stmt->fetch()) {
                throw new Exception('Username or email already exists.');
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'teacher')");
            $stmt->execute([$username, $email, $hashed_password, $full_name]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, department, phone) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $department, $phone]);

            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = 'teacher';

            header('Location: teacher/dashboard.php?success=' . urlencode('Registration successful! Welcome to Mabaso Secondary School.'));
            exit;

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
    <meta name="description" content="Register as a teacher at Mabaso Secondary School.">
    <meta name="theme-color" content="#0f172a">
    <title>Teacher Registration | Mabaso Secondary School</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --navy: #0f172a;
            --navy-dark: #07111f;
            --teal: #0d9488;
            --teal-light: #2dd4bf;
            --green: #059669;
            --white: #ffffff;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --red: #dc2626;
            --red-bg: #fef2f2;
            --green-bg: #ecfdf5;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--navy);
            background: var(--slate-50);
        }

        a {
            text-decoration: none;
        }

        .register-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
        }

        .brand-panel {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
            overflow: hidden;
            color: var(--white);
            background:
                radial-gradient(circle at 85% 15%, rgba(45, 212, 191, 0.20), transparent 30%),
                radial-gradient(circle at 15% 85%, rgba(5, 150, 105, 0.20), transparent 32%),
                linear-gradient(135deg, #07111f 0%, #0f172a 55%, #134e4a 100%);
        }

        .brand-panel::before {
            content: "";
            position: absolute;
            width: 650px;
            height: 650px;
            right: -360px;
            top: 50%;
            transform: translateY(-50%);
            border: 1px solid rgba(45, 212, 191, 0.13);
            border-radius: 50%;
            box-shadow: 0 0 0 80px rgba(45, 212, 191, 0.025), 0 0 0 160px rgba(45, 212, 191, 0.018);
        }

        .brand-content {
            position: relative;
            z-index: 2;
            max-width: 510px;
        }

        .brand-logo {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            margin-bottom: 27px;
            border-radius: 17px;
            font-size: 27px;
            background: linear-gradient(135deg, var(--teal-light), var(--green));
            box-shadow: 0 15px 35px rgba(13, 148, 136, 0.28);
        }

        .brand-eyebrow {
            display: inline-block;
            margin-bottom: 12px;
            color: #5eead4;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .brand-content h1 {
            margin-bottom: 20px;
            font-size: clamp(38px, 4vw, 56px);
            line-height: 1.04;
            letter-spacing: -2.5px;
        }

        .brand-content h1 span {
            color: var(--teal-light);
        }

        .brand-description {
            max-width: 475px;
            color: rgba(255, 255, 255, 0.66);
            font-size: 14px;
            line-height: 1.85;
        }

        .benefits {
            display: grid;
            gap: 13px;
            margin-top: 34px;
        }

        .benefit {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
        }

        .benefit-icon {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 50%;
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(110, 231, 183, 0.08);
        }

        .form-panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 45px;
            background: #ffffff;
            overflow-y: auto;
        }

        .register-container {
            width: 100%;
            max-width: 600px;
        }

        .mobile-logo {
            display: none;
            width: 48px;
            height: 48px;
            place-items: center;
            margin: 0 auto 22px;
            border-radius: 14px;
            color: #ffffff;
            background: linear-gradient(135deg, var(--teal-light), var(--green));
            font-size: 23px;
            box-shadow: 0 12px 28px rgba(13, 148, 136, 0.22);
        }

        .register-heading {
            margin-bottom: 25px;
        }

        .register-heading .eyebrow {
            display: inline-block;
            margin-bottom: 8px;
            color: var(--teal);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .register-heading h2 {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 30px;
            line-height: 1.15;
            letter-spacing: -1.2px;
        }

        .register-heading p {
            color: var(--slate-500);
            font-size: 13px;
            line-height: 1.7;
        }

        .info-box {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 23px;
            padding: 13px 15px;
            border: 1px solid #a7f3d0;
            border-radius: 11px;
            background: var(--green-bg);
        }

        .info-icon {
            width: 27px;
            height: 27px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 8px;
            color: var(--green);
            background: #d1fae5;
            font-size: 12px;
            font-weight: 800;
        }

        .info-text {
            color: #065f46;
            font-size: 11px;
            line-height: 1.65;
        }

        .info-text strong {
            display: block;
            margin-bottom: 2px;
            color: var(--navy);
            font-size: 11px;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
            padding: 11px 13px;
            border-radius: 10px;
            font-size: 11px;
            line-height: 1.5;
        }

        .alert-error {
            border: 1px solid #fecaca;
            color: #991b1b;
            background: var(--red-bg);
        }

        .alert-icon {
            flex-shrink: 0;
            font-weight: 800;
        }

        .registration-form {
            display: grid;
            gap: 17px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            display: grid;
            gap: 7px;
        }

        .form-label {
            color: var(--navy);
            font-size: 11px;
            font-weight: 700;
        }

        .required {
            color: var(--red);
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            color: var(--slate-400);
            font-size: 13px;
            text-align: center;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            height: 46px;
            padding: 0 13px 0 39px;
            border: 1px solid var(--slate-200);
            border-radius: 10px;
            outline: none;
            color: var(--navy);
            background: #ffffff;
            font-family: inherit;
            font-size: 12px;
            transition: all 0.2s ease;
        }

        .form-input::placeholder {
            color: var(--slate-400);
        }

        .form-input:hover {
            border-color: var(--slate-300);
        }

        .form-input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.08);
        }

        .form-input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.08);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-input {
            padding-right: 58px;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            cursor: pointer;
            color: var(--slate-500);
            background: transparent;
            font-family: inherit;
            font-size: 10px;
            font-weight: 700;
        }

        .password-toggle:hover {
            color: var(--teal);
        }

        .password-hint {
            margin-top: 1px;
            color: var(--slate-400);
            font-size: 9px;
        }

        .btn-register {
            width: 100%;
            height: 49px;
            margin-top: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            color: #ffffff;
            background: linear-gradient(135deg, var(--teal), var(--green));
            box-shadow: 0 12px 27px rgba(13, 148, 136, 0.20);
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            transition: all 0.25s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(13, 148, 136, 0.28);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .register-footer {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--slate-200);
            text-align: center;
            color: var(--slate-500);
            font-size: 11px;
            line-height: 1.8;
        }

        .register-footer a {
            color: var(--teal);
            font-weight: 800;
        }

        .register-footer a:hover {
            color: var(--green);
        }

        .footer-divider {
            margin: 0 7px;
            color: var(--slate-300);
        }

        .back-home {
            display: block;
            margin-top: 12px;
            text-align: center;
            color: var(--slate-400);
            font-size: 10px;
            font-weight: 600;
        }

        .back-home:hover {
            color: var(--teal);
        }

        .btn-register.loading {
            opacity: 0.75;
            pointer-events: none;
        }

        .spinner {
            width: 15px;
            height: 15px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 950px) {
            .register-page {
                grid-template-columns: 1fr;
            }
            .brand-panel {
                display: none;
            }
            .form-panel {
                min-height: 100vh;
                padding: 40px 25px;
            }
            .mobile-logo {
                display: grid;
            }
        }

        @media (max-width: 600px) {
            .form-panel {
                padding: 30px 18px;
            }
            .register-heading h2 {
                font-size: 27px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 17px;
            }
            .info-box {
                padding: 12px;
            }
        }

        @media (max-width: 380px) {
            .register-heading h2 {
                font-size: 24px;
            }
            .form-input {
                height: 44px;
            }
        }
    </style>
</head>

<body>

    <div class="register-page">

        <aside class="brand-panel">
            <div class="brand-content">
                <div class="brand-logo">🏫</div>
                <span class="brand-eyebrow">Mabaso Secondary School</span>
                <h1>Empowering <span>educators.</span></h1>
                <p class="brand-description">
                    Create your teacher account and get access to the Mabaso Secondary School management platform. Manage students, lessons, attendance, assignments, quizzes and results from one secure dashboard.
                </p>
                <div class="benefits">
                    <div class="benefit">
                        <span class="benefit-icon">✓</span>
                        <span>Manage students and academic records</span>
                    </div>
                    <div class="benefit">
                        <span class="benefit-icon">✓</span>
                        <span>Create lessons, quizzes and assignments</span>
                    </div>
                    <div class="benefit">
                        <span class="benefit-icon">✓</span>
                        <span>Track attendance and student performance</span>
                    </div>
                    <div class="benefit">
                        <span class="benefit-icon">✓</span>
                        <span>Access everything from one dashboard</span>
                    </div>
                </div>
            </div>
        </aside>

        <main class="form-panel">
            <div class="register-container">

                <div class="mobile-logo">🏫</div>

                <div class="register-heading">
                    <span class="eyebrow">Teacher Portal</span>
                    <h2>Create your teacher account</h2>
                    <p>Complete the form below to register as a teacher at Mabaso Secondary School.</p>
                </div>

                <div class="info-box">
                    <div class="info-icon">i</div>
                    <div class="info-text">
                        <strong>Teacher registration only</strong>
                        Only teachers and lecturers can register here. Student accounts are created and managed by teachers.
                    </div>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error" role="alert">
                        <span class="alert-icon">!</span>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-error" role="alert">
                            <span class="alert-icon">!</span>
                            <span><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm" class="registration-form" autocomplete="on">

                    <div class="form-group">
                        <label for="full_name" class="form-label">Full name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input type="text" id="full_name" name="full_name" class="form-input" placeholder="Enter your full name" value="<?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="username" class="form-label">Username <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <span class="input-icon">@</span>
                                <input type="text" id="username" name="username" class="form-input" placeholder="Choose a username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" minlength="3" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email address <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <span class="input-icon">✉</span>
                                <input type="email" id="email" name="email" class="form-input" placeholder="name@example.com" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">Password <span class="required">*</span></label>
                            <div class="input-wrapper password-wrapper">
                                <span class="input-icon">•</span>
                                <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 6 characters" minlength="6" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" id="passwordToggle">Show</button>
                            </div>
                            <span class="password-hint">Use at least 6 characters.</span>
                        </div>
                        <div class="form-group">
                            <label for="department" class="form-label">Department <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <span class="input-icon">◆</span>
                                <input type="text" id="department" name="department" class="form-input" placeholder="e.g. Mathematics" value="<?php echo htmlspecialchars($department, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Phone number</label>
                        <div class="input-wrapper">
                            <span class="input-icon">☎</span>
                            <input type="tel" id="phone" name="phone" class="form-input" placeholder="e.g. +27 71 234 5678" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="tel">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nat_emis" class="form-label">School NatEmis Code <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔑</span>
                            <input type="password" id="nat_emis" name="nat_emis" class="form-input" placeholder="Enter the school NatEmis code" value="<?php echo htmlspecialchars($nat_emis, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <span class="password-hint">Contact the school administration for the NatEmis code.</span>
                    </div>

                    <button type="submit" class="btn-register" id="registerButton">
                        <span id="buttonIcon">→</span>
                        <span id="buttonText">Create teacher account</span>
                    </button>

                </form>

                <div class="register-footer">
                    Already have an account?
                    <a href="signin.php">Log in</a>
                    <span class="footer-divider">•</span>
                    <a href="../index.php">Home</a>
                </div>

                <a href="../index.php" class="back-home">← Back to Mabaso Secondary School</a>

            </div>
        </main>

    </div>

    <script>
        const password = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');

        if (password && passwordToggle) {
            passwordToggle.addEventListener('click', function() {
                if (password.type === 'password') {
                    password.type = 'text';
                    passwordToggle.textContent = 'Hide';
                } else {
                    password.type = 'password';
                    passwordToggle.textContent = 'Show';
                }
            });
        }

        const form = document.getElementById('registerForm');
        const button = document.getElementById('registerButton');
        const buttonText = document.getElementById('buttonText');
        const buttonIcon = document.getElementById('buttonIcon');

        if (form) {
            form.addEventListener('submit', function(event) {
                let valid = true;
                const requiredInputs = form.querySelectorAll('input[required]');

                requiredInputs.forEach(function(input) {
                    if (!input.value.trim()) {
                        input.classList.add('error');
                        valid = false;
                    } else {
                        input.classList.remove('error');
                    }
                });

                if (password && password.value.length > 0 && password.value.length < 6) {
                    password.classList.add('error');
                    valid = false;
                }

                if (!valid) {
                    event.preventDefault();
                    return;
                }

                button.classList.add('loading');
                buttonIcon.innerHTML = '<span class="spinner"></span>';
                buttonText.textContent = 'Creating account...';
            });
        }

        document.querySelectorAll('.form-input').forEach(function(input) {
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('error');
                }
            });
        });
    </script>

</body>

</html>