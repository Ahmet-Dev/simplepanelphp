<?php
class CategoryManager {
    private $conn;
    private $table_name = "categories";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Kategori ekleme
    public function addCategory($name, $type, $parent_id = null) {
        $query = "INSERT INTO " . $this->table_name . " SET name=:name, type=:type, parent_id=:parent_id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":parent_id", $parent_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Kategori düzenleme
    public function updateCategory($id, $name, $type, $parent_id = null) {
        $query = "UPDATE " . $this->table_name . " SET name=:name, type=:type, parent_id=:parent_id WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":parent_id", $parent_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Kategori silme
    public function deleteCategory($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Ana kategorileri listeleme
    public function listParentCategories($type = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE parent_id IS NULL";
        if ($type !== null) {
            $query .= " AND type = :type";
        }
        $stmt = $this->conn->prepare($query);
        if ($type !== null) {
            $stmt->bindParam(":type", $type);
        }
        $stmt->execute();
        return $stmt;
    }

    // Alt kategorileri listeleme
    public function listSubcategories($parent_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE parent_id = :parent_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":parent_id", $parent_id);
        $stmt->execute();
        return $stmt;
    }

    // Tüm kategorileri listeleme
    public function listAllCategories($type = null) {
        $query = "SELECT * FROM " . $this->table_name;
        if ($type !== null) {
            $query .= " WHERE type = :type";
        }
        $stmt = $this->conn->prepare($query);
        if ($type !== null) {
            $stmt->bindParam(":type", $type);
        }
        $stmt->execute();
        return $stmt;
    }

    // Kategoriyi ID ile getirme
    public function getCategoryById($id) {
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
