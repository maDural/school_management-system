<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];

// Get student info with class and teacher
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, t.full_name as teacher_name
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN users t ON s.teacher_id = t.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('../../logout.php');
}

$teacher_id = $student['teacher_id'];
$class_name = $student['class_name'];

// Get lessons with materials for this student's class
$stmt = $pdo->prepare("
    SELECT l.*, 
           (SELECT COUNT(*) FROM lesson_materials WHERE lesson_id = l.id) as material_count
    FROM lessons l 
    WHERE l.teacher_id = ? 
    AND (l.class_name = ? OR l.class_name IS NULL OR l.class_name = '')
    ORDER BY l.lesson_date DESC, l.id DESC
");
$stmt->execute([$teacher_id, $class_name]);
$lessons = $stmt->fetchAll();

// Get materials for each lesson
foreach ($lessons as &$lesson) {
    $stmt = $pdo->prepare("SELECT * FROM lesson_materials WHERE lesson_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$lesson['id']]);
    $lesson['materials'] = $stmt->fetchAll();
}

$teacher_name = $student['teacher_name'] ?? 'Not Assigned';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - Mabaso Secondary School</title>
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
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
        }
        
        .page-header .class-badge {
            display: inline-block;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 6px 20px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .subtitle .teacher-badge {
            display: inline-block;
            background: #f1f5f9;
            padding: 4px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .teacher-info-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            padding: 18px 24px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .teacher-info-card .label {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }
        
        .teacher-info-card .value {
            font-weight: 600;
            color: #0f172a;
            font-size: 15px;
        }
        
        .teacher-info-card .lesson-count {
            background: #0d9488;
            color: white;
            padding: 4px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .lessons-list {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }
        
        .lesson-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        
        .lesson-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            transform: translateX(5px);
            border-color: #0d9488;
        }
        
        .lesson-card .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .lesson-card .lesson-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        
        .lesson-card .class-tag {
            display: inline-block;
            background: #f1f5f9;
            padding: 2px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
        }
        
        .lesson-card .class-tag.all-classes {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .lesson-card .class-tag.specific-class {
            background: #fef3c7;
            color: #92400e;
        }
        
        .lesson-card .lesson-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 10px 0 12px;
        }
        
        .lesson-card .lesson-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 14px;
        }
        
        .lesson-card .lesson-meta .meta-item strong {
            color: #0f172a;
        }
        
        .lesson-card .lesson-description {
            color: #475569;
            line-height: 1.8;
            margin: 10px 0 15px;
            padding: 10px 16px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 3px solid #0d9488;
        }
        
        .lesson-card .materials-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }
        
        .lesson-card .materials-section .materials-label {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .lesson-card .material-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #0f172a;
            transition: all 0.3s ease;
            margin-right: 10px;
            margin-bottom: 8px;
        }
        
        .lesson-card .material-item:hover {
            background: #f0fdf4;
            border-color: #0d9488;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(13, 148, 136, 0.15);
        }
        
        .lesson-card .material-item .material-icon {
            font-size: 18px;
        }
        
        .lesson-card .material-item .material-name {
            font-size: 13px;
            font-weight: 500;
        }
        
        .lesson-card .material-item .material-size {
            font-size: 11px;
            color: #94a3b8;
        }
        
        .lesson-card .material-item .download-btn {
            background: #0d9488;
            color: white;
            border: none;
            padding: 4px 14px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .lesson-card .material-item .download-btn:hover {
            background: #059669;
            transform: scale(1.05);
        }
        
        .lesson-card .material-item .file-missing {
            color: #ef4444;
            font-size: 11px;
            font-weight: 600;
        }
        
        .lesson-card .timestamp {
            margin-top: 15px;
            font-size: 12px;
            color: #94a3b8;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fafafa;
            border-radius: 16px;
            border: 2px dashed #e2e8f0;
        }
        
        .empty-state .empty-icon {
            font-size: 56px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            color: #0f172a;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #94a3b8;
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .page-header h2 {
                font-size: 22px;
            }
            
            .lesson-card .lesson-header {
                flex-direction: column;
            }
            
            .lesson-card .lesson-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .lesson-card .material-item {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            
            .teacher-info-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .lesson-card .lesson-title {
                font-size: 17px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <div class="page-header">
                <h2>📚 Lessons</h2>
                <span class="class-badge">🏫 Class <?php echo htmlspecialchars($class_name); ?></span>
            </div>
            
            <div class="subtitle">
                <span>Lessons for <strong><?php echo htmlspecialchars($class_name); ?></strong></span>
                <span class="teacher-badge">👨‍🏫 <?php echo htmlspecialchars($teacher_name); ?></span>
            </div>
            
            <div class="teacher-info-card">
                <div>
                    <div class="label">👨‍🏫 Your Teacher</div>
                    <div class="value"><?php echo htmlspecialchars($teacher_name); ?></div>
                </div>
                <div>
                    <div class="label">🏫 Your Class</div>
                    <div class="value"><?php echo htmlspecialchars($class_name); ?></div>
                </div>
                <div>
                    <span class="lesson-count">📚 <?php echo count($lessons); ?> Lessons</span>
                </div>
            </div>
            
            <?php if (count($lessons) > 0): ?>
                <div class="lessons-list">
                    <?php foreach($lessons as $lesson): ?>
                        <div class="lesson-card">
                            <div class="lesson-header">
                                <div class="lesson-title">
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </div>
                                <?php if (!empty($lesson['class_name'])): ?>
                                    <span class="class-tag specific-class">🏫 <?php echo htmlspecialchars($lesson['class_name']); ?></span>
                                <?php else: ?>
                                    <span class="class-tag all-classes">📚 All Classes</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="lesson-meta">
                                <span class="meta-item">
                                    📚 <strong>Subject:</strong> <?php echo htmlspecialchars($lesson['subject']); ?>
                                </span>
                                <span class="meta-item">
                                    📅 <strong>Date:</strong> <?php echo date('l, F d, Y', strtotime($lesson['lesson_date'])); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($lesson['description'])): ?>
                                <div class="lesson-description">
                                    <?php echo nl2br(htmlspecialchars($lesson['description'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Materials -->
                            <?php if (!empty($lesson['materials'])): ?>
                                <div class="materials-section">
                                    <div class="materials-label">
                                        📎 Materials (<?php echo count($lesson['materials']); ?>)
                                    </div>
                                    <?php foreach($lesson['materials'] as $material): ?>
                                        <?php 
                                            $file_path = '../../' . $material['material_path'];
                                            $file_exists = file_exists($file_path);
                                        ?>
                                        <div class="material-item">
                                            <span class="material-icon">📄</span>
                                            <span class="material-name"><?php echo htmlspecialchars($material['material_name']); ?></span>
                                            <?php if (!empty($material['file_size'])): ?>
                                                <span class="material-size">(<?php echo htmlspecialchars($material['file_size']); ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($file_exists): ?>
                                                <a href="<?php echo htmlspecialchars($file_path); ?>" class="download-btn" download>
                                                    ⬇️ Download
                                                </a>
                                            <?php else: ?>
                                                <span class="file-missing">⚠️ File not found</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="timestamp">
                                Posted: <?php echo date('M d, Y h:i A', strtotime($lesson['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <h3>No Lessons Available</h3>
                    <p>Your teacher hasn't posted any lessons for <?php echo htmlspecialchars($class_name); ?> yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>