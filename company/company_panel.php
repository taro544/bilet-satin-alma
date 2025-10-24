<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: ../login.php');
    exit;
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM trips WHERE company_id = :company_id AND status = 'active'");
    $stmt->execute([':company_id' => $_SESSION['company_id']]);
    $total_trips = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = :company_id) AND status = 'active'");
    $stmt->execute([':company_id' => $_SESSION['company_id']]);
    $sold_tickets = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT SUM(price) as total FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = :company_id) AND status = 'active'");
    $stmt->execute([':company_id' => $_SESSION['company_id']]);
    $total_revenue = $stmt->fetchColumn() ?: 0;

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM trips WHERE company_id = :company_id AND status = 'active' AND strftime('%Y-%m', departure_time) = strftime('%Y-%m', 'now')");
    $stmt->execute([':company_id' => $_SESSION['company_id']]);
    $current_month_trips = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = :company_id) AND status = 'active' AND strftime('%Y-%m', purchased_at) = strftime('%Y-%m', 'now')");
    $stmt->execute([':company_id' => $_SESSION['company_id']]);
    $current_month_sales = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT SUM(price) as total FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = :company_id) AND status = 'active' AND strftime('%Y-%m', purchased_at) = strftime('%Y-%m', 'now')");
    $stmt->execute([':company_id' => $_SESSION['company_id']]);
    $current_month_revenue = $stmt->fetchColumn() ?: 0;

} catch (Exception $e) {
    $error_message = "İstatistikler yüklenirken hata oluştu: " . $e->getMessage();
    $total_trips = $sold_tickets = $total_revenue = 0;
    $current_month_trips = $current_month_sales = $current_month_revenue = 0;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Şirket Paneli - BiletAl</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .dashboard { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); text-align: center; }
        .stat-box h3 { color: #0077cc; margin-bottom: 15px; }
        .stat-box p { margin: 8px 0; font-size: 16px; }
        .actions-container { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .action-buttons { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px; }
        .btn { display: inline-block; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .btn:hover { background: #005fa3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border: 1px solid #f5c6cb; margin-bottom: 20px; }
        .recent-trips { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .trip-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee; }
        .trip-item:last-child { border-bottom: none; }
        .trip-info { flex: 1; }
        .trip-actions { display: flex; gap: 10px; }
        .btn-small { padding: 6px 12px; font-size: 14px; }
    </style>
</head>
<body>

<header>
    <h1><a href="../index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?>!</span>
        <a href="../index.php"> Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="dashboard">
    <h1> Şirket Yönetim Paneli</h1>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="stats-container">
        <div class="stat-box">
            <h3> İstatistikler</h3>
            <p><strong>Toplam Sefer:</strong> <?= $total_trips ?></p>
            <p><strong>Satılan Bilet:</strong> <?= $sold_tickets ?></p>
            <p><strong>Toplam Gelir:</strong> <?= number_format($total_revenue, 2) ?> ₺</p>
        </div>
        
        <div class="stat-box">
            <h3> Bu Ay</h3>
            <p><strong>Sefer Sayısı:</strong> <?= $current_month_trips ?></p>
            <p><strong>Satış:</strong> <?= $current_month_sales ?></p>
            <p><strong>Gelir:</strong> <?= number_format($current_month_revenue, 2) ?> ₺</p>
        </div>
    </div>

    <div class="actions-container">
        <h3> Sefer Yönetimi</h3>
        <div class="action-buttons">
            <a href="../new_trip.php" class="btn btn-success"> Yeni Sefer Ekle</a>
            <a href="manage_trips.php" class="btn btn-secondary"> Seferleri Yönet</a>
        </div>
    </div>

    <div class="actions-container">
        <h3> İndirim Yönetimi</h3>
        <div class="action-buttons">
            <a href="manage_coupons.php" class="btn btn-warning"> İndirim Kuponları</a>
        </div>
    </div>

    <div class="recent-trips">
        <h3> Son Seferler</h3>
        <?php
        try {
            $stmt = $db->prepare("
                SELECT t.*, 
                       (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'active') as sold_tickets,
                       c.name as company_name
                FROM trips t 
                LEFT JOIN companies c ON t.company_id = c.id 
                WHERE t.company_id = :company_id AND t.status = 'active'
                ORDER BY t.departure_time DESC 
                LIMIT 5
            ");
            $stmt->execute([':company_id' => $_SESSION['company_id']]);
            $recent_trips = $stmt->fetchAll();

            if (empty($recent_trips)): ?>
                <p>Henüz sefer eklenmemiş. <a href="../new_trip.php">İlk seferinizi ekleyin!</a></p>
            <?php else: ?>
                <?php foreach ($recent_trips as $trip): ?>
                    <div class="trip-item">
                        <div class="trip-info">
                            <strong><?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['arrival_city']) ?></strong><br>
                            <small>
                                 <?= date('d.m.Y H:i', strtotime($trip['departure_time'])) ?> - 
                                <?= date('d.m.Y H:i', strtotime($trip['arrival_time'])) ?> | 
                                 <?= number_format($trip['price'], 2) ?> ₺ | 
                                 <?= $trip['sold_tickets'] ?>/<?= $trip['capacity'] ?>
                            </small>
                        </div>
                        <div class="trip-actions">
                            <a href="../edit_trip.php?id=<?= $trip['id'] ?>" class="btn btn-small"> Düzenle</a>
                            <a href="../delete_trip.php?id=<?= $trip['id'] ?>" class="btn btn-small" style="background: #dc3545;" onclick="return confirm('Bu seferi silmek istediğinizden emin misiniz?')"> Sil</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="manage_trips.php" class="btn"> Tüm Seferleri Görüntüle</a>
                </div>
            <?php endif;
        } catch (Exception $e) {
            echo '<p>Seferler yüklenirken hata oluştu: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
</div>

</body>
</html>
