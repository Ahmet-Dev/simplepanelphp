<?php
set_time_limit(0); // Süresiz çalışmasını sağlar

function pingSearchEngines($sitemapUrl)
{
    $engines = [
        "Google" => "https://www.google.com/ping?sitemap=$sitemapUrl",
        "Bing" => "https://www.bing.com/ping?sitemap=$sitemapUrl",
        "Yandex" => "https://yandex.com/indexnow?url=$sitemapUrl",
        "DuckDuckGo" => "https://duckduckgo.com/?q=site:$sitemapUrl"
    ];

    foreach ($engines as $name => $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        sleep(5); // Sunucu yükünü azaltmak için bekleme süresi
    }
}

// Sürekli çalıştırma, her 20 dakikada bir çalıştırır
$sitemapUrl = $_SERVER["HTTP_HOST"]."sitemap.xml";

while (true) {
    pingSearchEngines($sitemapUrl);
    sleep(1200); // 20 dakika bekle
}
