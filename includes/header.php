<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    box-sizing: border-box;
}

.navbar-premium {
    background:
        radial-gradient(circle at 15% 0%, rgba(45, 212, 191, 0.10), transparent 28%),
        radial-gradient(circle at 90% 100%, rgba(59, 130, 246, 0.08), transparent 30%),
        linear-gradient(135deg, #020617 0%, #0f172a 35%, #123c50 65%, #064e3b 100%);
    padding: 0 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 76px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.25), 0 2px 8px rgba(15, 23, 42, 0.12);
    position: sticky;
    top: 0;
    z-index: 1000;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 1px solid rgba(45, 212, 191, 0.22);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.navbar-premium::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #2dd4bf, #0d9488, #3b82f6, transparent);
    opacity: 0.8;
}

.navbar-premium .brand {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: max-content;
}

.navbar-premium .brand .logo-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    border-radius: 13px;
    background: linear-gradient(135deg, rgba(45, 212, 191, 0.22), rgba(13, 148, 136, 0.12));
    border: 1px solid rgba(45, 212, 191, 0.20);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.10), 0 6px 18px rgba(13, 148, 136, 0.12);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.navbar-premium .brand:hover .logo-icon {
    transform: translateY(-2px) rotate(-2deg);
    box-shadow: 0 8px 22px rgba(13, 148, 136, 0.20);
}

.navbar-premium .brand .header-title {
    font-size: 20px;
    line-height: 1.15;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.4px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.22);
    white-space: nowrap;
}

.navbar-premium .brand .header-title span {
    font-weight: 500;
    color: #5eead4;
}

.navbar-premium .nav-links {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    flex-wrap: wrap;
}

.navbar-premium .nav-links a {
    color: rgba(255, 255, 255, 0.70);
    text-decoration: none;
    padding: 10px 14px;
    border-radius: 10px;
    transition: color 0.25s ease, background 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
    font-size: 12px;
    font-weight: 600;
    position: relative;
    white-space: nowrap;
}

.navbar-premium .nav-links a::after {
    content: "";
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 2px;
    background: #5eead4;
    border-radius: 100px;
    transition: width 0.3s ease;
}

.navbar-premium .nav-links a:hover {
    background: rgba(45, 212, 191, 0.10);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.navbar-premium .nav-links a:hover::after {
    width: 55%;
}

.navbar-premium .nav-links a.active {
    background: linear-gradient(135deg, rgba(45, 212, 191, 0.18), rgba(13, 148, 136, 0.10));
    color: #ffffff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 4px 15px rgba(13, 148, 136, 0.10);
    border: 1px solid rgba(45, 212, 191, 0.10);
}

.navbar-premium .nav-links a.active::after {
    width: 55%;
    background: #2dd4bf;
}

.navbar-premium .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.navbar-premium .user-info .user-profile {
    display: flex;
    align-items: center;
    gap: 9px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.07), rgba(45, 212, 191, 0.07));
    padding: 5px 13px 5px 5px;
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: background 0.25s ease, transform 0.25s ease, border-color 0.25s ease;
}

.navbar-premium .user-info .user-profile:hover {
    background: linear-gradient(135deg, rgba(45, 212, 191, 0.13), rgba(45, 212, 191, 0.08));
    transform: translateY(-1px);
    border-color: rgba(45, 212, 191, 0.18);
}

.navbar-premium .user-info .user-avatar {
    width: 37px;
    height: 37px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2dd4bf 0%, #0d9488 50%, #059669 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 14px;
    color: #ffffff;
    border: 2px solid rgba(255, 255, 255, 0.16);
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
}

.navbar-premium .user-info .user-name {
    color: #ffffff;
    font-weight: 650;
    font-size: 12px;
    line-height: 1.2;
    max-width: 135px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.navbar-premium .user-info .user-role {
    color: #99f6e4;
    font-size: 9px;
    font-weight: 600;
    background: rgba(45, 212, 191, 0.10);
    padding: 2px 8px;
    border-radius: 50px;
    display: inline-block;
    margin-top: 3px;
    border: 1px solid rgba(45, 212, 191, 0.08);
}

.navbar-premium .logout-btn-premium {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.13), rgba(220, 38, 38, 0.08));
    padding: 8px 14px;
    border-radius: 11px;
    color: #fca5a5;
    text-decoration: none;
    font-weight: 650;
    font-size: 12px;
    transition: background 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease, color 0.25s ease;
    border: 1px solid rgba(239, 68, 68, 0.13);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}

.navbar-premium .logout-btn-premium:hover {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.23), rgba(220, 38, 38, 0.16));
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(239, 68, 68, 0.16);
    color: #ffffff;
    border-color: rgba(239, 68, 68, 0.25);
}

.navbar-premium .menu-toggle {
    display: none;
    background: linear-gradient(135deg, rgba(45, 212, 191, 0.14), rgba(13, 148, 136, 0.08));
    border: 1px solid rgba(45, 212, 191, 0.13);
    color: #ffffff;
    font-size: 21px;
    padding: 8px 11px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.25s ease, transform 0.25s ease;
}

.navbar-premium .menu-toggle:hover {
    background: rgba(45, 212, 191, 0.22);
    transform: translateY(-1px);
}

@media (max-width: 1200px) {
    .navbar-premium {
        padding: 0 22px;
    }
    .navbar-premium .brand .header-title {
        font-size: 18px;
    }
    .navbar-premium .nav-links a {
        padding: 9px 10px;
        font-size: 11px;
    }
    .navbar-premium .user-info {
        gap: 7px;
    }
}

