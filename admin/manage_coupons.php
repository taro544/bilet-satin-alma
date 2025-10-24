<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';

// Admin genel kupon ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_general_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount_percent = (int)$_POST['discount_percent'];
    $expiry_date = $_POST['expiry_date'];
    $max_uses = isset($_POST['max_uses']) && $_POST['max_uses'] > 0 ? (int)$_POST['max_uses'] : 0;

    if (empty($code) || $discount_percent <= 0) {
        $message = "Kupon kodu ve indirim yüzdesi gerekli!";
    } else {
        // Kupon kodu kontrolü
        $stmt = $db->prepare("SELECT COUNT(*) FROM coupons WHERE code = :code");
        $stmt->execute([':code' => $code]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = "Bu kupon kodu zaten mevcut!";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO coupons (code, discount_percent, expiry_date, company_id, max_uses, used_count)
                    VALUES (:code, :discount_percent, :expiry_date, NULL, :max_uses, 0)
                ");
                $stmt->execute([
                    ':code' => $code,
                    ':discount_percent' => $discount_percent,
                    ':expiry_date' => $expiry_date,
                    ':max_uses' => $max_uses
                ]);
                
                $message = "Genel kupon başarıyla oluşturuldu!";
                $_POST = [];
                
            } catch (Exception $e) {
                $message = "Kupon oluşturulurken hata: " . $e->getMessage();
            }
        }
    }
}

