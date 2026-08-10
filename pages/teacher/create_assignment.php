<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];
$error = '';
$errors = [];

// Get classes for this teacher
$stmt = $pdo->prepare("SELECT DISTINCT class_name FROM students WHERE teacher_id = ? AND class_name IS NOT NULL AND class_name != '' ORDER BY class_name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Create upload directory
$upload_dir = '../../uploads/assignments/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        $error = "Failed to create upload directory. Please check permissions.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $class_name = $_POST['class_name'] ?? '';
    $total_marks = intval($_POST['total_marks'] ?? 100);
    $start_date = $_POST['start_date'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $file_name = null;
    $file_path = null;
    
    // Validation
    if (empty($title)) $errors[] = "Assignment title is required";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($class_name)) $errors[] = "Class is required";
    if ($total_marks < 1) $errors[] = "Total marks must be at least 1";
    if (empty($start_date)) $errors[] = "Start date is required";
    if (empty($due_date)) $errors[] = "Due date is required";
    if (!empty($start_date) && !empty($due_date) && strtotime($start_date) >= strtotime($due_date)) {
        $errors[] = "Due date must be after start date";
    }
    
    // Handle file upload
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] == 0) {
        $allowed_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain'
        ];
        $max_size = 10 * 1024 * 1024;
        
        $file_type = $_FILES['assignment_file']['type'];
        $file_size = $_FILES['assignment_file']['size'];
        $file_tmp = $_FILES['assignment_file']['tmp_name'];
        $file_original_name = $_FILES['assignment_file']['name'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "File type not allowed. Please upload PDF, Word, PowerPoint, Excel, images, or text files.";
        } elseif ($file_size > $max_size) {
            $errors[] = "File size exceeds 10MB limit.";
        } else {
            $file_extension = pathinfo($file_original_name, PATHINFO_EXTENSION);
            $file_name = time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $file_path = 'uploads/assignments/' . $file_name;
            } else {
                $errors[] = "Failed to upload file. Please try again.";
                $file_name = null;
                $file_path = null;
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO assignments (teacher_id, title, description, subject, class_name, total_marks, start_date, due_date, file_name, file_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $teacher_id,
                $title,
                $description,
                $subject,
                $class_name,
                $total_marks,
                $start_date,
                $due_date,
                $file_name,
                $file_path
            ]);
            
            $pdo->commit();
            header("Location: manage_assignments.php?success=Assignment created successfully!");
            exit();
            
        } catch(Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Failed to create assignment: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            background:
                radial-gradient(circle at top left, rgba(13, 148, 136, 0.08), transparent 35%),
                radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.05), transparent 35%),
                linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .container {
            max-width: 1280px;
            padding: 30px;
            margin: 0 auto;
        }

        .content {
            max-width: 850px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 25px 70px rgba(15, 23, 42, 0.09);
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

        .page-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 30px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
            z-index: 1;
        }

        .page-icon {
            width: 54px;
            height: 54px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: linear-gradient(135deg, #ccfbf1, #d1fae5);
            font-size: 26px;
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.12);
            transition: all 0.3s ease;
        }

        .page-icon:hover {
            transform: rotate(-5deg) scale(1.05);
        }

        .page-header h2 {
            margin: 0 0 5px;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.6px;
            color: #0f172a;
        }

        .subtitle {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 18px;
            margin: 15px 0;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            border-left: 4px solid;
            animation: slideDown .3s ease;
            position: relative;
            z-index: 1;
        }

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

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .form-section {
            margin-top: 25px;
            padding: 22px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .form-section:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 18px;
            color: #0f172a;
            font-size: 16px;
            font-weight: 800;
        }

        .section-title span {
            display: flex;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #ccfbf1;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .section-title:hover span {
            transform: scale(1.05);
        }

        .form-group {
            margin: 18px 0;
        }

        .form-group:first-child {
            margin-top: 0;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 13px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            color: #1e293b;
            font-family: inherit;
            font-size: 14px;
            transition: all .25s ease;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #94a3b8;
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0d9488;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(13, 148, 136, .10);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .marks-input {
            position: relative;
        }

        .marks-input input {
            padding-right: 75px;
        }

        .marks-suffix {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            pointer-events: none;
        }

        .date-hint {
            margin-top: 6px;
            color: #94a3b8;
            font-size: 11px;
        }

        .file-upload-wrapper {
            position: relative;
            padding: 28px 20px;
            text-align: center;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            background: #ffffff;
            cursor: pointer;
            transition: all .3s ease;
        }

        .file-upload-wrapper:hover,
        .file-upload-wrapper.dragover {
            border-color: #0d9488;
            background: #f0fdfa;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(13, 148, 136, .08);
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            display: block;
            margin-bottom: 10px;
            font-size: 38px;
            transition: all 0.3s ease;
        }

        .file-upload-wrapper:hover .upload-icon {
            transform: translateY(-3px);
        }

        .upload-title {
            color: #334155;
            font-size: 14px;
            font-weight: 700;
        }

        .upload-subtitle {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
        }

        .file-preview {
            display: none;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
            padding: 13px 15px;
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            border-radius: 12px;
            animation: slideDown .3s ease;
        }

        .file-preview.show {
            display: flex;
        }

        .file-icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 10px;
            background: #ccfbf1;
            font-size: 18px;
        }

        .file-details {
            min-width: 0;
            flex: 1;
        }

        .file-name {
            display: block;
            overflow: hidden;
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-size {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 11px;
        }

        .remove-file {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 9px;
            background: #fee2e2;
            color: #dc2626;
            font-size: 15px;
            cursor: pointer;
            transition: all .2s ease;
        }

        .remove-file:hover {
            background: #fecaca;
            transform: scale(1.05);
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            position: relative;
            z-index: 1;
        }

        .btn-premium {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 24px;
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            overflow: hidden;
            transition: all .3s ease;
        }

        .btn-premium::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.16), transparent);
            transition: left .5s ease;
        }

        .btn-premium:hover::before {
            left: 100%;
        }

        .btn-premium:hover {
            transform: translateY(-3px);
        }

        .btn-success {
            background: linear-gradient(135deg, #0d9488, #059669);
            box-shadow: 0 8px 25px rgba(13,148,136,.25);
        }

        .btn-success:hover {
            box-shadow: 0 12px 32px rgba(13,148,136,.32);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 8px 25px rgba(245,158,11,.22);
        }

        .btn-warning:hover {
            box-shadow: 0 12px 32px rgba(245,158,11,.30);
        }

        .required-note {
            margin-top: 12px;
            color: #94a3b8;
            font-size: 11px;
            text-align: right;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .content {
                padding: 24px;
                border-radius: 22px;
            }
            .page-header h2 {
                font-size: 23px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .form-section {
                padding: 18px;
            }
            .actions {
                flex-direction: column;
            }
            .actions .btn-premium {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            .content {
                padding: 18px;
                border-radius: 18px;
            }
            .page-header {
                gap: 12px;
            }
            .page-icon {
                width: 46px;
                height: 46px;
                font-size: 22px;
            }
            .page-header h2 {
                font-size: 21px;
            }
            .form-section {
                padding: 15px;
                border-radius: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="content">

            <div class="page-header">
                <div class="page-icon">📝</div>
                <div>
                    <h2>Create New Assignment</h2>
                    <p class="subtitle">Create and publish an assignment for your students.</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span>❌</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <?php foreach($errors as $err): ?>
                    <div class="alert alert-error">
                        <span>❌</span>
                        <span><?php echo htmlspecialchars($err); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" id="assignmentForm" enctype="multipart/form-data">

                <div class="form-section">
                    <div class="section-title">
                        <span>📋</span>
                        Assignment Information
                    </div>

                    <div class="form-group">
                        <label>📌 Assignment Title <span class="required">*</span></label>
                        <input type="text" name="title" required maxlength="150" placeholder="e.g., Algebra Practice Assignment" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>📝 Description <span class="required">*</span></label>
                        <textarea name="description" rows="4" required placeholder="Describe the assignment requirements, instructions and expectations..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📚 Subject <span class="required">*</span></label>
                            <input type="text" name="subject" required maxlength="100" placeholder="e.g., Mathematics" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>🏫 Class <span class="required">*</span></label>
                            <select name="class_name" required>
                                <option value="">Select Class</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>" <?php echo (isset($_POST['class_name']) && $_POST['class_name'] == $class) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <span>📊</span>
                        Assessment Settings
                    </div>

                    <div class="form-group">
                        <label>📊 Total Marks <span class="required">*</span></label>
                        <div class="marks-input">
                            <input type="number" name="total_marks" required min="1" max="1000" step="1" value="<?php echo isset($_POST['total_marks']) ? intval($_POST['total_marks']) : 100; ?>">
                            <span class="marks-suffix">marks</span>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <span>📅</span>
                        Assignment Schedule
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📅 Start Date <span class="required">*</span></label>
                            <input type="datetime-local" name="start_date" required value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                            <div class="date-hint">When students can start the assignment.</div>
                        </div>

                        <div class="form-group">
                            <label>⏰ Due Date <span class="required">*</span></label>
                            <input type="datetime-local" name="due_date" required value="<?php echo isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : ''; ?>">
                            <div class="date-hint">Final submission deadline.</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <span>📎</span>
                        Assignment Attachment
                    </div>

                    <div class="form-group">
                        <label>Attach File <span style="font-weight:400;color:#94a3b8;">(Optional)</span></label>

                        <div class="file-upload-wrapper" id="fileUploadWrapper">
                            <span class="upload-icon">📄</span>
                            <div class="upload-title">Click to upload or drag and drop</div>
                            <div class="upload-subtitle">PDF, Word, PowerPoint, Excel, Images or TXT<br>Maximum file size: 10MB</div>
                            <input type="file" name="assignment_file" id="assignmentFile" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                        </div>

                        <div class="file-preview" id="filePreview">
                            <div class="file-icon" id="fileIcon">📄</div>
                            <div class="file-details">
                                <span class="file-name" id="fileName">No file selected</span>
                                <span class="file-size" id="fileSize"></span>
                            </div>
                            <button type="button" class="remove-file" onclick="removeFile()" aria-label="Remove file">✕</button>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn-premium btn-success">💾 Create Assignment</button>
                    <a href="manage_assignments.php" class="btn-premium btn-warning">❌ Cancel</a>
                </div>

                <div class="required-note">* Required fields</div>

            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('assignmentFile');
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileIcon = document.getElementById('fileIcon');
            const uploadWrapper = document.getElementById('fileUploadWrapper');
            const startInput = document.querySelector('input[name="start_date"]');
            const dueInput = document.querySelector('input[name="due_date"]');

            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;

                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('The selected file is larger than 10MB.');
                    this.value = '';
                    return;
                }

                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileIcon.textContent = getFileIcon(file.name);
                filePreview.classList.add('show');
                uploadWrapper.style.display = 'none';
            });

            uploadWrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            uploadWrapper.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });

            uploadWrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });

            window.removeFile = function() {
                fileInput.value = '';
                filePreview.classList.remove('show');
                uploadWrapper.style.display = 'block';
                fileName.textContent = 'No file selected';
                fileSize.textContent = '';
                fileIcon.textContent = '📄';
            };

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 B';
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
            }

            function getFileIcon(filename) {
                const extension = filename.split('.').pop().toLowerCase();
                const icons = {
                    pdf: '📕',
                    doc: '📘',
                    docx: '📘',
                    ppt: '📙',
                    pptx: '📙',
                    xls: '📗',
                    xlsx: '📗',
                    jpg: '🖼️',
                    jpeg: '🖼️',
                    png: '🖼️',
                    gif: '🖼️',
                    txt: '📄'
                };
                return icons[extension] || '📄';
            }

            const now = new Date();
            const startDate = new Date(now.getTime() + 60 * 60000);
            const dueDate = new Date(now.getTime() + 7 * 24 * 60 * 60000);

            function toDateTimeLocal(date) {
                const offset = date.getTimezoneOffset() * 60000;
                return new Date(date.getTime() - offset).toISOString().slice(0, 16);
            }

            if (startInput && !startInput.value) {
                startInput.value = toDateTimeLocal(startDate);
            }
            if (dueInput && !dueInput.value) {
                dueInput.value = toDateTimeLocal(dueDate);
            }

            if (startInput && dueInput) {
                startInput.addEventListener('change', function() {
                    dueInput.min = this.value;
                    if (dueInput.value && dueInput.value < this.value) {
                        dueInput.value = '';
                    }
                });
                dueInput.min = startInput.value;
            }
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>