@media (max-width: 1024px) {
    .navbar-premium {
        padding: 10px 20px;
        min-height: 68px;
        position: sticky;
    }
    .navbar-premium .menu-toggle {
        display: block;
        margin-left: auto;
    }
    .navbar-premium .nav-links {
        display: none;
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        padding: 12px 0 5px;
        margin-top: 8px;
        border-top: 1px solid rgba(45, 212, 191, 0.10);
        gap: 4px;
    }
    .navbar-premium .nav-links.open {
        display: flex;
    }
    .navbar-premium .nav-links a {
        padding: 12px 15px;
        border-radius: 10px;
        font-size: 13px;
        text-align: left;
    }
    .navbar-premium .nav-links a::after {
        display: none;
    }
    .navbar-premium .nav-links a:hover {
        transform: translateX(3px);
    }
    .navbar-premium .nav-links a.active {
        border-left: 3px solid #2dd4bf;
    }
    .navbar-premium .user-info {
        margin-left: 8px;
    }
}

@media (max-width: 768px) {
    .navbar-premium {
        padding: 9px 17px;
    }
    .navbar-premium .brand {
        gap: 9px;
    }
    .navbar-premium .brand .logo-icon {
        width: 39px;
        height: 39px;
        font-size: 21px;
    }
    .navbar-premium .brand .header-title {
        font-size: 16px;
        letter-spacing: -0.3px;
    }
    .navbar-premium .user-info .user-name {
        font-size: 11px;
        max-width: 100px;
    }
    .navbar-premium .user-info .user-role {
        font-size: 8px;
    }
    .navbar-premium .logout-btn-premium {
        padding: 7px 11px;
        font-size: 11px;
    }
}

@media (max-width: 560px) {
    .navbar-premium {
        padding: 9px 13px;
    }
    .navbar-premium .brand .logo-icon {
        width: 36px;
        height: 36px;
        font-size: 19px;
        border-radius: 10px;
    }
    .navbar-premium .brand .header-title {
        font-size: 14px;
    }
    .navbar-premium .user-info {
        margin-left: 4px;
    }
    .navbar-premium .user-info .user-name,
    .navbar-premium .user-info .user-role {
        display: none;
    }
    .navbar-premium .user-info .user-profile {
        padding: 3px;
        background: transparent;
        border-color: transparent;
    }
    .navbar-premium .user-info .user-avatar {
        width: 34px;
        height: 34px;
        font-size: 13px;
    }
    .navbar-premium .logout-btn-premium {
        padding: 7px 9px;
        border-radius: 9px;
    }
    .navbar-premium .logout-btn-premium span:last-child {
        display: none;
    }
    .navbar-premium .menu-toggle {
        font-size: 19px;
        padding: 7px 10px;
    }
}

@media (max-width: 380px) {
    .navbar-premium .brand .header-title {
        font-size: 12px;
    }
    .navbar-premium .brand .logo-icon {
        width: 33px;
        height: 33px;
        font-size: 17px;
    }
    .navbar-premium .user-info .user-profile {
        display: none;
    }
}

.navbar-premium a:focus-visible,
.navbar-premium button:focus-visible {
    outline: 2px solid #5eead4;
    outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
    .navbar-premium *,
    .navbar-premium *::before,
    .navbar-premium *::after {
        animation: none !important;
        transition: none !important;
    }
}
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="navbar-premium">
        <div class="brand">
            <span class="logo-icon">🏫</span>
            <span class="header-title">Mabaso Secondary <span>School</span></span>
        </div>
        
        <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle menu">
            ☰
        </button>
        
        <div class="nav-links" id="navLinks">
            <?php if (isTeacher()): ?>
                <a href="dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>>📊 Dashboard</a>
                <a href="manage_students.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_students.php' || basename($_SERVER['PHP_SELF']) == 'add_student.php' ? 'class="active"' : ''; ?>>👨‍🎓 Students</a>
                <a href="attendance.php" <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'class="active"' : ''; ?>>📋 Attendance</a>
                <a href="manage_results.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_results.php' || basename($_SERVER['PHP_SELF']) == 'add_result.php' ? 'class="active"' : ''; ?>>📊 Results</a>
                <a href="lessons.php" <?php echo basename($_SERVER['PHP_SELF']) == 'lessons.php' || basename($_SERVER['PHP_SELF']) == 'add_lesson.php' ? 'class="active"' : ''; ?>>📚 Lessons</a>
                <a href="profile.php" <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'class="active"' : ''; ?>>👤 Profile</a>
            <?php else: ?>
                <a href="dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>>📊 Dashboard</a>
                <a href="attendance.php" <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'class="active"' : ''; ?>>📋 My Attendance</a>
                <a href="results.php" <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'class="active"' : ''; ?>>📊 My Results</a>
                <a href="lessons.php" <?php echo basename($_SERVER['PHP_SELF']) == 'lessons.php' ? 'class="active"' : ''; ?>>📚 Lessons</a>
                <a href="profile.php" <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'class="active"' : ''; ?>>👤 Profile</a>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn-premium">
                <span>🚪</span> <span>Logout</span>
            </a>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="container">
    
    <script>
        // Mobile menu toggle
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('open');
        }
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const nav = document.querySelector('.navbar-premium');
            const menuToggle = document.querySelector('.menu-toggle');
            const navLinks = document.getElementById('navLinks');
            
            if (nav && !nav.contains(event.target) && navLinks.classList.contains('open')) {
                navLinks.classList.remove('open');
            }
        });
        
        // Close menu on window resize (if going to desktop)
        window.addEventListener('resize', function() {
            const navLinks = document.getElementById('navLinks');
            if (window.innerWidth > 1024 && navLinks.classList.contains('open')) {
                navLinks.classList.remove('open');
            }
        });
    </script>