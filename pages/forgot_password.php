<?php
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';
$email = '';
$redirectUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $deleteStmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $deleteStmt->execute([$user['id']]);

                $insertStmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $insertStmt->execute([$user['id'], $token, $expires_at]);

                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email'] = $email;

                $message = 'Your password reset request was successful. You will be redirected to the password reset page.';
                $redirectUrl = 'reset_password.php?token=' . urlencode($token);
            } else {
                $message = 'If an account exists with this email, you will receive a password reset link.';
                $redirectUrl = null;
            }
        } catch (PDOException $e) {
            $error = 'We could not process your request right now. Please try again later.';
            $redirectUrl = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="Reset your Mabaso Secondary School account password.">
    <title>Forgot Password | Mabaso Secondary School</title>

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

        html,
        body {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 18px;
            color: var(--navy);
            background:
                radial-gradient(circle at 10% 10%, rgba(45, 212, 191, 0.10), transparent 28%),
                radial-gradient(circle at 90% 90%, rgba(5, 150, 105, 0.08), transparent 30%),
                linear-gradient(135deg, #f8fafc, #eef5f5);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        a {
            text-decoration: none;
        }

        .page {
            width: 100%;
            max-width: 950px;
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
            overflow: hidden;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 25px 70px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .brand-panel {
            position: relative;
            display: flex;
            align-items: center;
            padding: 48px;
            overflow: hidden;
            color: #ffffff;
            background:
                radial-gradient(circle at 90% 10%, rgba(45, 212, 191, 0.18), transparent 32%),
                linear-gradient(145deg, #07111f, #0f172a 60%, #134e4a);
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            right: -190px;
            bottom: -150px;
            border: 1px solid rgba(45, 212, 191, 0.12);
            border-radius: 50%;
            box-shadow:
                0 0 0 55px rgba(45, 212, 191, 0.025),
                0 0 0 110px rgba(45, 212, 191, 0.015);
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            width: 55px;
            height: 55px;
            display: grid;
            place-items: center;
            margin-bottom: 25px;
            border-radius: 16px;
            font-size: 26px;
            background: linear-gradient(135deg, var(--teal-light), var(--green));
            box-shadow: 0 14px 30px rgba(13, 148, 136, 0.28);
        }

        .brand-eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            color: #5eead4;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .brand-content h1 {
            margin-bottom: 17px;
            font-size: 35px;
            line-height: 1.08;
            letter-spacing: -1.7px;
        }

        .brand-content h1 span {
            color: var(--teal-light);
        }

        .brand-content p {
            color: rgba(255, 255, 255, 0.64);
            font-size: 12px;
            line-height: 1.8;
        }

        .security-list {
            display: grid;
            gap: 12px;
            margin-top: 27px;
        }

        .security-item {
            display: flex;
            align-items: center;
            gap: 9px;
            color: rgba(255, 255, 255, 0.70);
            font-size: 10px;
        }

        .security-icon {
            width: 23px;
            height: 23px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 7px;
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.12);
        }

        .form-panel {
            padding: 50px 45px;
        }

        .form-container {
            width: 100%;
            max-width: 430px;
            margin: 0 auto;
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
            font-size: 22px;
        }

        .form-header {
            margin-bottom: 25px;
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 8px;
            color: var(--teal);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .form-header h2 {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 28px;
            line-height: 1.15;
            letter-spacing: -1.2px;
        }

        .form-header p {
            color: var(--slate-500);
            font-size: 12px;
            line-height: 1.7;
        }

        .info-box {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-bottom: 21px;
            padding: 12px 13px;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            background: var(--green-bg);
        }

        .info-icon {
            width: 25px;
            height: 25px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 7px;
            color: var(--green);
            background: #d1fae5;
            font-size: 11px;
            font-weight: 800;
        }

        .info-text {
            color: #065f46;
            font-size: 10px;
            line-height: 1.6;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-bottom: 18px;
            padding: 11px 13px;
            border-radius: 9px;
            font-size: 10px;
            line-height: 1.5;
        }

        .alert-success {
            border: 1px solid #a7f3d0;
            color: #065f46;
            background: var(--green-bg);
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

        .form-group {
            display: grid;
            gap: 7px;
            margin-bottom: 18px;
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
            color: var(--slate-400);
            font-size: 14px;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            height: 48px;
            padding: 0 13px 0 38px;
            border: 1px solid var(--slate-200);
            border-radius: 10px;
            outline: none;
            color: var(--navy);
            background: #ffffff;
            font-family: inherit;
            font-size: 12px;
            transition: all 0.2s ease;
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

        .btn-reset {
            width: 100%;
            height: 49px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 4px;
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

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(13, 148, 136, 0.28);
        }

        .btn-reset:active {
            transform: translateY(0);
        }

        .footer-links {
            margin-top: 23px;
            padding-top: 19px;
            border-top: 1px solid var(--slate-200);
            text-align: center;
            color: var(--slate-500);
            font-size: 10px;
        }

        .footer-links a {
            color: var(--teal);
            font-weight: 800;
        }

        .footer-links a:hover {
            color: var(--green);
        }

        .divider {
            margin: 0 8px;
            color: var(--slate-300);
        }

        .back-home {
            display: block;
            margin-top: 13px;
            text-align: center;
            color: var(--slate-400);
            font-size: 9px;
            font-weight: 600;
        }

        .back-home:hover {
            color: var(--teal);
        }

        @media (max-width: 850px) {
            .page {
                grid-template-columns: 1fr;
                max-width: 520px;
            }
            .brand-panel {
                display: none;
            }
            .form-panel {
                padding: 40px 30px;
            }
            .mobile-logo {
                display: grid;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            .page {
                border-radius: 19px;
            }
            .form-panel {
                padding: 30px 20px;
            }
            .form-header h2 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

    <div class="page">

        <aside class="brand-panel">
            <div class="brand-content">
                <div class="brand-logo">🔑</div>
                <span class="brand-eyebrow">Mabaso Secondary School</span>
                <h1>Secure your <span>account.</span></h1>
                <p>Forgot your password? Don't worry. Enter your registered email address and we'll help you regain access to your school management account.</p>
                <div class="security-list">
                    <div class="security-item">
                        <span class="security-icon">✓</span>
                        <span>Secure password recovery</span>
                    </div>
                    <div class="security-item">
                        <span class="security-icon">✓</span>
                        <span>Reset links expire after one hour</span>
                    </div>
                    <div class="security-item">
                        <span class="security-icon">✓</span>
                        <span>Your account information stays protected</span>
                    </div>
                </div>
            </div>
        </aside>

        <main class="form-panel">
            <div class="form-container">
                <div class="mobile-logo">🔑</div>

                <div class="form-header">
                    <span class="eyebrow">Account Recovery</span>
                    <h2>Forgot your password?</h2>
                    <p>Enter the email address associated with your account and we'll help you reset your password.</p>
                </div>

                <div class="info-box">
                    <div class="info-icon">i</div>
                    <div class="info-text">Enter your registered email address. If an account exists, a password reset option will be provided.</div>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="alert alert-success" role="alert">
                        <span class="alert-icon">✓</span>
                        <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error" role="alert">
                        <span class="alert-icon">!</span>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="resetForm" novalidate>
                    <div class="form-group">
                        <label for="email" class="form-label">Email address <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">✉</span>
                            <input type="email" id="email" name="email" class="form-input" placeholder="Enter your registered email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-reset" id="resetButton">
                        <span>✉</span>
                        <span>Send reset link</span>
                    </button>
                </form>

                <div class="footer-links">
                    <a href="signin.php">Back to login</a>
                    <span class="divider">|</span>
                    <a href="../index.php">Home</a>
                </div>

                <a href="../index.php" class="back-home">← Back to Mabaso Secondary School</a>
            </div>
        </main>

    </div>

    <script>
        const form = document.getElementById('resetForm');
        const email = document.getElementById('email');
        const button = document.getElementById('resetButton');

        if (form && email) {
            form.addEventListener('submit', function(event) {
                const value = email.value.trim();
                email.classList.remove('error');

                if (value === '') {
                    event.preventDefault();
                    email.classList.add('error');
                    email.focus();
                    return;
                }

                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!emailPattern.test(value)) {
                    event.preventDefault();
                    email.classList.add('error');
                    email.focus();
                    return;
                }

                if (button) {
                    button.style.opacity = '0.75';
                    button.style.pointerEvents = 'none';
                    button.innerHTML = '<span>⏳</span><span>Processing...</span>';
                }
            });

            email.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('error');
                }
            });
        }
    </script>

    <?php if (!empty($redirectUrl)): ?>
        <script>
            setTimeout(function() {
                window.location.href = <?php echo json_encode($redirectUrl); ?>;
            }, 2500);
        </script>
    <?php endif; ?>

</body>

</html>