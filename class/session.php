<?php
// Sadece panel için geçerlidir.
require_once 'class/user.php';

// Kullanıcı çıkışını kontrol edin
if (isset($_GET['page']) && $_GET['page'] === 'cikis') {
logoutUser();
}

// Geçerli sayfa belirlenir
$page = isset($_GET['page']) ? $_GET['page'] : 'pages'; // Default olarak 'pages' ayarlanır

// Sayfayı belirlemek için if-else blokları
if ($page === 'blog') {
include_once 'panel/blogs.php';
} 
else if ($page === 'carousel') {
include_once 'panel/carousels.php';
}
else if ($page === 'category') {
include_once 'panel/categories.php';
} 
else if ($page === 'footer') {
include_once 'panel/footer.php';
}
else if ($page === 'login') {
include_once 'panel/login.php';
} 
else if ($page === 'menu') {
include_once 'panel/menus.php';
} 
else if ($page === 'other') {  
include_once 'panel/other.php';
} 
else if ($page === 'pages') {  
include_once 'panel/pages.php';
} 
else if ($page === 'slider') {   
include_once 'panel/sliders.php';
} 
else if ($page === 'section') {   
include_once 'panel/section.php';
} 
else if ($page === 'users') {   
include_once 'panel/users.php';
} 
else if ($page === 'firewall') {   
include_once 'panel/wall.php';
}
else {   
include_once 'panel/pages.php';
}
