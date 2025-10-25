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

$trip_id = $_GET['id'] ?? null;
if (!$trip_id) {
    die("Geçersiz sefer ID");
}

// Seferin bu şirkete ait olup olmadığını kontrol et
$stmt = $db->prepare("SELECT * FROM trips WHERE id = :trip_id AND company_id = :company_id");
$stmt->execute([':trip_id' => $trip_id, ':company_id' => $_SESSION['company_id']]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    die("Bu sefer size ait değil veya bulunamadı");
}

$message = '';

// Form submit - Sefer güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_trip'])) {
    $departure_city = trim($_POST['departure_city']);
    $arrival_city = trim($_POST['arrival_city']);
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = (float)$_POST['price'];
    $capacity = (int)$_POST['capacity'];
    
    // Doğrulama
    if (empty($departure_city) || empty($arrival_city) || empty($departure_time) || empty($arrival_time) || $price <= 0 || $capacity <= 0) {
        $message = "Lütfen tüm alanları doğru şekilde doldurun.";
    } elseif ($departure_city === $arrival_city) {
        $message = "Kalkış ve varış şehrini aynı olamaz.";
    } elseif (strtotime($departure_time) >= strtotime($arrival_time)) {
        $message = "Kalkış zamanı varış zamanından önce olmalıdır.";
    } elseif (strtotime($departure_time) <= time()) {
        $message = "Kalkış zamanı gelecekte bir tarih olmalıdır.";
    } else {
        // Satılmış bilet sayısını kontrol et - kapasite düşürülmeye çalışılıyorsa
        $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = :trip_id AND status = 'active'");
        $stmt->execute([':trip_id' => $trip_id]);
        $sold_tickets = $stmt->fetchColumn();
        
        if ($capacity < $sold_tickets) {
            $message = "Kapasite, satılan bilet sayısından ({$sold_tickets}) az olamaz.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE trips SET 
                    departure_city = :departure_city,
                    arrival_city = :arrival_city, 
                    departure_time = :departure_time,
                    arrival_time = :arrival_time,
                    price = :price,
                    capacity = :capacity,
                    available_seats = :available_seats
                    WHERE id = :trip_id AND company_id = :company_id");
                
                $available_seats = $capacity - $sold_tickets;
                
                $stmt->execute([
                    ':departure_city' => $departure_city,
                    ':arrival_city' => $arrival_city,
                    ':departure_time' => $departure_time,
                    ':arrival_time' => $arrival_time,
                    ':price' => $price,
                    ':capacity' => $capacity,
                    ':available_seats' => $available_seats,
                    ':trip_id' => $trip_id,
                    ':company_id' => $_SESSION['company_id']
                ]);
                
                $message = "Sefer başarıyla güncellendi!";
                
                // Güncellenmiş sefer bilgilerini tekrar çek
                $stmt = $db->prepare("SELECT * FROM trips WHERE id = :trip_id AND company_id = :company_id");
                $stmt->execute([':trip_id' => $trip_id, ':company_id' => $_SESSION['company_id']]);
                $trip = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $message = "Güncelleme sırasında hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sefer Düzenle - BiletAl</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        .edit-container { max-width: 700px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 16px; }
        .form-group input:focus { border-color: #0077cc; outline: none; }
        .btn { display: inline-block; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin-right: 10px; }
        .btn:hover { background: #005fa3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .trip-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header>
    <h1><a href="/index.php">🚍 BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?>!</span>
        <a href="/index.php">🏠 Ana Sayfa</a>
        <a href="/company/company_panel.php">📊 Panel</a>
        <a href="/logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="edit-container">
    <h1>✏️ Sefer Düzenle</h1>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="trip-info">
        <h3>📋 Mevcut Sefer Bilgileri</h3>
        <p><strong>Güzergah:</strong> <?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['arrival_city']) ?></p>
        <p><strong>Kalkış:</strong> <?= date("d.m.Y H:i", strtotime($trip['departure_time'])) ?></p>
        <p><strong>Varış:</strong> <?= date("d.m.Y H:i", strtotime($trip['arrival_time'])) ?></p>
        <p><strong>Fiyat:</strong> <?= number_format($trip['price'], 2) ?> ₺</p>
        <p><strong>Kapasite:</strong> <?= $trip['capacity'] ?> kişi</p>
        <?php
        $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = :trip_id AND status = 'active'");
        $stmt->execute([':trip_id' => $trip_id]);
        $sold_tickets = $stmt->fetchColumn();
        ?>
        <p><strong>Satılan Bilet:</strong> <?= $sold_tickets ?> adet</p>
    </div>

    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label for="departure_city">🏙️ Kalkış Şehri *</label>
                <input type="text" id="departure_city" name="departure_city" value="<?= htmlspecialchars($trip['departure_city']) ?>" required>
            </div>
            <div class="form-group">
                <label for="arrival_city">🏙️ Varış Şehri *</label>
                <input type="text" id="arrival_city" name="arrival_city" value="<?= htmlspecialchars($trip['arrival_city']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="departure_time">⏰ Kalkış Tarihi ve Saati *</label>
                <input type="datetime-local" id="departure_time" name="departure_time" value="<?= date('Y-m-d\TH:i', strtotime($trip['departure_time'])) ?>" required>
            </div>
            <div class="form-group">
                <label for="arrival_time">⏰ Varış Tarihi ve Saati *</label>
                <input type="datetime-local" id="arrival_time" name="arrival_time" value="<?= date('Y-m-d\TH:i', strtotime($trip['arrival_time'])) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="price">💰 Bilet Fiyatı (₺) *</label>
                <input type="number" id="price" name="price" step="0.01" min="1" value="<?= $trip['price'] ?>" required>
            </div>
            <div class="form-group">
                <label for="capacity">🪑 Kapasite (Kişi) *</label>
                <input type="number" id="capacity" name="capacity" min="<?= $sold_tickets ?>" value="<?= $trip['capacity'] ?>" required>
                <small style="color: #666;">Not: Kapasite en az <?= $sold_tickets ?> olmalıdır (satılan bilet sayısı)</small>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="submit" name="update_trip" class="btn btn-success">💾 Seferi Güncelle</button>
            <a href="/index.php" class="btn btn-secondary">❌ İptal Et</a>
            <a href="/delete_trip.php?id=<?= $trip['id'] ?>" class="btn" style="background: #dc3545;" onclick="return confirm('Bu seferi silmek istediğinize emin misiniz?')">🗑️ Seferi Sil</a>
        </div>
    </form>
</div>

<script>
// Kalkış zamanı değiştiğinde, varış zamanını otomatik olarak 1 saat sonraya ayarla
document.getElementById('departure_time').addEventListener('change', function() {
    const departureTime = new Date(this.value);
    if (departureTime) {
        departureTime.setHours(departureTime.getHours() + 1);
        const arrivalTime = departureTime.toISOString().slice(0, 16);
        document.getElementById('arrival_time').value = arrivalTime;
    }
});
</script>

</body>
</html>
