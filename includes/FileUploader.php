<?php
class FileUploader {
    private $upload_dir;
    private $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/jpg',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    private $max_size = 5242880; // 5MB
    
    public function __construct() {
        $this->upload_dir = __DIR__ . '/../uploads/';
        $this->checkPermissions();
    }
    
    private function checkPermissions() {
        if (!file_exists($this->upload_dir)) {
            if (!mkdir($this->upload_dir, 0755, true)) {
                throw new Exception("Cannot create upload directory. Please check permissions.");
            }
        }
        
        if (!is_writable($this->upload_dir)) {
            throw new Exception("Upload directory is not writable. Please set permissions to 755 or 777.");
        }
    }
    
    public function upload($file, $subfolder = 'documents') {
        try {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors = [
                    UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize",
                    UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE",
                    UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
                    UPLOAD_ERR_NO_FILE => "No file was uploaded",
                    UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
                    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
                    UPLOAD_ERR_EXTENSION => "File upload stopped by extension"
                ];
                $message = $errors[$file['error']] ?? "Unknown upload error";
                throw new Exception($message);
            }
            
            // Check file size
            if ($file['size'] > $this->max_size) {
                throw new Exception("File is too large. Maximum size is " . ($this->max_size / 1048576) . "MB");
            }
            
            // Check file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $this->allowed_types)) {
                throw new Exception("File type not allowed. Please upload PDF, images, or Word documents.");
            }
            
            // Create subfolder if it doesn't exist
            $target_dir = $this->upload_dir . $subfolder . '/';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            // Generate safe filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $target_path = $target_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Return relative path for database storage
                return 'uploads/' . $subfolder . '/' . $filename;
            } else {
                throw new Exception("Failed to move uploaded file. Check folder permissions.");
            }
            
        } catch (Exception $e) {
            throw new Exception("Upload failed: " . $e->getMessage());
        }
    }
    
    public function uploadMultiple($files, $subfolder = 'documents') {
        $uploaded_files = [];
        $errors = [];
        
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                ];
                
                try {
                    $uploaded_files[] = $this->upload($file, $subfolder);
                } catch (Exception $e) {
                    $errors[] = "Failed to upload {$name}: " . $e->getMessage();
                }
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode("\n", $errors));
        }
        
        return $uploaded_files;
    }
    
    public function deleteFile($filepath) {
        $full_path = __DIR__ . '/../' . $filepath;
        if (file_exists($full_path) && is_file($full_path)) {
            return unlink($full_path);
        }
        return false;
    }
}
?>