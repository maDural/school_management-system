<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];

// Get all classes for this teacher
$stmt = $pdo->prepare("SELECT DISTINCT class_name FROM students WHERE teacher_id = ? AND class_name IS NOT NULL AND class_name != '' ORDER BY class_name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get selected class filter
$selected_class = isset($_GET['class']) ? $_GET['class'] : null;

// Build query based on class filter
$sql = "
    SELECT s.*, u.full_name, u.email, u.username 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.teacher_id = ?
";
$params = [$teacher_id];

if ($selected_class) {
    $sql .= " AND s.class_name = ?";
    $params[] = $selected_class;
}

$sql .= " ORDER BY s.class_name, u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get teacher info
$stmt = $pdo->prepare("SELECT u.full_name, t.department FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

// Get stats
$total_students = count($students);
$total_classes = count($classes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - EduManager</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        body { background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%); min-height: 100vh; }
        .container { max-width: 1280px; padding: 20px 30px; }
        
        .content {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            padding: 35px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
            position: relative;
            overflow: hidden;
        }

        .content::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(13, 148, 136, 0.03), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .content::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(15, 23, 42, 0.03), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-section h2 { font-size: 28px; font-weight: 800; color: #0f172a; }
        .header-section .teacher-info { color: #64748b; font-size: 14px; }
        .header-section .teacher-info strong { color: #0f172a; }
        
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-premium:hover::before {
            left: 100%;
        }

        .btn-premium:hover { 
            transform: translateY(-3px) scale(1.02); 
        }
        
        .btn-success { background: linear-gradient(135deg, #0d9488, #059669); box-shadow: 0 8px 25px rgba(13, 148, 136, 0.25); }
        .btn-success:hover { box-shadow: 0 12px 35px rgba(13, 148, 136, 0.32); }
        .btn-primary { background: linear-gradient(135deg, #0f172a, #1e293b); box-shadow: 0 8px 25px rgba(15, 23, 42, 0.25); }
        .btn-primary:hover { box-shadow: 0 12px 35px rgba(15, 23, 42, 0.32); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 8px 25px rgba(239, 68, 68, 0.25); }
        .btn-danger:hover { box-shadow: 0 12px 35px rgba(239, 68, 68, 0.32); }
        .btn-sm { padding: 6px 14px; font-size: 12px; }
        
        .alert { padding: 14px 20px; margin: 15px 0; border-radius: 12px; font-weight: 500; border-left: 4px solid; animation: slideDown 0.3s ease; }
        .alert-success { background: #d1fae5; color: #065f46; border-left-color: #0d9488; }
        .alert-info { background: #dbeafe; color: #1e40af; border-left-color: #3b82f6; }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
        .stat-box { background: #f8fafc; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .stat-box:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .stat-box .stat-number { font-size: 28px; font-weight: 700; color: #0f172a; }
        .stat-box .stat-label { color: #64748b; font-size: 13px; margin-top: 5px; }
        
        .filter-section {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
            padding: 15px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            align-items: center;
        }
        
        .filter-section .label { font-weight: 600; color: #0f172a; font-size: 14px; }
        
        .filter-btn {
            padding: 8px 18px;
            border-radius: 100px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 13px;
        }
        
        .filter-btn:hover { border-color: #0d9488; color: #0d9488; transform: translateY(-2px); }
        .filter-btn.active { background: linear-gradient(135deg, #0d9488, #059669); color: white; border-color: #0d9488; box-shadow: 0 4px 15px rgba(13, 148, 136, 0.25); }
        .filter-btn .count { background: rgba(255,255,255,0.2); padding: 1px 8px; border-radius: 100px; font-size: 11px; margin-left: 5px; }
        .filter-btn.active .count { background: rgba(255,255,255,0.2); }
        
        .table-responsive { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03); }
        thead { background: #f8fafc; }
        th { padding: 15px 20px; text-align: left; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        tbody tr { transition: all 0.2s ease; }
        tbody tr:hover { background: #f8fafc; transform: scale(1.01); }
        
        .badge { display: inline-block; padding: 4px 14px; border-radius: 100px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #e0e7ff; color: #3730a3; }
        
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .empty-icon { font-size: 64px; margin-bottom: 20px; opacity: 0.5; }
        .empty-state h3 { color: #0f172a; font-size: 24px; margin-bottom: 10px; }
        .empty-state p { color: #64748b; font-size: 16px; max-width: 400px; margin: 0 auto 20px; }
        
        @media (max-width: 768px) {
            .container { padding: 10px 15px; }
            .content { padding: 20px; }
            .header-section { flex-direction: column; align-items: flex-start; }
            .header-section h2 { font-size: 22px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-btn { text-align: center; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <div class="header-section">
                <div>
                    <h2>👨‍🎓 My Students</h2>
                    <div class="teacher-info">
                        👨‍🏫 <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong> 
                        • Department: <?php echo htmlspecialchars($teacher['department']); ?>
                        • <?php echo $total_classes; ?> Class<?php echo $total_classes > 1 ? 'es' : ''; ?>
                    </div>
                </div>
                <a href="add_student.php" class="btn-premium btn-success">➕ Add New Student</a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">✅ Student added successfully! They can now login with their credentials.</div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $total_classes; ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">
                        <?php 
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE teacher_id = ? AND date = CURDATE()");
                        $stmt->execute([$_SESSION['user_id']]);
                        echo $stmt->fetchColumn();
                        ?>
                    </div>
                    <div class="stat-label">Present Today</div>
                </div>
            </div>
            
            <!-- Filter by Class -->
            <?php if (!empty($classes)): ?>
            <div class="filter-section">
                <span class="label">📚 Filter by Class:</span>
                <a href="manage_students.php" class="filter-btn <?php echo !$selected_class ? 'active' : ''; ?>">
                    All Classes
                    <?php 
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ?");
                    $stmt->execute([$teacher_id]);
                    echo '<span class="count">' . $stmt->fetchColumn() . '</span>';
                    ?>
                </a>
                <?php foreach($classes as $class): 
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE teacher_id = ? AND class_name = ?");
                    $stmt->execute([$teacher_id, $class]);
                    $count = $stmt->fetchColumn();
                ?>
                    <a href="?class=<?php echo urlencode($class); ?>" 
                       class="filter-btn <?php echo $selected_class == $class ? 'active' : ''; ?>">
                        📚 <?php echo htmlspecialchars($class); ?>
                        <span class="count"><?php echo $count; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($students) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Class</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $student): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><span class="badge badge-primary">📚 <?php echo htmlspecialchars($student['class_name']); ?></span></td>
                                <td>
                                    <div class="actions">
                                        <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn-premium btn-primary btn-sm">👁️ View</a>
                                        <a href="#" class="btn-premium btn-danger btn-sm" onclick="return confirmAction('Are you sure you want to delete this student?')">🗑️ Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👨‍🎓</div>
                    <h3><?php echo $selected_class ? 'No Students in ' . htmlspecialchars($selected_class) : 'No Students Yet'; ?></h3>
                    <p><?php echo $selected_class ? 'Add students to this class to get started.' : 'You haven\'t added any students to your classes. Click the button below to add your first student.'; ?></p>
                    <a href="add_student.php" class="btn-premium btn-success">➕ Add Student</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>