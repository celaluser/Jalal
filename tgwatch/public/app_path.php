<?php
// tgwatch klasörünün yolu. public/ klasörü tgwatch içinde duruyorsa otomatik bulunur, düzenlemeye gerek yok.
// public/ içeriğini başka bir yere (ör. public_html/tgadmin) kopyaladıysanız alttaki yolu düzeltin.
define('APP_DIR', is_file(dirname(__DIR__) . '/src/bootstrap.php')
    ? dirname(__DIR__)
    : '/home/KULLANICI/tgwatch');

if (!is_file(APP_DIR . '/src/bootstrap.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("tgwatch bulunamadı. public/app_path.php içindeki yolu düzeltin.\n");
}
