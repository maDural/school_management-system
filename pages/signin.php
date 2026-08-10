<?php

/*
|--------------------------------------------------------------------------
| Mabaso Secondary School
| Login Page
|--------------------------------------------------------------------------
| File location:
| C:/xampp/htdocs/school_management/pages/login.php
|--------------------------------------------------------------------------
*/

require_once '../config/database.php';

/*
|--------------------------------------------------------------------------
| Start session if it has not already been started
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| If the user is already logged in, send them to their dashboard
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {

    if ($_SESSION['role'] === 'teacher') {

        header('Location: teacher/dashboard.php');
        exit;

    } elseif ($_SESSION['role'] === 'student') {

        header('Location: student/dashboard.php');
        exit;

    }
}


/*
|--------------------------------------------------------------------------
| Login processing
|--------------------------------------------------------------------------
*/

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Get submitted values
    |--------------------------------------------------------------------------
    */

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate input
    |--------------------------------------------------------------------------
    */

    if ($username === '' || $password === '') {

        $error = 'Please enter your username/email and password.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Find user by username OR email
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM users
                 WHERE username = :username
                    OR email = :email
                 LIMIT 1"
            );

            $stmt->execute([
                ':username' => $username,
                ':email'    => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | Verify password
            |--------------------------------------------------------------------------
            */

            if ($user && password_verify($password, $user['password'])) {

                /*
                |--------------------------------------------------------------------------
                | Regenerate session ID after successful login
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);


                /*
                |--------------------------------------------------------------------------
                | Store authenticated user information
                |--------------------------------------------------------------------------
                */

                $_SESSION['user_id']   = (int) $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];


                /*
                |--------------------------------------------------------------------------
                | Redirect according to user role
                |--------------------------------------------------------------------------
                */

                if ($user['role'] === 'teacher') {

                    /*
                    * login.php is inside /pages/
                    * Therefore this is:
                    * /pages/teacher/dashboard.php
                    */

                    header('Location: teacher/dashboard.php');
                    exit;


                } elseif ($user['role'] === 'student') {

                    /*
                    * login.php is inside /pages/
                    * Therefore this is:
                    * /pages/student/dashboard.php
                    */

                    header('Location: student/dashboard.php');
                    exit;


                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Unknown role
                    |--------------------------------------------------------------------------
                    */

                    $error = 'Your account has an invalid user role. Please contact the administrator.';

                    /*
                    * Remove the session information because
                    * this user cannot be redirected safely.
                    */

                    unset(
                        $_SESSION['user_id'],
                        $_SESSION['username'],
                        $_SESSION['full_name'],
                        $_SESSION['role']
                    );
                }


            } else {

                /*
                |--------------------------------------------------------------------------
                | Invalid credentials
                |--------------------------------------------------------------------------
                */

                $error = 'Invalid username/email or password.';
            }


        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | Database error
            |--------------------------------------------------------------------------
            |
            | Do not expose the actual database error to users.
            |
            */

            $error = 'Unable to process your login right now. Please try again later.';
        }

    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to the Mabaso Secondary School management portal.">
    <meta name="theme-color" content="#0f172a">

    <title>Sign In | Mabaso Secondary School</title>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
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
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --danger: #dc2626;
            --danger-bg: #fef2f2;
        }


        html,
        body {
            min-height: 100%;
        }


        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--navy);
            background: var(--slate-50);
        }


        a {
            text-decoration: none;
        }


        /* =========================================================
           PAGE
        ========================================================= */

        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }


        /* =========================================================
           LEFT SIDE
        ========================================================= */

        .login-brand-panel {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
            overflow: hidden;
            color: #ffffff;
            background:
                radial-gradient(circle at 80% 20%, rgba(45, 212, 191, 0.20), transparent 30%),
                radial-gradient(circle at 15% 80%, rgba(16, 185, 129, 0.15), transparent 30%),
                linear-gradient(135deg, #07111f, #0f172a 55%, #134e4a);
        }


        .login-brand-panel::before {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            right: -250px;
            top: 50%;
            transform: translateY(-50%);
            border: 1px solid rgba(45, 212, 191, 0.14);
            border-radius: 50%;
            box-shadow:
                0 0 0 70px rgba(45, 212, 191, 0.025),
                0 0 0 140px rgba(45, 212, 191, 0.018);
        }


        .brand-content {
            position: relative;
            z-index: 2;
            max-width: 520px;
        }


        .brand-logo {
            width: 55px;
            height: 55px;
            display: grid;
            place-items: center;
            margin-bottom: 25px;
            border-radius: 16px;
            font-size: 27px;
            background: linear-gradient(135deg, var(--teal-light), var(--green));
            box-shadow: 0 15px 35px rgba(13, 148, 136, 0.30);
        }


        .brand-content h1 {
            margin-bottom: 18px;
            font-size: clamp(38px, 4vw, 58px);
            line-height: 1.05;
            letter-spacing: -2.5px;
        }


        .brand-content h1 span {
            color: var(--teal-light);
        }


        .brand-content > p {
            max-width: 490px;
            color: rgba(255, 255, 255, 0.65);
            font-size: 15px;
            line-height: 1.8;
        }


        .brand-features {
            display: grid;
            gap: 13px;
            margin-top: 32px;
        }


        .brand-feature {
            display: flex;
            align-items: center;
            gap: 11px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
        }


        .brand-feature-icon {
            width: 25px;
            height: 25px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 50%;
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.12);
        }


        /* =========================================================
           RIGHT SIDE
        ========================================================= */

        .login-form-panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 45px 30px;
            background: #ffffff;
        }


        .login-container {
            width: 100%;
            max-width: 430px;
        }


        .mobile-logo {
            display: none;
        }


        .login-heading {
            margin-bottom: 28px;
        }


        .login-heading .eyebrow {
            display: inline-block;
            margin-bottom: 9px;
            color: var(--teal);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }


        .login-heading h2 {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 31px;
            line-height: 1.15;
            letter-spacing: -1.3px;
        }


        .login-heading p {
            color: var(--slate-500);
            font-size: 13px;
            line-height: 1.7;
        }


        /* =========================================================
           ERROR
        ========================================================= */

        .login-error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            color: #991b1b;
            background: var(--danger-bg);
            font-size: 12px;
            line-height: 1.5;
        }


        .login-error-icon {
            flex-shrink: 0;
            font-weight: 800;
        }


        /* =========================================================
           FORM
        ========================================================= */

        .login-form {
            display: grid;
            gap: 18px;
        }


        .form-group {
            display: grid;
            gap: 7px;
        }


        .form-label {
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
        }


        .input-wrapper {
            position: relative;
        }


        .form-input {
            width: 100%;
            height: 50px;
            padding: 0 15px;
            border: 1px solid var(--slate-200);
            border-radius: 11px;
            outline: none;
            color: var(--navy);
            background: #ffffff;
            font-family: inherit;
            font-size: 13px;
            transition: all 0.2s ease;
        }


        .form-input::placeholder {
            color: var(--slate-400);
        }


        .form-input:hover {
            border-color: #cbd5e1;
        }


        .form-input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.09);
        }


        .password-input {
            padding-right: 75px;
        }


        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            cursor: pointer;
            color: var(--slate-500);
            background: transparent;
            font-size: 11px;
            font-weight: 700;
        }


        .password-toggle:hover {
            color: var(--teal);
        }


        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: -3px;
        }


        .remember-me {
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--slate-500);
            font-size: 11px;
            cursor: pointer;
        }


        .remember-me input {
            width: 14px;
            height: 14px;
            accent-color: var(--teal);
            cursor: pointer;
        }


        .forgot-password {
            color: var(--teal);
            font-size: 11px;
            font-weight: 700;
        }


        .forgot-password:hover {
            color: var(--green);
        }


        .login-button {
            width: 100%;
            height: 51px;
            margin-top: 2px;
            border: 0;
            border-radius: 11px;
            cursor: pointer;
            color: #ffffff;
            background: linear-gradient(135deg, var(--teal), var(--green));
            box-shadow: 0 12px 28px rgba(13, 148, 136, 0.20);
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            transition: all 0.25s ease;
        }


        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 35px rgba(13, 148, 136, 0.28);
        }


        .login-button:active {
            transform: translateY(0);
        }


        /* =========================================================
           REGISTER
        ========================================================= */

        .register-prompt {
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid var(--slate-200);
            text-align: center;
            color: var(--slate-500);
            font-size: 12px;
        }


        .register-prompt a {
            color: var(--teal);
            font-weight: 800;
        }


        .register-prompt a:hover {
            color: var(--green);
        }


        /* =========================================================
           BACK HOME
        ========================================================= */

        .back-home {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: var(--slate-400);
            font-size: 11px;
            font-weight: 600;
        }


        .back-home:hover {
            color: var(--teal);
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 900px) {

            .login-page {
                grid-template-columns: 1fr;
            }

            .login-brand-panel {
                display: none;
            }

            .login-form-panel {
                min-height: 100vh;
                padding: 35px 22px;
            }

            .mobile-logo {
                width: 48px;
                height: 48px;
                display: grid;
                place-items: center;
                margin: 0 auto 24px;
                border-radius: 14px;
                color: #ffffff;
                background: linear-gradient(135deg, var(--teal-light), var(--green));
                font-size: 23px;
                box-shadow: 0 12px 28px rgba(13, 148, 136, 0.25);
            }
        }


        @media (max-width: 480px) {

            .login-form-panel {
                padding: 28px 18px;
            }

            .login-heading h2 {
                font-size: 28px;
            }

            .login-options {
                align-items: flex-start;
                flex-direction: column;
            }
        }

    </style>

