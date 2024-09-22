<?php
class CarouselItem {
    private $conn;
    private $table_name = "carousel_items";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Carousel öğesi ekleme
    public function addItem($carousel_id, $title, $description, $link, $image_path) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET carousel_id=:carousel_id, title=:title, description=:description, 
                      link=:link, image_path=:image_path";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":carousel_id", $carousel_id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":link", $link);
        $stmt->bindParam(":image_path", $image_path);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Carousel öğesi düzenleme
    public function updateItem($id, $title, $description, $link, $image_path) {
        $query = "UPDATE " . $this->table_name . " 
                  SET title=:title, description=:description, 
                      link=:link, image_path=:image_path 
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":link", $link);
        $stmt->bindParam(":image_path", $image_path);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Carousel öğesi silme
    public function deleteItem($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Belirli bir carousel'e ait öğeleri listeleme
    public function listItemsByCarousel($carousel_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE carousel_id = :carousel_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":carousel_id", $carousel_id);
        $stmt->execute();
        return $stmt;
    }

    // Carousel öğesini ID ile getirme
    public function getItemById($id) {
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
class Carousel {
    private $conn;
    private $table_name = "carousels";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Carousel ekleme
    public function addCarousel($name) {
        $query = "INSERT INTO " . $this->table_name . " SET name=:name";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":name", $name);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Carousel düzenleme
    public function updateCarousel($id, $name) {
        $query = "UPDATE " . $this->table_name . " SET name=:name WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":name", $name);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Carousel silme
    public function deleteCarousel($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Tüm carousel'leri listeleme
    public function listCarousels() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Carousel'i ID ile getirme
    public function getCarouselById($id) {
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
