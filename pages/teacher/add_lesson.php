<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../../index.php');
}

$teacher_id = $_SESSION['user_id'];

// Get all classes for this teacher
$stmt = $pdo->prepare("SELECT DISTINCT class_name FROM students WHERE teacher_id = ? ORDER BY class_name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Create upload directory if it doesn't exist
$upload_dir = '../../uploads/lessons/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $subject = $_POST['subject'];
    $lesson_date = $_POST['lesson_date'];
    $class_name = $_POST['class_name'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Insert lesson first
        $stmt = $pdo->prepare("
            INSERT INTO lessons (title, description, subject, lesson_date, class_name, teacher_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $subject, $lesson_date, $class_name, $teacher_id]);
        $lesson_id = $pdo->lastInsertId();
        
        // Handle file uploads
        $file_uploaded = false;
        if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] == 0) {
            $allowed_types = [
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint', 
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel', 
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg', 'image/png', 'image/gif', 'text/plain'
            ];
            $max_size = 10 * 1024 * 1024; // 10MB
            
            $file_type = $_FILES['lesson_file']['type'];
            $file_size = $_FILES['lesson_file']['size'];
            $file_tmp = $_FILES['lesson_file']['tmp_name'];
            $file_original_name = $_FILES['lesson_file']['name'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("File type not allowed. Please upload PDF, Word, PowerPoint, Excel, images, or text files.");
            }
            
            if ($file_size > $max_size) {
                throw new Exception("File size exceeds 10MB limit.");
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_original_name, PATHINFO_EXTENSION);
            $unique_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Store in lesson_materials table
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_materials (lesson_id, material_type, material_name, material_path, file_size, mime_type)
                    VALUES (?, 'file', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $lesson_id,
                    $file_original_name,
                    'uploads/lessons/' . $unique_filename,
                    number_format($file_size / 1024, 1) . ' KB',
                    $file_type
                ]);
                $file_uploaded = true;
                
                // Update lessons table to indicate it has materials
                $stmt = $pdo->prepare("UPDATE lessons SET has_materials = 1 WHERE id = ?");
                $stmt->execute([$lesson_id]);
            } else {
                throw new Exception("Failed to upload file. Please try again.");
            }
        }
        
        $pdo->commit();
        
        $success_message = "Lesson added successfully!";
        if ($file_uploaded) {
            $success_message .= " File uploaded successfully.";
        }
        
        header("Location: lessons.php?success=1&message=" . urlencode($success_message));
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lesson - Mabaso Secondary School</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        body {
    background:
        radial-gradient(circle at 10% 10%, rgba(13, 148, 136, 0.08), transparent 30%),
        radial-gradient(circle at 90% 90%, rgba(5, 150, 105, 0.07), transparent 30%),
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
    min-height: 100vh;
    color: #0f172a;
}

.container {
    max-width: 1280px;
    padding: 30px;
}

.content {
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 24px;
    padding: 40px;
    box-shadow:
        0 10px 30px rgba(15, 23, 42, 0.05),
        0 25px 70px rgba(15, 23, 42, 0.08);
    max-width: 700px;
    margin: 30px auto;
    position: relative;
    overflow: hidden;
}

.content::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #0d9488, #059669, #14b8a6);
}

.content h2 {
    font-size: 30px;
    font-weight: 800;
    letter-spacing: -0.7px;
    color: #0f172a;
    margin: 0 0 8px;
    line-height: 1.25;
}

.content .subtitle {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 28px;
    line-height: 1.6;
}

.form-group {
    margin: 20px 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    color: #1e293b;
    font-size: 13px;
    letter-spacing: 0.1px;
}

.form-group label .required {
    color: #ef4444;
    margin-left: 3px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 13px 16px;
    border: 1.5px solid #dbe3ec;
    border-radius: 11px;
    font-size: 14px;
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease,
        background 0.25s ease,
        transform 0.2s ease;
    background: #f8fafc;
    color: #0f172a;
    font-family: inherit;
    box-sizing: border-box;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #94a3b8;
}

