<?php
class Database {
    private $host = '127.0.0.1:3306';
    private $db_name = '';
    private $username = '';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
    public function getDb()
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
            ob_start();
        }
    }
    //Bağlantı Kapama
    public function closeConnection()
    {
        return $this->conn = null;
        ob_end_flush();
    }
}
$dbo = new Database();
$db = $dbo -> getConnection();
$db = $dbo -> getDb();
?>
