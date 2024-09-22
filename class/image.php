<?php
class ImageManager {
    private $conn;
    private $upload_dir;

    public function __construct($db) {
        $this->conn = $db;
        $this->upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/asset/uploads/";

    // Yükleme dizinine yazma izni kontrolü
    if (!is_writable($this->upload_dir)) {
        throw new Exception("Yükleme dizinine yazma izni yok.");
        }
    }

public function uploadAndConvertToWebP($file, $alt_text = "", $lazyload = false) {
    // Maksimum dosya boyutu kontrolü
    $max_file_size = 5 * 2048 * 2048; // 5MB
    if ($file['size'] > $max_file_size) {
        throw new Exception("Dosya boyutu 5MB'yi geçemez.");
    }

    // Benzersiz dosya adı oluşturma
    $unique_name = uniqid() . "." . strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $target_file = $_SERVER['DOCUMENT_ROOT'] . "/asset/uploads/" . $unique_name;

    // Dosya uzantısını kontrol et
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif", "webp");
    if (!in_array($imageFileType, $allowed_extensions)) {
        throw new Exception("Sadece JPG, JPEG, PNG, WEBP ve GIF dosyalarına izin verilir.");
    }

    // Dosyayı belirtilen klasöre taşı
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        if (!file_exists($target_file)) {
            throw new Exception("Yüklenen dosya bulunamadı.");
        }

        // GIF veya palet destekli PNG ise WebP'ye dönüştürme
        if ($imageFileType === 'gif' || $this->isPaletteImage($target_file)) {
            $relative_web_path = "/asset/uploads/" . $unique_name;
            $full_url = "https://" . $_SERVER['HTTP_HOST'] . $relative_web_path;
            return array(
                "path" => $full_url,
                "alt" => $alt_text,
                "lazyload" => $lazyload
            );
        } else {
            // WebP formatına dönüştür
            $webp_file = $this->convertToWebP($target_file, $imageFileType);

            // Relative web path ve tam URL oluştur
            $relative_web_path = "/asset/uploads/" . basename($webp_file);
            $full_url = "https://" . $_SERVER['HTTP_HOST'] . $relative_web_path;

            return array(
                "path" => $full_url,
                "alt" => $alt_text,
                "lazyload" => $lazyload
            );
        }
    } else {
        throw new Exception("Dosya yükleme sırasında hata oluştu.");
    }
}


    // WebP formatına dönüştürme
    private function convertToWebP($file, $imageFileType) {
        $webp_file = pathinfo($file, PATHINFO_DIRNAME) . "/" . pathinfo($file, PATHINFO_FILENAME) . ".webp";

        switch ($imageFileType) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($file);
                break;
            case 'png':
                $image = @imagecreatefrompng($file);
                break;
            case 'gif':
                $image = @imagecreatefromgif($file);
                break;
            case 'webp':
                $image = @imagecreatefromwebp($file);
                break;
            default:
                throw new Exception("Geçersiz resim formatı.");
        }

        if (!$image) {
            throw new Exception("Resim oluşturulamadı.");
        }

        if (imagewebp($image, $webp_file)) {
            imagedestroy($image);
            unlink($file); // Orijinal dosyayı sil
            return $webp_file;
        } else {
            imagedestroy($image);
            throw new Exception("WebP formatına dönüştürme başarısız oldu.");
        }
    }

    // Paletli görüntü olup olmadığını kontrol et
    private function isPaletteImage($file_path) {
        $info = getimagesize($file_path);
        if ($info === false) {
            throw new Exception("Resim bilgileri alınamadı.");
        }
        return (isset($info['channels']) && $info['channels'] == 1);
    }
}
?>
