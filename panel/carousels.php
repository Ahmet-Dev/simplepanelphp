<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/carousel.php';
include_once 'class/session.php';
include_once 'class/image.php'; // ImageManager sınıfını dahil edin
// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: user?page=login");
    exit();
}
$carouselManager = new Carousel($db);
$carouselItemManager = new CarouselItem($db);
$imageManager = new ImageManager($db); // ImageManager örneği oluşturun

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Çoklu resim yükleme işlemi
    $imagePaths = []; // Yüklenen resim yollarını saklayacak dizi
    if (isset($_FILES['image_path']) && count($_FILES['image_path']['name']) > 0) {
        foreach ($_FILES['image_path']['name'] as $key => $fileName) {
            if ($_FILES['image_path']['error'][$key] === UPLOAD_ERR_OK) {
                $fileTmpName = $_FILES['image_path']['tmp_name'][$key];
                try {
                    $uploaded_image = $imageManager->uploadAndConvertToWebP(
                        ['name' => $fileName, 'tmp_name' => $fileTmpName]
                    );
                    $imagePaths[] = $uploaded_image['path'];
                } catch (Exception $e) {
                    echo "Resim yükleme hatası: " . $e->getMessage();
                }
            }
        }
    }
    // Yüklenen resimleri dizi olarak saklayın, yoksa eski resimleri kullanın
    $image_path = !empty($imagePaths) ? implode(',', $imagePaths) : ($_POST['image_path'] ?? '');

    if (isset($_POST['addCarousel'])) {
        $carouselManager->addCarousel($_POST['name']);
    } elseif (isset($_POST['updateCarousel'])) {
        $carouselManager->updateCarousel($_POST['id'], $_POST['name']);
    } elseif (isset($_POST['deleteCarousel'])) {
        $carouselManager->deleteCarousel($_POST['id']);
    } elseif (isset($_POST['addItem'])) {
        $carouselItemManager->addItem($_POST['carousel_id'], $_POST['title'], $_POST['description'], $_POST['link'], $image_path);
    } elseif (isset($_POST['updateItem'])) {
        $carouselItemManager->updateItem($_POST['id'], $_POST['title'], $_POST['description'], $_POST['link'], $image_path);
    } elseif (isset($_POST['deleteItem'])) {
        $carouselItemManager->deleteItem($_POST['id']);
    }
}

// Düzenleme formu için mevcut verileri al
$editCarousel = null;
$editItem = null;
if (isset($_GET['edit_carousel_id'])) {
    $editCarousel = $carouselManager->getCarouselById($_GET['edit_carousel_id']);
}
if (isset($_GET['edit_item_id'])) {
    $editItem = $carouselItemManager->getItemById($_GET['edit_item_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carousel Yönetimi</title>
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
    <h2 class="text-center mb-4">Carousel Yönetimi</h2>

    <!-- Carousel Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editCarousel) ? 'Carousel Güncelle' : 'Carousel Ekle'; ?></h5>
            <hr>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editCarousel['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Carousel Adı</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $editCarousel['name'] ?? ''; ?>" required>
                </div>
                <button type="submit" name="<?php echo isset($editCarousel) ? 'updateCarousel' : 'addCarousel'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editCarousel) ? 'Carousel Güncelle' : 'Carousel Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Carousel Listesi -->
    <h3 class="text-center mb-4">Mevcut Carouseller</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Adı</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $carouselManager->listCarousels();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['name']}</td>
                            <td>
                                <a href='?page=carousel&edit_carousel_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
                                <form method='POST' style='display:inline-block; margin-left: 5px;'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <button type='submit' name='deleteCarousel' class='btn btn-danger btn-sm'>Sil</button>
                                </form>
                                <a href='?page=carousel&carousel_id={$row['id']}' class='btn btn-info btn-sm'>Öğeleri Görüntüle</a>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($_GET['carousel_id'])): ?>

    <!-- Carousel Öğeleri Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editItem) ? 'Öğeyi Güncelle' : 'Öğe Ekle'; ?></h5>
            <hr>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $editItem['id'] ?? ''; ?>">
                <input type="hidden" name="carousel_id" value="<?php echo $_GET['carousel_id']; ?>">
                <div class="mb-3">
                    <label for="title" class="form-label">Başlık</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo $editItem['title'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo $editItem['description'] ?? ''; ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="link" class="form-label">Link</label>
                    <input type="text" class="form-control" id="link" name="link" value="<?php echo $editItem['link'] ?? ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="image_path" class="form-label">Resim Yükle (Çoklu Yükleme)</label>
                    <input type="file" class="form-control" id="image_path" name="image_path[]" multiple>
                    <?php if (isset($editItem['image_path'])): ?>
                        <div class="mt-2">
                            <p>Mevcut Resimler:</p>
                            <?php
                            $images = explode(',', $editItem['image_path']);
                            foreach ($images as $image) {
                                echo "<img src='{$image}' alt='Mevcut Resim' class='img-thumbnail' style='max-width: 100px; margin-top: 10px;'>";
                            }
                            ?>
                            <input type="hidden" name="image_path" value="<?php echo $editItem['image_path']; ?>">
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" name="<?php echo isset($editItem) ? 'updateItem' : 'addItem'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editItem) ? 'Öğeyi Güncelle' : 'Öğe Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Carousel Öğeleri Listesi -->
    <h3 class="text-center mb-4">Mevcut Carousel Öğeleri</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Açıklama</th>
                    <th>Resim</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $carouselItemManager->listItemsByCarousel($_GET['carousel_id']);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['title']}</td>
                            <td>{$row['description']}</td>
                            <td><img src='{$row['image_path']}' alt='{$row['title']}' class='img-thumbnail' width='50'></td>
                            <td>
                                <a href='?page=carousel&carousel_id={$_GET['carousel_id']}&edit_item_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
                                <form method='POST' style='display:inline-block; margin-left: 5px;'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <button type='submit' name='deleteItem' class='btn btn-danger btn-sm'>Sil</button>
                                </form>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
