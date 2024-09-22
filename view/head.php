<?php
include_once 'class/database.php';
include_once 'class/menu.php';
include_once 'class/slider.php';
include_once 'class/page.php';
include_once 'class/blog.php';
include_once 'class/carousel.php';
include_once 'class/footer.php';
include_once 'class/special.php';
include_once 'class/firewallsimple.php';
include_once 'class/pagemanager.php';
include_once 'class/section.php';

// Veritabanındaki favicon, logo, custom_css, custom_js ve dil (language) değerlerini al
$favicon = isset($website_settings[0]['favicon']) ? $website_settings[0]['favicon'] : '';
$logo = isset($website_settings[0]['logo']) ? $website_settings[0]['logo'] : '';
$custom_css = isset($website_settings[0]['custom_css']) ? $website_settings[0]['custom_css'] : '';
$custom_js = isset($website_settings[0]['custom_js']) ? $website_settings[0]['custom_js'] : '';
$language = isset($website_settings[0]['language']) ? $website_settings[0]['language'] : 'en';
$cprt = isset($website_settings[0]['copyright_text']) ? $website_settings[0]['copyright_text'] : 'Copyright';

// Belirtilen sayfaya ait slaytları listele
$slide = new Slide($db);

if (!empty($page_data)) {
    $page_id = $page_data['id'];
} elseif (!empty($blog_data)) {
    $page_id = $blog_data['id']; // $blog_data boş değilse, blog verisinden id al
} else {
    $page_id = null; // Her iki durumda da id bulunamazsa null olarak ayarla
}

$slides = $slide->listSlidesByPage($page_id);

// Carousel ve CarouselItem sınıflarını başlat
$carousel = new Carousel($db);
$carouselItem = new CarouselItem($db);

// Tüm carouselleri getir
$carousels = $carousel->listCarousels();

$sectionManager = new SectionManager($db);
$sections = $sectionManager->listSections();

$footerManager = new FooterManager($db);
$footer_data = $footerManager->getFooter();

if (!$footer_data) {
    die("Footer bilgileri bulunamadı.");
}
$company_name = isset($footer_data['phone_number']) ? htmlspecialchars($footer_data['phone_number']) : '';
$address = isset($footer_data['address']) ? htmlspecialchars($footer_data['address']) : '';
$social_media_links = isset($footer_data['social_media_links']) ? json_decode($footer_data['social_media_links'], true) : [];

// Sosyal medya linklerinin her biri boşsa varsayılan linkler atanır
$twitter_link = isset($social_media_links['twitter']) ? htmlspecialchars($social_media_links['twitter']) : '#';
$instagram_link = isset($social_media_links['instagram']) ? htmlspecialchars($social_media_links['instagram']) : '#';
$facebook_link = isset($social_media_links['facebook']) ? htmlspecialchars($social_media_links['facebook']) : '#';

// Sosyal medya bağlantılarını JSON formatından diziye çevir
$social_media_links = json_decode($footer_data['social_media_links'], true);
$page_title = isset($page_data['page_title']) ? $page_data['page_title'] : (isset($blog_data['title']) ? $blog_data['title'] : 'Blog Listesi');
?>