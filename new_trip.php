<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı login kontrolü ve şirket yetkisi kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: login.php');
    exit;
}

// Company ID kontrolü
if (!isset($_SESSION['company_id']) || empty($_SESSION['company_id'])) {
    die("Şirket bilgisi bulunamadı. Lütfen tekrar giriş yapın. <a href='login.php'>Giriş Yap</a>");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departure_city = trim($_POST['departure_city']);
    $arrival_city = trim($_POST['arrival_city']);
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = (float)$_POST['price'];
    $capacity = (int)$_POST['capacity'];

    if (empty($departure_city) || empty($arrival_city) || empty($departure_time) || empty($arrival_time) || $price <= 0 || $capacity <= 0) {
        $message = "Tüm alanları doğru şekilde doldurun.";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO trips (company_id, departure_city, arrival_city, departure_time, arrival_time, price, capacity, available_seats) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['company_id'], $departure_city, $arrival_city, $departure_time, $arrival_time, $price, $capacity, $capacity]);
            
            $message = "Sefer başarıyla eklendi!";
            $_POST = []; // Form temizle
        } catch (Exception $e) {
            $message = "Sefer eklenirken hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Sefer Ekle - BiletAl</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container { max-width: 800px; margin: 20px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus { border-color: #0077cc; outline: none; }
        .form-row { display: flex; gap: 20px; }
        .form-row .form-group { flex: 1; }
        .btn { display: inline-block; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin-right: 10px; font-size: 16px; }
        .btn:hover { background: #005fa3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">Hoş geldin, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?>!</span>
        <a href="company/company_panel.php">Şirket Paneli</a>
        <a href="index.php">Ana Sayfa</a>
        <a href="logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="form-container">
    <h2 style="color: #0077cc; margin-bottom: 30px;">Yeni Sefer Ekle</h2>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label for="departure_city">Kalkış Şehri:</label>
                <input type="text" id="departure_city" name="departure_city" required 
                       value="<?= htmlspecialchars($_POST['departure_city'] ?? '') ?>" 
                       placeholder="Örn: Istanbul">
            </div>

            <div class="form-group">
                <label for="arrival_city">Varış Şehri:</label>
                <input type="text" id="arrival_city" name="arrival_city" required 
                       value="<?= htmlspecialchars($_POST['arrival_city'] ?? '') ?>" 
                       placeholder="Örn: Ankara">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="departure_time">Kalkış Tarihi ve Saati:</label>
                <input type="datetime-local" id="departure_time" name="departure_time" required 
                       value="<?= htmlspecialchars($_POST['departure_time'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="arrival_time">Varış Tarihi ve Saati:</label>
                <input type="datetime-local" id="arrival_time" name="arrival_time" required 
                       value="<?= htmlspecialchars($_POST['arrival_time'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="price">Bilet Fiyatı (₺):</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required 
                       value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" 
                       placeholder="Örn: 150.00">
            </div>

            <div class="form-group">
                <label for="capacity">Kapasite (Kişi):</label>
                <input type="number" id="capacity" name="capacity" min="1" max="60" required 
                       value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>" 
                       placeholder="Örn: 40">
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="submit" class="btn">Sefer Ekle</button>
            <a href="index.php" class="btn btn-secondary">İptal</a>
        </div>
    </form>

    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h4 style="color: #0077cc; margin-bottom: 15px;">💡 İpuçları:</h4>
        <ul style="color: #666; line-height: 1.6;">
            <li>Kalkış saati varış saatinden önce olmalıdır</li>
            <li>Kapasite 1-60 kişi arasında olmalıdır</li>
            <li>Fiyat 0'dan büyük olmalıdır</li>
            <li>Şehir adlarını doğru yazdığınızdan emin olun</li>
        </ul>
    </div>
</div>

</body>
</html>
