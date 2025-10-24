<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users 
        WHERE role = 'user' AND created_at >= DATE('now', '-30 days')
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute();
    $daily_registrations = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT 
            strftime('%Y-%m', created_at) as month,
            COUNT(*) as registrations,
            COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance
        FROM users 
        WHERE role = 'user'
        GROUP BY strftime('%Y-%m', created_at)
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute();
    $monthly_stats = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT u.full_name, u.email, u.balance,
               COUNT(t.id) as ticket_count,
               SUM(t.price) as total_spent
        FROM users u
        LEFT JOIN tickets t ON u.id = t.user_id AND t.status = 'active'
        WHERE u.role = 'user'
        GROUP BY u.id
        ORDER BY ticket_count DESC, total_spent DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_users = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT tr.departure_city,
               COUNT(t.id) as ticket_count,
               COUNT(DISTINCT t.user_id) as unique_users
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        WHERE t.status = 'active'
        GROUP BY tr.departure_city
        ORDER BY ticket_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $city_distribution = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = "Rapor verileri yüklenirken hata oluştu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Raporları - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .reports-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .report-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .report-card h3 { color: #0077cc; margin-bottom: 15px; }
        .chart-container { height: 200px; overflow-y: auto; }
        .bar-chart { display: flex; align-items: end; height: 150px; gap: 5px; }
        .bar { background: #0077cc; min-height: 5px; flex: 1; display: flex; align-items: end; justify-content: center; color: white; font-size: 12px; padding: 2px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .table tr:hover { background: #f8f9fa; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .btn { display: inline-block; padding: 8px 15px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; margin-right: 10px; }
        .btn:hover { background: #005fa3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .stat-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .stat-row:last-child { border-bottom: none; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border: 1px solid #f5c6cb; margin-bottom: 20px; }
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

<div class="reports-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1> Kullanıcı Raporları</h1>
        <a href="manage_users.php" class="btn btn-secondary"> Kullanıcı Yönetimine Dön</a>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="report-grid">
        <!-- Günlük Kayıtlar -->
        <div class="report-card">
            <h3> Son 30 Gün Kayıtları</h3>
            <div class="chart-container">
                <?php if (!empty($daily_registrations)): ?>
                    <div class="bar-chart">
                        <?php 
                        $max_count = max(array_column($daily_registrations, 'count'));
                        foreach (array_reverse($daily_registrations) as $day): 
                            $height = $max_count > 0 ? ($day['count'] / $max_count) * 100 : 5;
                        ?>
                            <div class="bar" style="height: <?= $height ?>%" title="<?= date('d.m', strtotime($day['date'])) ?>: <?= $day['count'] ?> kayıt">
                                <?= $day['count'] ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p><strong>Toplam:</strong> <?= array_sum(array_column($daily_registrations, 'count')) ?> yeni kayıt</p>
                <?php else: ?>
                    <p>Son 30 günde kayıt bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aylık İstatistikler -->
        <div class="report-card">
            <h3> Aylık İstatistikler</h3>
            <div class="chart-container">
                <?php if (!empty($monthly_stats)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ay</th>
                                <th>Kayıt</th>
                                <th>Bakiyeli</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_stats as $month): ?>
                                <tr>
                                    <td><?= date('m/Y', strtotime($month['month'] . '-01')) ?></td>
                                    <td><?= $month['registrations'] ?></td>
                                    <td><?= $month['users_with_balance'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aylık istatistik bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- En Aktif Kullanıcılar -->
        <div class="report-card">
            <h3>🏆 En Aktif Kullanıcılar</h3>
            <div class="chart-container">
                <?php if (!empty($top_users)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Bilet</th>
                                <th>Harcama</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($user['email']) ?></small>
                                    </td>
                                    <td><?= $user['ticket_count'] ?></td>
                                    <td><?= number_format($user['total_spent'], 2) ?>₺</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Henüz aktif kullanıcı bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Şehir Dağılımı -->
        <div class="report-card">
            <h3>🌍 Popüler Kalkış Şehirleri</h3>
            <div class="chart-container">
                <?php if (!empty($city_distribution)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Şehir</th>
                                <th>Bilet</th>
                                <th>Kullanıcı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($city_distribution as $city): ?>
                                <tr>
                                    <td><?= htmlspecialchars($city['departure_city']) ?></td>
                                    <td><?= $city['ticket_count'] ?></td>
                                    <td><?= $city['unique_users'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Henüz şehir bazlı veri bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Özet İstatistikler -->
    <div class="report-card">
        <h3> Genel Özet</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <?php
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
            $stmt->execute();
            $total_users = $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'user' AND balance > 0");
            $stmt->execute();
            $users_with_balance = $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT SUM(balance) FROM users WHERE role = 'user'");
            $stmt->execute();
            $total_balance = $stmt->fetchColumn() ?: 0;

            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE('now', '-7 days')");
            $stmt->execute();
            $new_users_week = $stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT t.user_id) 
                FROM tickets t 
                WHERE t.status = 'active' AND t.purchased_at >= DATE('now', '-30 days')
            ");
            $stmt->execute();
            $active_users_month = $stmt->fetchColumn();
            ?>
            
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong style="font-size: 24px; color: #0077cc;"><?= $total_users ?></strong><br>
                <small>Toplam Kullanıcı</small>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong style="font-size: 24px; color: #28a745;"><?= $users_with_balance ?></strong><br>
                <small>Bakiyeli Kullanıcı</small>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong style="font-size: 24px; color: #dc3545;"><?= number_format($total_balance, 0) ?>₺</strong><br>
                <small>Toplam Bakiye</small>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong style="font-size: 24px; color: #ffc107;"><?= $new_users_week ?></strong><br>
                <small>Son 7 Gün Kayıt</small>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <strong style="font-size: 24px; color: #17a2b8;"><?= $active_users_month ?></strong><br>
                <small>Bu Ay Aktif</small>
            </div>
        </div>
    </div>
</div>

</body>
</html>
