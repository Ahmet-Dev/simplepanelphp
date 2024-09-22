<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/special.php';
include_once 'class/session.php';
include_once 'class/user.php';
include_once 'class/image.php'; // ImageManager sınıfını dahil edin

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}

$websiteManager = new WebsiteManager($db);
$imageManager = new ImageManager($db);

// Mevcut düzenlenecek veriyi al
$editWebsite = null;
if (isset($_GET['id'])) {
    $editWebsite = $websiteManager->getWebsiteDetails($_GET['id']);
}

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logo ve favicon dosyalarını yükleme
    $logo_path = isset($_FILES['logo']['name']) && $_FILES['logo']['error'] == 0 ? $imageManager->uploadAndConvertToWebP($_FILES['logo'])['path'] : $editWebsite['logo'] ?? '';
    $favicon_path = isset($_FILES['favicon']['name']) && $_FILES['favicon']['error'] == 0 ? $imageManager->uploadAndConvertToWebP($_FILES['favicon'])['path'] : $editWebsite['favicon'] ?? '';

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

    // Kategori ID doğrulama
    $category_id = $_POST['category_id'] ?? null;
    if ($category_id) {
        // Geçerli bir kategori olup olmadığını kontrol edin
        $stmt = $db->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);

        if ($stmt->rowCount() == 0) {
            echo "Geçersiz kategori ID'si!";
            exit(); // Geçersiz kategori olduğunda işlemi sonlandırın
        }
    }

    $schema_data = [
        '@context' => $_POST['schema_context'],
        '@type' => $_POST['schema_type'],
        'name' => $_POST['schema_name'],
        'image' => $_POST['schema_image'],
        '@id' => $_POST['schema_id'],
        'url' => $_POST['schema_url'],
        'telephone' => $_POST['schema_telephone'],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $_POST['schema_streetAddress'],
            'addressLocality' => $_POST['schema_addressLocality'],
            'postalCode' => $_POST['schema_postalCode'],
            'addressCountry' => $_POST['schema_addressCountry']
        ]
    ];

    if (isset($_POST['add'])) {
        $websiteManager->addWebsiteDetails(
            $_POST['name'], 
            $logo_path, 
            $_POST['slogan'], 
            $favicon_path, 
            $_POST['custom_css'], 
            $_POST['custom_js'], 
            $_POST['copyright_text'], 
            $_POST['language'], 
            $schema_data
        );
    } elseif (isset($_POST['update'])) {
        $websiteManager->updateWebsiteDetails(
            $_POST['id'], 
            $_POST['name'], 
            $logo_path, 
            $_POST['slogan'], 
            $favicon_path, 
            $_POST['custom_css'], 
            $_POST['custom_js'], 
            $_POST['copyright_text'], 
            $_POST['language'], 
            $schema_data
        );
    } elseif (isset($_POST['delete'])) {
        $websiteManager->deleteWebsiteDetails($_POST['id']);
    }

    header("Location: admin?page=other");
    exit();
}

