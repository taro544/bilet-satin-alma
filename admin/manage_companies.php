<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';

if (isset($_POST['delete_company']) && isset($_POST['company_id'])) {
    $company_id = (int)$_POST['company_id'];
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE trips SET status = 'cancelled' WHERE company_id = :company_id");
        $stmt->execute([':company_id' => $company_id]);
        
        $stmt = $db->prepare("DELETE FROM users WHERE company_id = :company_id AND role = 'company'");
        $stmt->execute([':company_id' => $company_id]);
        
        $stmt = $db->prepare("DELETE FROM companies WHERE id = :company_id");
        $stmt->execute([':company_id' => $company_id]);
        
        $db->commit();
        $message = "Firma başarıyla silindi ve tüm seferler iptal edildi.";
        
    } catch (Exception $e) {
        $db->rollback();
        $message = "Firma silinirken hata oluştu: " . $e->getMessage();
    }
}

try {
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(DISTINCT u.id) as admin_count,
               COUNT(DISTINCT t.id) as trip_count,
               COUNT(DISTINCT tk.id) as ticket_count
        FROM companies c
        LEFT JOIN users u ON c.id = u.company_id AND u.role = 'company'
        LEFT JOIN trips t ON c.id = t.company_id
        LEFT JOIN tickets tk ON t.id = tk.trip_id AND tk.status = 'active'
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $companies = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Firmalar yüklenirken hata oluştu: " . $e->getMessage();
    $companies = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .companies-table { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; color: #333; }
        .table tr:hover { background: #f8f9fa; }
        .btn { display: inline-block; padding: 8px 15px; margin: 2px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #005fa3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-small { padding: 6px 10px; font-size: 12px; }
        .btn-small.btn-danger { background: #dc3545; color: #fff; }
        .btn-small.btn-danger:hover { background: #c82333; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .stats-badge { background: #e9ecef; padding: 4px 8px; border-radius: 12px; font-size: 12px; margin: 0 2px; }
    </style>
</head>
<body>

<header>
    <h1><a href="../index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome"> Admin: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?></span>
        <a href="admin_panel.php"> Admin Panel</a>
        <a href="../index.php"> Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="admin-container">
    <div class="action-bar">
        <h1> Firma Yönetimi</h1>
        <a href="add_company.php" class="btn btn-success">Yeni Firma Ekle</a>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="message error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="companies-table">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Firma Adı</th>
                    <th>İletişim</th>
                    <th>İstatistikler</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($companies)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            Henüz firma kaydı bulunmuyor.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($companies as $company): ?>
                        <tr>
                            <td><?= $company['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($company['name']) ?></strong>
                            </td>
                            <td>
                                E-posta: <?= htmlspecialchars($company['address'] ?? 'Belirtilmemis') ?><br>
                                Tel: <?= htmlspecialchars($company['phone'] ?? 'Belirtilmemis') ?>
                            </td>
                            <td>
                                <span class="stats-badge"> <?= $company['admin_count'] ?> Admin</span>
                                <span class="stats-badge"> <?= $company['trip_count'] ?> Sefer</span>
                                <span class="stats-badge"> <?= $company['ticket_count'] ?> Bilet</span>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($company['created_at'])) ?></td>
                            <td>
                                <a href="edit_company.php?id=<?= $company['id'] ?>" class="btn btn-small btn-warning">Düzenle</a>
                                <form method="POST" onsubmit="return confirm('Bu firmayi silmek istediginizden emin misiniz? Tum seferler iptal edilecek!')" style="display: inline-block;">
                                    <input type="hidden" name="company_id" value="<?= $company['id'] ?>">
                                    <button type="submit" name="delete_company" class="btn btn-small btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
