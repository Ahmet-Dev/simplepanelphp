<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/user.php';
include_once 'class/footer.php';
include_once 'class/session.php';
// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}
$footerManager = new FooterManager($db);

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['addFooter'])) {
        $footerManager->addFooter($_POST['phone_number'], $_POST['address'], $_POST['email'], $_POST['social_media_links'], $_POST['additional_links']);
    } elseif (isset($_POST['updateFooter'])) {
        $footerManager->updateFooter($_POST['id'], $_POST['phone_number'], $_POST['address'], $_POST['email'], $_POST['social_media_links'], $_POST['additional_links']);
    } elseif (isset($_POST['deleteFooter'])) {
        $footerManager->deleteFooter($_POST['id']);
    }
	header("Location: admin?page=footer");
    exit();
}

// Footer düzenleme formu için mevcut verileri al
$editFooter = null;
if (isset($_GET['edit_id'])) {
    $editFooter = $footerManager->getFooterById($_GET['edit_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer Yönetimi</title>
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
    <h2 class="text-center mb-4">Footer Yönetimi</h2>

    <!-- Footer Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editFooter) ? 'Footeri Güncelle' : 'Footer Ekle'; ?></h5>
            <hr>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editFooter['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Telefon Numarası</label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo $editFooter['phone_number'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Adres</label>
                    <textarea class="form-control" id="address" name="address" rows="2" required><?php echo $editFooter['address'] ?? ''; ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $editFooter['email'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="social_media_links" class="form-label">Sosyal Medya Linkleri</label>
                    <textarea class="form-control" id="social_media_links" name="social_media_links" rows="2" required><?php echo $editFooter['social_media_links'] ?? ''; ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="additional_links" class="form-label">Ekstra Linkler</label>
                    <textarea class="form-control" id="additional_links" name="additional_links" rows="2"><?php echo $editFooter['additional_links'] ?? ''; ?></textarea>
                </div>
                <button type="submit" name="<?php echo isset($editFooter) ? 'updateFooter' : 'addFooter'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editFooter) ? 'Footeri Güncelle' : 'Footer Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Footer Listesi -->
    <h3 class="text-center mb-4">Mevcut Footer Bilgileri</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Telefon Numarası</th>
                    <th>Adres</th>
                    <th>Email</th>
                    <th>Sosyal Medya</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $footerManager->listFooters();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['phone_number']}</td>
                            <td>{$row['address']}</td>
                            <td>{$row['email']}</td>
                            <td>{$row['social_media_links']}</td>
                            <td>
                                <a href='admin?page=footer&edit_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
                                <form method='POST' style='display:inline-block; margin-left: 5px;'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <button type='submit' name='deleteFooter' class='btn btn-danger btn-sm'>Sil</button>
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
