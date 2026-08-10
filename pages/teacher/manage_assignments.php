<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];

// Get all assignments for this teacher
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
           (SELECT COUNT(*) FROM students WHERE teacher_id = a.teacher_id AND class_name = a.class_name) as student_count
    FROM assignments a
    WHERE a.teacher_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll();

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $assignment_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$assignment_id, $teacher_id]);
    header("Location: manage_assignments.php?success=Assignment deleted successfully");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments - Mabaso Secondary School</title>
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
        
        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.25);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.25);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
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
        
        .assignment-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .assignment-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            transform: translateX(5px);
            border-color: #0d9488;
        }
        
        .assignment-card .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .assignment-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        
        .assignment-card .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .assignment-card .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .assignment-card .status-badge.expired {
            background: #fef2f2;
            color: #991b1b;
        }
        
        .assignment-card .status-badge.upcoming {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .assignment-card .assignment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 12px 0;
        }
        
        .assignment-card .assignment-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 14px;
        }
        
        .assignment-card .assignment-meta .meta-item strong {
            color: #0f172a;
        }
        
        .assignment-card .assignment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
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
        
        .file-attachment {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            color: #0f172a;
            text-decoration: none;
        }
        
        .file-attachment:hover {
            background: #f0fdf4;
            border-color: #0d9488;
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
            
            .assignment-card .assignment-header {
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
                <h2>📝 Manage Assignments</h2>
                <a href="create_assignment.php" class="btn-premium btn-success">➕ Create New Assignment</a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            
            <?php if (count($assignments) > 0): ?>
                <?php foreach($assignments as $assignment): 
                    $now = new DateTime();
                    $start = new DateTime($assignment['start_date']);
                    $due = new DateTime($assignment['due_date']);
                    
                    if ($now < $start) {
                        $status = 'upcoming';
                        $status_label = 'Upcoming';
                        $status_class = 'upcoming';
                    } elseif ($now > $due) {
                        $status = 'expired';
                        $status_label = 'Expired';
                        $status_class = 'expired';
                    } else {
                        $status = 'active';
                        $status_label = 'Active';
                        $status_class = 'active';
                    }
                ?>
                <div class="assignment-card">
                    <div class="assignment-header">
                        <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </div>
                    
                    <div class="assignment-meta">
                        <span class="meta-item">📚 <strong>Subject:</strong> <?php echo htmlspecialchars($assignment['subject']); ?></span>
                        <span class="meta-item">🏫 <strong>Class:</strong> <?php echo htmlspecialchars($assignment['class_name']); ?></span>
                        <span class="meta-item">📊 <strong>Total Marks:</strong> <?php echo $assignment['total_marks']; ?></span>
                        <span class="meta-item">📝 <strong>Submissions:</strong> <?php echo $assignment['submission_count']; ?> / <?php echo $assignment['student_count']; ?></span>
                    </div>
                    
                    <div class="assignment-meta">
                        <span class="meta-item">📅 <strong>Starts:</strong> <?php echo date('M d, Y h:i A', strtotime($assignment['start_date'])); ?></span>
                        <span class="meta-item">⏰ <strong>Due:</strong> <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?></span>
                    </div>
                    
                    <?php if (!empty($assignment['description'])): ?>
                        <p style="color: #475569; margin: 10px 0;"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($assignment['file_path']) && !empty($assignment['file_name'])): ?>
                        <div style="margin: 10px 0;">
                            <a href="../../<?php echo $assignment['file_path']; ?>" class="file-attachment" download>
                                📎 <?php echo htmlspecialchars($assignment['file_name']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="assignment-actions">
                        <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-premium btn-primary btn-sm">👁️ View</a>
                        <a href="grade_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn-premium btn-warning btn-sm">📊 Grade</a>
                        <a href="?delete=<?php echo $assignment['id']; ?>" class="btn-premium btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this assignment?')">🗑️ Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <h3>No Assignments Created</h3>
                    <p>You haven't created any assignments yet. Click the button above to create your first assignment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>