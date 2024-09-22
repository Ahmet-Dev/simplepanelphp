<?php
class Firewall {
    private $db;
    private $config;

    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'max_attempts' => 5, 
            'block_duration' => 3600, // 1 saat
            'enabled' => true
        ], $config);
    }

    public function isEnabled() {
        return $this->config['enabled'];
    }

    public function logAttack($ip, $permanent = false) {
        if (!$this->isEnabled()) return;

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

        if ($result) {
            $failed_attempts = $result['failed_attempts'] + 1;
            $stmt = $this->db->prepare('UPDATE firewall_logs SET failed_attempts = ? WHERE ip_address = ?');
            $stmt->execute([$failed_attempts, $ip]);
            
            if ($failed_attempts >= $this->config['max_attempts']) {
                $this->blockIP($ip);
            }
        } else {
            $stmt = $this->db->prepare('INSERT INTO firewall_logs (ip_address, failed_attempts) VALUES (?, 1)');
            $stmt->execute([$ip]);
        }
    }

    private function isBlocked($ip) {
        $stmt = $this->db->prepare('SELECT * FROM blocked_ips WHERE ip_address = ? AND (unblock_time IS NULL OR unblock_time > NOW())');
        $stmt->execute([$ip]);
        $result = $stmt->fetch();
        return $result !== false;
    }

    private function blockIP($ip, $permanent = false) {
        $unblock_time = $permanent ? null : date('Y-m-d H:i:s', time() + $this->config['block_duration']);
        $stmt = $this->db->prepare('INSERT INTO blocked_ips (ip_address, unblock_time) VALUES (?, ?)');
        $stmt->execute([$ip, $unblock_time]);
        if ($permanent) {
            $this->send403(); // Kalıcı engelliyse HTTP 403 gönder
        }
    }

// HTTP 403 hata sayfası gönder ve 403.php'ye yönlendir
    private function send403() {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /403'); // 403.php sayfasına yönlendirme
    exit; // Sayfanın devam etmesini engelle
    }

    // XSS saldırılarını algıla
    public function detectXSS($input) {
        if (preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $input) || preg_match('/(on\w+=\W*["\']?[^>]*["\']?)/i', $input)) {
            $this->logAttack($_SERVER['REMOTE_ADDR']);
        }
    }

    // Dosya yükleme güvenliği kontrolü
    public function detectMaliciousFileUpload($file) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            $this->logAttack($_SERVER['REMOTE_ADDR']);
            die('Geçersiz dosya türü. IP adresiniz engellendi.');
        }
    }

    // Zararlı URL parametrelerini algıla
    public function detectMaliciousURLParams($params) {
        foreach ($params as $param) {
            if (preg_match('/[\'"^$#@]/', $param)) {
                $this->logAttack($_SERVER['REMOTE_ADDR']);
            }
        }
    }

    // HSTS saldırılarını algıla ve kalıcı engelle
public function detectHSTSAttack() {
    // Yerel geliştirme veya HTTP üzerinden çalışan bir ortamda isek HSTS kontrolü yapma
    if ($_SERVER['SERVER_NAME'] == 'localhost' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        return; // HSTS kontrolünü atla
    }

    // 'Strict-Transport-Security' başlığı olup olmadığını kontrol ediyoruz
    if (empty($_SERVER['HTTP_STRICT_TRANSPORT_SECURITY'])) {
        // Eğer HSTS başlığı yoksa, bu bir saldırı olarak kabul edilir ve kalıcı engelleme yapılır
        $this->logAttack($_SERVER['REMOTE_ADDR'], true); // Kalıcı engelle
        $this->send403();
    }
}


    // CORS saldırılarını algıla ve IP'yi engelle
public function detectCORSSecurity() {
    // CORS başlığının olup olmadığını kontrol edelim
    if (!isset($_SERVER['HTTP_ORIGIN'])) {
        // Eğer 'HTTP_ORIGIN' başlığı yoksa bu bir tarayıcı isteği olabilir ve saldırı değildir
        return;
    }

    // Güvenilir kökenlerin listesini tanımlayalım
    $allowed_origins = [
        'https://' . $_SERVER['SERVER_NAME'],
        'https://' . $_SERVER['SERVER_NAME'] . '/asset/uploads'
    ];

    // Eğer gelen 'Origin' başlığı güvenilir değilse engelleme yap
    if (!in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        // İlgili IP adresini kaydet ve engelle
        $this->logAttack($_SERVER['REMOTE_ADDR']);
        $this->send403(); // HTTP 403 sayfasını gönder ve isteği durdur
    }
}


    // Güvenlik duvarını aç/kapa
    public function toggle($enabled) {
        $this->config['enabled'] = $enabled;
    }

    // Manuel IP engellemesini kaldır
    public function unblockIP($ip) {
        $this->db->query('DELETE FROM blocked_ips WHERE ip_address = ?', [$ip]);
    }
}
