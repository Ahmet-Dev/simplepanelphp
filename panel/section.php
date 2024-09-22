<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/user.php';
include_once 'class/session.php';
include_once 'class/section.php';

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}
$sectionManager = new SectionManager($db);
$mesaj = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // Yeni bölüm ekle
        if ($action == 'add') {
            $baslik = $_POST['title'];
            $icerik = $_POST['content'];
            if ($sectionManager->addSection($baslik, $icerik)) {
                $mesaj = 'Bölüm başarıyla eklendi.';
            } else {
                $mesaj = 'Bölüm eklenemedi.';
            }
        }

        // Bölüm düzenle
        elseif ($action == 'edit') {
            $id = $_POST['id'];
            $baslik = $_POST['title'];
            $icerik = $_POST['content'];
            if ($sectionManager->editSection($id, $baslik, $icerik)) {
                $mesaj = 'Bölüm başarıyla güncellendi.';
            } else {
                $mesaj = 'Bölüm güncellenemedi.';
            }
        }

        // Bölüm sil
        elseif ($action == 'delete') {
            $id = $_POST['id'];
            if ($sectionManager->deleteSection($id)) {
                $mesaj = 'Bölüm başarıyla silindi.';
            } else {
                $mesaj = 'Bölüm silinemedi.';
            }
        }
    }
}

// Bölümleri listele
$sections = $sectionManager->listSections();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bölüm Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
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
        <h1 class="text-center">Bölüm Yönetimi</h1>

        <!-- Bildirim Mesajı -->
        <?php if ($mesaj): ?>
            <div class="alert alert-info"><?= $mesaj ?></div>
        <?php endif; ?>

        <!-- Bölüm Ekle/Düzenle Formu -->
        <div class="card mt-3">
            <div class="card-header">
                <h4 id="form-title">Yeni Bölüm Ekle</h4>
            </div>
            <div class="card-body">
                <form id="section-form" action="" method="POST">
                    <input type="hidden" name="action" value="add" id="action-input">
                    <input type="hidden" name="id" id="section-id">
                    <div class="mb-3">
                        <label for="title" class="form-label">Bölüm Başlığı</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">İçerik</label>
                        <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="form-submit-btn">Bölüm Ekle</button>
                </form>
            </div>
        </div>

        <!-- Bölüm Listesi -->
        <div class="card mt-3">
            <div class="card-header">
                <h4>Tüm Bölümler</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>İçerik</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="section-list">
                        <?php foreach ($sections as $section): ?>
                            <tr>
                                <td><?= $section['id'] ?></td>
                                <td><?= $section['title'] ?></td>
                                <td><?= $section['content'] ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="editSection(<?= $section['id'] ?>, '<?= $section['title'] ?>', '<?= htmlspecialchars($section['content'], ENT_QUOTES) ?>')">Düzenle</button>
                                    <form action="" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $section['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editSection(id, title, content) {
            document.getElementById('form-title').innerText = 'Bölümü Düzenle';
            document.getElementById('action-input').value = 'edit';
            document.getElementById('section-id').value = id;
            document.getElementById('title').value = title;
            document.getElementById('content').value = content;
            document.getElementById('form-submit-btn').innerText = 'Bölümü Güncelle';
        }
    </script>
</body>
</html>