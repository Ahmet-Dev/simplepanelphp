<?php
require_once 'view/head.php';
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($language); ?>">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Sayfa başlığı -->
    <title>
      <?php 
      echo !empty($page_data['page_title']) ? 
           htmlspecialchars($page_data['page_title']) : 
           (!empty($blog_data['title']) ? htmlspecialchars($blog_data['title']) : 'Blog Listesi');
      ?>
    </title>

    <!-- Meta description -->
    <meta name="description" content="<?php 
      echo !empty($page_data['meta_description']) ? 
           htmlspecialchars($page_data['meta_description']) : 
           (!empty($blog_data['meta_description']) ? htmlspecialchars($blog_data['meta_description']) : 'Ahmet Kahramanın Blog Listesi Sayfası');
    ?>">

    <!-- Meta keywords -->
    <meta name="keywords" content="<?php 
      echo !empty($page_data['meta_keywords']) ? 
           htmlspecialchars($page_data['meta_keywords']) : 
           (!empty($blog_data['meta_keywords']) ? htmlspecialchars($blog_data['meta_keywords']) : 'ahmet kahraman');
    ?>">

    <!-- Meta author -->
    <meta name="author" content="<?php 
      echo !empty($page_data['meta_author']) ? 
           htmlspecialchars($page_data['meta_author']) : 
           (!empty($blog_data['meta_author']) ? htmlspecialchars($blog_data['meta_author']) : 'ahmet kahraman');
    ?>">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>" type="image/x-icon">

    <!-- Custom CSS -->
    <?php if (!empty($custom_css)): ?>
    <style>
        <?php echo $custom_css; ?>
    </style>
    <?php endif; ?>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Custom JS -->
    <?php if (!empty($custom_js)): ?>
    <script>
        <?php echo $custom_js; ?>
    </script>
    <?php endif; ?>
  </head>
  <body>
<!-- Header -->
<header class="d-flex flex-wrap justify-content-center py-3 mb-4 border-bottom" style="margin-left:2%; margin-right:2%;">
    <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
        <!-- Logo -->
        <img src="<?php echo htmlspecialchars($logo); ?>" width="160" height="45" style="width: 160px; height:45px;" alt="Site Logo">
    </a>

    <ul class="nav nav-pills">
        <?php
        while ($menu = $menus->fetch(PDO::FETCH_ASSOC)) {
            $active = ($menu['name'] == $page_title) ? 'active' : '';
            echo '<li class="nav-item"><a href="' . htmlspecialchars($menu['link']) . '" class="nav-link ' . $active . '">' . htmlspecialchars($menu['name']) . '</a></li>';
        }
        ?>
    </ul>
</header>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
