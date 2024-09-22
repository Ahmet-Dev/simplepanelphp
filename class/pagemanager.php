<?php
require_once './class/database.php';
require_once './class/menu.php';
require_once './class/page.php';
require_once './class/blog.php';
require_once './class/special.php';

// Veritabanı bağlantısı
try {
    $menuManager = new MenuManager($db);
    $settingsManager = new WebsiteManager($db);
    $blogManager = new Blog($db);
    $pagesManager = new PagesManager($db);  // PagesManager sınıfını da dahil ettik
} catch (PDOException $exception) {
    error_log("Veritabanı bağlantı hatası: " . $exception->getMessage());
    die("Veritabanına bağlanırken bir sorun oluştu. Lütfen daha sonra tekrar deneyin.");
}

// Menü öğelerini veritabanından al
$menus = $menuManager->listMenus();

// Website ayarlarını veritabanından alalım
$website_settings = $settingsManager->getAllWebsiteDetails();
$base_url = 'https://' . $_SERVER['SERVER_NAME'];

// URL'den tam yolu al
$url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// URL'yi '/' işaretine göre parçalara ayır
$url_parts = explode('/', trim($url_path, '/'));

// Eğer URL `uploads` ile başlıyorsa, dosya yolunu çözümleyip dosyayı göster
if ($url_parts[0] === 'asset' && $url_parts[1] === 'uploads') {
    // Veritabanındaki base_url ile birleştirerek dosya yolunu oluştur
    $file_url = $base_url . '/' . implode('/', $url_parts);

    // Dosyanın var olup olmadığını kontrol edelim (base_url yerine gerçek dosya sisteminde kontrol için file_exists gerekebilir)
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . implode('/', $url_parts); // Sunucu üzerinde gerçek dosya yolunu kontrol et
    if (file_exists($file_path)) {
        // Doğrudan dosyayı sunucuya gönder
        header('Content-Type: ' . mime_content_type($file_path));
        readfile($file_path);
        exit;
    } else {
        // Eğer dosya bulunamazsa 404 döndür
        http_response_code(404);
        include './404.php';
        exit;
    }
}

// Eğer URL boşsa, ana sayfayı yükle
if (empty($url_parts[0])) {
    $page_data = $pagesManager->getMainPage();
    if ($page_data) {
        include_once './view/page.php'; // Ana sayfa için view/page.php dosyasını dahil ediyoruz
    } else {
        include './404.php'; // Özel 404 sayfası
    }
} 
// Eğer URL'de 'blogs' varsa blog sayfasını yönet
elseif ($url_parts[0] === 'blogs') {
    if (isset($url_parts[1]) && $url_parts[1] === 'blog' && isset($url_parts[2])) {
        $blog_id = intval($url_parts[2]); // Blog ID'si alınıyor

        // Blog verisini alıyoruz
        $blog_data = $blogManager->getBlogById($blog_id);

        if ($blog_data) {
            // Blog single dosyasını yükle
            if (file_exists('./view/blog_single.php')) {
                include_once './view/blog_single.php';
            } else {
                include './404.php'; // Blog single dosyası yoksa 404 göster
            }
        } else {
            // Blog bulunamadıysa 404 göster
            http_response_code(404);
            include './404.php';
        }
    }
    else {
        // Blog listesi gösterilecek
        if (file_exists('./view/blog_list.php')) {
            include_once 'view/blog_list.php';
        } else {
            include './404.php'; // Blog listesi dosyası yoksa 404 göster
        }
    }
}

// Diğer sayfalar için slug'ı kullanarak sayfa yükle
else {
    // URL'deki ilk parçayı slug olarak alıyoruz
    $page_slug = htmlspecialchars($url_parts[0]);
    
    // Slug ile sayfa verilerini veritabanından alıyoruz
    $page_data = $pagesManager->getPageBySlug($page_slug);
    
    if ($page_data) {
        // Eğer sayfa bulunduysa, ilgili sayfa görünümünü dahil ediyoruz
        include_once './view/page.php';
    } else {
        // Sayfa bulunamazsa 404 sayfası veya hata mesajı gösteriyoruz
        http_response_code(404); // 404 HTTP kodunu döndürüyoruz
        include './404.php'; // Özel 404 sayfası
    }
}
?>
