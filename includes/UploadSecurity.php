<?php

declare(strict_types=1);
require_once __DIR__ . '/src/Engine/Media/Support/helpers.php';
/**
 * Upload Security Manager
 * Dosya yükleme güvenliğini sağlar
 */

class UploadSecurity {
    private static $instance = null;
    
    // İzin verilen dosya tipleri
    private $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'],
    ];
    
    // Tehlikeli dosya uzantıları
    private $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'bat', 'cmd', 'com', 'scr',
        'js', 'vbs', 'wsf', 'wsh',
        'sh', 'bash', 'cgi', 'pl', 'py',
        'asp', 'aspx', 'jsp', 'jspx',
        'htaccess', 'htpasswd', 'ini', 'conf'
    ];
    
    // Maksimum dosya boyutları (bytes)
    private $maxSizes = [
        'image' => 5242880,      // 5MB
        'document' => 10485760,  // 10MB
        'archive' => 52428800,   // 50MB
        'video' => 104857600,    // 100MB
        'default' => 5242880     // 5MB
    ];

    // Uzanti -> izinli MIME tipleri (guvenlik kontrati icin kapsamli kontrol).
    private $validMimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'rtf' => ['application/rtf', 'text/rtf', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'rar' => ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
        '7z' => ['application/x-7z-compressed', 'application/octet-stream'],
        'tar' => ['application/x-tar', 'application/octet-stream'],
        'gz' => ['application/gzip', 'application/x-gzip', 'application/octet-stream'],
        'mp4' => ['video/mp4'],
        'avi' => ['video/x-msvideo', 'video/avi', 'application/octet-stream'],
        'mov' => ['video/quicktime'],
        'wmv' => ['video/x-ms-wmv', 'application/octet-stream'],
        'flv' => ['video/x-flv', 'application/octet-stream'],
        'webm' => ['video/webm'],
    ];
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Dosya yüklemesini doğrula
     */
    public function validateUpload($file, $type = 'image') {
        $errors = [];
        
        // Dosya var mı?
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Dosya seçilmedi';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Upload hatası var mı?
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Dosya boyutu kontrolü
        $maxSize = $this->maxSizes[$type] ?? $this->maxSizes['default'];
        if ($file['size'] > $maxSize) {
            $errors[] = 'Dosya boyutu çok büyük (Max: ' . $this->formatBytes($maxSize) . ')';
        }
        
        // Dosya uzantısı kontrolü
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, $this->dangerousExtensions)) {
            $errors[] = 'Bu dosya tipi güvenlik nedeniyle yüklenemez';
            Logger::getInstance()->security('Dangerous file upload attempt', [
                'filename' => $file['name'],
                'extension' => $extension,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        if (!in_array($extension, $this->allowedTypes[$type] ?? [])) {
            $errors[] = 'Geçersiz dosya tipi. İzin verilen: ' . implode(', ', $this->allowedTypes[$type] ?? []);
        }
        
        // MIME type kontrolü
        if (!$this->validateMimeType($file['tmp_name'], $extension)) {
            $errors[] = 'Dosya içeriği uzantısıyla eşleşmiyor';
        }
        
        // Compressed raster bytes can legitimately contain short tags and function names.
        // Accepted images are decoded here and sanitized before either save path publishes them.
        if ($this->containsPhpCode($file['tmp_name'], $type === 'image')) {
            $errors[] = 'Dosya içeriği güvenli değil';
            Logger::getInstance()->alert('PHP code detected in upload', [
                'filename' => $file['name'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        // Görsel dosyalar için ekstra kontrol
        if ($type === 'image' && !$this->isValidImage($file['tmp_name'])) {
            $header = (string) file_get_contents($file['tmp_name'], false, null, 0, 21);
            $animatedWebp = substr($header, 0, 4) === 'RIFF' && substr($header, 8, 8) === 'WEBPVP8X'
                && strlen($header) === 21 && (ord($header[20]) & 2);
            $errors[] = $animatedWebp ? 'Hareketli WebP bu sunucuda desteklenmiyor; GIF kullanabilirsiniz.' : 'Geçersiz görsel dosyası';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'extension' => $extension,
            'size' => $file['size']
        ];
    }
    
    /**
     * Güvenli dosya adı oluştur
     */
    public function generateSafeFilename($originalName, $prefix = '') {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Güvenli dosya adı oluştur
        $safeName = $prefix . uniqid() . '_' . bin2hex(random_bytes(8));
        
        return $safeName . '.' . $extension;
    }
    
    /**
     * Dosyayı güvenli şekilde kaydet
     */
    public function saveFile($file, $destination, $type = 'image') {
        // Validasyon
        $validation = $this->validateUpload($file, $type);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        if ($type === 'image') {
            try {
                mediaSanitizeImage($file['tmp_name']);
            } catch (Throwable $exception) {
                return ['success' => false, 'errors' => ['Görsel güvenli biçimde kaydedilemedi.']];
            }
        }
        
        // Hedef dizini oluştur
        $uploadDir = dirname($destination);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Güvenli dosya adı oluştur
        $safeFilename = $this->generateSafeFilename($file['name']);
        $finalPath = $uploadDir . '/' . $safeFilename;
        
        // Dosyayı taşı
        if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
            return [
                'success' => false,
                'errors' => ['Dosya kaydedilemedi']
            ];
        }
        
        // Dosya izinlerini ayarla
        chmod($finalPath, 0644);
        
        Logger::getInstance()->info('File uploaded successfully', [
            'original_name' => $file['name'],
            'saved_name' => $safeFilename,
            'size' => $file['size'],
            'type' => $type
        ]);
        
        return [
            'success' => true,
            'filename' => $safeFilename,
            'path' => $finalPath,
            'size' => $file['size']
        ];
    }
    
    /**
     * MIME type doğrulama - finfo_file kullanarak
     */
    private function validateMimeType($filePath, $extension) {
        $extension = strtolower((string) $extension);
        if (!isset($this->validMimes[$extension])) {
            return true;
        }

        $mimeType = null;
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            unset($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
        } else {
            return true;
        }

        return in_array((string) $mimeType, $this->validMimes[$extension], true);
    }
    
    /**
     * PHP kodu içeriyor mu kontrol et
     */
    private function containsPhpCode($filePath, bool $isImage = false) {
        $content = file_get_contents($filePath, false, null, 0, $isImage ? $this->maxSizes['image'] : 1024 * 1024);
        if ($content === false) {
            return true;
        }
        
        $patterns = $isImage ? [
            '/<\?php\b/i',
            '/<\?=/i',
            '/<script[^>]*language\s*=\s*[\x22\x27]?php\b/i',
        ] : [
            '/<\?php/i',
            '/<\?=/i',
            '/<\?(?!xml)/i',
            '/<script.*language.*php.*>/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Geçerli görsel mi kontrol et
     */
    private function isValidImage($filePath) {
        try {
            $imageInfo = mediaInspectImage($filePath);
            if (!in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
                return false;
            }
            return mediaCreateImageFromFile($filePath, $imageInfo['extension']) !== null;
        } catch (Throwable $exception) {
            return false;
        }
    }
    
    /**
     * Upload hata mesajı
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu sunucu limitini aşıyor',
            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor',
            UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi',
            UPLOAD_ERR_NO_FILE => 'Dosya yüklenmedi',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici dizin bulunamadı',
            UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı',
            UPLOAD_ERR_EXTENSION => 'Dosya yükleme bir uzantı tarafından durduruldu',
        ];
        
        return $errors[$errorCode] ?? 'Bilinmeyen upload hatası';
    }
    
    /**
     * Byte'ı okunabilir formata çevir
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Dosyayı sil
     */
    public function deleteFile($filePath) {
        if (file_exists($filePath) && is_file($filePath)) {
            if (unlink($filePath)) {
                Logger::getInstance()->info('File deleted', ['path' => $filePath]);
                return true;
            }
        }
        return false;
    }
}
