<?php
// Veritabanı bağlantısı (örnek)
include_once 'class/database.php';
include_once 'class/user.php';
include_once 'class/category.php';
include_once 'class/session.php';

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isAuthenticated()) {
    header("Location: admin?page=login");
    exit();
}

$categoryManager = new CategoryManager($db);

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $categoryManager->addCategory($_POST['name'], $_POST['parent_id']);
    } elseif (isset($_POST['update'])) {
        $categoryManager->updateCategory($_POST['id'], $_POST['name'], $_POST['parent_id']);
    } elseif (isset($_POST['delete'])) {
        $categoryManager->deleteCategory($_POST['id']);
    }
    header("Location: admin?page=category");
    exit();
}

// Kategori düzenleme formu için mevcut verileri al
$editCategory = null;
if (isset($_GET['edit_id'])) {
    $editCategory = $categoryManager->getCategoryById($_GET['edit_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Yönetimi</title>
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
    <h2 class="text-center mb-4">Kategori Yönetimi</h2>

    <!-- Kategori Ekleme/Düzenleme Formu -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($editCategory) ? 'Kategoriyi Güncelle' : 'Kategori Ekle'; ?></h5>
            <hr>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $editCategory['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Kategori Adı</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $editCategory['name'] ?? ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="parent_id" class="form-label">Ana Kategori</label>
                    <select class="form-control" id="parent_id" name="parent_id">
                        <option value="">Yok</option>
                        <?php
                        $stmt = $categoryManager->listParentCategories();
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = isset($editCategory['parent_id']) && $editCategory['parent_id'] == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' {$selected}>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="<?php echo isset($editCategory) ? 'update' : 'add'; ?>" class="btn btn-primary w-100">
                    <?php echo isset($editCategory) ? 'Kategoriyi Güncelle' : 'Kategori Ekle'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Kategori Listesi -->
    <h3 class="text-center mb-4">Mevcut Kategoriler</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Kategori Adı</th>
                    <th>Ana Kategori</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $categoryManager->listAllCategories();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $parentCategory = $row['parent_id'] ? $categoryManager->getCategoryById($row['parent_id'])['name'] : 'Yok';
                    echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$row['name']}</td>
                            <td>{$parentCategory}</td>
                            <td>
                                <a href='admin?page=category&edit_id={$row['id']}' class='btn btn-warning btn-sm'>Düzenle</a>
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
