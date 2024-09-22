<?php
class Blog {
    private $conn;
    private $table_name = "blogs";
    private $robots_file = "/robots.txt"; // Robots.txt dosya yolu

    public function __construct($db) {
        $this->conn = $db;
    }

    // Blog ekleme
    public function addBlog($title, $description, $image_path, $meta_title, $meta_description, $meta_keywords, $meta_author, $slug, $sitemap_link, $sitemap_score, $category_id) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET title=:title, description=:description, image_path=:image_path, 
                      meta_title=:meta_title, meta_description=:meta_description, 
                      meta_keywords=:meta_keywords, meta_author=:meta_author, 
                      slug=:slug, sitemap_link=:sitemap_link, sitemap_score=:sitemap_score, 
                      category_id=:category_id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":image_path", $image_path);
        $stmt->bindParam(":meta_title", $meta_title);
        $stmt->bindParam(":meta_description", $meta_description);
        $stmt->bindParam(":meta_keywords", $meta_keywords);
        $stmt->bindParam(":meta_author", $meta_author);
        $stmt->bindParam(":slug", $slug);
        $stmt->bindParam(":sitemap_link", $sitemap_link);
        $stmt->bindParam(":sitemap_score", $sitemap_score);
        $stmt->bindParam(":category_id", $category_id);

        if($stmt->execute()) {
            // Robots.txt dosyasına ekleme
            $this->addToRobotsTxt($sitemap_link);
            return true;
        }
        return false;
    }

    // Blog düzenleme
    public function updateBlog($id, $title, $description, $image_path, $meta_title, $meta_description, $meta_keywords, $meta_author, $slug, $sitemap_link, $sitemap_score, $category_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET title=:title, description=:description, image_path=:image_path, 
                      meta_title=:meta_title, meta_description=:meta_description, 
                      meta_keywords=:meta_keywords, meta_author=:meta_author, 
                      slug=:slug, sitemap_link=:sitemap_link, sitemap_score=:sitemap_score, 
                      category_id=:category_id 
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":image_path", $image_path);
        $stmt->bindParam(":meta_title", $meta_title);
        $stmt->bindParam(":meta_description", $meta_description);
        $stmt->bindParam(":meta_keywords", $meta_keywords);
        $stmt->bindParam(":meta_author", $meta_author);
        $stmt->bindParam(":slug", $slug);
        $stmt->bindParam(":sitemap_link", $sitemap_link);
        $stmt->bindParam(":sitemap_score", $sitemap_score);
        $stmt->bindParam(":category_id", $category_id);

        if($stmt->execute()) {
            // Robots.txt dosyasına ekleme
            $this->addToRobotsTxt($sitemap_link);
            return true;
        }
        return false;
    }

    // Blog silme
    public function deleteBlog($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Blogları listeleme
    public function listBlogs() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Kategoriye göre blogları listeleme
    public function listBlogsByCategory($category_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":category_id", $category_id);
        $stmt->execute();
        return $stmt;
    }

    // Blogu ID ile getirme
    public function getBlogById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    // Blog slug ile getirme
    public function getBlogBySlug($slug) {
    $query = "SELECT * FROM " . $this->table_name . " WHERE slug = :slug LIMIT 1";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":slug", $slug);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return false;
    }

    // Robots.txt dosyasına ekleme
    private function addToRobotsTxt($sitemap_link) {
        if (file_exists($this->robots_file)) {
            $robots_content = file_get_contents($this->robots_file);
            $new_entry = "\nSitemap: " . $sitemap_link;
            
            // Eğer bu link daha önce eklenmemişse ekle
            if (strpos($robots_content, $new_entry) === false) {
                file_put_contents($this->robots_file, $new_entry, FILE_APPEND);
            }
        } else {
            // Robots.txt dosyası yoksa oluştur ve ekle
            file_put_contents($this->robots_file, "User-agent: *\nDisallow:\nSitemap: " . $sitemap_link);
        }
    }

    // Blogları sayfalı listeleme
    public function listBlogsPaginated($limit, $offset) {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
    
        // Slug güncelleme fonksiyonu
    public function updateSlug($id, $slug) {
        $query = "UPDATE " . $this->table_name . " SET slug=:slug WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":slug", $slug);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

// Son 3 blogu veritabanından al
$query = "SELECT * FROM blogs ORDER BY created_at DESC LIMIT 3";
$stmt = $db->prepare($query);
$stmt->execute();

$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
