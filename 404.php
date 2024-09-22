<?php
// Set 404 header
header("HTTP/1.0 404 Not Found");

// Redirect to the homepage after 5 seconds
// Ana sayfa URL'sini dinamik olarak oluştur
$homepage = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];

// 5 saniye sonra ana sayfaya yönlendirme
header("refresh:5;url=$homepage");

// Display a stylish error message
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sayfa Bulunamadı</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            color: #333;
            text-align: center;
            padding: 50px;
        }
        h1 {
            font-size: 50px;
            color: #e74c3c;
        }
        p {
            font-size: 18px;
            margin: 20px 0;
        }
        a {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 20px;
            background-color: #3498db;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 18px;
        }
        a:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <h1>Sayfa Bulunamadı</h1>
    <p>Aradığınız sayfa mevcut değil. Lütfen ana sayfaya geri dönün.</p>
    <p>5 saniye içinde ana sayfaya yönlendirileceksiniz.</p>
    <a href="<?php echo $homepage; ?>">Ana Sayfaya Dön</a>
</body>
</html>
