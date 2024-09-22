<?php
class Slide {
    private $conn;
    private $table_name = "slides";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Slayt ekleme
    public function addSlide($slider_id, $page_id, $title, $description, $link, $image_path) {
        // slider_id'nin geçerli olup olmadığını kontrol et
        $sliderCheck = $this->conn->prepare("SELECT id FROM sliders WHERE id = :slider_id");
        $sliderCheck->bindParam(':slider_id', $slider_id);
        $sliderCheck->execute();

        // Eğer slider_id yoksa yeni bir slider oluştur
        if ($sliderCheck->rowCount() == 0) {
            // Yeni bir slider ekle
            $newSlider = $this->conn->prepare("INSERT INTO sliders (name, description) VALUES (:name, :description)");
            $newSliderName = "Rastgele Slider " . rand(1, 1000);  // Rastgele slider ismi
            $newSliderDesc = "Otomatik oluşturulan slider";
            $newSlider->bindParam(':name', $newSliderName);
            $newSlider->bindParam(':description', $newSliderDesc);
            $newSlider->execute();

            // Yeni slider'ın ID'sini al
            $slider_id = $this->conn->lastInsertId();
        }

        // Slaytı ekle
        $query = "INSERT INTO " . $this->table_name . " 
                  SET slider_id=:slider_id, page_id=:page_id, title=:title, description=:description, 
                      link=:link, image_path=:image_path";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":slider_id", $slider_id);
        $stmt->bindParam(":page_id", $page_id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":link", $link);
        $stmt->bindParam(":image_path", $image_path);

        return $stmt->execute();
    }

    // Slayt düzenleme
    public function updateSlide($id, $page_id, $title, $description, $link, $image_path) {
        $query = "UPDATE " . $this->table_name . " 
                  SET page_id=:page_id, title=:title, description=:description, 
                      link=:link, image_path=:image_path 
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":page_id", $page_id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":link", $link);
        $stmt->bindParam(":image_path", $image_path);

        return $stmt->execute();
    }

    // Belirli bir sayfaya ait slaytları listeleme
    public function listSlidesByPage($page_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE page_id = :page_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":page_id", $page_id);
        $stmt->execute();
        return $stmt;
    }

    // Slaytı ID ile getirme
    public function getSlideById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    // Slaytı ID ile silme
    public function deleteSlide($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }
	public function listAllSlides() {
    $query = "SELECT * FROM " . $this->table_name;
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt;
}
}

class Slider {
    private $conn;
    private $table_name = "sliders";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Yeni slider ekleme
    public function addSlider($name, $description) {
        $query = "INSERT INTO sliders (name, description) VALUES (:name, :description)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        return $stmt->execute();
    }

    // Slider güncelleme
    public function updateSlider($id, $name, $description) {
        $query = "UPDATE " . $this->table_name . " 
                  SET name=:name, description=:description 
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":description", $description);

        return $stmt->execute();
    }

    // Slider silme
    public function deleteSlider($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    // Tüm slider'ları listeleme
    public function listSliders() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    // Belirli bir slider'ı ID ile getirme
    public function getSliderById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }
}
?>
