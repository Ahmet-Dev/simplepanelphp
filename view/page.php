<?php require_once './view/header.php'; ?>
<!-- Slider -->
<div class="container mw-100 mh-100 m-0">
    <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php
            $first_slide = true;
            while ($row = $slides->fetch(PDO::FETCH_ASSOC)) {
                $active_class = $first_slide ? 'active' : '';
                $first_slide = false;
                ?>
                <div class="carousel-item <?php echo $active_class; ?>">
                    <img src="<?php echo $row['image_path']; ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($row['title']); ?>">
                    <div class="carousel-caption d-none d-md-block">
                        <h5><?php echo htmlspecialchars($row['title']); ?></h5>
                        <p><?php echo htmlspecialchars($row['description']); ?></p>
                        <?php if (!empty($row['link'])): ?>
                            <a href="<?php echo htmlspecialchars($row['link']); ?>" class="btn btn-primary">Daha Fazla Bilgi</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
            }
            ?>

        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Geri</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">İleri</span>
        </button>
    </div>
</div>

<!-- Content -->
<div class="container w-100 p-3">
<?php echo $page_data['content']; ?>
</div>

<!-- Carousel -->
<div class="container mt-5 w-100 p-3">
    <?php 
    // Her bir carousel'i al ve öğelerini listele
    while ($carouselRow = $carousels->fetch(PDO::FETCH_ASSOC)) {
        $carousel_id = $carouselRow['id'];
        $carousel_name = $carouselRow['name'];
    ?>
        <h2><?php echo htmlspecialchars($carousel_name); ?></h2>
        <div class="row">
            <?php
            // Belirli carousel'e ait tüm öğeleri al
            $carouselItems = $carouselItem->listItemsByCarousel($carousel_id);
            $count = 0;

            while ($itemRow = $carouselItems->fetch(PDO::FETCH_ASSOC)) {
                // Her 3 itemde bir yeni bir satır başlat
                if ($count % 3 == 0 && $count != 0) {
                    echo '</div><div class="row">';
                }

                // Anahtarların varlığını kontrol et ve yoksa varsayılan değer ata
                $image_path = isset($itemRow['image_path']) ? $itemRow['image_path'] : 'default_image.jpg'; // Varsayılan görsel
                $title = isset($itemRow['title']) ? htmlspecialchars($itemRow['title']) : 'Başlık yok';
                $description = isset($itemRow['description']) ? htmlspecialchars($itemRow['description']) : 'Açıklama yok';
                $link = isset($itemRow['link']) ? htmlspecialchars($itemRow['link']) : '#';
            ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <img src="<?php echo $image_path; ?>" class="card-img-top" alt="<?php echo $title; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $title; ?></h5>
                        <p class="card-text"><?php echo $description; ?></p>
                        <a href="<?php echo $link; ?>" class="btn btn-primary">Detaylar</a>
                    </div>
                </div>
            </div>
            <?php
                $count++;
            } // CarouselItem döngüsü sonu
            ?>
        </div>
    <?php
    } // Carousel döngüsü sonu
    ?>
</div>

<div class="container w-100 p-3">
    <h2>Yazılar</h2>
    <div class="row">
        <?php foreach ($blogs as $blog): ?>
            <div class="col-md-4">
                <div class="card mb-4">
                    <img src="<?php echo htmlspecialchars($blog['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($blog['meta_title']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo mb_strimwidth($blog['title'], 0, 100, '...'); ?></h5>
                        <p class="card-text"><?php echo mb_strimwidth($blog['description'], 0, 200, '...'); ?></p>
                        <a href="blogs/blog/<?php echo $blog['id']; ?>" class="btn btn-primary">Devamını Oku</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-4">
        <a href="blogs/" class="btn btn-secondary">Daha Fazlası</a>
    </div>
</div>

<div class="container w-100 p-3">
    <h2>Bölümler</h2>
    <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $section): ?>
            <div class="section">
                <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                <p><?php echo $section['content']; ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center">No sections found.</p>
    <?php endif; ?>
</div>

<?php include_once './view/footer.php'; ?>