</head>


<body>


<div class="login-page">


    <!-- ========================================================
         LEFT BRAND PANEL
    ========================================================= -->

    <section class="login-brand-panel">

        <div class="brand-content">

            <div class="brand-logo">🏫</div>

            <h1>
                Welcome to
                <span>
                    Mabaso Secondary.
                </span>
            </h1>

            <p>
                Your central school management and
                learning platform. Access lessons,
                attendance, assignments, quizzes,
                results and more from one place.
            </p>

            <div class="brand-features">

                <div class="brand-feature">
                    <span class="brand-feature-icon">✓</span>
                    <span>Manage school activities in one place</span>
                </div>

                <div class="brand-feature">
                    <span class="brand-feature-icon">✓</span>
                    <span>Dedicated teacher and student dashboards</span>
                </div>

                <div class="brand-feature">
                    <span class="brand-feature-icon">✓</span>
                    <span>Access your learning information anywhere</span>
                </div>

            </div>

        </div>

    </section>


    <!-- ========================================================
         LOGIN FORM PANEL
    ========================================================= -->

    <section class="login-form-panel">

        <div class="login-container">

            <!-- Mobile logo -->
            <div class="mobile-logo">🏫</div>

            <!-- Heading -->
            <div class="login-heading">
                <span class="eyebrow">School Portal</span>
                <h2>Sign in to your account</h2>
                <p>Enter your details below to access your Mabaso Secondary dashboard.</p>
            </div>

            <!-- Error message -->
            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert">
                    <span class="login-error-icon">!</span>
                    <span>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Login form -->
            <form method="POST" action="" class="login-form" autocomplete="on">

                <!-- Username / Email -->
                <div class="form-group">
                    <label for="username" class="form-label">Username or email</label>
                    <div class="input-wrapper">
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input"
                            placeholder="Enter your username or email"
                            value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input password-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="password-toggle" id="passwordToggle">Show</button>
                    </div>
                </div>

                <!-- Options -->
                <div class="login-options">

                    <label class="remember-me">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>

                    <a href="forgot_password.php" class="forgot-password">Forgot password?</a>

                </div>

                <!-- Submit -->
                <button type="submit" class="login-button">Sign in to portal</button>

            </form>

            <!-- Registration -->
            <div class="register-prompt">
                Don't have a teacher account?
                <a href="register.php">Register here</a>
            </div>

            <!-- Back home -->
            <a href="../index.php" class="back-home">← Back to homepage</a>

        </div>

    </section>


</div>


<!-- ============================================================
     PASSWORD VISIBILITY
============================================================ -->

<script>
    (function () {
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');

        if (!passwordInput || !passwordToggle) {
            return;
        }

        passwordToggle.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                passwordToggle.textContent = 'Show';
            }
        });
    })();
</script>


</body>

</html>