<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/user.php';
include_once 'class/blog.php';
include_once 'class/page.php';
include_once 'class/session.php';
include_once 'class/image.php';
include_once 'class/category.php'; // CategoryManager sınıfını dahil edin

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}

$blogManager = new Blog($db);
$imageManager = new ImageManager($db); // ImageManager örneği oluşturun
$categoryManager = new CategoryManager($db); // CategoryManager örneği oluşturun

// Kategori listesini getir
$categories = $categoryManager->listAllCategories(); // Tüm kategorileri getir

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imagePaths = []; // Yüklenen resim yollarını saklayacak dizi

    // Image upload işlemi
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

    // Eğer yüklenen resim varsa, resim yolunu dizi olarak saklayın
    $image_path = !empty($imagePaths) ? implode(',', $imagePaths) : ($_POST['image_path'] ?? '');

    // Kategori ID doğrulama
    $category_id = $_POST['category_id'] ?? null;

    // Blog ekleme/güncelleme işlemleri
    if (isset($_POST['add'])) {
        $blogAdded = $blogManager->addBlog(
            $_POST['title'],
            $_POST['description'],
            $image_path,
            $_POST['meta_title'],
            $_POST['meta_description'],
            $_POST['meta_keywords'],
            $_POST['meta_author'],
            '', // Slug başta boş kalacak, sonra ID ile güncellenecek
            $_POST['sitemap_link'],
            $_POST['sitemap_score'],
            $category_id
        );

        if ($blogAdded) {
            $blog_id = $db->lastInsertId();
            $slug = 'blog-' . $blog_id;
            $blogManager->updateSlug($blog_id, $slug);
            updateSitemap($_POST['sitemap_link'], $_POST['sitemap_score']);
        }
    } elseif (isset($_POST['update'])) {
        $blogManager->updateBlog(
            $_POST['id'],
            $_POST['title'],
            $_POST['description'],
            $image_path,
            $_POST['meta_title'],
            $_POST['meta_description'],
            $_POST['meta_keywords'],
            $_POST['meta_author'],
            '', // Slug yine otomatik olarak güncellenecek
            $_POST['sitemap_link'],
            $_POST['sitemap_score'],
            $category_id
        );
        $slug = 'blog-' . $_POST['id'];
        $blogManager->updateSlug($_POST['id'], $slug);
        updateSitemap($_POST['sitemap_link'], $_POST['sitemap_score']);
    } elseif (isset($_POST['delete'])) {
        $blogManager->deleteBlog($_POST['id']);
    }

    header("Location: admin?page=blog");
    exit();
}

// Blog düzenleme formu için mevcut verileri al
$editBlog = null;
if (isset($_GET['edit_id'])) {
    $editBlog = $blogManager->getBlogById($_GET['edit_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Yönetimi</title>
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="shortcut icon" href="../asset/favicon.webp" type="image">
    <link rel="icon" href="../asset/favicon.webp" type="image">
</head>
<body>
    
<?php include_once 'panel/sidebar.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Blog Yönetimi</h2>

    <!-- Blog Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editBlog) ? 'Blogu Güncelle' : 'Blog Ekle'; ?></h5>
            <hr>
            <form method="POST" class="mb-4" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $editBlog['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="title" class="form-label">Başlık</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo $editBlog['title'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <div id="editor-container" style="min-height:200px;"><?php echo $editBlog['description'] ?? ''; ?></div>
                    <input type="hidden" id="description" name="description">
                </div>
                <div class="mb-3">
                    <label for="image_path" class="form-label">Görsel Yolu</label>
                    <input type="file" class="form-control" id="image_path" name="image_path[]" multiple>
                </div>
                <div class="mb-3">
                    <label for="meta_title" class="form-label">Meta Başlık</label>
                    <input type="text" class="form-control" id="meta_title" name="meta_title" value="<?php echo $editBlog['meta_title'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="meta_description" class="form-label">Meta Açıklama</label>
                    <input type="text" class="form-control" id="meta_description" name="meta_description" value="<?php echo $editBlog['meta_description'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="meta_keywords" class="form-label">Meta Anahtar Kelimeler</label>
                    <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?php echo $editBlog['meta_keywords'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="meta_author" class="form-label">Meta Yazar</label>
                    <input type="text" class="form-control" id="meta_author" name="meta_author" value="<?php echo $editBlog['meta_author'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="sitemap_link" class="form-label">Sitemap Linki</label>
                    <input type="text" class="form-control" id="sitemap_link" name="sitemap_link" value="<?php echo $editBlog['sitemap_link'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="sitemap_score" class="form-label">Sitemap Skoru</label>
                    <input type="text" class="form-control" id="sitemap_score" name="sitemap_score" value="<?php echo $editBlog['sitemap_score'] ?? ''; ?>" required>
                </div>

                <!-- Kategori Seçimi -->
                <div class="mb-3">
                    <label for="category_id" class="form-label">Kategori Seçin</label>
                    <select name="category_id" id="category_id" class="form-control" required>
                        <option value="">Kategori Seçin</option>
                        <?php while ($category = $categories->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?= $category['id']; ?>" <?= isset($editBlog['category_id']) && $editBlog['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?= $category['name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" name="<?php echo isset($editBlog) ? 'update' : 'add'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editBlog) ? 'Blogu Güncelle' : 'Blog Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Blog Listesi -->
    <h3 class="text-center mb-4">Mevcut Bloglar</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Başlık</th>
                    <th>Açıklama</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $blogManager->listBlogs();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['title']}</td>
                            <td>" . substr($row['description'], 0, 50) . "...</td>
                            <td>
                                <form method='POST' style='display:inline-block; margin-right: 5px;'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <button type='submit' name='delete' class='btn btn-danger btn-sm'>Sil</button>
                                </form>
                                <a href='admin?page=blog&edit_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

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
        document.querySelector('#description').value = quill.root.innerHTML;
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
