<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/page.php';
include_once 'class/session.php';
include_once 'class/user.php';
include_once 'class/category.php'; // Kategori yönetimi sınıfını dahil edin

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}

$pagesManager = new PagesManager($db);
$categoryManager = new CategoryManager($db); // Kategori yöneticisi oluştur

// Kategori listesini getir
$categories = $categoryManager->listAllCategories(); // Tüm kategorileri getir

// Düzenleme için sayfa bilgilerini getirmek
$page_data = [];
if (isset($_GET['edit']) && !empty($_GET['id'])) {
    $page_id = $_GET['id'];
    $page_data = $pagesManager->getPageById($page_id); // Sayfa bilgilerini al
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imagePaths = [];

    // Dosya yükleme işlemi varsa (ImageManager ile entegre edildiğinde kullanılabilir)
    if (isset($_FILES['image_path']) && count($_FILES['image_path']['name']) > 0) {
        foreach ($_FILES['image_path']['name'] as $key => $fileName) {
            if ($_FILES['image_path']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    "name" => $_FILES['image_path']['name'][$key],
                    "tmp_name" => $_FILES['image_path']['tmp_name'][$key]
                ];

                try {
                    // Dosyayı WebP formatına çevir ve yükle
                    $imageData = $imageManager->uploadAndConvertToWebP($file, "", false);
                    $imagePaths[] = $imageData['path']; // Yüklenen dosya yollarını topla
                } catch (Exception $e) {
                    echo "Dosya yükleme hatası: " . $e->getMessage();
                }
            }
        }
    }

    // Resim yollarını virgülle ayırarak birleştir
    $imagePathString = implode(',', $imagePaths);

    // Ana sayfa olup olmadığını kontrol et
    $is_mainpage = isset($_POST['is_mainpage']) ? 1 : 0;

    // Slug oluşturma
    $slug = empty($_POST['slug']) ? strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['title'])) : $_POST['slug'];

    if (isset($_POST['add'])) {
        $pagesManager->addPage($_POST['title'], $_POST['content'], $_POST['meta_description'], $_POST['meta_keywords'], $_POST['meta_author'], $slug, $_POST['sitemap_link'], $_POST['sitemap_score'], $_POST['category_id'], $is_mainpage);
        updateSitemap($_POST['sitemap_link'], $_POST['sitemap_score']);
    } elseif (isset($_POST['update'])) {
        $pagesManager->updatePage($_POST['id'], $_POST['title'], $_POST['content'], $_POST['meta_description'], $_POST['meta_keywords'], $_POST['meta_author'], $slug, $_POST['sitemap_link'], $_POST['sitemap_score'], $_POST['category_id'], $is_mainpage);
        updateSitemap($_POST['sitemap_link'], $_POST['sitemap_score']);
    } elseif (isset($_POST['delete'])) {
        $pagesManager->deletePage($_POST['id']);
    }

    header("Location: admin?page=pages");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sayfa Yöneticisi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="shortcut icon" href="../asset/favicon.webp" type="image">
    <link rel="icon" href="../asset/favicon.webp" type="image">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.bubble.css" rel="stylesheet">
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
    </style>
</head>
<body>

<?php include_once 'panel/sidebar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Sayfa Yönetimi</h2>
    
    <!-- Sayfa Ekleme Formu -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Sayfa Ekle / Güncelle</h5>
            <hr>
            <form method="POST">
                <input type="hidden" name="id" value="<?= isset($page_data['id']) ? $page_data['id'] : ''; ?>">
                <div class="mb-3">
                    <label for="title" class="form-label">Başlık</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= isset($page_data['title']) ? $page_data['title'] : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" class="form-control" id="slug" name="slug" value="<?= isset($page_data['slug']) ? $page_data['slug'] : ''; ?>" readonly>
                </div>
                <div class="mb-3">
                    <label for="content" class="form-label">İçerik</label>
                    <div id="editor-container" style="min-height:200px;"><?= isset($page_data['content']) ? $page_data['content'] : ''; ?></div>
                    <input type="hidden" id="content" name="content">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="meta_description" class="form-label">Meta Açıklama</label>
                        <input type="text" class="form-control" id="meta_description" name="meta_description" value="<?= isset($page_data['meta_description']) ? $page_data['meta_description'] : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="meta_keywords" class="form-label">Meta Anahtar Kelimeler</label>
                        <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?= isset($page_data['meta_keywords']) ? $page_data['meta_keywords'] : ''; ?>">
                    </div>
                </div>

                <!-- Dinamik Kategori Seçimi -->
                <div class="mb-3">
                    <label for="category_id" class="form-label">Kategori Seçin</label>
                    <select name="category_id" id="category_id" class="form-control" required>
                        <option value="">Kategori Seçin</option>
                        <?php while ($category = $categories->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?= $category['id']; ?>" <?= isset($page_data['category_id']) && $page_data['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?= $category['name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_mainpage" name="is_mainpage" <?= isset($page_data['is_mainpage']) && $page_data['is_mainpage'] == 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_mainpage">Bu sayfayı ana sayfa olarak ayarla</label>
                </div>
                <button type="submit" name="<?= isset($page_data['id']) ? 'update' : 'add'; ?>" class="btn btn-primary"><?= isset($page_data['id']) ? 'Güncelle' : 'Sayfa Ekle'; ?></button>
            </form>
        </div>
    </div>

    <!-- Sayfa Listesi -->
    <h3 class="text-center mb-4">Mevcut Sayfalar</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>İçerik</th>
                    <th>Ana Sayfa</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pagesManager->listPages();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                            <td>" . htmlspecialchars($row['id']) . "</td>
                            <td>" . htmlspecialchars($row['title']) . "</td>
                            <td>" . htmlspecialchars(substr($row['content'], 0, 50)) . "...</td>
                            <td>" . ($row['is_mainpage'] == 1 ? 'Evet' : 'Hayır') . "</td>
                            <td>
                                <a href='admin?page=pages&edit=true&id=" . htmlspecialchars($row['id']) . "' class='btn btn-warning btn-sm'>Düzenle</a>
                                <form method='POST' style='display:inline-block;'>
                                    <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>
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

<script>
document.getElementById('title').addEventListener('input', function() {
    var title = this.value;
    var slug = title.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')   // Alfanumerik olmayan karakterleri kaldır
                    .replace(/\s+/g, '-')           // Boşlukları tire ile değiştir
                    .replace(/-+/g, '-');           // Birden fazla tireyi tek tire ile değiştir

    document.getElementById('slug').value = slug;   // Slug alanını doldur
});
</script>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    var toolbarOptions = [
        [{ 'font': [] }, { 'size': [] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'script': 'sub'}, { 'script': 'super' }],
        [{ 'header': 1 }, { 'header': 2 }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'indent': '-1'}, { 'indent': '+1' }],
        [{ 'direction': 'rtl' }],
        [{ 'align': [] }],
        ['link', 'image', 'video', 'blockquote', 'code-block'],
        ['clean']
    ];

    var quill = new Quill('#editor-container', {
        modules: {
            toolbar: toolbarOptions
        },
        theme: 'snow'
    });

    document.querySelector('form').onsubmit = function() {
        document.querySelector('#content').value = quill.root.innerHTML;
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

