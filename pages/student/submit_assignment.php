<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../../index.php');
}

$user_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assignment_id == 0) {
    redirect('assignments.php?error=No assignment selected');
}

// Get student info
$stmt = $pdo->prepare("SELECT id, class_name, teacher_id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('../../logout.php');
}

$student_id = $student['id'];
$class_name = $student['class_name'];

// Get assignment details
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submission_status
    FROM assignments a
    WHERE a.id = ? AND a.class_name = ? AND a.teacher_id = ?
");
$stmt->execute([$student_id, $assignment_id, $class_name, $student['teacher_id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    redirect('assignments.php?error=Assignment not found');
}

// Check if already submitted
if ($assignment['submission_status'] == 'submitted' || $assignment['submission_status'] == 'graded') {
    redirect('assignments.php?error=You have already submitted this assignment');
}

// Check if assignment is still open
$now = new DateTime();
$due = new DateTime($assignment['due_date']);
if ($now > $due) {
    redirect('assignments.php?error=This assignment has expired');
}

$error = '';
$success = '';

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/submissions/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $submission_text = trim($_POST['submission_text']);
    $file_name = null;
    $file_path = null;
    
    // Handle file upload
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png', 'image/gif', 'text/plain'];
        $max_size = 10 * 1024 * 1024;
        
        $file_type = $_FILES['submission_file']['type'];
        $file_size = $_FILES['submission_file']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "File type not allowed. Please upload PDF, Word, PowerPoint, Excel, images, or text files.";
        } elseif ($file_size > $max_size) {
            $error = "File size exceeds 10MB limit.";
        } else {
            $file_extension = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . $student_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['submission_file']['name']));
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $file_path)) {
                $file_path = 'uploads/submissions/' . $file_name;
            } else {
                $error = "Failed to upload file. Please try again.";
                $file_name = null;
                $file_path = null;
            }
        }
    }
    
    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_name, file_path, status, submitted_at)
                VALUES (?, ?, ?, ?, ?, 'submitted', NOW())
                ON DUPLICATE KEY UPDATE
                submission_text = VALUES(submission_text),
                file_name = VALUES(file_name),
                file_path = VALUES(file_path),
                status = 'submitted',
                submitted_at = NOW()
            ");
            $stmt->execute([$assignment_id, $student_id, $submission_text, $file_name, $file_path]);
            
            header("Location: assignments.php?success=Assignment submitted successfully!");
            exit();
            
        } catch(Exception $e) {
            $error = "Failed to submit assignment: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment - Mabaso Secondary School</title>
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
            max-width: 800px;
            margin: 0 auto;
        }
        
        .content h2 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 10px;
        }
        
        .content .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
        }
        
        .alert {
            padding: 14px 20px;
            margin: 15px 0;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #0d9488;
        }
        
        .assignment-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        
        .assignment-info .info-item {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .assignment-info .info-item:last-child {
            border-bottom: none;
        }
        
        .assignment-info .info-item .label {
            font-weight: 600;
            color: #64748b;
            min-width: 120px;
        }
        
        .assignment-info .info-item .value {
            color: #0f172a;
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
        
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
            background: white;
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
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .file-upload-wrapper {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            background: #fafafa;
            cursor: pointer;
            position: relative;
        }
        
        .file-upload-wrapper:hover {
            border-color: #0d9488;
            background: #f0fdf4;
        }
        
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-upload-wrapper .upload-icon {
            font-size: 36px;
            display: block;
            margin-bottom: 8px;
        }
        
        .file-preview {
            display: none;
            margin-top: 10px;
            padding: 10px 16px;
            background: #f1f5f9;
            border-radius: 8px;
            align-items: center;
            gap: 10px;
        }
        
        .file-preview.show {
            display: flex;
        }
        
        .file-preview .remove-file {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #ef4444;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .actions .btn-premium {
                width: 100%;
                justify-content: center;
            }
            
            .assignment-info .info-item {
                flex-direction: column;
                gap: 4px;
            }
            
            .assignment-info .info-item .label {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <h2>📤 Submit Assignment</h2>
            <p class="subtitle">Submit your assignment for grading</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="assignment-info">
                <div class="info-item">
                    <span class="label">📌 Title</span>
                    <span class="value"><?php echo htmlspecialchars($assignment['title']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">📚 Subject</span>
                    <span class="value"><?php echo htmlspecialchars($assignment['subject']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">📊 Total Marks</span>
                    <span class="value"><?php echo $assignment['total_marks']; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">⏰ Due Date</span>
                    <span class="value"><?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?></span>
                </div>
                <?php if (!empty($assignment['description'])): ?>
                    <div class="info-item">
                        <span class="label">📝 Description</span>
                        <span class="value"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($assignment['file_path']) && !empty($assignment['file_name'])): ?>
                    <div class="info-item">
                        <span class="label">📎 Attachment</span>
                        <span class="value">
                            <a href="../../<?php echo $assignment['file_path']; ?>" download style="color: #0d9488; text-decoration: none;">
                                📄 <?php echo htmlspecialchars($assignment['file_name']); ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" id="submissionForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label>📝 Your Submission <span class="required">*</span></label>
                    <textarea name="submission_text" rows="5" required placeholder="Write your submission here..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>📎 Attach File <span style="font-weight: 400; color: #94a3b8;">(Optional)</span></label>
                    <div style="position: relative;">
                        <div class="file-upload-wrapper" id="fileUploadWrapper">
                            <span class="upload-icon">📄</span>
                            <div>Click to upload or drag and drop</div>
                            <div style="font-size: 12px; color: #94a3b8; margin-top: 5px;">
                                PDF, Word, PowerPoint, Excel, Images (Max 10MB)
                            </div>
                            <input type="file" name="submission_file" id="submissionFile" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                        </div>
                        <div class="file-preview" id="filePreview">
                            <span id="fileIcon">📄</span>
                            <span id="fileName" style="flex: 1;">file.pdf</span>
                            <button type="button" class="remove-file" onclick="removeFile()">✕</button>
                        </div>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn-premium btn-success">📤 Submit Assignment</button>
                    <a href="assignments.php" class="btn-premium btn-warning">❌ Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const fileInput = document.getElementById('submissionFile');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const uploadWrapper = document.getElementById('fileUploadWrapper');
        
        fileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                fileName.textContent = file.name;
                filePreview.classList.add('show');
                uploadWrapper.style.display = 'none';
            }
        });
        
        function removeFile() {
            fileInput.value = '';
            filePreview.classList.remove('show');
            uploadWrapper.style.display = 'block';
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>