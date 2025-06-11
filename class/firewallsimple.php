<?php
class Firewall {
    private $db;
    private $config;
    private $securityLog;
    private $whitelistedIPs = [];
    private $allowedScanners = []; // Added for scanning tools
    private $ipModeOverrides = []; // IP-specific mode overrides

    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'max_attempts' => 5, 
            'block_duration' => 3600, // 1 saat
            'enabled' => true,
            'rate_limit_requests' => 60,
            'rate_limit_period' => 60, // 1 dakika
            'log_file' => 'firewall_security.log',
            'debug_mode' => false,
            'hsts_check_enabled' => false, // HSTS kontrolünü varsayılan olarak kapatıyoruz
            'whitelist' => ['127.0.0.1', '::1'], // Yerel IP'leri varsayılan olarak whitelist'e alıyoruz
            'scan_mode' => true, // Tarama modunu aktif et - güvenli taramaya izin verir
            'scan_trusted_ips' => [], // Tarama yapacak IP'ler için ek whitelist
            'auto_switch_threshold' => 3, // Kaç saldırı sonrasında normal moda geçiş
            'auto_switch_timeframe' => 300, // 5 dakika içinde
            'auto_switch_duration' => 600 // Normal modda kalma süresi (10 dakika)
        ], $config);
        
        $this->securityLog = $this->config['log_file'];
        $this->whitelistedIPs = array_merge($this->config['whitelist'], $this->config['scan_trusted_ips']);
        
        // Tarama araçları için kullanıcı ajanları - yaygın güvenli scanner'lar
        $this->allowedScanners = [
            'scanner',
            'crawler',
            'spider',
            'bot',
            'security',
            'wpscan',
            'nikto',
            'acunetix',
            'detectify'
        ];
        
        // Tabloların varlığını kontrol et ve oluştur
        $this->initializeTables();
    }
    
    // Gerekli tabloları oluştur
    private function initializeTables() {
        try {
            // blocked_ips tablosunu kontrol et ve yoksa oluştur
            $this->db->query("CREATE TABLE IF NOT EXISTS blocked_ips (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                unblock_time DATETIME NULL,
                block_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_ip (ip_address)
            )");
            
            // firewall_logs tablosunu kontrol et ve yoksa oluştur
            $this->db->query("CREATE TABLE IF NOT EXISTS firewall_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                failed_attempts INT DEFAULT 1,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (ip_address)
            )");

            // IP mode overrides tablosunu kontrol et ve yoksa oluştur
            $this->db->query("CREATE TABLE IF NOT EXISTS ip_mode_overrides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                mode VARCHAR(20) NOT NULL DEFAULT 'normal',
                expire_time DATETIME NOT NULL,
                created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                attack_count INT DEFAULT 0,
                UNIQUE KEY unique_ip (ip_address),
                INDEX (expire_time)
            )");
            
            // Attack tracking tablosunu kontrol et ve yoksa oluştur
            $this->db->query("CREATE TABLE IF NOT EXISTS attack_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attack_type VARCHAR(50) NOT NULL,
                attack_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (ip_address, attack_time)
            )");
        } catch (Exception $e) {
            // Hata durumunda güvenliği devre dışı bırak
            $this->config['enabled'] = false;
            error_log("Firewall tables initialization error: " . $e->getMessage());
        }
    }

    public function isEnabled() {
        return $this->config['enabled'];
    }
    
    // IP'nin whitelist'te olup olmadığını kontrol et - Geliştirilmiş
    private function isWhitelisted($ip) {
        // CIDR desteği ekle (IP aralıkları için)
        foreach ($this->whitelistedIPs as $whitelistedIP) {
            // Tam eşleşme kontrolü
            if ($ip === $whitelistedIP) {
                return true;
            }
            
            // CIDR notasyonu kontrolü (örn: 192.168.1.0/24)
            if (strpos($whitelistedIP, '/') !== false) {
                if ($this->isIPInRange($ip, $whitelistedIP)) {
                    return true;
                }
            }
        }
        
        // Tarama modu aktifse ve kullanıcı ajanı bir tarayıcıysa
        if ($this->config['scan_mode'] && isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
            foreach ($this->allowedScanners as $scanner) {
                if (strpos($ua, strtolower($scanner)) !== false) {
                    $this->debugLog("Allowed scanner detected: $ua from IP: $ip");
                    return true;
                }
            }
        }
        
        return false;
    }
    
    // IP aralığı kontrolü için yardımcı metod
    private function isIPInRange($ip, $range) {
        list($subnet, $bits) = explode('/', $range);
        $ip_decimal = ip2long($ip);
        $subnet_decimal = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet_decimal &= $mask;
        return ($ip_decimal & $mask) == $subnet_decimal;
    }
    
    // Whitelist'e IP ekle
    public function addToWhitelist($ip) {
        if (!in_array($ip, $this->whitelistedIPs)) {
            $this->whitelistedIPs[] = $ip;
            $this->debugLog("IP added to whitelist: $ip");
        }
    }

    // IP için mevcut modu kontrol et
    private function getIPMode($ip) {
        // Whitelist'te ise her zaman scan mode
        if ($this->isWhitelisted($ip)) {
            return 'scan';
        }
        
        try {
            // Expire olan kayıtları temizle
            $this->cleanupExpiredModeOverrides();
            
            $stmt = $this->db->prepare('SELECT mode FROM ip_mode_overrides WHERE ip_address = ? AND expire_time > NOW()');
            $stmt->execute([$ip]);
            $result = $stmt->fetch();
            
            if ($result) {
                return $result['mode'];
            }
            
            // Override yoksa global ayarı kullan
            return $this->config['scan_mode'] ? 'scan' : 'normal';
        } catch (Exception $e) {
            $this->debugLog("Error getting IP mode: " . $e->getMessage());
            return 'normal'; // Güvenli tarafta kal
        }
    }
    
    // IP için modu ayarla
    private function setIPMode($ip, $mode, $duration = null) {
        if ($this->isWhitelisted($ip)) {
            return; // Whitelist'teki IP'ler için mod değişikliği yapma
        }
        
        try {
            $duration = $duration ?: $this->config['auto_switch_duration'];
            $expireTime = date('Y-m-d H:i:s', time() + $duration);
            
            $stmt = $this->db->prepare('INSERT INTO ip_mode_overrides (ip_address, mode, expire_time) 
                                       VALUES (?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE 
                                       mode = VALUES(mode), 
                                       expire_time = VALUES(expire_time),
                                       attack_count = attack_count + 1');
            $stmt->execute([$ip, $mode, $expireTime]);
            
            $this->debugLog("IP mode set: $ip -> $mode (expires: $expireTime)");
        } catch (Exception $e) {
            $this->debugLog("Error setting IP mode: " . $e->getMessage());
        }
    }
    
    // Saldırı takibi ve otomatik mod değişikliği
    private function trackAttackAndCheckMode($ip, $attackType) {
        if ($this->isWhitelisted($ip)) {
            return;
        }
        
        try {
            // Saldırıyı kaydet
            $stmt = $this->db->prepare('INSERT INTO attack_tracking (ip_address, attack_type) VALUES (?, ?)');
            $stmt->execute([$ip, $attackType]);
            
            // Son timeframe içindeki saldırıları say
            $timeframe = date('Y-m-d H:i:s', time() - $this->config['auto_switch_timeframe']);
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM attack_tracking WHERE ip_address = ? AND attack_time > ?');
            $stmt->execute([$ip, $timeframe]);
            $attackCount = (int)$stmt->fetchColumn();
            
            // Threshold aşıldıysa normal moda geç
            if ($attackCount >= $this->config['auto_switch_threshold']) {
                $currentMode = $this->getIPMode($ip);
                if ($currentMode === 'scan') {
                    $this->setIPMode($ip, 'normal');
                    $this->logSecurity("Auto-switched IP $ip from scan to normal mode due to $attackCount attacks");
                    $this->debugLog("Auto-switched IP $ip to normal mode (attacks: $attackCount)");
                }
            }
            
            // Eski attack kayıtlarını temizle
            $this->cleanupOldAttackRecords();
        } catch (Exception $e) {
            $this->debugLog("Error tracking attack: " . $e->getMessage());
        }
    }
    
    // Expire olan mod override'ları temizle
    private function cleanupExpiredModeOverrides() {
        try {
            $stmt = $this->db->prepare('DELETE FROM ip_mode_overrides WHERE expire_time <= NOW()');
            $stmt->execute();
        } catch (Exception $e) {
            $this->debugLog("Error cleaning expired mode overrides: " . $e->getMessage());
        }
    }
    
    // Eski saldırı kayıtlarını temizle
    private function cleanupOldAttackRecords() {
        try {
            // 24 saatten eski kayıtları sil
            $oldTime = date('Y-m-d H:i:s', time() - 86400);
            $stmt = $this->db->prepare('DELETE FROM attack_tracking WHERE attack_time < ?');
            $stmt->execute([$oldTime]);
        } catch (Exception $e) {
            $this->debugLog("Error cleaning old attack records: " . $e->getMessage());
        }
    }
    
    // IP için scan modunda olup olmadığını kontrol et
    private function isInScanMode($ip) {
        $mode = $this->getIPMode($ip);
        return $mode === 'scan';
    }

    public function logAttack($ip, $permanent = false) {
        if (!$this->isEnabled() || $this->isWhitelisted($ip)) return;

        try {
            // Saldırıyı takip et ve gerekirse mod değiştir
            $this->trackAttackAndCheckMode($ip, 'login_attempt');
            
            if ($this->isBlocked($ip)) {
                $this->send403(); // IP engelliyse HTTP 403 gönder
            }

            if ($permanent) {
                $this->blockIP($ip, true); // Kalıcı olarak engelle
                $this->send403(); // HTTP 403 hatası göster
            }

            $stmt = $this->db->prepare('SELECT * FROM firewall_logs WHERE ip_address = ?');
            $stmt->execute([$ip]);
            $result = $stmt->fetch();
            $now = date('Y-m-d H:i:s');

            if ($result) {
                $failed_attempts = $result['failed_attempts'] + 1;
                $stmt = $this->db->prepare('UPDATE firewall_logs SET failed_attempts = ?, last_attempt = ? WHERE ip_address = ?');
                $stmt->execute([$failed_attempts, $now, $ip]);
                
                if ($failed_attempts >= $this->config['max_attempts']) {
                    $this->blockIP($ip);
                }
            } else {
                $stmt = $this->db->prepare('INSERT INTO firewall_logs (ip_address, failed_attempts, timestamp, last_attempt) VALUES (?, 1, ?, ?)');
                $stmt->execute([$ip, $now, $now]);
            }
        } catch (Exception $e) {
            $this->debugLog("Log attack error: " . $e->getMessage());
        }
    }

    // XSS saldırılarını algıla - IP bazlı mod kontrolü
    public function detectXSS($input) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        // IP'nin moduna göre pattern ayarla
        if ($inScanMode && $this->isScanRequest()) {
            $xssPatterns = [
                '/<script\b[^>]*>(.*?)<\/script>/is',
                '/javascript:[\s\S]*?alert\s*\(/i'
            ];
        } else {
            $xssPatterns = [
                '/<script\b[^>]*>(.*?)<\/script>/is',
                '/(on\w+=\W*["\']?[^>]*["\']?)/i',
                '/javascript:/i',
                '/<\s*img[^>]+src\s*=\s*["\']?\s*(javascript|data):/i',
                '/<iframe/i',
                '/<object/i',
                '/<embed/i',
                '/<body/i',
                '/<applet/i',
                '/<meta/i',
                '/(\b)(on\S+)(\s*)=/i',
                '/<svg\s+.*onload\s*=/i',
                '/%3Cscript/i',
                '/<[^>]*\s+href\s*=\s*javascript\s*:/i'
            ];
        }

        foreach ($input as $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurity("XSS attempt detected: " . substr($value, 0, 100));
                        
                        // Saldırıyı takip et
                        $this->trackAttackAndCheckMode($ip, 'xss');
                        
                        // IP moduna göre tepki ver
                        if (!($inScanMode && $this->isScanRequest())) {
                            $this->logAttack($_SERVER['REMOTE_ADDR']);
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // İsteğin bir tarama olup olmadığını kontrol et
    private function isScanRequest() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        foreach ($this->allowedScanners as $scanner) {
            if (strpos($ua, strtolower($scanner)) !== false) {
                return true;
            }
        }
        
        return false;
    }

    // Dosya yükleme güvenliği kontrolü - IP bazlı mod kontrolü
    public function detectMaliciousFileUpload($file) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        // IP moduna göre allowed types ayarla
        if ($inScanMode && $this->isScanRequest()) {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'image/webp', 'text/plain'];
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'image/webp'];
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            // Saldırıyı takip et
            $this->trackAttackAndCheckMode($ip, 'file_upload');
            
            // IP moduna göre tepki ver
            if (!($inScanMode && $this->isScanRequest())) {
                $this->logAttack($_SERVER['REMOTE_ADDR']);
                die('Geçersiz dosya türü. IP adresiniz engellendi.');
            }
        }
    }

    // Zararlı URL parametrelerini algıla - IP bazlı mod kontrolü
    public function detectMaliciousURLParams($params) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        // IP moduna göre kontrol et
        if ($inScanMode && $this->isScanRequest()) {
            return;
        }
        
        foreach ($params as $param) {
            if (preg_match('/[\'"^$#@]/', $param)) {
                $this->trackAttackAndCheckMode($ip, 'url_param');
                $this->logAttack($_SERVER['REMOTE_ADDR']);
            }
        }
    }

    // Tüm güvenlik kontrollerini çalıştır - IP bazlı mod kontrolü ile
    public function runSecurityChecks() {
        if (!$this->isEnabled()) return;
        
        try {
            $ip = $_SERVER['REMOTE_ADDR'];
            
            // Eğer IP whitelist'te ise kontrollerden muaf tut
            if ($this->isWhitelisted($ip)) {
                return;
            }
            
            $inScanMode = $this->isInScanMode($ip);
            
            // IP engelli mi?
            if ($this->isBlocked($ip)) {
                // IP moduna göre tepki ver
                if ($inScanMode && $this->isScanRequest()) {
                    $this->debugLog("Blocked IP allowed for scanning: $ip");
                } else {
                    $this->send403();
                }
            }
            
            // Rate limiting kontrolü - IP moduna göre
            if (!($inScanMode && $this->isScanRequest())) {
                if ($this->isRateLimited($ip)) {
                    $this->logSecurity("Rate limit exceeded for IP: $ip");
                    $this->trackAttackAndCheckMode($ip, 'rate_limit');
                    $this->logAttack($ip);
                    $this->send429();
                }
            }
            
            // İstek yöntemi kontrolü - tarama istekleri için bypass
            $this->checkRequestMethod();
            
            // İstek parametrelerini güvenli bir şekilde kontrol et - sadece POST isteklerinde
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
                $this->detectSQLInjection($_POST);
                $this->detectXSS($_POST);
            }
            
            // URL parametrelerini sadece varsa kontrol et
            if (!empty($_GET)) {
                $this->check7GPatterns();
            }
            
            // User-Agent kontrolü - IP moduna göre
            if (!($inScanMode && $this->isScanRequest())) {
                $this->validateUserAgent();
            }
            
            // CORS kontrolü - sadece kayıt tutar, engelleme yapmaz
            $this->detectCORSSecurity();
            
            // Güvenlik başlıklarını ayarla
            $this->setSecurityHeaders();
        } catch (Exception $e) {
            $this->debugLog("Firewall security checks error: " . $e->getMessage());
        }
    }

    // 7G WAF kurallarını kontrol et - IP bazlı mod kontrolü
    public function check7GPatterns() {
        try {
            $ip = $_SERVER['REMOTE_ADDR'];
            $inScanMode = $this->isInScanMode($ip);
            
            // IP moduna göre pattern ayarla
            if ($inScanMode && $this->isScanRequest()) {
                $bad_patterns = [
                    '/\.\.\//i', // Path traversal
                    '/[;&|`](?:rm|wget|curl|chmod|chown)\s+-[a-z]{1,5}\s+/i' // Tehlikeli komutlar
                ];
            } else {
                $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                
                $bad_patterns = [
                    '/\.\.\//i',
                    '/[;&|`](?:echo|ls|pwd|cat)/i',
                    '/union\s+select/i',
                    '/select.+from/i',
                    '/<script>/i',
                    '/<iframe/i',
                    '/\.(?:php|phtml|exe|bat|sh)/i',
                    '/(?:\/|\\\\)\.\.(?:\/|\\\\)/i',
                ];
            }
            
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            
            foreach ($bad_patterns as $pattern) {
                if (preg_match($pattern, $request_uri)) {
                    $this->logSecurity("7G pattern match detected in URI: $pattern");
                    
                    // Saldırıyı takip et
                    $this->trackAttackAndCheckMode($ip, '7g_pattern');
                    
                    // IP moduna göre tepki ver
                    if (!($inScanMode && $this->isScanRequest())) {
                        $this->logAttack($_SERVER['REMOTE_ADDR'], false);
                    }
                    break;
                }
                
                // Request parametrelerini kontrol et
                if (!($inScanMode && $this->isScanRequest())) {
                    $count = 0;
                    foreach ($_REQUEST as $key => $value) {
                        if ($count > 10) break;
                        
                        if (is_string($value) && preg_match($pattern, $value)) {
                            $this->logSecurity("7G pattern match detected in parameters: $pattern");
                            $this->trackAttackAndCheckMode($ip, '7g_pattern');
                            $this->logAttack($_SERVER['REMOTE_ADDR'], false);
                            break 2;
                        }
                        $count++;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("7G pattern check error: " . $e->getMessage());
        }
    }

    // SQL Injection saldırılarını algıla - IP bazlı mod kontrolü
    public function detectSQLInjection($input) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        // IP moduna göre pattern ayarla
        if ($inScanMode && $this->isScanRequest()) {
            $sqlPatterns = [
                '/delete\s+from\s+.*/i',
                '/drop\s+table\s+.*/i',
                '/update\s+.*\s+set\s+.*/i'
            ];
        } else {
            $sqlPatterns = [
                '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
                '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(\%23)|(#))/i',
                '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
                '/((\%27)|(\'))union/i',
                '/exec(\s|\+)+(s|x)p\w+/ix',
                '/insert|update|delete|drop|select|union|exec|declare|truncate|information_schema|outfile|load_file/i'
            ];
        }
        
        foreach ($input as $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurity("SQL Injection attempt detected: $value");
                        
                        // Saldırıyı takip et
                        $this->trackAttackAndCheckMode($ip, 'sql_injection');
                        
                        // IP moduna göre tepki ver
                        if (!($inScanMode && $this->isScanRequest())) {
                            $this->logAttack($_SERVER['REMOTE_ADDR']);
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // Kullanıcı ajanını doğrula - IP bazlı mod kontrolü
    public function validateUserAgent() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        if (!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->logSecurity("Missing User-Agent header");
            
            // Saldırıyı takip et
            $this->trackAttackAndCheckMode($ip, 'user_agent');
            
            // IP moduna göre tepki ver
            if (!($inScanMode && $this->isScanRequest())) {
                $this->logAttack($_SERVER['REMOTE_ADDR']);
            }
            return false;
        }
        
        $ua = $_SERVER['HTTP_USER_AGENT'];
        
        if ($this->config['scan_mode']) {
            foreach ($this->allowedScanners as $scanner) {
                if (stripos($ua, $scanner) !== false) {
                    $this->debugLog("Scanner user-agent allowed: $ua");
                    return true;
                }
            }
        }
        
        $maliciousAgents = [
            '/sqlmap/i',
            '/nikto/i',
            '/acunetix/i',
            '/nmap/i',
            '/netsparker/i',
            '/dirbuster/i',
            '/burpsuite/i'
        ];
        
        if ($this->config['scan_mode']) {
            $maliciousAgents = ['/sqlmap/i'];
        }
        
        foreach ($maliciousAgents as $agent) {
            if (preg_match($agent, $ua)) {
                $this->logSecurity("Malicious User-Agent detected: $ua");
                
                // Saldırıyı takip et
                $this->trackAttackAndCheckMode($ip, 'malicious_agent');
                
                // IP moduna göre tepki ver
                if (!($inScanMode && $this->isScanRequest())) {
                    $this->logAttack($_SERVER['REMOTE_ADDR'], true); // Permanent block
                    $this->send403();
                }
            }
        }
        
        return true;
    }

    // İstek metodunu kontrol et - IP bazlı mod kontrolü
    public function checkRequestMethod() {
        $method = $_SERVER['REQUEST_METHOD'];
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        // IP moduna göre kontrol et
        if ($inScanMode && $this->isScanRequest()) {
            return;
        }
        
        $allowedMethods = ['GET', 'POST', 'HEAD'];
        
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            $allowedMethods = array_merge($allowedMethods, ['PUT', 'DELETE', 'PATCH']);
        }
        
        if (!in_array($method, $allowedMethods)) {
            $this->logSecurity("Unauthorized request method: $method");
            $this->trackAttackAndCheckMode($ip, 'method');
            $this->logAttack($_SERVER['REMOTE_ADDR']);
            $this->send405();
        }
    }

    // Güvenlik başlıklarını ayarla - IP bazlı mod kontrolü
    public function setSecurityHeaders() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        // IP moduna göre başlık ayarla
        if ($inScanMode && $this->isScanRequest()) {
            header('X-XSS-Protection: 1; mode=block');
            header('X-Content-Type-Options: nosniff');
            header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval'; script-src * 'unsafe-inline' 'unsafe-eval'; connect-src * 'unsafe-inline'; img-src * data: blob: 'unsafe-inline'; frame-src *; style-src * 'unsafe-inline';");
        } else {
            header('X-XSS-Protection: 1; mode=block');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https://www.google-analytics.com; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https://www.google-analytics.com;");
            
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
            
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        }
    }

    // IP mod durumunu manuel olarak sıfırla
    public function resetIPMode($ip) {
        try {
            $stmt = $this->db->prepare('DELETE FROM ip_mode_overrides WHERE ip_address = ?');
            $stmt->execute([$ip]);
            
            $stmt = $this->db->prepare('DELETE FROM attack_tracking WHERE ip_address = ?');
            $stmt->execute([$ip]);
            
            $this->debugLog("IP mode reset: $ip");
        } catch (Exception $e) {
            $this->debugLog("Error resetting IP mode: " . $e->getMessage());
        }
    }
    
    // IP mod bilgilerini getir
    public function getIPModeInfo($ip = null) {
        try {
            if ($ip) {
                $stmt = $this->db->prepare('SELECT * FROM ip_mode_overrides WHERE ip_address = ?');
                $stmt->execute([$ip]);
                return $stmt->fetch();
            } else {
                $stmt = $this->db->prepare('SELECT * FROM ip_mode_overrides WHERE expire_time > NOW() ORDER BY created_time DESC');
                $stmt->execute();
                return $stmt->fetchAll();
            }
        } catch (Exception $e) {
            $this->debugLog("Error getting IP mode info: " . $e->getMessage());
            return [];
        }
    }

    private function isBlocked($ip) {
        if ($this->isWhitelisted($ip)) return false;
        
        try {
            $stmt = $this->db->prepare('SELECT * FROM blocked_ips WHERE ip_address = ? AND (unblock_time IS NULL OR unblock_time > NOW())');
            $stmt->execute([$ip]);
            $result = $stmt->fetch();
            return $result !== false;
        } catch (Exception $e) {
            $this->debugLog("IP block check error: " . $e->getMessage());
            return false;
        }
    }

    private function blockIP($ip, $permanent = false) {
        if ($this->isWhitelisted($ip)) return;
        
        try {
            $unblock_time = $permanent ? null : date('Y-m-d H:i:s', time() + $this->config['block_duration']);
            
            $stmt = $this->db->prepare('INSERT INTO blocked_ips (ip_address, unblock_time) VALUES (?, ?) 
                                     ON DUPLICATE KEY UPDATE unblock_time = VALUES(unblock_time)');
            $stmt->execute([$ip, $unblock_time]);
            
            $this->debugLog("IP blocked: $ip" . ($permanent ? " (permanent)" : " (temporary)"));
            
            if ($permanent) {
                $this->send403();
            }
        } catch (Exception $e) {
            $this->debugLog("IP blocking error: " . $e->getMessage());
        }
    }

    private function send403() {
        try {
            if ($this->config['debug_mode']) {
                $this->debugLog("403 Forbidden would be sent - bypassed in debug mode");
                
                $is_scan = false;
                if (isset($_SERVER['HTTP_USER_AGENT'])) {
                    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
                    foreach ($this->allowedScanners as $scanner) {
                        if (strpos($ua, strtolower($scanner)) !== false) {
                            $is_scan = true;
                            break;
                        }
                    }
                }
                
                if ($this->config['scan_mode'] && $is_scan) {
                    $this->debugLog("Allowing scan through firewall: " . $_SERVER['REQUEST_URI']);
                    return;
                }
            }
            
            $page403Path = $_SERVER['DOCUMENT_ROOT'] . '/403.php';
            if (!file_exists($page403Path)) {
                header('HTTP/1.1 403 Forbidden');
                echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Bu sayfaya erişim izniniz yoktur.</p></body></html>';
                exit;
            }
            
            header('HTTP/1.1 403 Forbidden');
            header('Location: /403');
            exit;
        } catch (Exception $e) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access Forbidden';
            exit;
        }
    }

    public function isRateLimited($ip) {
        try {
            $timeFrame = time() - $this->config['rate_limit_period'];
            $timeFrameFormatted = date('Y-m-d H:i:s', $timeFrame);
            
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM firewall_logs WHERE ip_address = ? AND last_attempt > ?');
            $stmt->execute([$ip, $timeFrameFormatted]);
            $count = (int)$stmt->fetchColumn();
            
            return $count > $this->config['rate_limit_requests'];
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return false;
        }
    }

    private function send429() {
        header('HTTP/1.1 429 Too Many Requests');
        header('Retry-After: ' . $this->config['rate_limit_period']);
        echo 'Rate limit exceeded. Please try again later.';
        exit;
    }

    private function send405() {
        header('HTTP/1.1 405 Method Not Allowed');
        header('Allow: GET, POST, HEAD');
        exit;
    }

    public function detectCORSSecurity() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $inScanMode = $this->isInScanMode($ip);
        
        if ($inScanMode && $this->isScanRequest()) {
            return;
        }
        
        if (!isset($_SERVER['HTTP_ORIGIN'])) {
            return;
        }

        $allowed_origins = [
            'https://' . $_SERVER['SERVER_NAME'],
            'http://' . $_SERVER['SERVER_NAME'],
            'https://www.' . $_SERVER['SERVER_NAME'],
            'http://www.' . $_SERVER['SERVER_NAME'],
            'https://' . $_SERVER['SERVER_NAME'] . '/asset/uploads',
            'http://localhost',
            'https://localhost'
        ];

        if (!in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
            $this->logSecurity("Suspicious CORS request from origin: " . $_SERVER['HTTP_ORIGIN']);
        }
    }

    public function toggle($enabled) {
        $this->config['enabled'] = $enabled;
    }

    public function toggleScanMode($enabled) {
        $this->config['scan_mode'] = $enabled;
    }

    public function unblockIP($ip) {
        try {
            $stmt = $this->db->prepare('DELETE FROM blocked_ips WHERE ip_address = ?');
            $stmt->execute([$ip]);
            $this->debugLog("IP unblocked: $ip");
        } catch (Exception $e) {
            $this->debugLog("Error unblocking IP: " . $e->getMessage());
        }
    }

    private function logSecurity($message) {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            
            $logEntry = "[$timestamp] IP: $ip | URI: $uri | UA: $ua | $message\n";
            
            $logDir = dirname(__FILE__) . '/../logs';
            
            if (!file_exists($logDir)) {
                if (!mkdir($logDir, 0755, true)) {
                    error_log("Failed to create log directory: $logDir");
                    return;
                }
            }
            
            $logFile = $logDir . '/' . $this->securityLog;
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log("Security logging error: " . $e->getMessage());
        }
    }
    
    private function debugLog($message) {
        if ($this->config['debug_mode']) {
            error_log("[Firewall Debug] " . $message);
            
            try {
                $logDir = dirname(__FILE__) . '/../logs';
                if (!file_exists($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                file_put_contents($logDir . '/firewall_debug.log', date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND | LOCK_EX);
            } catch (Exception $e) {
                // Sessizce devam et
            }
        }
    }

    public function getBlockedIPs() {
        try {
            $stmt = $this->db->prepare('SELECT * FROM blocked_ips ORDER BY block_time DESC');
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            $this->debugLog("Error fetching blocked IPs: " . $e->getMessage());
            return [];
        }
    }
}
?>
