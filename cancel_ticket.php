<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı login kontrolü - sadece normal kullanıcılar bilet iptal edebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

$ticket_id = $_GET['id'] ?? null;
if (!$ticket_id) {
    die("Geçersiz bilet ID");
}

// Bilet bilgilerini çek (gerçek ödenen tutarı hesaplamak için order bilgilerini de al)
$stmt = $db->prepare("
    SELECT t.*, tr.departure_city, tr.arrival_city, tr.departure_time, tr.arrival_time,
           c.name as company_name, o.id as order_id, o.total_amount, o.final_amount, o.discount_amount,
           (SELECT COUNT(*) FROM tickets WHERE order_id = t.order_id) as tickets_in_order
    FROM tickets t
    JOIN trips tr ON t.trip_id = tr.id
    JOIN companies c ON tr.company_id = c.id
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.id = :ticket_id AND t.user_id = :user_id AND t.status = 'active'
");
$stmt->execute([':ticket_id' => $ticket_id, ':user_id' => $_SESSION['user_id']]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Bilet bulunamadı, size ait değil veya zaten iptal edilmiş");
}

$message = '';
$can_cancel = false;

// Son 1 saat kuralı kontrolü
$departure_timestamp = strtotime($ticket['departure_time']);
$current_timestamp = time();
$time_until_departure = $departure_timestamp - $current_timestamp;

if ($time_until_departure > 3600) { // 1 saat = 3600 saniye
    $can_cancel = true;
} else {
    $hours_left = max(0, round($time_until_departure / 3600, 1));
    $message = "Bilet iptal edilemez! Kalkış saatine {$hours_left} saatten az kaldı. İptal işlemi kalkıştan en az 1 saat önce yapılmalıdır.";
}

// İptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket']) && $can_cancel) {
    try {
        $db->beginTransaction();
        
        // Gerçek iade tutarını hesapla
        if ($ticket['order_id'] && $ticket['tickets_in_order'] > 0) {
            // Eğer kupon kullanılmışsa, indirimli fiyatı hesapla (siparişteki toplam tutarı bilet sayısına böl)
            $refund_amount = ($ticket['final_amount'] / $ticket['tickets_in_order']);
        } else {
            // Sipariş yoksa (eski sistem) tam fiyat iade et
            $refund_amount = $ticket['price'];
        }
        
        // Bileti iptal et
        $stmt = $db->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = :ticket_id");
        $stmt->execute([':ticket_id' => $ticket_id]);
        
        // Trip kapasitesini artır (iptal edilen koltuk tekrar müsait olsun)
        $stmt = $db->prepare("UPDATE trips SET available_seats = available_seats + 1 WHERE id = :trip_id");
        $stmt->execute([':trip_id' => $ticket['trip_id']]);
        
        // Gerçek ödenen tutarı iade et
        $stmt = $db->prepare("UPDATE users SET balance = balance + :refund_amount WHERE id = :user_id");
        $stmt->execute([':refund_amount' => $refund_amount, ':user_id' => $_SESSION['user_id']]);
        
        // Bildirim gönder
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (:user_id, 'Bilet İptal Edildi', :message, 'info')
        ");
        $notification_message = " " . $ticket['departure_city'] . " → " . $ticket['arrival_city'] . 
                               " seferine ait biletiniz iptal edildi. " . 
                               number_format($refund_amount, 2) . " ₺ hesabınıza iade edilmiştir.";
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':message' => $notification_message
        ]);
        
        $db->commit();
        
        // Session mesajı ile bilgi ver ve yönlendir
        $_SESSION['success'] = "Bilet başarıyla iptal edildi! " . number_format($refund_amount, 2) . " ₺ hesabınıza iade edildi.";
        header('Location: account.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $message = "Bilet iptal edilirken hata oluştu: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Bilet İptal - BiletAl</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cancel-container { max-width: 600px; margin: 20px auto; padding: 20px; }
        .ticket-info { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .ticket-info h3 { color: #0077cc; margin-bottom: 15px; }
        .ticket-info p { margin: 8px 0; }
        .warning-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .warning-box.error { background: #f8d7da; border-color: #f5c6cb; }
        .warning-box h4 { color: #856404; margin-top: 0; }
        .warning-box.error h4 { color: #721c24; }
        .warning-box p { color: #856404; margin-bottom: 15px; }
        .warning-box.error p { color: #721c24; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .success-box h4 { color: #155724; margin-top: 0; }
        .success-box p { color: #155724; }
        .btn { display: inline-block; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin-right: 10px; }
        .btn:hover { background: #005fa3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .time-info { background: #e9ecef; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .refund-info { background: #e2f3ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #0077cc; }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?>!</span>
        <a href="cart.php">🛒 Sepetim</a>
        <a href="account.php">👤 Hesabım</a>
        <a href="logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="cancel-container">
    <a href="account.php" class="btn btn-secondary"> Hesabıma Dön</a>
    
    <h1> Bilet İptal</h1>
    
    <!-- Bilet Bilgileri -->
    <div class="ticket-info">
        <h3> Bilet Bilgileri</h3>
        <p><strong> Firma:</strong> <?= htmlspecialchars($ticket['company_name']) ?></p>
        <p><strong> Güzergah:</strong> <?= htmlspecialchars($ticket['departure_city']) ?> → <?= htmlspecialchars($ticket['arrival_city']) ?></p>
        <p><strong> Kalkış:</strong> <?= date('d.m.Y H:i', strtotime($ticket['departure_time'])) ?></p>
        <p><strong> Varış:</strong> <?= date('d.m.Y H:i', strtotime($ticket['arrival_time'])) ?></p>
        <p><strong>🪑 Koltuk No:</strong> <?= $ticket['seat_number'] ?></p>
        <p><strong> Bilet Fiyatı:</strong> <?= number_format($ticket['price'], 2) ?> ₺</p>
        <p><strong> Satın Alma Tarihi:</strong> <?= date('d.m.Y H:i', strtotime($ticket['purchased_at'])) ?></p>
    </div>

    <!-- Zaman Kontrolü -->
    <div class="time-info">
        <h4> Zaman Kontrolü</h4>
        <?php
        $hours_left = round($time_until_departure / 3600, 1);
        if ($hours_left > 0):
        ?>
            <p>Kalkış saatine <strong><?= $hours_left ?> saat</strong> kaldı.</p>
        <?php else: ?>
            <p>Sefer <strong>başlamış</strong> veya çok yakın zamanda başlayacak.</p>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="warning-box <?= $can_cancel ? '' : 'error' ?>">
            <h4><?= $can_cancel ? '💡 Bilgi' : ' İptal Edilemez' ?></h4>
            <p><?= htmlspecialchars($message) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($can_cancel): ?>
        <!-- İade Bilgisi -->
        <div class="refund-info">
            <h4> İade Bilgileri</h4>
            <p>Biletinizi iptal etmeniz durumunda:</p>
            <ul>
                <li> <strong><?= number_format($ticket['price'], 2) ?> ₺</strong> tam olarak hesabınıza iade edilecek</li>
                <li> İade işlemi anında gerçekleşir</li>
                <li> Bakiyenizi diğer bilet alımlarında kullanabilirsiniz</li>
                <li> Size bildirim gönderilecek</li>
            </ul>
        </div>

        <!-- İptal Butonu -->
        <div class="warning-box">
            <h4> Dikkat</h4>
            <p>Bu işlem <strong>geri alınamaz</strong>. Biletinizi iptal ettikten sonra aynı sefer için tekrar bilet almak istediğinizde, koltuk müsaitliğine bağlı olarak yeniden satın alma işlemi yapmanız gerekecek.</p>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="cancel_ticket" class="btn btn-danger" onclick="return confirm('Bu bileti iptal etmek istediğinizden emin misiniz?\n\n <?= number_format($ticket['price'], 2) ?> ₺ hesabınıza iade edilecek\n Bu işlem geri alınamaz')">
                     Bileti İptal Et
                </button>
                <a href="account.php" class="btn btn-secondary"> Vazgeç</a>
            </form>
        </div>
    <?php else: ?>
        <!-- İptal Edilemez Durumu -->
        <div class="warning-box error">
            <h4> İptal Kuralları</h4>
            <p>Bilet iptal işlemi şu kurallara tabidir:</p>
            <ul>
                <li> Kalkış saatine <strong>en az 1 saat</strong> kala iptal edilmelidir</li>
                <li>🚫 Kalkış saatine 1 saatten az kaldığında iptal edilemez</li>
                <li>💡 Bu kural müşteri güvenliği ve operasyon düzeni için konulmuştur</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="account.php" class="btn"> Hesabıma Dön</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
