<?php
class PagesManager {
    private $conn;
    private $table_name = "pages";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Sayfa ekleme
    public function addPage($title, $content, $meta_description, $meta_keywords, $meta_author, $slug, $sitemap_link, $sitemap_score, $category_id, $is_mainpage) {
        if ($is_mainpage) {
            $this->unsetMainPage(); // Diğer ana sayfa olan sayfaların ana sayfa durumunu sıfırla
        }

        $category_id = !empty($category_id) ? $category_id : null;

        $query = "INSERT INTO " . $this->table_name . " 
                  SET title=:title, content=:content, meta_description=:meta_description, 
                      meta_keywords=:meta_keywords, meta_author=:meta_author,
                      slug=:slug, sitemap_link=:sitemap_link, sitemap_score=:sitemap_score, 
                      category_id=:category_id, is_mainpage=:is_mainpage";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":meta_description", $meta_description);
        $stmt->bindParam(":meta_keywords", $meta_keywords);
        $stmt->bindParam(":meta_author", $meta_author);
        $stmt->bindParam(":slug", $slug);
        $stmt->bindParam(":sitemap_link", $sitemap_link);
        $stmt->bindParam(":sitemap_score", $sitemap_score);
        $stmt->bindParam(":category_id", $category_id, PDO::PARAM_INT);
        $stmt->bindParam(":is_mainpage", $is_mainpage, PDO::PARAM_BOOL);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Sayfa güncelleme
    public function updatePage($id, $title, $content, $meta_description, $meta_keywords, $meta_author, $slug, $sitemap_link, $sitemap_score, $category_id, $is_mainpage) {
        if ($is_mainpage) {
            $this->unsetMainPage(); // Diğer ana sayfa olan sayfaların ana sayfa durumunu sıfırla
        }

        $category_id = !empty($category_id) ? $category_id : null;

        $query = "UPDATE " . $this->table_name . " 
                  SET title=:title, content=:content, meta_description=:meta_description, 
                      meta_keywords=:meta_keywords, meta_author=:meta_author,
                      slug=:slug, sitemap_link=:sitemap_link, sitemap_score=:sitemap_score, 
                      category_id=:category_id, is_mainpage=:is_mainpage
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":meta_description", $meta_description);
        $stmt->bindParam(":meta_keywords", $meta_keywords);
        $stmt->bindParam(":meta_author", $meta_author);
        $stmt->bindParam(":slug", $slug);
        $stmt->bindParam(":sitemap_link", $sitemap_link);
        $stmt->bindParam(":sitemap_score", $sitemap_score);
        $stmt->bindParam(":category_id", $category_id, PDO::PARAM_INT);
        $stmt->bindParam(":is_mainpage", $is_mainpage, PDO::PARAM_BOOL);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Diğer ana sayfaların ana sayfa olma durumunu sıfırlar
    private function unsetMainPage() {
        $query = "UPDATE " . $this->table_name . " SET is_mainpage = 0 WHERE is_mainpage = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
    }

    // Sayfayı ID'ye göre getirme
    public function getPageById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Sayfaları listeleme
    public function listPages() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Sayfayı slug'a göre getirme
    public function getPageBySlug($slug) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE slug = :slug LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":slug", $slug);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    // Sayfayı kategoriye göre listeleme
    public function listPagesByCategory($category_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":category_id", $category_id);
        $stmt->execute();
        return $stmt;
    }

    // Kategorinin var olup olmadığını kontrol et
    public function categoryExists($category_id) {
        $query = "SELECT COUNT(*) FROM categories WHERE id = :category_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":category_id", $category_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

	// Ana sayfayı getiren fonksiyon
    public function getMainPage() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE is_mainpage = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }	
}

function updateSitemap($sitemap_link, $sitemap_score) {
    $sitemapFile = 'sitemap.xml';

    // Dosya var mı ve boyutu 0 mı kontrol et
    if (file_exists($sitemapFile) && filesize($sitemapFile) > 0) {
        // Dosya boş değilse, SimpleXML ile yükle
        try {
            $xml = simplexml_load_file($sitemapFile);
        } catch (Exception $e) {
            // XML yüklenemezse dosyayı sıfırdan oluştur
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
        }
    } else {
        // Dosya yoksa veya boşsa yeni bir XML dosyası oluştur
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
    }

    // Namespace desteği ekleyelim
    $namespaces = $xml->getNamespaces(true);

    // Namespace dahil ederek xpath sorgusu yapalım
    $existingUrl = $xml->xpath("//ns:url[ns:loc='{$sitemap_link}']");
    
    // Namespace için prefix belirtelim
    $xml->registerXPathNamespace('ns', $namespaces['']);

    if ($existingUrl) {
        // Mevcut girdiyi güncelle
        $existingUrl[0]->lastmod = date(DATE_W3C);
        $existingUrl[0]->priority = $sitemap_score;
    } else {
        // Yeni bir giriş ekle
        $urlElement = $xml->addChild('url', null, $namespaces['']);
        $urlElement->addChild('loc', $sitemap_link, $namespaces['']);
        $urlElement->addChild('lastmod', date(DATE_W3C), $namespaces['']);
        $urlElement->addChild('priority', $sitemap_score, $namespaces['']);
    }

    // Güncellenmiş site haritasını kaydet
    $xml->asXML($sitemapFile);
}

?>
