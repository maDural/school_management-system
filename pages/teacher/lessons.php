<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];

// Get lessons with materials
$stmt = $pdo->prepare("
    SELECT l.*, 
           (SELECT COUNT(*) FROM lesson_materials WHERE lesson_id = l.id) as material_count
    FROM lessons l 
    WHERE l.teacher_id = ? 
    ORDER BY l.lesson_date DESC, l.id DESC
");
$stmt->execute([$teacher_id]);
$lessons = $stmt->fetchAll();

// Get materials for each lesson
foreach ($lessons as &$lesson) {
    $stmt = $pdo->prepare("SELECT * FROM lesson_materials WHERE lesson_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$lesson['id']]);
    $lesson['materials'] = $stmt->fetchAll();
}
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
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-section h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
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
        
        .alert {
            padding: 14px 20px;
            margin: 15px 0;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
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
        
        .lesson-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
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
        
        .lesson-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        
        .lesson-card .class-tag {
            display: inline-block;
            background: #f1f5f9;
            padding: 2px 12px;
            border-radius: 100px;
            font-size: 12px;
            color: #475569;
        }
        
        .lesson-card .class-tag.all-classes {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .lesson-card .meta {
            color: #64748b;
            font-size: 14px;
            margin: 10px 0;
        }
        
        .lesson-card .meta strong {
            color: #0f172a;
        }
        
        .lesson-card p {
            color: #475569;
            margin-top: 10px;
            line-height: 1.8;
        }
        
        .lesson-card .timestamp {
            margin-top: 10px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        /* Materials Section */
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
        
        .lesson-card .no-materials {
            font-size: 13px;
            color: #94a3b8;
            font-style: italic;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left-color: #f59e0b;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-section h2 {
                font-size: 22px;
            }
            
            .lesson-card .material-item {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            
            .lesson-card .lesson-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <div class="header-section">
                <h2>📚 My Lessons</h2>
                <a href="add_lesson.php" class="btn-premium btn-success">➕ Add New Lesson</a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✅ <?php echo isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Lesson added successfully!'; ?>
                </div>
            <?php endif; ?>
            
            <?php if (count($lessons) > 0): ?>
                <div style="margin-top: 20px;">
                    <?php foreach($lessons as $lesson): ?>
                    <div class="lesson-card">
                        <div class="lesson-header">
                            <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                            <?php if (!empty($lesson['class_name'])): ?>
                                <span class="class-tag">🏫 <?php echo htmlspecialchars($lesson['class_name']); ?></span>
                            <?php else: ?>
                                <span class="class-tag all-classes">📚 All Classes</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="meta">
                            <strong>📚 Subject:</strong> <?php echo htmlspecialchars($lesson['subject']); ?>
                        </div>
                        <div class="meta">
                            <strong>📅 Date:</strong> <?php echo date('l, F d, Y', strtotime($lesson['lesson_date'])); ?>
                        </div>
                        
                        <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                        
                        <!-- Materials Section -->
                        <div class="materials-section">
                            <div class="materials-label">
                                📎 Materials (<?php echo !empty($lesson['materials']) ? count($lesson['materials']) : 0; ?>)
                            </div>
                            <?php if (!empty($lesson['materials'])): ?>
                                <?php foreach($lesson['materials'] as $material): ?>
                                    <?php 
                                        // Check if file exists
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
                            <?php else: ?>
                                <span class="no-materials">No materials uploaded for this lesson</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="timestamp">
                            <?php if (isset($lesson['created_at'])): ?>
                                Posted: <?php echo date('M d, Y h:i A', strtotime($lesson['created_at'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">No lessons added yet.</div>
                <a href="add_lesson.php" class="btn-premium btn-success" style="margin-top: 10px;">➕ Add Your First Lesson</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>