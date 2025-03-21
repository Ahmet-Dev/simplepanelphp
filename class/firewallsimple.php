<?php
class Firewall {
    private $db;
    private $config;
    private $securityLog;
    private $whitelistedIPs = [];
    private $allowedScanners = []; // Added for scanning tools

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
            'scan_trusted_ips' => [] // Tarama yapacak IP'ler için ek whitelist
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

    public function logAttack($ip, $permanent = false) {
        if (!$this->isEnabled() || $this->isWhitelisted($ip)) return;

        try {
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

    private function isBlocked($ip) {
        if ($this->isWhitelisted($ip)) return false;
        
        try {
            $stmt = $this->db->prepare('SELECT * FROM blocked_ips WHERE ip_address = ? AND (unblock_time IS NULL OR unblock_time > NOW())');
            $stmt->execute([$ip]);
            $result = $stmt->fetch();
            return $result !== false;
        } catch (Exception $e) {
            $this->debugLog("IP block check error: " . $e->getMessage());
            return false; // Hata durumunda engelleme yok
        }
    }

    private function blockIP($ip, $permanent = false) {
        if ($this->isWhitelisted($ip)) return;
        
        try {
            $unblock_time = $permanent ? null : date('Y-m-d H:i:s', time() + $this->config['block_duration']);
            
            // Engelleme verisini güncellemek için ON DUPLICATE KEY kullan
            $stmt = $this->db->prepare('INSERT INTO blocked_ips (ip_address, unblock_time) VALUES (?, ?) 
                                     ON DUPLICATE KEY UPDATE unblock_time = VALUES(unblock_time)');
            $stmt->execute([$ip, $unblock_time]);
            
            $this->debugLog("IP blocked: $ip" . ($permanent ? " (permanent)" : " (temporary)"));
            
            if ($permanent) {
                $this->send403(); // Kalıcı engelliyse HTTP 403 gönder
            }
        } catch (Exception $e) {
            $this->debugLog("IP blocking error: " . $e->getMessage());
        }
    }

    // HTTP 403 hata sayfası gönder ve 403.php'ye yönlendir - Geliştirildi
    private function send403() {
        try {
            // Debug modda ise 403 hatası yerine sadece uyarı log'u
            if ($this->config['debug_mode']) {
                $this->debugLog("403 Forbidden would be sent - bypassed in debug mode");
                
                // İsteğin tarama olup olmadığını kontrol et
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
                
                // Tarama ise ve scan_mode aktifse, engelleme yapma
                if ($this->config['scan_mode'] && $is_scan) {
                    $this->debugLog("Allowing scan through firewall: " . $_SERVER['REQUEST_URI']);
                    return; // İşlemi durdurma, devam etmesine izin ver
                }
            }
            
            // 403.php sayfasının var olduğunu kontrol et
            $page403Path = $_SERVER['DOCUMENT_ROOT'] . '/403.php';
            if (!file_exists($page403Path)) {
                // 403.php yoksa basit bir hata mesajı göster
                header('HTTP/1.1 403 Forbidden');
                echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Bu sayfaya erişim izniniz yoktur.</p></body></html>';
                exit;
            }
            
            // 403.php varsa ona yönlendir
            header('HTTP/1.1 403 Forbidden');
            header('Location: /403');
            exit;
        } catch (Exception $e) {
            // Hata durumunda basit bir 403 mesajı göster
            header('HTTP/1.1 403 Forbidden');
            echo 'Access Forbidden';
            exit;
        }
    }

    // XSS saldırılarını algıla - Tarama için daha toleranslı
    public function detectXSS($input) {
        // Tarama modunda daha az katı kontrol
        if ($this->config['scan_mode'] && $this->isScanRequest()) {
            $xssPatterns = [
                '/<script\b[^>]*>(.*?)<\/script>/is',
                '/javascript:[\s\S]*?alert\s*\(/i'
            ];
        } else {
            // Normal mod için daha katı kontroller
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
                        // Tarama isteği ise ve scan_mode aktifse, engelleme yapma
                        if (!($this->config['scan_mode'] && $this->isScanRequest())) {
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

    // Dosya yükleme güvenliği kontrolü - Tarama için gevşetilmiş
    public function detectMaliciousFileUpload($file) {
        // Tarama modunda ve tarama isteğiyse daha az katı
        if ($this->config['scan_mode'] && $this->isScanRequest()) {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'image/webp', 'text/plain'];
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'image/webp'];
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            // Tarama isteği değilse engelle
            if (!($this->config['scan_mode'] && $this->isScanRequest())) {
                $this->logAttack($_SERVER['REMOTE_ADDR']);
                die('Geçersiz dosya türü. IP adresiniz engellendi.');
            }
        }
    }

    // Zararlı URL parametrelerini algıla - Tarama için gevşetilmiş
    public function detectMaliciousURLParams($params) {
        // Tarama modunda ve tarama isteğiyse atla
        if ($this->config['scan_mode'] && $this->isScanRequest()) {
            return;
        }
        
        foreach ($params as $param) {
            if (preg_match('/[\'"^$#@]/', $param)) {
                $this->logAttack($_SERVER['REMOTE_ADDR']);
            }
        }
    }

    // HSTS saldırılarını algıla ve kalıcı engelle - düzeltilmiş
    public function detectHSTSAttack() {
        // HSTS kontrolü devre dışı bırakıldıysa hiçbir şey yapma
        if (!$this->config['hsts_check_enabled']) {
            return;
        }
        
        // Yerel geliştirme veya HTTP üzerinden çalışan bir ortamda isek HSTS kontrolü yapma
        if ($_SERVER['SERVER_NAME'] == 'localhost' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            return;
        }

        // Bu alan tamamen kaldırıldı çünkü HSTS başlığı client tarafında olur, sunucu tarafında değil
    }

    // CORS saldırılarını algıla - Tarama için gevşetilmiş
    public function detectCORSSecurity() {
        // Tarama modunda ve tarama isteğiyse atla
        if ($this->config['scan_mode'] && $this->isScanRequest()) {
            return;
        }
        
        // HTTP_ORIGIN olmayabilir, bu normal bir durumdur
        if (!isset($_SERVER['HTTP_ORIGIN'])) {
            return;
        }

        // Güvenilir kökenlerin listesini tanımlayalım
        $allowed_origins = [
            'https://' . $_SERVER['SERVER_NAME'],
            'http://' . $_SERVER['SERVER_NAME'], // HTTP da izin verelim
            'https://www.' . $_SERVER['SERVER_NAME'],
            'http://www.' . $_SERVER['SERVER_NAME'],
            'https://' . $_SERVER['SERVER_NAME'] . '/asset/uploads',
            'http://localhost',
            'https://localhost'
        ];

        // Origin başlığı varsa ve güvenilir değilse loglama yap, ama otomatik engelleme yapma
        if (!in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
            $this->logSecurity("Suspicious CORS request from origin: " . $_SERVER['HTTP_ORIGIN']);
        }
    }

    // Güvenlik duvarını aç/kapa
    public function toggle($enabled) {
        $this->config['enabled'] = $enabled;
    }

    // Tarama modunu aç/kapa
    public function toggleScanMode($enabled) {
        $this->config['scan_mode'] = $enabled;
    }

    // Manuel IP engellemesini kaldır
    public function unblockIP($ip) {
        try {
            $stmt = $this->db->prepare('DELETE FROM blocked_ips WHERE ip_address = ?');
            $stmt->execute([$ip]);
            $this->debugLog("IP unblocked: $ip");
        } catch (Exception $e) {
            $this->debugLog("Error unblocking IP: " . $e->getMessage());
        }
    }

    // Tüm güvenlik kontrollerini çalıştır - İyileştirildi
    public function runSecurityChecks() {
        if (!$this->isEnabled()) return;
        
        try {
            $ip = $_SERVER['REMOTE_ADDR'];
            
            // Eğer IP whitelist'te ise kontrollerden muaf tut
            if ($this->isWhitelisted($ip)) {
                return;
            }
            
            // IP engelli mi?
            if ($this->isBlocked($ip)) {
                // Tarama modunda ve tarama isteğiyse bypass et
                if ($this->config['scan_mode'] && $this->isScanRequest()) {
                    $this->debugLog("Blocked IP allowed for scanning: $ip");
                } else {
                    $this->send403();
                }
            }
            
            // Tarama modu aktifse ve tarama isteğiyse rate limiting kontrolü atla
            if (!($this->config['scan_mode'] && $this->isScanRequest())) {
                // Rate limiting kontrolü
                if ($this->isRateLimited($ip)) {
                    $this->logSecurity("Rate limit exceeded for IP: $ip");
                    $this->logAttack($ip);
                    $this->send429(); // Too Many Requests
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
            
            // User-Agent kontrolü - tarama modu ve tarama isteği için bypass
            if (!($this->config['scan_mode'] && $this->isScanRequest())) {
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

    // 7G WAF kurallarını kontrol et - Tarama için iyileştirildi
    public function check7GPatterns() {
        try {
            // Tarama modunda ve tarama isteğiyse daha az sıkı kontroller
            if ($this->config['scan_mode'] && $this->isScanRequest()) {
                $bad_patterns = [
                    // Daha az sıkı kurallar - sadece ciddi güvenlik tehditleri
                    '/\.\.\//i', // Path traversal
                    '/[;&|`](?:rm|wget|curl|chmod|chown)\s+-[a-z]{1,5}\s+/i' // Tehlikeli komutlar
                ];
            } else {
                // URL yolu kontrolü
                $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                
                // 7G BAD URL PATTERNS - OWASP kritik güvenlik açıkları için kontrol
                $bad_patterns = [
                    // LFI (Local File Inclusion) attempts
                    '/\.\.\//i',
                    // Command injection - daha basit regex
                    '/[;&|`](?:echo|ls|pwd|cat)/i',
                    // SQL Injection - basitleştirilmiş pattern
                    '/union\s+select/i',
                    '/select.+from/i',
                    // XSS patterns - temel koruma
                    '/<script>/i',
                    '/<iframe/i',
                    // File upload exploits
                    '/\.(?:php|phtml|exe|bat|sh)/i',
                    // Path traversal attempts
                    '/(?:\/|\\\\)\.\.(?:\/|\\\\)/i',
                ];
            }
            
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            
            foreach ($bad_patterns as $pattern) {
                if (preg_match($pattern, $request_uri)) {
                    $this->logSecurity("7G pattern match detected in URI: $pattern");
                    
                    // Tarama modunda ve tarama isteğiyse engelleme yapma
                    if (!($this->config['scan_mode'] && $this->isScanRequest())) {
                        $this->logAttack($_SERVER['REMOTE_ADDR'], false);
                    }
                    break;
                }
                
                // Request parametrelerini kontrol et (performans için sadece birkaçını)
                if (!($this->config['scan_mode'] && $this->isScanRequest())) {
                    $count = 0;
                    foreach ($_REQUEST as $key => $value) {
                        if ($count > 10) break; // Çok fazla parametre varsa sınırla
                        
                        if (is_string($value) && preg_match($pattern, $value)) {
                            $this->logSecurity("7G pattern match detected in parameters: $pattern");
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

    // İstek parametrelerini kontrol et
    private function checkRequestParams($pattern) {
        foreach ($_REQUEST as $key => $value) {
            if (is_string($value) && preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    // Rate limiting kontrolü
    public function isRateLimited($ip) {
        try {
            $timeFrame = time() - $this->config['rate_limit_period'];
            $timeFrameFormatted = date('Y-m-d H:i:s', $timeFrame);
            
            // Sorguyu değiştiriyoruz - timestamp veya last_attempt alanını kullanacağız
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM firewall_logs WHERE ip_address = ? AND last_attempt > ?');
            $stmt->execute([$ip, $timeFrameFormatted]);
            $count = (int)$stmt->fetchColumn();
            
            return $count > $this->config['rate_limit_requests'];
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return false; // Hata durumunda güvenli tarafta kal
        }
    }

    // 429 - Too Many Requests hatası gönder
    private function send429() {
        header('HTTP/1.1 429 Too Many Requests');
        header('Retry-After: ' . $this->config['rate_limit_period']);
        echo 'Rate limit exceeded. Please try again later.';
        exit;
    }

    // SQL Injection saldırılarını algıla - Tarama için gevşetildi
    public function detectSQLInjection($input) {
        // Tarama modunda ve tarama isteğiyse daha az sıkı kontroller
        if ($this->config['scan_mode'] && $this->isScanRequest()) {
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
                        
                        // Tarama modunda ve tarama isteğiyse engelleme yapma
                        if (!($this->config['scan_mode'] && $this->isScanRequest())) {
                            $this->logAttack($_SERVER['REMOTE_ADDR']);
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // Kullanıcı ajanını doğrula - Tarama için iyileştirildi
    public function validateUserAgent() {
        if (!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->logSecurity("Missing User-Agent header");
            
            // Tarama modunda ve tarama isteğiyse engelleme yapma
            if (!($this->config['scan_mode'] && $this->isScanRequest())) {
                $this->logAttack($_SERVER['REMOTE_ADDR']);
            }
            return false;
        }
        
        $ua = $_SERVER['HTTP_USER_AGENT'];
        
        // Tarama modunda, tarama araçları için allowlist
        if ($this->config['scan_mode']) {
            // İzin verilen scanner araçlarını kontrol et
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
        
        // Tarama modunda bazı araçları hariç tut
        if ($this->config['scan_mode']) {
            $maliciousAgents = ['/sqlmap/i']; // Sadece SQLMap'i engelle
        }
        
        foreach ($maliciousAgents as $agent) {
            if (preg_match($agent, $ua)) {
                $this->logSecurity("Malicious User-Agent detected: $ua");
                
                // Tarama modunda ve tarama isteğiyse engelleme yapma
                if (!($this->config['scan_mode'] && $this->isScanRequest())) {
                    $this->logAttack($_SERVER['REMOTE_ADDR'], true); // Permanent block
                    $this->send403();
                }
            }
        }
        
        return true;
    }

    // İstek metodunu kontrol et - Tarama için iyileştirildi
    public function checkRequestMethod() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Tarama modunda ve tarama isteğiyse tüm metodlara izin ver
        if ($this->config['scan_mode'] && $this->isScanRequest()) {
            return;
        }
        
        $allowedMethods = ['GET', 'POST', 'HEAD'];
        
        // API için ek metodlar eklenebilir
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            $allowedMethods = array_merge($allowedMethods, ['PUT', 'DELETE', 'PATCH']);
        }
        
        if (!in_array($method, $allowedMethods)) {
            $this->logSecurity("Unauthorized request method: $method");
            $this->logAttack($_SERVER['REMOTE_ADDR']);
            $this->send405();
        }
    }

    // 405 Method Not Allowed hatası gönder
    private function send405() {
        header('HTTP/1.1 405 Method Not Allowed');
        header('Allow: GET, POST, HEAD');
        exit;
    }

    // Güvenlik başlıklarını ayarla - Tarama için iyileştirildi
    public function setSecurityHeaders() {
        // Tarama modunda ve tarama isteğiyse daha esnek başlıklar
        if ($this->config['scan_mode'] && $this->isScanRequest()) {
            // XSS koruması
            header('X-XSS-Protection: 1; mode=block');
            
            // MIME sniffing koruması
            header('X-Content-Type-Options: nosniff');
            
            // CSP - tarama için daha esnek
            header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval'; script-src * 'unsafe-inline' 'unsafe-eval'; connect-src * 'unsafe-inline'; img-src * data: blob: 'unsafe-inline'; frame-src *; style-src * 'unsafe-inline';");
        } else {
            // XSS koruması
            header('X-XSS-Protection: 1; mode=block');
            
            // Clickjacking koruması
            header('X-Frame-Options: SAMEORIGIN');
            
            // MIME sniffing koruması
            header('X-Content-Type-Options: nosniff');
            
            // Referrer Policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Content Security Policy - daha esnek bir politika
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https://www.google-analytics.com; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https://www.google-analytics.com;");
            
            // HTTPS için HSTS başlığı
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
            
            // Ek güvenlik başlıkları
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        }
    }

    // Güvenlik olayını kaydet
    private function logSecurity($message) {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            
            $logEntry = "[$timestamp] IP: $ip | URI: $uri | UA: $ua | $message\n";
            
            // Log dosyasının yazılabilir olduğundan emin ol
            $logDir = dirname(__FILE__) . '/../logs';
            
            // Dizin yoksa oluştur
            if (!file_exists($logDir)) {
                if (!mkdir($logDir, 0755, true)) {
                    error_log("Failed to create log directory: $logDir");
                    return;
                }
            }
            
            // Dosyaya yaz, yoksa oluştur
            $logFile = $logDir . '/' . $this->securityLog;
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        } catch (Exception $e) {
            error_log("Security logging error: " . $e->getMessage());
        }
    }
    
    // Debug mod için log
    private function debugLog($message) {
        if ($this->config['debug_mode']) {
            error_log("[Firewall Debug] " . $message);
            
            // Ayrıca debug.log dosyasına da yaz
            try {
                $logDir = dirname(__FILE__) . '/../logs';
                if (!file_exists($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                file_put_contents($logDir . '/firewall_debug.log', date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);
            } catch (Exception $e) {
                // Hata loglaması bile başarısız olursa sessizce devam et
            }
        }
    }

    // IP'nin engellenmesi için kod örneği (web ara yüzü için)
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
