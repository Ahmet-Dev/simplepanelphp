<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/user.php';

// Mesaj değişkeni tanımlama
$message = '';

// Kullanıcı ekleme fonksiyonu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (registerUser($username, $email, $password)) {
        $message = 'Kullanıcı başarıyla eklendi.';
    } else {
        $message = 'Kullanıcı eklenirken bir hata oluştu.';
    }
}

// Kullanıcı düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $userId = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $database = new Database();
    $db = $database->getConnection();

    // Şifreyi hashleyin
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $query = "UPDATE users SET username = :username, email = :email, password = :password WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $userId);
    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $passwordHash);

    if ($stmt->execute()) {
        $message = 'Kullanıcı başarıyla güncellendi.';
    } else {
        $message = 'Kullanıcı güncellenirken bir hata oluştu.';
    }
}

// Kullanıcıları listeleme
function listUsers() {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT * FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Tüm kullanıcıları çek
$users = listUsers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="shortcut icon" href="../asset/favicon.webp" type="image">
    <link rel="icon" href="../asset/favicon.webp" type="image">
    <style>
        body {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            min-width: 250px;
            max-width: 250px;
            background-color: #343a40;
            color: white;
            padding-top: 1rem;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 0.75rem 1rem;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .content {
            flex-grow: 1;
            padding: 2rem;
        }
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
    
<?php include_once 'panel/sidebar.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">Kullanıcı Yönetimi</h1>

        <!-- Bildirim Mesajı -->
        <?php if ($message): ?>
            <div class="alert alert-info"><?= $message ?></div>
        <?php endif; ?>

        <!-- Kullanıcı Ekleme Formu -->
        <div class="card mt-3">
            <div class="card-header">
                <h4>Yeni Kullanıcı Ekle</h4>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label for="username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Kullanıcı Ekle</button>
                </form>
            </div>
        </div>

        <!-- Kullanıcı Listesi -->
        <div class="card mt-3">
            <div class="card-header">
                <h4>Tüm Kullanıcılar</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>')">Düzenle</button>
                                    <!-- Silme işlemi için form -->
                                    <form action="" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kullanıcı Düzenleme Formu -->
        <div class="card mt-3" id="edit-form" style="display:none;">
            <div class="card-header">
                <h4>Kullanıcıyı Düzenle</h4>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <div class="mb-3">
                        <label for="edit-username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="edit-username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="edit-email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-password" class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" id="edit-password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Kullanıcıyı Güncelle</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Kullanıcıyı düzenlemek için formu göster
        function editUser(id, username, email) {
            document.getElementById('edit-user-id').value = id;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-form').style.display = 'block';
        }
    </script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
