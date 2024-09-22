<?php
class MenuManager {
    private $conn;
    private $table_name = "menus";

    public function __construct($db) {
        $this->conn = $db;
    }
	
    // Menü ekleme
    public function addMenu($name, $link, $imagePathString) {
        $query = "INSERT INTO " . $this->table_name . " (name, link, image_path)
                  VALUES (:name, :link, :image_path)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":link", $link);
        $stmt->bindParam(":image_path", $imagePathString);

        return $stmt->execute();
    }

    // Menü güncelleme
    public function updateMenu($id, $name, $link, $imagePathString) {
        $query = "UPDATE " . $this->table_name . " 
                  SET name = :name, link = :link, image_path = :image_path 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":link", $link);
        $stmt->bindParam(":image_path", $imagePathString);

        return $stmt->execute();
    }

    // Menü silme
    public function deleteMenu($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Menüyü listeleme
    public function listMenus() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Menü detaylarını getirme
    public function getMenuById($id) {
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
