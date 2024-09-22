<?php
include_once 'class/database.php';
function registerUser($username, $email, $password) {
    $database = new Database();
    $db = $database->getConnection();

    $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
    $stmt = $db->prepare($query);

    // Şifreyi hashleyin
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $passwordHash);

    if ($stmt->execute()) {
        return true;
    }
    return false;
}
function loginUser($username, $password) {
    $database = new Database();
    $db = $database->getConnection();
    $secured = rand();
    $query = "SELECT * FROM users WHERE username = :username LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Şifreyi doğrulayın
        if (password_verify($password, $user['password'])) {
            // Oturum başlatma
            if (session_status() == PHP_SESSION_NONE) {
            session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['auth'] = $secured;
            return true;
        }
    }
    return false;
}
function isAuthenticated() {
    if (session_status() == PHP_SESSION_NONE) {
    session_start();
    }
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    return false;
}
function logoutUser() {
    if (session_status() == PHP_SESSION_NONE) {
    session_start();
    }
    session_unset();
    session_destroy();
    header("Location: /admin?page=login");
    exit();
}
function createAdminUserIfNoneExists() {
    // Veritabanı bağlantısını al
    $database = new Database();
    $db = $database->getConnection();

    // Kullanıcı tablosunda veri olup olmadığını kontrol edin
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['total'] == 0) {
        // Eğer tablo boşsa, admin kullanıcısını oluşturun
        $username = 'admin';
        $email = 'admin@example.com';  // E-posta adresi isteğe bağlıdır, burada örnek olarak verilmiştir
        $password = 'admin123';

        // Şifreyi hashleyin
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Admin kullanıcısını ekleyin
        $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
        $stmt = $db->prepare($query);

        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $passwordHash);

        if ($stmt->execute()) {
            echo "Admin kullanıcısı başarıyla oluşturuldu.";
        } else {
            echo "Admin kullanıcısı oluşturulurken bir hata oluştu.";
        }
    }
}
function getEmailByUsername($username) {
    global $db; // Veritabanı bağlantısını global olarak kullanın
    $stmt = $db->prepare("SELECT email FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['email'] ?? null; // E-posta yoksa null döner
}
// Fonksiyonu çağırın
createAdminUserIfNoneExists();
?>