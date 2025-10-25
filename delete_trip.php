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

// Satılmış bilet sayısını kontrol et
$stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = :trip_id AND status = 'active'");
$stmt->execute([':trip_id' => $trip_id]);
$sold_tickets = $stmt->fetchColumn();

$message = '';

// Silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $db->beginTransaction();
        
        // Eğer satılmış biletler varsa, önce onları iptal et ve para iadesi yap
        if ($sold_tickets > 0) {
            // Satılmış biletleri ve gerçek ödenen tutarları al
            $stmt = $db->prepare("
                SELECT t.id, t.user_id, t.price, t.order_id, u.full_name, u.email,
                       o.total_amount, o.final_amount, o.discount_amount,
                       (SELECT COUNT(*) FROM tickets WHERE order_id = t.order_id) as tickets_in_order
                FROM tickets t 
                JOIN users u ON t.user_id = u.id 
                LEFT JOIN orders o ON t.order_id = o.id
                WHERE t.trip_id = :trip_id AND t.status = 'active'
            ");
            $stmt->execute([':trip_id' => $trip_id]);
            $active_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($active_tickets as $ticket) {
                // Gerçek iade tutarını hesapla
                if ($ticket['order_id'] && $ticket['tickets_in_order'] > 0) {
                    // Eğer kupon kullanılmışsa, indirimli fiyatı hesapla
                    $refund_amount = ($ticket['final_amount'] / $ticket['tickets_in_order']);
                } else {
                    // Sipariş yoksa (eski sistem) tam fiyat iade et
                    $refund_amount = $ticket['price'];
                }
                
                // Bileti iptal et
                $stmt = $db->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = :ticket_id");
                $stmt->execute([':ticket_id' => $ticket['id']]);
                
                // Gerçek ödenen tutarı iade et
                $stmt = $db->prepare("UPDATE users SET balance = balance + :refund_amount WHERE id = :user_id");
                $stmt->execute([':refund_amount' => $refund_amount, ':user_id' => $ticket['user_id']]);
                
                // Kullanıcıya bildirim gönder
                $notification_message = "🚨 SEFER İPTAL EDİLDİ!\n\n" .
                                      "📍 Güzergah: " . $trip['departure_city'] . " → " . $trip['arrival_city'] . "\n" .
                                      "📅 Tarih: " . date('d.m.Y H:i', strtotime($trip['departure_time'])) . "\n" .
                                      "💰 İade Tutarı: " . number_format($refund_amount, 2) . " ₺\n\n" .
                                      "Sefer şirket tarafından iptal edilmiştir. Gerçek ödediğiniz tutar hesabınıza iade edilmiştir.";
                
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type) 
                    VALUES (:user_id, 'Sefer İptal Edildi', :message, 'warning')
                ");
                $stmt->execute([
                    ':user_id' => $ticket['user_id'], 
                    ':message' => $notification_message
                ]);
            }
            
            // Available seats'i güncelle (tüm biletler iptal olduğu için kapasite kadar olsun)
            $stmt = $db->prepare("UPDATE trips SET available_seats = capacity WHERE id = :trip_id");
            $stmt->execute([':trip_id' => $trip_id]);
        }
        
        // Sepetlerden bu seferi kaldır
        $stmt = $db->prepare("DELETE FROM cart WHERE trip_id = :trip_id");
        $stmt->execute([':trip_id' => $trip_id]);
        
        // Seferi sil
        $stmt = $db->prepare("DELETE FROM trips WHERE id = :trip_id AND company_id = :company_id");
        $stmt->execute([':trip_id' => $trip_id, ':company_id' => $_SESSION['company_id']]);
        
        $db->commit();
        
        // Başarı mesajı ile ana sayfaya yönlendir
        if ($sold_tickets > 0) {
            header('Location: index.php?message=Sefer başarıyla silindi. ' . $sold_tickets . ' adet bilet iptal edildi ve para iadeleri yapıldı.');
        } else {
            header('Location: index.php?message=Sefer başarıyla silindi.');
        }
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "Silme işlemi sırasında hata oluştu: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sefer Sil - BiletAl</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .delete-container { max-width: 600px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .trip-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .warning { margin: 15px 0; padding: 15px; border-radius: 8px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .danger-zone { background: #fff5f5; border: 2px solid #fed7d7; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .btn { display: inline-block; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin-right: 10px; }
        .btn:hover { background: #005fa3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
    <h1><a href="index.php">🚍 BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?>!</span>
        <a href="index.php">🏠 Ana Sayfa</a>
        <a href="logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="delete-container">
    <h1>🗑️ Sefer Sil</h1>
    
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="trip-info">
        <h3>📋 Sefer Bilgileri</h3>
        <p><strong>Güzergah:</strong> <?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['arrival_city']) ?></p>
        <p><strong>Kalkış:</strong> <?= date("d.m.Y H:i", strtotime($trip['departure_time'])) ?></p>
        <p><strong>Varış:</strong> <?= date("d.m.Y H:i", strtotime($trip['arrival_time'])) ?></p>
        <p><strong>Fiyat:</strong> <?= number_format($trip['price'], 2) ?> ₺</p>
        <p><strong>Kapasite:</strong> <?= $trip['capacity'] ?> kişi</p>
    </div>

    <div class="danger-zone">
        <h4>⚠️ Tehlikeli Bölge</h4>
        <p>Bu seferi silmek üzeresiniz. Bu işlem <strong>geri alınamaz</strong>.</p>
        
        <?php if ($sold_tickets > 0): ?>
            <div class="warning" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <h5>📋 Otomatik İşlemler</h5>
                <p>Bu seferden <strong><?= $sold_tickets ?> adet</strong> bilet satılmış durumda.</p>
                <p><strong>Sefer silindiğinde otomatik olarak:</strong></p>
                <ul>
                    <li>✅ Tüm biletler iptal edilecek</li>
                    <li>💰 Müşterilere para iadesi yapılacak</li>
                    <li>📢 Müşterilere bildirim gönderilecek</li>
                    <li>🗑️ Sefer silinecek</li>
                </ul>
            </div>
        <?php else: ?>
            <p>Şu anda bu seferden bilet satılmamış, güvenle silebilirsiniz.</p>
        <?php endif; ?>
        
        <form method="POST" style="margin-top: 20px;">
            <p><strong>Bu seferi silmek istediğinize emin misiniz?</strong></p>
            <?php if ($sold_tickets > 0): ?>
                <p style="color: #e67e22; font-weight: bold;">
                    ⚠️ DİKKAT: <?= $sold_tickets ?> müşteriye otomatik bildirim gönderilecek ve para iadesi yapılacaktır!
                </p>
            <?php endif; ?>
            <button type="submit" name="confirm_delete" class="btn btn-danger" onclick="return confirm('<?= $sold_tickets > 0 ? 'Bu işlem ' . $sold_tickets . ' müşteriye bildirim gönderecek ve para iadesi yapacaktır. Emin misiniz?' : 'Son kez soruyorum: Bu seferi silmek istediğinize emin misiniz?' ?>')">
                🗑️ Evet, Seferi Sil<?= $sold_tickets > 0 ? ' ve Biletleri İptal Et' : '' ?>
            </button>
            <a href="index.php" class="btn btn-secondary">❌ Hayır, İptal Et</a>
        </form>
    </div>

    <div style="margin-top: 20px;">
        <a href="edit_trip.php?id=<?= $trip['id'] ?>" class="btn">✏️ Seferi Düzenle</a>
        <a href="index.php" class="btn btn-secondary">⬅️ Geri Dön</a>
    </div>
</div>

</body>
</html>

