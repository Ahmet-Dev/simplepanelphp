<?php
// Set 403 header
header("HTTP/1.0 403 Forbidden");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erişim Engellendi</title>
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
    <h1>Erişim Engellendi</h1>
    <p>Bu sayfaya erişim yetkiniz bulunmamaktadır.</p>
</body>
</html>
<?php
die;
?>