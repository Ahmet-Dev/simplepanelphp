<?php
include_once 'class/database.php';
include_once 'class/firewallsimple.php';
$firewall = new Firewall($db);

// POST isteği kontrolü: Yeni IP ekleme veya silme işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ip'])) {
        // Yeni IP engelle
        $new_ip = $_POST['ip_address'];
        if (filter_var($new_ip, FILTER_VALIDATE_IP)) {
            $firewall->blockIP($new_ip, false); // Geçici engelleme (permanent = false)
            echo "<div class='alert alert-success'>IP başarıyla eklendi: $new_ip</div>";
        } else {
            echo "<div class='alert alert-danger'>Geçersiz IP adresi.</div>";
        }
    }

    if (isset($_POST['delete_ip'])) {
        // IP engelini kaldır
        $ip_to_unblock = $_POST['ip_address'];
        $firewall->unblockIP($ip_to_unblock);
        echo "<div class='alert alert-success'>IP başarıyla kaldırıldı: $ip_to_unblock</div>";
    }
}

// Veritabanındaki engellenen IP'leri listeleme
$stmt = $db->query("SELECT ip_address, unblock_time FROM blocked_ips");
$blocked_ips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firewall Yönetimi</title>
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
    <h2>Engellenen IP Yönetimi</h2><hr>

    <!-- Yeni IP Ekleme Formu -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Yeni IP Ekle</h5>
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label for="ip_address" class="form-label">IP Adresi</label>
                    <input type="text" class="form-control" id="ip_address" name="ip_address" required>
                </div>
                <div class="col-md-6">
                    <button type="submit" name="add_ip" class="btn btn-primary mt-4">IP Ekle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Engellenen IP'leri Listeleme -->
    <h3>Mevcut Engellenen IP'ler</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>IP Adresi</th>
                    <th>Engelleme Bitiş Zamanı</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($blocked_ips)): ?>
                    <?php foreach ($blocked_ips as $blocked_ip): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($blocked_ip['ip_address']); ?></td>
                            <td><?php echo $blocked_ip['unblock_time'] ? htmlspecialchars($blocked_ip['unblock_time']) : 'Kalıcı'; ?></td>
                            <td>
                                <!-- Silme İşlemi -->
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($blocked_ip['ip_address']); ?>">
                                    <button type="submit" name="delete_ip" class="btn btn-danger btn-sm">Engeli Kaldır</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Engellenen IP adresi bulunamadı.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
