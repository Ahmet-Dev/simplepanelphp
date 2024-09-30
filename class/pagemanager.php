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

// **robots.txt Yönlendirmesi**
if ($url_parts[0] === 'robots.txt') {
    $robots_path = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
    if (file_exists($robots_path)) {
        header('Content-Type: text/plain');
        readfile($robots_path);
        exit;
    } else {
        http_response_code(404);
        echo "robots.txt bulunamadı.";
        exit;
    }
}

// **ads.txt Yönlendirmesi**
if ($url_parts[0] === 'ads.txt') {
    $ads_path = $_SERVER['DOCUMENT_ROOT'] . '/ads.txt';
    if (file_exists($ads_path)) {
        header('Content-Type: text/plain');
        readfile($ads_path);
        exit;
    } else {
        http_response_code(404);
        echo "ads.txt bulunamadı.";
        exit;
    }
}

// **sitemap.xml Yönlendirmesi**
if ($url_parts[0] === 'sitemap.xml') {
    $sitemap_path = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
    if (file_exists($sitemap_path)) {
        header('Content-Type: application/xml');
        readfile($sitemap_path);
        exit;
    } else {
        http_response_code(404);
        echo "sitemap.xml bulunamadı.";
        exit;
    }
}

// **Asset Klasörüne ve Uploads'a Erişim** - Statik dosyalar için
if ($url_parts[0] === 'asset') {
    // Sunucudaki gerçek dosya yolunu oluştur
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . implode('/', $url_parts);

    // Dosyanın var olup olmadığını ve dosya olup olmadığını kontrol et
    if (file_exists($file_path) && is_file($file_path)) {
        
        // MIME türünü manuel olarak uzantıya göre belirleyelim
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'css':
                $mime_type = 'text/css';
                break;
            case 'js':
                $mime_type = 'application/javascript';
                break;
            case 'woff':
                $mime_type = 'font/woff';
                break;
            case 'woff2':
                $mime_type = 'font/woff2';
                break;
            case 'ttf':
                $mime_type = 'font/ttf';
                break;
            case 'otf':
                $mime_type = 'font/otf';
                break;
            case 'eot':
                $mime_type = 'application/vnd.ms-fontobject';
                break;
            case 'svg':
                $mime_type = 'image/svg+xml';
                break;
            case 'png':
                $mime_type = 'image/png';
                break;
            case 'jpg':
            case 'jpeg':
                $mime_type = 'image/jpeg';
                break;
            case 'gif':
                $mime_type = 'image/gif';
                break;
            case 'pdf':
                $mime_type = 'application/pdf';
                break;
            default:
                // MIME türü bilinmeyen dosyalar için varsayılan tür
                $mime_type = mime_content_type($file_path);
                if (!$mime_type) {
                    $mime_type = 'application/octet-stream'; // Varsayılan dosya türü
                }
                break;
        }

        // Doğru MIME türü ile dosyayı tarayıcıya gönder
        header('Content-Type: ' . $mime_type);
        readfile($file_path);
        exit;
    } else {
        // Dosya bulunamazsa 404 hata sayfası göster
        echo "Dosya bulunamadı veya bir dizin: " . $file_path . "<br>";
        http_response_code(404); // 404 hata kodu
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
