<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/menu.php';
include_once 'class/session.php';
include_once 'class/user.php';
include_once 'class/image.php'; // ImageManager class

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}

$menuManager = new MenuManager($db);
$imageManager = new ImageManager($db); // Initialize ImageManager

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imagePaths = [];
    
    // Handling file uploads using ImageManager
    if (isset($_FILES['image_path']) && count($_FILES['image_path']['name']) > 0) {
        foreach ($_FILES['image_path']['name'] as $key => $fileName) {
            // Check for errors and validate file
            if ($_FILES['image_path']['error'][$key] === UPLOAD_ERR_OK) {
                // Handle file upload and convert to WebP using ImageManager
                $file = [
                    "name" => $_FILES['image_path']['name'][$key],
                    "tmp_name" => $_FILES['image_path']['tmp_name'][$key]
                ];
                try {
                    $imageData = $imageManager->uploadAndConvertToWebP($file, "", false); // Convert to WebP
                    $imagePaths[] = $imageData['path']; // Collect uploaded file paths
                } catch (Exception $e) {
                    echo "Dosya yükleme hatası: " . $e->getMessage();
                }
            }
        }
    }

    // Join all image paths as a single string to store in the database (separated by commas)
    $imagePathString = implode(',', $imagePaths);

    if (isset($_POST['add'])) {
        $menuManager->addMenu($_POST['name'], $_POST['link'], $imagePathString);
    } elseif (isset($_POST['update'])) {
        $menuManager->updateMenu($_POST['id'], $_POST['name'], $_POST['link'], $imagePathString);
    } elseif (isset($_POST['delete'])) {
        $menuManager->deleteMenu($_POST['id']);
    }
	header("Location: admin?page=menu");
    exit();
}

// Menü düzenleme formu için mevcut verileri al
$editMenu = null;
if (isset($_GET['edit_id'])) {
    $editMenu = $menuManager->getMenuById($_GET['edit_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menü Yöneticisi</title>
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
    <h2 class="text-center mb-4">Menü Yönetimi</h2>

    <!-- Menü Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editMenu) ? 'Menüyü Güncelle' : 'Menü Ekle'; ?></h5>
            <hr>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $editMenu['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Menü Adı</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $editMenu['name'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="link" class="form-label">Link</label>
                    <input type="text" class="form-control" id="link" name="link" value="<?php echo $editMenu['link'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="image_path" class="form-label">Görsel Yolu</label>
                    <input type="file" class="form-control" id="image_path" name="image_path[]" multiple>
                </div>
                <button type="submit" name="<?php echo isset($editMenu) ? 'update' : 'add'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editMenu) ? 'Menüyü Güncelle' : 'Menü Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Menü Listesi -->
    <h3 class="text-center mb-4">Mevcut Menüler</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Menü Adı</th>
                    <th>Link</th>
                    <th>Resim</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $menuManager->listMenus();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $images = explode(',', $row['image_path']); // Görsel yollarını ayır
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['name']}</td>
                            <td>{$row['link']}</td>
                            <td>";
                    // Görselleri göster
                    foreach ($images as $image) {
                        echo "<img src='$image' alt='{$row['name']}' width='50' class='me-2 mb-2 img-thumbnail'>";
                    }
                    echo "</td>
                            <td>
                                <a href='admin?page=menu&edit_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
                                <form method='POST' style='display:inline-block; margin-left: 5px;'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <button type='submit' name='delete' class='btn btn-danger btn-sm'>Sil</button>
                                </form>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
