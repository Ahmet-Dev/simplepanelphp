<?php
class FooterManager {
    private $conn;
    private $table_name = "footer";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Footer bilgisi ekleme
    public function addFooter($phone_number, $address, $email, $social_media_links, $additional_links) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET phone_number=:phone_number, address=:address, email=:email, 
                    social_media_links=:social_media_links, 
                      additional_links=:additional_links";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":phone_number", $phone_number);
        $stmt->bindParam(":address", $address);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":social_media_links", $social_media_links);
        $stmt->bindParam(":additional_links", $additional_links);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Footer bilgisi düzenleme
    public function updateFooter($id, $phone_number, $address, $email, $social_media_links, $additional_links) {
        $query = "UPDATE " . $this->table_name . " 
                  SET phone_number=:phone_number, address=:address, email=:email, 
                    social_media_links=:social_media_links, 
                      additional_links=:additional_links 
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":phone_number", $phone_number);
        $stmt->bindParam(":address", $address);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":social_media_links", $social_media_links);
        $stmt->bindParam(":additional_links", $additional_links);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Footer bilgisi silme
    public function deleteFooter($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Footer bilgilerini listeleme
    public function listFooters() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Footer bilgilerini ID ile getirme
    public function getFooterById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }

    // Footer bilgilerini tek satırda getirme (varsayılan footer)
    public function getFooter() {
        $query = "SELECT * FROM " . $this->table_name . " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return false;
    }
}
?>