// Kupon silme (sadece admin)
if (isset($_POST['delete_coupon'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM coupons WHERE id = :id");
        $stmt->execute([':id' => $coupon_id]);
        $message = "Kupon silindi!";
    } catch (Exception $e) {
        $message = "Kupon silinirken hata: " . $e->getMessage();
    }
}

// Tüm kuponları firma bilgileriyle çek
$stmt = $db->prepare("
    SELECT c.*, comp.name as company_name,
           COALESCE(c.max_uses, 0) as max_uses,
           COALESCE(c.used_count, 0) as used_count
    FROM coupons c 
    LEFT JOIN companies comp ON c.company_id = comp.id 
    ORDER BY c.created_at DESC
");
$stmt->execute();
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kupon Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 20px auto; padding: 20px; }
        .message { padding: 15px; margin: 15px 0; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .coupons-table { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin: 20px 0; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; color: #333; }
        .btn { display: inline-block; padding: 8px 15px; margin: 2px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 12px; }
        .btn:hover { background: #005fa3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
        .coupon-code { font-family: 'Courier New', monospace; background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
        .company-name { color: #0077cc; font-weight: bold; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .stats-row { display: flex; gap: 20px; margin: 20px 0; }
        .stat-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); flex: 1; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #0077cc; }
        .stat-label { color: #666; margin-top: 5px; }
    </style>
</head>
<body>

<header>
    <h1><a href="../index.php">BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">Admin: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?></span>
        <a href="admin_panel.php">Admin Panel</a>
        <a href="manage_companies.php">Firmalar</a>
        <a href="../index.php">Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="admin-container">
    <a href="admin_panel.php" class="btn btn-secondary">Admin Panele Dön</a>
    
    <h1>Kupon Yönetimi</h1>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'silindi') !== false || strpos($message, 'oluşturuldu') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Admin Genel Kupon Ekleme -->
    <div class="form-container" style="background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin: 20px 0;">
        <h3>Genel İndirim Kuponu Oluştur (Tüm Firmalar İçin)</h3>
        <form method="POST">
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Kupon Kodu:</label>
                    <input type="text" name="code" placeholder="Örn: GENEL2024" required 
                           value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; text-transform: uppercase;">
                </div>
                
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">İndirim Yüzdesi (%):</label>
                    <input type="number" name="discount_percent" min="1" max="100" required
                           value="<?= htmlspecialchars($_POST['discount_percent'] ?? '') ?>" placeholder="Örn: 15"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Kullanım Limiti:</label>
                    <input type="number" name="max_uses" min="0" placeholder="0 = Sınırsız"
                           value="<?= htmlspecialchars($_POST['max_uses'] ?? '') ?>"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Son Kullanım Tarihi:</label>
                    <input type="date" name="expiry_date" required
                           value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 5px; color: transparent;">.</label>
                    <button type="submit" name="add_general_coupon" class="btn btn-success" style="width: 100%; background: #28a745;">
                        Genel Kupon Oluştur
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- İstatistikler -->
    <div class="stats-row">
        <?php
        $total_coupons = count($coupons);
        $expired_coupons = count(array_filter($coupons, fn($c) => strtotime($c['expiry_date']) <= time()));
        $active_coupons = $total_coupons - $expired_coupons;
        ?>
        <div class="stat-card">
            <div class="stat-number"><?= $total_coupons ?></div>
            <div class="stat-label">Toplam Kupon</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $active_coupons ?></div>
            <div class="stat-label">Aktif Kupon</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $expired_coupons ?></div>
            <div class="stat-label">Süresi Dolmuş</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">-</div>
            <div class="stat-label">Basit Sistem</div>
        </div>
    </div>

    <!-- Kuponlar Tablosu -->
    <div class="coupons-table">
        <h3 style="padding: 20px; margin: 0; background: #f8f9fa; border-bottom: 1px solid #ddd;">
            Tüm İndirim Kuponları (<?= count($coupons) ?>)
        </h3>
        
        <?php if (empty($coupons)): ?>
            <div style="padding: 40px; text-align: center; color: #666;">
                Henüz kupon oluşturulmamış.
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Kupon Kodu</th>
                        <th>İndirim</th>
                        <th>Kullanım Durumu</th>
                        <th>Son Kullanım</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): 
                        $is_expired = strtotime($coupon['expiry_date']) < time();
                        $max_uses = $coupon['max_uses'] ?? 0;
                        $used_count = $coupon['used_count'] ?? 0;
                        $is_limit_reached = ($max_uses > 0 && $used_count >= $max_uses);
                        $usage_percent = $max_uses > 0 ? round(($used_count / $max_uses) * 100) : 0;
                    ?>
                        <tr style="<?= ($is_expired || $is_limit_reached) ? 'opacity: 0.6;' : '' ?>">
                            <td>
                                <?php if ($coupon['company_name']): ?>
                                    <span class="company-name"><?= htmlspecialchars($coupon['company_name']) ?></span>
                                <?php else: ?>
                                    <span style="color: #28a745; font-weight: bold;">Genel (Admin)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="coupon-code"><?= htmlspecialchars($coupon['code']) ?></span>
                                <?php if ($is_expired): ?>
                                    <br><small style="color: #dc3545;">Süresi Dolmuş</small>
                                <?php elseif ($is_limit_reached): ?>
                                    <br><small style="color: #dc3545;">Limit Doldu</small>
                                <?php endif; ?>
                            </td>
                            <td>%<?= $coupon['discount_percent'] ?></td>
                            <td>
                                <?= $used_count ?> / <?= $max_uses > 0 ? $max_uses : '∞' ?>
                                <?php if ($max_uses > 0): ?>
                                    <br><div style="background: #f0f0f0; border-radius: 10px; height: 6px; margin-top: 3px;">
                                        <div style="background: <?= $usage_percent < 80 ? '#28a745' : ($usage_percent < 100 ? '#ffc107' : '#dc3545') ?>; height: 6px; border-radius: 10px; width: <?= $usage_percent ?>%;"></div>
                                    </div>
                                    <small>(<?= $usage_percent ?>%)</small>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d.m.Y', strtotime($coupon['expiry_date'])) ?></td>
                            <td>
                                <?php if (!$is_expired && !$is_limit_reached): ?>
                                    <span class="status-active">Aktif</span>
                                <?php else: ?>
                                    <span class="status-inactive">
                                        <?= $is_expired ? 'Süresi Dolmuş' : 'Limit Doldu' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')">
                                    <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                    <button type="submit" name="delete_coupon" class="btn btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
