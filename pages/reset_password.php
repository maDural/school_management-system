<?php
require_once '../config/database.php';

$error = '';
$success = '';

// Get token from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    // Check if we have a token in session (for demo purposes)
    if (isset($_SESSION['reset_token'])) {
        $token = $_SESSION['reset_token'];
    } else {
        header("Location: forgot_password.php?error=Invalid or missing reset token");
        exit();
    }
}

// Verify token
$stmt = $pdo->prepare("
    SELECT pr.*, u.id as user_id, u.email, u.full_name 
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
");
$stmt->execute([$token]);
$reset_data = $stmt->fetch();

if (!$reset_data) {
    header("Location: forgot_password.php?error=Invalid or expired reset token");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password)) {
        $error = "Please enter a new password";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update user password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $reset_data['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Clear session tokens
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            
            $pdo->commit();
            
            $success = "Password reset successfully! You can now login with your new password.";
            
            // Redirect to login after 3 seconds
            header("Refresh: 3; URL=login.php?success=Password reset successfully. Please login.");
            exit();
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 440px;
            width: 100%;
        }
        
        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 28px;
            padding: 45px 40px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }
        
        .reset-card:hover {
            box-shadow: 0 25px 70px rgba(15, 23, 42, 0.15);
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .reset-header .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
        }
        
        .reset-header h1 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #0f172a, #0d9488);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }
        
        .reset-header .subtitle {
            color: #64748b;
            font-size: 14px;
            font-weight: 400;
        }
        
        .reset-header .user-info {
            background: #f1f5f9;
            padding: 10px 16px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 14px;
            color: #0f172a;
        }
        
        .reset-header .user-info strong {
            color: #0d9488;
        }
        
        .alert {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 14px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
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
            background: #d1fae5;
            color: #065f46;
            border-left-color: #0d9488;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        
        .alert .alert-icon {
            font-size: 18px;
        }
        
        .form-group {
            margin: 18px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #ef4444;
            margin-left: 2px;
        }
        
        .form-group .input-wrapper {
            position: relative;
        }
        
        .form-group .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #94a3b8;
            pointer-events: none;
        }
        
        .form-group input {
            width: 100%;
            padding: 13px 16px 13px 46px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
            color: #0f172a;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
            background: white;
        }
        
        .form-group input::placeholder {
            color: #94a3b8;
        }
        
        .form-group input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .btn-reset {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0f172a, #0d9488);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(13, 148, 136, 0.35);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 25px;
            color: #64748b;
            font-size: 14px;
        }
        
        .footer-links a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: #059669;
            text-decoration: underline;
        }
        
        .footer-links .divider {
            display: inline-block;
            margin: 0 8px;
            color: #e2e8f0;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 5px;
        }
        
        @media (max-width: 480px) {
            .reset-card {
                padding: 30px 24px;
                border-radius: 24px;
            }
            
            .reset-header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-card">
            <div class="reset-header">
                <span class="icon">🔐</span>
                <h1>Reset Password</h1>
                <p class="subtitle">Create a new password for your account</p>
                <div class="user-info">
                    👤 Resetting password for: <strong><?php echo htmlspecialchars($reset_data['full_name']); ?></strong>
                    <br>
                    📧 <?php echo htmlspecialchars($reset_data['email']); ?>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">❌</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="resetForm">
                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" id="password" required placeholder="Min 6 characters">
                    </div>
                    <div class="password-requirements">Password must be at least 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">✅</span>
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm your new password">
                    </div>
                </div>
                
                <button type="submit" class="btn-reset">
                    <span class="btn-icon">🔄</span> Reset Password
                </button>
            </form>
            
            <div class="footer-links">
                <a href="login.php">Back to Login</a>
                <span class="divider">|</span>
                <a href="../index.php">Home</a>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            let isValid = true;
            
            // Clear errors
            password.classList.remove('error');
            confirm.classList.remove('error');
            
            // Validate password
            if (!password.value.trim()) {
                password.classList.add('error');
                isValid = false;
            } else if (password.value.length < 6) {
                password.classList.add('error');
                alert('Password must be at least 6 characters');
                e.preventDefault();
                return false;
            }
            
            // Validate confirm password
            if (!confirm.value.trim()) {
                confirm.classList.add('error');
                isValid = false;
            } else if (password.value !== confirm.value) {
                confirm.classList.add('error');
                alert('Passwords do not match');
                e.preventDefault();
                return false;
            }
            
            if (!isValid) {
                alert('Please fill in all fields');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('error');
                }
            });
        });
    </script>
</body>
</html>