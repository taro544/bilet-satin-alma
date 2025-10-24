<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kullanıcı bilgilerini çek
$stmt = $db->prepare("SELECT full_name, email, phone, balance FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Form submit (kullanıcı bilgilerini güncelleme)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    if ($full_name === '' || $email === '') {
        $message = "Ad ve e-posta boş olamaz.";
    } else {
        // Email başka kullanıcıya ait mi kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :user_id");
        $stmt->execute([':email' => $email, ':user_id' => $_SESSION['user_id']]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $message = "Bu e-posta zaten başka bir kullanıcıya ait!";
        } else {
            // Güncelle
            $stmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, phone = :phone WHERE id = :user_id");
            $stmt->execute([
                ':full_name' => $full_name,
                ':email' => $email,
                ':phone' => $phone,
                ':user_id' => $_SESSION['user_id']
            ]);
            $message = "Bilgiler başarıyla güncellendi!";
            $user['full_name'] = $full_name;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $_SESSION['full_name'] = $full_name;
        }
    }
}

// Kullanıcının siparişlerini çek
$stmt = $db->prepare("
    SELECT o.id as order_id, o.total_amount, o.discount_amount, o.coupon_code, o.discount_percent, 
           o.final_amount, o.status as order_status, o.created_at,
           COUNT(t.id) as ticket_count
    FROM orders o
    LEFT JOIN tickets t ON o.id = t.order_id
    WHERE o.user_id = :user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcının bildirimlerini çek
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısı
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Hesabım - <?= htmlspecialchars($user['full_name']) ?></title>
<link rel="stylesheet" href="style.css">

<style>

/* BILET TABLO */
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
table, th, td { border: 1px solid #ccc; }
th, td { padding: 10px; text-align: center; }
th { background: #0077cc; color: #fff; }

/* MESAJLAR */
.message { 
    padding: 15px; 
    margin: 15px 0; 
    border-radius: 8px; 
    border: 1px solid #ddd;
    background: #f8f9fa;
    color: #333;
}

/* BİLDİRİMLER */
.notifications-container { background: #f8f9fa; border-radius: 10px; padding: 15px; margin: 20px 0; }
.notification-item { background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #007bff; position: relative; }
.notification-item.unread { border-left-color: #dc3545; background: #fff5f5; }
.notification-item.warning { border-left-color: #ffc107; }
.notification-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.notification-header strong { color: #333; }
.notification-header small { color: #666; }
.notification-message { color: #555; line-height: 1.4; }
.unread-indicator { position: absolute; top: 10px; right: 10px; font-size: 10px; }
.pdf-btn { background: #dc3545; color: #fff; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 12px; }
.pdf-btn:hover { background: #c82333; }
</style>
</head>
<body>

<header>
    <h1><a href="index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($user['full_name']) ?>!</span>
        <span class="balance"> Bakiye: <?= number_format($user['balance'],2) ?> ₺</span>
        <a href="cart.php">🛒 Sepetim</a>
        <a href="index.php"> Ana Sayfa</a>
        <a href="logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="container">
    <h1>Hesap Bilgilerim</h1>

    <?php if($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="message" style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="message" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST">
        <label>Ad Soyad</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>

        <label>E-posta</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Telefon</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">

        <button type="submit" name="update_profile">Bilgileri Güncelle</button>
    </form>

    <!-- Bildirimler Bölümü -->
    <h1>🔔 Bildirimler <?php if($unread_count > 0): ?><span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 8px; font-size: 12px;"><?= $unread_count ?></span><?php endif; ?></h1>
    
    <?php if(count($notifications) > 0): ?>
        <div class="notifications-container">
            <?php foreach($notifications as $notification): ?>
                <div class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?> <?= $notification['type'] ?>">
                    <div class="notification-header">
                        <strong><?= htmlspecialchars($notification['title']) ?></strong>
                        <small><?= date('d.m.Y H:i', strtotime($notification['created_at'])) ?></small>
                    </div>
                    <div class="notification-message">
                        <?= htmlspecialchars($notification['message']) ?>
                    </div>
                    <?php if(!$notification['is_read']): ?>
                        <div class="unread-indicator">🔴 Yeni</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Henüz bildiriminiz yok.</p>
    <?php endif; ?>

    <h1> Biletleriniz</h1>
    <?php
    // Kullanıcının aktif biletlerini çek
    $stmt = $db->prepare("
        SELECT t.id as ticket_id, t.seat_number, t.price, t.status, t.purchased_at,
               tr.departure_city, tr.arrival_city, tr.departure_time, tr.arrival_time,
               c.name as company_name, o.id as order_id
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN companies c ON tr.company_id = c.id
        LEFT JOIN orders o ON t.order_id = o.id
        WHERE t.user_id = :user_id
        ORDER BY tr.departure_time DESC
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <?php if(count($tickets) > 0): ?>
    <table>
        <tr>
            <th>Güzergah</th>
            <th>Tarih & Saat</th>
            <th>Koltuk</th>
            <th>Fiyat</th>
            <th>Durum</th>
            <th>İşlemler</th>
        </tr>
        <?php foreach($tickets as $ticket): 
            $departure_timestamp = strtotime($ticket['departure_time']);
            $can_cancel = ($ticket['status'] === 'active' && ($departure_timestamp - time() > 3600));
            $is_past = ($departure_timestamp <= time());
        ?>
        <tr style="<?= $ticket['status'] !== 'active' ? 'opacity: 0.6;' : '' ?>">
            <td>
                <strong><?= htmlspecialchars($ticket['company_name']) ?></strong><br>
                <?= htmlspecialchars($ticket['departure_city']) ?> → <?= htmlspecialchars($ticket['arrival_city']) ?>
            </td>
            <td><?= date("d.m.Y H:i", strtotime($ticket['departure_time'])) ?></td>
            <td>🪑 <?= $ticket['seat_number'] ?></td>
            <td><?= number_format($ticket['price'], 2) ?> ₺</td>
            <td>
                <?php if ($ticket['status'] === 'active'): ?>
                    <?php if ($is_past): ?>
                        <span style="color: #6c757d;"> Tamamlandı</span>
                    <?php else: ?>
                        <span style="color: #28a745;"> Aktif</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color: #dc3545;"> İptal</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($can_cancel): ?>
                    <a href="cancel_ticket.php?id=<?= $ticket['ticket_id'] ?>" class="pdf-btn" style="background: #dc3545;" title="Bileti İptal Et" onclick="return confirm('Bu bileti iptal etmek istediğinizden emin misiniz?')">🗑️</a>
                <?php elseif ($ticket['status'] === 'active' && !$is_past): ?>
                    <span class="pdf-btn" style="background: #6c757d; opacity: 0.5; cursor: not-allowed;" title="Kalkışa 1 saatten az kaldı, iptal edilemez">🗑️</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p>📭 Henüz biletiniz bulunmamaktadır.</p>
    <?php endif; ?>

    <h1> Siparişleriniz</h1>
    <?php if(count($orders) > 0): ?>
    <table>
        <tr>
            <th>Bilet Sayısı</th>
            <th>Toplam Tutar</th>
            <th>İndirim</th>
            <th>Ödenen Tutar</th>
            <th>Durum</th>
            <th>Sipariş Tarihi</th>
            <th>Fatura</th>
        </tr>
        <?php foreach($orders as $order): ?>
        <tr>
            <td><?= $order['ticket_count'] ?> Bilet</td>
            <td><?= number_format($order['total_amount'],2) ?> ₺</td>
            <td>
                <?php if($order['discount_amount'] > 0): ?>
                    <?= $order['coupon_code'] ?> (%<?= $order['discount_percent'] ?>)<br>
                    -<?= number_format($order['discount_amount'],2) ?> ₺
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><strong><?= number_format($order['final_amount'],2) ?> ₺</strong></td>
            <td><?= ucfirst($order['order_status']) ?></td>
            <td><?= date("d.m.Y H:i", strtotime($order['created_at'])) ?></td>
            <td><a href="pdf_order_invoice.php?id=<?= $order['order_id'] ?>" class="pdf-btn" target="_blank">📄 PDF İndir</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p>📭 Henüz siparişiniz bulunmamaktadır.</p>
    <?php endif; ?>
</div>

<script>
// Sayfa yüklendiğinde okunmamış bildirimleri okundu olarak işaretle
document.addEventListener('DOMContentLoaded', function() {
    <?php if($unread_count > 0): ?>
        fetch('mark_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
    <?php endif; ?>
});
</script>

</body>
</html>
