<?php
// Veritabanı bağlantı ayarları
$dsn = "sqlite:" . __DIR__ . "/db/database.sqlite";

try {
    // PDO veritabanı bağlantısı
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Session başlat
session_start();