.form-group input:hover,
.form-group select:hover,
.form-group textarea:hover {
    border-color: #b8c6d6;
    background: #ffffff;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0d9488;
    background: #ffffff;
    box-shadow:
        0 0 0 3px rgba(13, 148, 136, 0.10),
        0 4px 12px rgba(13, 148, 136, 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 125px;
    line-height: 1.6;
}

.form-group .help-text {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 7px;
    line-height: 1.5;
}

.info-box {
    background: linear-gradient(135deg, #f0fdfa 0%, #ecfdf5 100%);
    border: 1px solid #a7f3d0;
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 25px;
    display: flex;
    align-items: flex-start;
    gap: 13px;
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.05);
}

.info-box .icon {
    font-size: 20px;
    flex-shrink: 0;
    line-height: 1.4;
}

.info-box .text {
    color: #065f46;
    font-size: 13px;
    line-height: 1.65;
}

.info-box .text strong {
    color: #064e3b;
}

.alert {
    padding: 14px 17px;
    margin: 15px 0 22px;
    border-radius: 11px;
    font-weight: 600;
    font-size: 13px;
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}

.alert-error {
    background: linear-gradient(135deg, #fff7f7, #fef2f2);
    color: #991b1b;
    border-left-color: #ef4444;
}

.file-upload-wrapper {
    position: relative;
    border: 2px dashed #cbd5e1;
    border-radius: 15px;
    padding: 32px 25px;
    text-align: center;
    transition:
        border-color 0.3s ease,
        background 0.3s ease,
        transform 0.25s ease,
        box-shadow 0.25s ease;
    background: #f8fafc;
    cursor: pointer;
}

.file-upload-wrapper:hover {
    border-color: #0d9488;
    background: #f0fdfa;
    transform: translateY(-1px);
    box-shadow: 0 8px 25px rgba(13, 148, 136, 0.08);
}

.file-upload-wrapper.dragover {
    border-color: #0d9488;
    background: #ccfbf1;
    box-shadow:
        0 0 0 4px rgba(13, 148, 136, 0.08),
        0 10px 30px rgba(13, 148, 136, 0.10);
    transform: scale(1.01);
}

.file-upload-wrapper .upload-icon {
    font-size: 44px;
    display: block;
    margin-bottom: 12px;
    color: #94a3b8;
    transition: transform 0.3s ease;
}

.file-upload-wrapper:hover .upload-icon {
    transform: translateY(-3px);
}

.file-upload-wrapper .upload-text {
    font-size: 14px;
    color: #64748b;
    line-height: 1.5;
}

.file-upload-wrapper .upload-text strong {
    color: #0d9488;
    font-weight: 700;
}

.file-upload-wrapper .upload-hint {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 7px;
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

.file-preview {
    display: none;
    margin-top: 12px;
    padding: 13px 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}

.file-preview.show {
    display: flex;
}

.file-preview .file-icon {
    font-size: 27px;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    border-radius: 9px;
    border: 1px solid #e2e8f0;
}

.file-preview .file-info {
    flex: 1;
    min-width: 0;
}

.file-preview .file-info .file-name {
    font-weight: 700;
    color: #1e293b;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-preview .file-info .file-size {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 3px;
}

.file-preview .remove-file {
    background: transparent;
    border: none;
    font-size: 17px;
    cursor: pointer;
    color: #ef4444;
    transition: background 0.2s ease, transform 0.2s ease;
    padding: 6px 9px;
    border-radius: 7px;
}

.file-preview .remove-file:hover {
    background: #fef2f2;
    transform: scale(1.05);
}

.btn-premium {
    padding: 13px 22px;
    border-radius: 11px;
    font-weight: 700;
    font-size: 13px;
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        opacity 0.25s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #ffffff;
    line-height: 1;
}

.btn-premium:hover {
    transform: translateY(-2px);
}

.btn-premium:active {
    transform: translateY(0);
}

.btn-success {
    background: linear-gradient(135deg, #0d9488 0%, #059669 100%);
    box-shadow:
        0 7px 20px rgba(13, 148, 136, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.btn-success:hover {
    box-shadow:
        0 11px 30px rgba(13, 148, 136, 0.32),
        inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow:
        0 7px 20px rgba(245, 158, 11, 0.20),
        inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.btn-warning:hover {
    box-shadow:
        0 11px 30px rgba(245, 158, 11, 0.30),
        inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

.actions {
    display: flex;
    gap: 12px;
    margin-top: 28px;
    padding-top: 22px;
    border-top: 1px solid #eef2f7;
}

.actions .btn-premium {
    min-height: 44px;
}

@media (max-width: 768px) {
    .container {
        padding: 12px;
    }
    .content {
        padding: 25px 20px;
        margin: 15px auto;
        border-radius: 20px;
    }
    .content h2 {
        font-size: 25px;
    }
    .content .subtitle {
        font-size: 13px;
        margin-bottom: 22px;
    }
    .form-group {
        margin: 17px 0;
    }
    .info-box {
        padding: 14px;
    }
    .info-box .text {
        font-size: 12px;
    }
    .actions {
        flex-direction: column;
        gap: 10px;
    }
    .actions .btn-premium {
        width: 100%;
    }
    .file-upload-wrapper {
        padding: 25px 15px;
    }
    .file-upload-wrapper .upload-icon {
        font-size: 40px;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 8px;
    }
    .content {
        padding: 22px 16px;
        border-radius: 17px;
    }
    .content h2 {
        font-size: 22px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 12px 13px;
        font-size: 13px;
    }
    .info-box {
        gap: 9px;
    }
    .info-box .icon {
        font-size: 18px;
    }
    .file-upload-wrapper {
        padding: 22px 12px;
    }
    .file-upload-wrapper .upload-text {
        font-size: 13px;
    }
}
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="content">
            <h2>📖 Add New Lesson</h2>
            <p class="subtitle">Create a lesson and upload materials for your students</p>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <span class="icon">💡</span>
                <div class="text">
                    <strong>Choose a class for this lesson.</strong><br>
                    Select "All Classes" if this lesson is for all your students, or select a specific class.
                    You can also upload documents, presentations, or other materials.
                </div>
            </div>
            
            <form method="POST" id="addLessonForm" enctype="multipart/form-data" onsubmit="return validateForm('addLessonForm')">
                <div class="form-group">
                    <label>📌 Lesson Title <span class="required">*</span></label>
                    <input type="text" name="title" required placeholder="Enter lesson title">
                </div>
                
                <div class="form-group">
                    <label>📚 Subject <span class="required">*</span></label>
                    <input type="text" name="subject" required placeholder="e.g., Mathematics">
                </div>
                
                <div class="form-group">
                    <label>🏫 Class <span class="required">*</span></label>
                    <select name="class_name" required>
                        <option value="">Select Class</option>
                        <option value="">📚 All Classes</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class); ?>">🏫 <?php echo htmlspecialchars($class); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">Select "All Classes" for all students, or choose a specific class.</div>
                </div>
                
                <div class="form-group">
                    <label>📅 Lesson Date <span class="required">*</span></label>
                    <input type="date" name="lesson_date" required>
                </div>
                
                <div class="form-group">
                    <label>📝 Description <span class="required">*</span></label>
                    <textarea name="description" rows="5" required placeholder="Describe the lesson content, objectives, and key points..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>📎 Attach File <span style="font-weight: 400; color: #94a3b8;">(Optional)</span></label>
                    <div class="file-upload-wrapper" id="fileUploadWrapper">
                        <span class="upload-icon">📄</span>
                        <div class="upload-text">
                            <strong>Click to upload</strong> or drag and drop
                        </div>
                        <div class="upload-hint">
                            PDF, Word, PowerPoint, Excel, Images (Max 10MB)
                        </div>
                        <input type="file" name="lesson_file" id="lessonFile" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                    </div>
                    <div class="file-preview" id="filePreview">
                        <span class="file-icon" id="fileIcon">📄</span>
                        <div class="file-info">
                            <div class="file-name" id="fileName">No file selected</div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                        <button type="button" class="remove-file" onclick="removeFile()">✕</button>
                    </div>
                    <div class="help-text">Supported formats: PDF, Word, PowerPoint, Excel, Images, Text files</div>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn-premium btn-success">💾 Add Lesson</button>
                    <a href="lessons.php" class="btn-premium btn-warning">❌ Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // File upload handling
        const fileInput = document.getElementById('lessonFile');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const fileIcon = document.getElementById('fileIcon');
        const uploadWrapper = document.getElementById('fileUploadWrapper');
        
        // File type icons
        const fileIcons = {
            'pdf': '📕',
            'doc': '📘',
            'docx': '📘',
            'ppt': '📙',
            'pptx': '📙',
            'xls': '📗',
            'xlsx': '📗',
            'jpg': '🖼️',
            'jpeg': '🖼️',
            'png': '🖼️',
            'gif': '🖼️',
            'txt': '📄',
            'default': '📄'
        };
        
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            return fileIcons[ext] || fileIcons['default'];
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        fileInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png', 'image/gif', 'text/plain'];
                const maxSize = 10 * 1024 * 1024;
                
                if (!validTypes.includes(file.type)) {
                    alert('File type not allowed. Please upload PDF, Word, PowerPoint, Excel, images, or text files.');
                    this.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('File size exceeds 10MB limit.');
                    this.value = '';
                    return;
                }
                
                // Show preview
                fileIcon.textContent = getFileIcon(file.name);
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                filePreview.classList.add('show');
                uploadWrapper.style.display = 'none';
            }
        });
        
        function removeFile() {
            fileInput.value = '';
            filePreview.classList.remove('show');
            uploadWrapper.style.display = 'block';
        }
        
        // Drag and drop support
        uploadWrapper.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadWrapper.addEventListener('dragleave', function(e) {
            e.preventDefault();
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
        
        // Form validation
        function validateForm(formId) {
            const form = document.getElementById(formId);
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.style.borderColor = '#ef4444';
                    input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                    isValid = false;
                } else {
                    input.style.borderColor = '#0d9488';
                    input.style.boxShadow = 'none';
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields');
                return false;
            }
            
            return true;
        }
        
        // Real-time validation
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#0d9488';
                    this.style.boxShadow = 'none';
                }
            });
        });
    </script>
</body>
</html>