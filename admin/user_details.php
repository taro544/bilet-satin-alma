<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    die("Geçersiz kullanıcı ID");
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id AND role = 'user'");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Kullanıcı bulunamadı");
}

$stmt = $db->prepare("
    SELECT t.*, tr.departure_city, tr.arrival_city, tr.departure_time, tr.arrival_time,
           c.name as company_name, o.id as order_id
    FROM tickets t
    JOIN trips tr ON t.trip_id = tr.id
    JOIN companies c ON tr.company_id = c.id
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.user_id = :user_id
    ORDER BY tr.departure_time DESC
");
$stmt->execute([':user_id' => $user_id]);
$tickets = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT o.*, COUNT(t.id) as ticket_count
    FROM orders o
    LEFT JOIN tickets t ON o.id = t.order_id
    WHERE o.user_id = :user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([':user_id' => $user_id]);
$orders = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute([':user_id' => $user_id]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Detayları - <?= htmlspecialchars($user['full_name']) ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .detail-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .detail-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 30px; }
        .detail-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .detail-card h3 { color: #0077cc; margin-bottom: 15px; }
        .detail-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { font-weight: bold; color: #666; }
        .detail-value { color: #333; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .table tr:hover { background: #f8f9fa; }
        .btn { display: inline-block; padding: 8px 15px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; margin-right: 10px; }
        .btn:hover { background: #005fa3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-active { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        .notification-item { background: #f8f9fa; padding: 15px; margin-bottom: 10px; border-radius: 8px; }
        .notification-item.unread { background: #fff3cd; border-left: 4px solid #ffc107; }
        .notification-item.warning { border-left-color: #dc3545; }
    </style>
</head>
<body>

<header>
    <h1><a href="../index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome"> Admin: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?></span>
        <a href="admin_panel.php"> Admin Panel</a>
        <a href="manage_users.php"> Kullanıcılar</a>
        <a href="../index.php"> Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="detail-container">
    <a href="manage_users.php" class="btn btn-secondary"> Kullanıcı Listesine Dön</a>
    
    <h1>👤 Kullanıcı Detayları</h1>
    
    <div class="detail-grid">
        <!-- Kullanıcı Bilgileri -->
        <div class="detail-card">
            <h3> Kişisel Bilgiler</h3>
            <div class="detail-item">
                <span class="detail-label">ID:</span>
                <span class="detail-value">#<?= $user['id'] ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Ad Soyad:</span>
                <span class="detail-value"><?= htmlspecialchars($user['full_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">E-posta:</span>
                <span class="detail-value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Telefon:</span>
                <span class="detail-value"><?= htmlspecialchars($user['phone'] ?: 'Belirtilmemiş') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Bakiye:</span>
                <span class="detail-value"><?= number_format($user['balance'], 2) ?> ₺</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Rol:</span>
                <span class="detail-value"><?= ucfirst($user['role']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Kayıt Tarihi:</span>
                <span class="detail-value"><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></span>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="detail-card">
            <h3> Kullanım İstatistikleri</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong style="font-size: 24px; color: #0077cc;"><?= count($tickets) ?></strong><br>
                    <small>Toplam Bilet</small>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong style="font-size: 24px; color: #28a745;"><?= count($orders) ?></strong><br>
                    <small>Toplam Sipariş</small>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong style="font-size: 24px; color: #dc3545;"><?= count(array_filter($tickets, function($t){ return $t['status'] === 'active'; })) ?></strong><br>
                    <small>Aktif Bilet</small>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <strong style="font-size: 24px; color: #ffc107;"><?= count($notifications) ?></strong><br>
                    <small>Bildirim</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Biletler -->
    <div class="detail-card">
        <h3> Biletler (<?= count($tickets) ?>)</h3>
        <?php if (count($tickets) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Bilet ID</th>
                        <th>Güzergah</th>
                        <th>Tarih</th>
                        <th>Koltuk</th>
                        <th>Fiyat</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>#<?= $ticket['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($ticket['company_name']) ?></strong><br>
                                <?= htmlspecialchars($ticket['departure_city']) ?> → <?= htmlspecialchars($ticket['arrival_city']) ?>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($ticket['departure_time'])) ?></td>
                            <td>🪑 <?= $ticket['seat_number'] ?></td>
                            <td><?= number_format($ticket['price'], 2) ?> ₺</td>
                            <td>
                                <?php if ($ticket['status'] === 'active'): ?>
                                    <?php if (strtotime($ticket['departure_time']) <= time()): ?>
                                        <span class="status-badge status-completed"> Tamamlandı</span>
                                    <?php else: ?>
                                        <span class="status-badge status-active"> Aktif</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge status-cancelled"> İptal</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Henüz bilet alımı yapılmamış.</p>
        <?php endif; ?>
    </div>

    <!-- Siparişler -->
    <div class="detail-card">
        <h3> Siparişler (<?= count($orders) ?>)</h3>
        <?php if (count($orders) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Sipariş ID</th>
                        <th>Bilet Sayısı</th>
                        <th>Toplam Tutar</th>
                        <th>İndirim</th>
                        <th>Ödenen</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= $order['id'] ?></td>
                            <td><?= $order['ticket_count'] ?> bilet</td>
                            <td><?= number_format($order['total_amount'], 2) ?> ₺</td>
                            <td>
                                <?php if ($order['discount_amount'] > 0): ?>
                                    -<?= number_format($order['discount_amount'], 2) ?> ₺
                                    <br><small>(<?= $order['coupon_code'] ?>)</small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($order['final_amount'], 2) ?> ₺</strong></td>
                            <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Henüz sipariş verilmemiş.</p>
        <?php endif; ?>
    </div>

    <!-- Bildirimler -->
    <div class="detail-card">
        <h3>🔔 Son Bildirimler (<?= count($notifications) ?>)</h3>
        <?php if (count($notifications) > 0): ?>
            <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                <div class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?> <?= $notification['type'] ?>">
                    <strong><?= htmlspecialchars($notification['title']) ?></strong>
                    <small style="float: right;"><?= date('d.m.Y H:i', strtotime($notification['created_at'])) ?></small>
                    <br>
                    <?= htmlspecialchars($notification['message']) ?>
                </div>
            <?php endforeach; ?>
            <?php if (count($notifications) > 5): ?>
                <p><small>... ve <?= count($notifications) - 5 ?> tane daha</small></p>
            <?php endif; ?>
        <?php else: ?>
            <p>Henüz bildirim gönderilmemiş.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
