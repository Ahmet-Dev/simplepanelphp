<?php
class WebsiteManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Function to add website details
    public function addWebsiteDetails($name, $logo, $slogan, $favicon, $custom_css, $custom_js, $copyright_text, $language, $schema_markup) {
        $sql = "INSERT INTO website_details (name, logo, slogan, favicon, custom_css, custom_js, copyright_text, language, schema_markup) 
                VALUES (:name, :logo, :slogan, :favicon, :custom_css, :custom_js, :copyright_text, :language, :schema_markup)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':logo' => $logo,
            ':slogan' => $slogan,
            ':favicon' => $favicon,
            ':custom_css' => $custom_css,
            ':custom_js' => $custom_js,
            ':copyright_text' => $copyright_text,
            ':language' => $language,
            ':schema_markup' => json_encode($schema_markup),
        ]);
        return $this->pdo->lastInsertId();
    }

    // Function to delete website details by ID
    public function deleteWebsiteDetails($id) {
        $sql = "DELETE FROM website_details WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }

    // Function to get website details by ID
    public function getWebsiteDetails($id) {
        $sql = "SELECT * FROM website_details WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Function to update website details
    public function updateWebsiteDetails($id, $name, $logo, $slogan, $favicon, $custom_css, $custom_js, $copyright_text, $language, $schema_markup) {
        $sql = "UPDATE website_details 
                SET name = :name, logo = :logo, slogan = :slogan, favicon = :favicon, custom_css = :custom_css, custom_js = :custom_js, 
                    copyright_text = :copyright_text, language = :language, schema_markup = :schema_markup
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':logo' => $logo,
            ':slogan' => $slogan,
            ':favicon' => $favicon,
            ':custom_css' => $custom_css,
            ':custom_js' => $custom_js,
            ':copyright_text' => $copyright_text,
            ':language' => $language,
            ':schema_markup' => json_encode($schema_markup),
            ':id' => $id,
        ]);
        return $stmt->rowCount();
    }
    public function addOrUpdateWebsiteDetails($id, $name, $logo, $slogan, $favicon, $custom_css, $custom_js, $copyright_text, $language, $schema_markup) {
        // Kayıt mevcut mu diye kontrol et
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM website WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // Güncelle
            $stmt = $this->db->prepare("UPDATE website SET name = :name, logo = :logo, slogan = :slogan, favicon = :favicon, custom_css = :custom_css, custom_js = :custom_js, copyright_text = :copyright_text, language = :language, schema_markup = :schema_markup WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':logo' => $logo,
                ':slogan' => $slogan,
                ':favicon' => $favicon,
                ':custom_css' => $custom_css,
                ':custom_js' => $custom_js,
                ':copyright_text' => $copyright_text,
                ':language' => $language,
                ':schema_markup' => json_encode($schema_markup),
                ':id' => $id
            ]);
        } else {
            // Yeni kayıt ekle
            $stmt = $this->pdo->prepare("INSERT INTO website (name, logo, slogan, favicon, custom_css, custom_js, copyright_text, language, schema_markup) VALUES (:name, :logo, :slogan, :favicon, :custom_css, :custom_js, :copyright_text, :language, :schema_markup)");
            $stmt->execute([
                ':name' => $name,
                ':logo' => $logo,
                ':slogan' => $slogan,
                ':favicon' => $favicon,
                ':custom_css' => $custom_css,
                ':custom_js' => $custom_js,
                ':copyright_text' => $copyright_text,
                ':language' => $language,
                ':schema_markup' => json_encode($schema_markup)
            ]);
        }
    }
    // Tüm web sitesi detaylarını al
    public function getAllWebsiteDetails() {
        $stmt = $this->pdo->prepare("SELECT * FROM website_details");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>