// Mevcut tüm verileri listele
$websiteDetails = $websiteManager->getAllWebsiteDetails();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diğer Yönetimi</title>
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
    <h2 class="text-center mb-4">Diğer Yönetimi</h2>

    <!-- Web Site Detayları Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editWebsite) ? 'Güncelle' : 'Ekle'; ?></h5>
            <hr>
            <form method="POST" enctype="multipart/form-data" class="mb-4">
                <input type="hidden" name="id" value="<?php echo $editWebsite['id'] ?? ''; ?>">

                <div class="mb-3">
                    <label for="name" class="form-label">Site Adı</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $editWebsite['name'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="logo" class="form-label">Logo (160*45 px)</label>
                    <input type="file" class="form-control" id="logo" name="logo">
                    <?php if (isset($editWebsite['logo'])): ?>
                        <img src="<?php echo $editWebsite['logo']; ?>" alt="Mevcut Logo" class="img-thumbnail" style="max-width: 150px; margin-top: 10px;">
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="slogan" class="form-label">Slogan</label>
                    <input type="text" class="form-control" id="slogan" name="slogan" value="<?php echo $editWebsite['slogan'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="favicon" class="form-label">Favicon (Max 512*512 px)</label>
                    <input type="file" class="form-control" id="favicon" name="favicon">
                    <?php if (isset($editWebsite['favicon'])): ?>
                        <img src="<?php echo $editWebsite['favicon']; ?>" alt="Mevcut Favicon" class="img-thumbnail" style="max-width: 32px; margin-top: 10px;">
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="custom_css" class="form-label">Özel CSS</label>
                    <input type="text" class="form-control" id="custom_css" name="custom_css" value="<?php echo $editWebsite['custom_css'] ?? ''; ?>">
                </div>

                <div class="mb-3">
                    <label for="custom_js" class="form-label">Özel JS</label>
                    <input type="text" class="form-control" id="custom_js" name="custom_js" value="<?php echo $editWebsite['custom_js'] ?? ''; ?>">
                </div>

                <div class="mb-3">
                    <label for="copyright_text" class="form-label">Copyright Metni</label>
                    <input type="text" class="form-control" id="copyright_text" name="copyright_text" value="<?php echo $editWebsite['copyright_text'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="language" class="form-label">Dil</label>
                    <input type="text" class="form-control" id="language" name="language" value="<?php echo $editWebsite['language'] ?? ''; ?>" required>
                </div>

                <!-- Schema Markup (Yapılandırılmış Veri) -->
                <h5 class="mt-4">Schema Markup</h5>
                <div class="mb-3">
                    <label for="schema_context" class="form-label">Schema Context</label>
                    <input type="text" class="form-control" id="schema_context" name="schema_context" value="<?php echo $editWebsite['schema_markup']['@context'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_type" class="form-label">Schema Type</label>
                    <input type="text" class="form-control" id="schema_type" name="schema_type" value="<?php echo $editWebsite['schema_markup']['@type'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_name" class="form-label">Schema Name</label>
                    <input type="text" class="form-control" id="schema_name" name="schema_name" value="<?php echo $editWebsite['schema_markup']['name'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_image" class="form-label">Schema Image</label>
                    <input type="text" class="form-control" id="schema_image" name="schema_image" value="<?php echo $editWebsite['schema_markup']['image'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_id" class="form-label">Schema ID</label>
                    <input type="text" class="form-control" id="schema_id" name="schema_id" value="<?php echo $editWebsite['schema_markup']['@id'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_url" class="form-label">Schema URL</label>
                    <input type="text" class="form-control" id="schema_url" name="schema_url" value="<?php echo $editWebsite['schema_markup']['url'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_telephone" class="form-label">Schema Telefon</label>
                    <input type="text" class="form-control" id="schema_telephone" name="schema_telephone" value="<?php echo $editWebsite['schema_markup']['telephone'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_streetAddress" class="form-label">Street Address</label>
                    <input type="text" class="form-control" id="schema_streetAddress" name="schema_streetAddress" value="<?php echo $editWebsite['schema_markup']['address']['streetAddress'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_addressLocality" class="form-label">Address Locality</label>
                    <input type="text" class="form-control" id="schema_addressLocality" name="schema_addressLocality" value="<?php echo $editWebsite['schema_markup']['address']['addressLocality'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_postalCode" class="form-label">Postal Code</label>
                    <input type="text" class="form-control" id="schema_postalCode" name="schema_postalCode" value="<?php echo $editWebsite['schema_markup']['address']['postalCode'] ?? ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="schema_addressCountry" class="form-label">Address Country</label>
                    <input type="text" class="form-control" id="schema_addressCountry" name="schema_addressCountry" value="<?php echo $editWebsite['schema_markup']['address']['addressCountry'] ?? ''; ?>" required>
                </div>

                <button type="submit" name="<?php echo isset($editWebsite) ? 'update' : 'add'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editWebsite) ? 'Güncelle' : 'Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Kayıtları Listeleme -->
    <h3 class="text-center mb-4">Mevcut Kayıtlar</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Site Adı</th>
                    <th>Slogan</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($websiteDetails as $detail): ?>
                <tr>
                    <td><?php echo $detail['id']; ?></td>
                    <td><?php echo $detail['name']; ?></td>
                    <td><?php echo $detail['slogan']; ?></td>
                    <td>
                        <a href="admin?page=other&id=<?php echo $detail['id']; ?>" class="btn btn-warning btn-sm">Düzenle</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $detail['id']; ?>">
                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Sil</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
