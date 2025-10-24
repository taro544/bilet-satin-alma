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

if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE tickets SET status = 'cancelled' WHERE user_id = :user_id AND status = 'active'");
        $stmt->execute([':user_id' => $user_id]);
        
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = :user_id AND role = 'user'");
        $stmt->execute([':user_id' => $user_id]);
        
        $db->commit();
        $message = "Kullanıcı başarıyla silindi ve tüm biletleri iptal edildi.";
        
    } catch (Exception $e) {
        $db->rollback();
        $message = "Kullanıcı silinirken hata oluştu: " . $e->getMessage();
    }
}

if (isset($_POST['update_balance']) && isset($_POST['user_id']) && isset($_POST['new_balance'])) {
    $user_id = (int)$_POST['user_id'];
    $new_balance = (float)$_POST['new_balance'];
    
    if ($new_balance >= 0) {
        try {
            $stmt = $db->prepare("UPDATE users SET balance = :balance WHERE id = :user_id AND role = 'user'");
            $stmt->execute([':balance' => $new_balance, ':user_id' => $user_id]);
            $message = "Kullanıcı bakiyesi başarıyla güncellendi.";
        } catch (Exception $e) {
            $message = "Bakiye güncellenirken hata oluştu: " . $e->getMessage();
        }
    } else {
        $message = "Bakiye 0'dan küçük olamaz.";
    }
}

try {
    $stmt = $db->prepare("
        SELECT u.*, 
               COUNT(DISTINCT t.id) as ticket_count,
               COUNT(DISTINCT o.id) as order_count,
               SUM(CASE WHEN t.status = 'active' THEN t.price ELSE 0 END) as spent_amount
        FROM users u
        LEFT JOIN tickets t ON u.id = t.user_id
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.role = 'user'
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Kullanıcılar yüklenirken hata oluştu: " . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 20px auto; padding: 20px; }
        .users-table { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        .table th { background: #f8f9fa; font-weight: bold; color: #333; }
        .table tr:hover { background: #f8f9fa; }
        .btn { display: inline-block; padding: 6px 12px; margin: 2px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 12px; }
        .btn:hover { background: #005fa3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-small { padding: 4px 8px; font-size: 11px; }
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
        .stats-badge { background: #e9ecef; padding: 4px 8px; border-radius: 12px; font-size: 11px; margin: 0 2px; }
        .balance-form { display: inline-flex; align-items: center; gap: 5px; }
        .balance-input { width: 80px; padding: 4px; border: 1px solid #ccc; border-radius: 3px; font-size: 12px; }
        .user-status { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .user-status.active { background: #d4edda; color: #155724; }
        .user-status.inactive { background: #f8d7da; color: #721c24; }
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
        <h1> Kullanıcı Yönetimi</h1>
        <div>
            <span style="color: #666;">Toplam <?= count($users) ?> kullanıcı</span>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="message error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="users-table">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı Bilgileri</th>
                    <th>İletişim</th>
                    <th>Bakiye</th>
                    <th>İstatistikler</th>
                    <th>Durum</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            Henüz kullanıcı kaydı bulunmuyor.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): 
                        $has_active_tickets = $user['ticket_count'] > 0;
                        $last_activity = strtotime($user['created_at']);
                        $is_new = (time() - $last_activity) < (7 * 24 * 3600);
                    ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                <small style="color: #666;"><?= htmlspecialchars($user['email']) ?></small>
                            </td>
                            <td>
                                📞 <?= htmlspecialchars($user['phone'] ?: 'Belirtilmemiş') ?>
                            </td>
                            <td>
                                <form method="POST" class="balance-form">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="number" name="new_balance" class="balance-input" value="<?= number_format($user['balance'], 2, '.', '') ?>" step="0.01" min="0">
                                    <button type="submit" name="update_balance" class="btn btn-small btn-warning">💰 Güncelle</button>
                                </form>
                                <small><?= number_format($user['balance'], 2) ?> ₺</small>
                            </td>
                            <td>
                                <span class="stats-badge"> <?= $user['ticket_count'] ?> Bilet</span>
                                <span class="stats-badge">📦 <?= $user['order_count'] ?> Sipariş</span>
                                <span class="stats-badge">💸 <?= number_format($user['spent_amount'], 0) ?>₺</span>
                            </td>
                            <td>
                                <?php if ($is_new): ?>
                                    <span class="user-status active"> Yeni</span>
                                <?php elseif ($has_active_tickets): ?>
                                    <span class="user-status active"> Aktif</span>
                                <?php else: ?>
                                    <span class="user-status inactive">💤 Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                            <td>
                                <a href="../admin/user_details.php?id=<?= $user['id'] ?>" class="btn btn-small">👁️ Detay</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz? Tüm biletleri iptal edilecek!')">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-small btn-danger">🗑️ Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
        <h3> Kullanıcı İstatistikleri</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php
            $total_users = count($users);
            $active_users = count(array_filter($users, function($u) { return $u['ticket_count'] > 0; }));
            $new_users = count(array_filter($users, function($u) { return (time() - strtotime($u['created_at'])) < (7 * 24 * 3600); }));
            $total_balance = array_sum(array_column($users, 'balance'));
            $total_spent = array_sum(array_column($users, 'spent_amount'));
            ?>
            <div style="text-align: center;">
                <strong><?= $total_users ?></strong><br>
                <small>Toplam Kullanıcı</small>
            </div>
            <div style="text-align: center;">
                <strong><?= $active_users ?></strong><br>
                <small>Aktif Kullanıcı</small>
            </div>
            <div style="text-align: center;">
                <strong><?= $new_users ?></strong><br>
                <small>Son 7 Gün Kayıt</small>
            </div>
            <div style="text-align: center;">
                <strong><?= number_format($total_balance, 2) ?>₺</strong><br>
                <small>Toplam Bakiye</small>
            </div>
            <div style="text-align: center;">
                <strong><?= number_format($total_spent, 2) ?>₺</strong><br>
                <small>Toplam Harcama</small>
            </div>
        </div>
    </div>
</div>

</body>
</html>
