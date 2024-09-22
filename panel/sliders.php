<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/slider.php';
include_once 'class/session.php';
include_once 'class/user.php';
include_once 'class/image.php'; // ImageManager class

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}

$sliderManager = new Slider($db);
$slideManager = new Slide($db);
$imageManager = new ImageManager($db); // ImageManager örneği

// Mevcut slider'ları ve sayfaları çek
$sliders = $sliderManager->listSliders();
$pages = $db->query("SELECT id, title FROM pages")->fetchAll(PDO::FETCH_ASSOC);

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_slider'])) {
        $sliderManager->addSlider($_POST['name'], $_POST['description']);
    } elseif (isset($_POST['update_slider'])) {
        $sliderManager->updateSlider($_POST['slider_id'], $_POST['name'], $_POST['description']);
    } elseif (isset($_POST['delete_slider'])) {
        $sliderManager->deleteSlider($_POST['slider_id']);
    } elseif (isset($_POST['add_slide'])) {
        // Çoklu resim yükleme işlemi
        $imagePaths = [];
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
        $image_path = !empty($imagePaths) ? implode(',', $imagePaths) : ($_POST['image_path'] ?? '');
        $slideManager->addSlide($_POST['slider_id'], $_POST['page_id'], $_POST['title'], $_POST['description'], $_POST['link'], $image_path);
    } elseif (isset($_POST['update_slide'])) {
        $imagePaths = [];
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
        $image_path = !empty($imagePaths) ? implode(',', $imagePaths) : ($_POST['image_path'] ?? '');
        $slideManager->updateSlide($_POST['slide_id'], $_POST['page_id'], $_POST['title'], $_POST['description'], $_POST['link'], $image_path);
    } elseif (isset($_POST['delete_slide'])) {
        $slideManager->deleteSlide($_POST['slide_id']);
    }

    header("Location: admin?page=slider");
    exit();
}

// Slider ve slayt düzenleme formu için mevcut verileri al
$editSlider = null;
$editSlide = null;

if (isset($_GET['edit_slider_id'])) {
    $editSlider = $sliderManager->getSliderById($_GET['edit_slider_id']);
}

if (isset($_GET['edit_slide_id'])) {
    $editSlide = $slideManager->getSlideById($_GET['edit_slide_id']);
}

// Tüm slaytları listele
$allSlides = $slideManager->listAllSlides(); // Yeni fonksiyon ile tüm slaytları çekiyoruz
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slider Yöneticisi</title>
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
    <h2 class="text-center mb-4">Slider Yönetimi</h2>
    <!-- Slider Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editSlider) ? 'Sliderı Güncelle' : 'Slider Ekle'; ?></h5>
            <hr>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="slider_id" value="<?php echo $editSlider['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Slider Adı</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $editSlider['name'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Slider Açıklaması</label>
                    <input type="text" class="form-control" id="description" name="description" value="<?php echo $editSlider['description'] ?? ''; ?>" required>
                </div>
                <button type="submit" name="<?php echo isset($editSlider) ? 'update_slider' : 'add_slider'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editSlider) ? 'Sliderı Güncelle' : 'Slider Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Slayt Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editSlide) ? 'Slaytı Güncelle' : 'Slayt Ekle'; ?></h5>
            <hr>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="slide_id" value="<?php echo $editSlide['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="slider_id" class="form-label">Slider Seç</label>
                    <select class="form-control" id="slider_id" name="slider_id" required>
                        <?php foreach ($sliders as $slider): ?>
                            <option value="<?php echo $slider['id']; ?>" <?php echo isset($editSlide['slider_id']) && $editSlide['slider_id'] == $slider['id'] ? 'selected' : ''; ?>>
                                <?php echo $slider['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="page_id" class="form-label">Sayfa Seç</label>
                    <select class="form-control" id="page_id" name="page_id" required>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo $page['id']; ?>" <?php echo isset($editSlide['page_id']) && $editSlide['page_id'] == $page['id'] ? 'selected' : ''; ?>>
                                <?php echo $page['title']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">Başlık</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo $editSlide['title'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3" required><?php echo $editSlide['description'] ?? ''; ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="link" class="form-label">Link</label>
                    <input type="text" class="form-control" id="link" name="link" value="<?php echo $editSlide['link'] ?? ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="image_path" class="form-label">Görsel Yolu</label>
                    <input type="file" class="form-control" id="image_path" name="image_path[]" multiple>
                    <?php if (isset($editSlide['image_path'])): ?>
                        <div class="mt-2">
                            <p>Mevcut Resimler:</p>
                            <?php
                            $images = explode(',', $editSlide['image_path']);
                            foreach ($images as $image) {
                                echo "<img src='{$image}' alt='Mevcut Resim' class='img-thumbnail' style='max-width: 100px; margin-top: 10px;'>";
                            }
                            ?>
                            <input type="hidden" name="image_path" value="<?php echo $editSlide['image_path']; ?>">
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" name="<?php echo isset($editSlide) ? 'update_slide' : 'add_slide'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editSlide) ? 'Slaytı Güncelle' : 'Slayt Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Slider Listesi -->
    <h3 class="text-center mb-4">Mevcut Sliderlar</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Ad</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $sliderManager->listSliders();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['name']}</td>
                            <td>
                                <a href='admin?page=slider&edit_slider_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
                                <form method='POST' style='display:inline-block;'>
                                    <input type='hidden' name='slider_id' value='{$row['id']}'>
                                    <button type='submit' name='delete_slider' class='btn btn-danger btn-sm'>Sil</button>
                                </form>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Slayt Listesi -->
    <h3 class="text-center mb-4">Mevcut Slaytlar</h3>
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
                while ($row = $allSlides->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['title']}</td>
                            <td>{$row['description']}</td>
                            <td><img src='{$row['image_path']}' alt='{$row['title']}' class='img-thumbnail' width='50'></td>
                            <td>
                                <a href='admin?page=slider&edit_slide_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
                                <form method='POST' style='display:inline-block;'>
                                    <input type='hidden' name='slide_id' value='{$row['id']}'>
                                    <button type='submit' name='delete_slide' class='btn btn-danger btn-sm'>Sil</button>
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
