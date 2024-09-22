<?php include_once './view/header.php'; ?>
<?php 
if (!isset($db)) {
    die("Veritabanı bağlantısı yapılamadı.");
}

try {
    $blog = new Blog($db);
} catch (PDOException $exception) {
    die("Connection error: " . $exception->getMessage());
}

if ($blog_id > 0) {
    // Blogu veritabanından al
    $blog_data = $blog->getBlogById($page_id);
    
    if (!$blog_data) {
        header("Location: /404.php"); // Blog bulunamazsa 404 sayfasına yönlendir
        exit();
    }
} else {
    die("Geçersiz Blog ID.");
}
?>
<div class="container mt-5">
    <h1 class="text-center mb-4"><?php echo htmlspecialchars($blog_data['title']); ?></h1>
    <div class="row">
        <div class="col-md-12">
            <img src="<?php echo htmlspecialchars($blog_data['image_path']); ?>" class="img-fluid mb-4" alt="<?php echo htmlspecialchars($blog_data['meta_title']); ?>">
            <p><?php echo $blog_data['description']; ?></p>
        </div>
    </div>
</div>
    <div class="text-center mt-4">
        <a href="/blogs/" class="btn btn-secondary">Diğer Yazılar</a>
    </div>

<?php include_once("./view/footer.php"); ?>
