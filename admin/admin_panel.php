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
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stmt->execute();
    $total_users = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM companies");
    $stmt->execute();
    $total_companies = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM trips");
    $stmt->execute();
    $total_trips = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'active'");
    $stmt->execute();
    $total_tickets = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT SUM(price) FROM tickets WHERE status = 'active'");
    $stmt->execute();
    $total_revenue = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    $error_message = "İstatistikler alınırken hata oluştu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - BiletAl</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-dashboard { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { color: #0077cc; margin-bottom: 10px; }
        .stat-number { font-size: 32px; font-weight: bold; color: #333; margin: 10px 0; }
        .admin-sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .admin-section { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .admin-section h3 { color: #0077cc; margin-bottom: 15px; }
        .action-buttons { display: flex; flex-direction: column; gap: 10px; }
        .btn { display: inline-block; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; text-align: center; transition: background 0.3s; }
        .btn:hover { background: #005fa3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; border: 1px solid #f5c6cb; margin-bottom: 20px; }
    </style>
</head>
<body>

<header>
    <h1><a href="../index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome"> Admin: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?></span>
        <a href="../index.php"> Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="admin-dashboard">
    <h1> Sistem Yönetim Paneli</h1>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- İstatistikler -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3> Kullanıcılar</h3>
            <div class="stat-number"><?= $total_users ?></div>
            <p>Toplam Müşteri</p>
        </div>
        
        <div class="stat-card">
            <h3> Firmalar</h3>
            <div class="stat-number"><?= $total_companies ?></div>
            <p>Kayıtlı Firma</p>
        </div>
        
        <div class="stat-card">
            <h3> Seferler</h3>
            <div class="stat-number"><?= $total_trips ?></div>
            <p>Toplam Sefer</p>
        </div>
        
        <div class="stat-card">
            <h3> Biletler</h3>
            <div class="stat-number"><?= $total_tickets ?></div>
            <p>Satılan Bilet</p>
        </div>
        
        <div class="stat-card">
            <h3> Gelir</h3>
            <div class="stat-number"><?= number_format($total_revenue, 0) ?>₺</div>
            <p>Toplam Ciro</p>
        </div>
    </div>

    <!-- Yönetim Bölümleri -->
    <div class="admin-sections">
        <!-- Firma Yönetimi -->
        <div class="admin-section">
            <h3> Firma Yönetimi</h3>
            <div class="action-buttons">
                <a href="manage_companies.php" class="btn"> Firmaları Yönet</a>
                <a href="add_company.php" class="btn btn-success"> Yeni Firma Ekle</a>
                <a href="manage_company_admins.php" class="btn btn-warning"> Firma Adminleri</a>
            </div>
        </div>

        <!-- Kupon Yönetimi -->
        <div class="admin-section">
            <h3> İndirim Yönetimi</h3>
            <div class="action-buttons">
                <a href="manage_coupons.php" class="btn"> Kuponları Yönet</a>
            </div>
        </div>

        <!-- Kullanıcı Yönetimi -->
        <div class="admin-section">
            <h3> Kullanıcı Yönetimi</h3>
            <div class="action-buttons">
                <a href="manage_users.php" class="btn"> Kullanıcıları Yönet</a>
            </div>
        </div>

    </div>
</div>

</body>
</html>
