<?php

// Eğer AJAX isteği değilse header'ı dahil edin
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax'])) {
    include_once './view/header.php';
}

try {    
    $blog = new Blog($db);
} catch (PDOException $exception) {
    die("Connection error: " . $exception->getMessage());
}

// Eğer AJAX isteği POST ise, blogları veritabanından çek ve sadece blog verilerini döndür
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 8;
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    $stmt = $blog->listBlogsPaginated($limit, $offset);

    $blogCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $blogCount++;
        echo '
        <div class="col-md-4">
            <div class="card mb-4">
                <img src="' . htmlspecialchars($row['image_path']) . '" class="card-img-top" alt="' . htmlspecialchars($row['meta_title']) . '">
                <div class="card-body">
                    <h5 class="card-title">' . substr($row['title'], 0, 120) . '</h5>
                    <p class="card-text">' . substr($row['description'], 0, 240) . '...</p>
                    <a href="/blogs/blog/' . $row['id'] . '" class="btn btn-primary">Devamını Oku</a>
                </div>
            </div>
        </div>';
    }

    // Eğer blog sayısı limitten azsa, yani daha fazla blog yoksa "no-more-data" işaretini döndürmüyoruz
    if ($blogCount < $limit) {
        echo '';  // Hiçbir şey döndürmüyoruz, buton gizlenecek
    }
    exit; // AJAX isteği tamamlandıktan sonra buradan çık.
}
?>

<!-- Eğer AJAX isteği değilse HTML yapısını döndürüyoruz -->
<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax'])): ?>
<div class="container mt-5">
    <h1 class="text-center mb-4">Blog Listesi</h1>
    <div class="row" id="blogs">
        <!-- Blog Kartları Buraya Yüklenecek -->
    </div>
    <div class="text-center mt-4">
        <button id="load-more" class="btn btn-primary" style="display: none;">Daha Fazla Yükle</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- JavaScript -->
<script>
$(document).ready(function() {
    var limit = 8;
    var offset = 0;
    var totalLoaded = 0;  // Yüklenen blog sayısını takip et
    var loading = false;  // Yükleme durumunu takip etmek için

    function loadBlogs(limit, offset, initialLoad = false) {
        if (loading) return;  // Eğer halihazırda yükleme yapılıyorsa başka istek yapma
        loading = true;
        
        $.ajax({
            url: window.location.href,  // Aynı sayfaya istek gönderiyoruz
            method: 'POST',
            data: {limit: limit, offset: offset, ajax: true},  // ajax parametresini gönderiyoruz
            success: function(data) {
                loading = false;
                
                if (data.trim() === '') {
                    // Daha fazla veri yoksa "Load More" butonunu gizle
                    $('#load-more').hide();
                } else {
                    $('#blogs').append(data);  // Blog verisini ekle
                    offset += limit;  // Offset'i güncelle
                    totalLoaded += limit;  // Toplam yüklenen blog sayısını artır

                    if (totalLoaded >= limit) {
                        $('#load-more').show();  // Yüklenen blog sayısı 8'den fazla ise butonu göster
                    }
                }
            },
            error: function() {
                alert("Bir hata oluştu, lütfen tekrar deneyin.");  // Hata durumunu yönet
                loading = false;
            }
        });
    }

    // Sayfa yüklendiğinde ilk 8 blogu getir
    loadBlogs(limit, offset, true);

    // Daha Fazla Yükle butonuna tıklandığında sonraki 8 blogu getir
    $('#load-more').click(function() {
        loadBlogs(limit, offset);
    });
});
</script>

<?php include_once("./view/footer.php"); ?>
<?php endif; ?>
