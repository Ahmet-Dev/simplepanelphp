<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once 'class/database.php';
include_once 'class/session.php';
include_once 'class/user.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$error_message = '';
$show_2fa = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Eğer 2FA kodu girilmişse
    if (isset($_POST['token'])) {
        $username = $_SESSION['username'];
        $enteredToken = $_POST['token'];

        // Veritabanından token ve token süresini kontrol et
        $stmt = $db->prepare("SELECT two_factor_token, token_expiration FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Eğer token doğruysa ve süresi dolmamışsa giriş başarılı
        if ($user && $enteredToken == $user['two_factor_token'] && strtotime($user['token_expiration']) > time()) {
            // Token ve expiration'ı sıfırla
            $stmt = $db->prepare("UPDATE users SET two_factor_token = NULL, token_expiration = NULL WHERE username = ?");
            $stmt->execute([$username]);

            // Giriş başarılı, admin paneline yönlendir
            header("Location: admin?page=pages");
            exit();
        } else {
            $error_message = "Yanlış veya süresi dolmuş kod!";
        }
    }
    // Eğer kullanıcı adı ve şifre girilmişse
    else {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Kullanıcı adı ve şifre doğrulama
        if (loginUser($username, $password)) {
            // 2FA kodu oluştur ve veritabanına kaydet
            $twoFactorToken = rand(100000, 999999);
            $tokenExpiration = date("Y-m-d H:i:s", strtotime('+10 minutes'));

            $stmt = $db->prepare("UPDATE users SET two_factor_token = ?, token_expiration = ? WHERE username = ?");
            $stmt->execute([$twoFactorToken, $tokenExpiration, $username]);

            // 2FA kodunu e-posta ile gönder
            $to = getEmailByUsername($username); // Kullanıcının e-posta adresini al
            $subject = "2FA Doğrulama Kodu";
            $message = "Giriş yapmak için 2FA kodunuz: $twoFactorToken";
            $headers = "From: info@".$_SERVER['SERVER_NAME']."\r\n";
    if (mail($to, $subject, $message, $headers)) {
    echo "Mail başarıyla gönderildi.";
    } else {
    echo "Mail gönderilemedi. Lütfen sunucu ayarlarını kontrol edin."." Kodunuz: ".$twoFactorToken;
    }
            // 2FA ekranını göster
            $_SESSION['username'] = $username;  // Oturumda kullanıcı adını sakla
            $show_2fa = true;
        } else {
            $error_message = "Kullanıcı adı veya şifre yanlış!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş SimplePanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Firefox (uncomment to work in Firefox, although other properties will not work!)  */
/** {
  scrollbar-width: thin;
  scrollbar-color: #5E5E5E #DFE9EB;
}*/

/* Chrome, Edge and Safari */
*::-webkit-scrollbar {
  height: 12px;
  width: 12px;
}
*::-webkit-scrollbar-track {
  border-radius: 0px;
  background-color: #DFE9EB;
}

*::-webkit-scrollbar-track:hover {
  background-color: #B8C0C2;
}

*::-webkit-scrollbar-track:active {
  background-color: #B8C0C2;
}

*::-webkit-scrollbar-thumb {
  border-radius: 2px;
  background-color: #5E5E5E;
}

*::-webkit-scrollbar-thumb:hover {
  background-color: #474747;
}

*::-webkit-scrollbar-thumb:active {
  background-color: #0F0F0F;
}

    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title text-center mb-4"><img src="../asset/SimplePANEL.webp" width="240" height="52" style="margin-bottom: 5%;"></h4>

                    <!-- Hata mesajı -->
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <!-- Eğer 2FA kodu gönderilmişse, kodu gir -->
                    <?php if ($show_2fa): ?>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="token" class="form-label">2FA Kodu</label>
                                <input type="text" class="form-control" id="token" name="token" placeholder="2FA Kodunu girin" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Doğrula</button>
                        </form>

                    <!-- Giriş formu -->
                    <?php else: ?>
                        <form method="post" action="">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Kullanıcı Adı</label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Kullanıcı adınızı girin" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Şifrenizi girin" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
