<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Company admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: ../login.php');
    exit;
}

// Company ID'sini al
$company_id = $_SESSION['company_id'];
$message = '';

// Kupon ekleme (firma bazlı + kullanım limiti)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount_percent = (int)$_POST['discount_percent'];
    $max_uses = (int)$_POST['max_uses'];
    $expiry_date = $_POST['expiry_date'];

    if (empty($code) || $discount_percent <= 0 || $max_uses <= 0) {
        $message = "Tüm alanları doğru şekilde doldurun!";
    } else {
        // Kupon kodu kontrolü (firma bazlı)
        $stmt = $db->prepare("SELECT COUNT(*) FROM coupons WHERE code = :code AND (company_id = :company_id OR company_id IS NULL)");
        $stmt->execute([':code' => $code, ':company_id' => $company_id]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = "Bu kupon kodu zaten mevcut!";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO coupons (code, discount_percent, expiry_date, company_id, max_uses, used_count)
                    VALUES (:code, :discount_percent, :expiry_date, :company_id, :max_uses, 0)
                ");
                $stmt->execute([
                    ':code' => $code,
                    ':discount_percent' => $discount_percent,
                    ':expiry_date' => $expiry_date,
                    ':company_id' => $company_id,
                    ':max_uses' => $max_uses
                ]);
                
                $message = "Kupon başarıyla oluşturuldu!";
                $_POST = [];
                
            } catch (Exception $e) {
                $message = "Kupon oluşturulurken hata: " . $e->getMessage();
            }
        }
    }
}

// Kupon silme (sadece kendi kuponları)
if (isset($_POST['delete_coupon'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM coupons WHERE id = :id AND company_id = :company_id");
        $stmt->execute([':id' => $coupon_id, ':company_id' => $company_id]);
        $message = "Kupon silindi!";
    } catch (Exception $e) {
        $message = "Kupon silinirken hata: " . $e->getMessage();
    }
}

// Sadece kendi firmamın kuponlarını çek
$stmt = $db->prepare("
    SELECT *,
           COALESCE(max_uses, 0) as max_uses,
           COALESCE(used_count, 0) as used_count
    FROM coupons 
    WHERE company_id = :company_id 
    ORDER BY created_at DESC
");
$stmt->execute([':company_id' => $company_id]);
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Firma bilgilerini çek
$stmt = $db->prepare("SELECT name FROM companies WHERE id = :company_id");
$stmt->execute([':company_id' => $company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İndirim Kuponları - <?= htmlspecialchars($company['name']) ?></title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .company-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .message { padding: 15px; margin: 15px 0; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-container { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin: 20px 0; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { display: inline-block; padding: 10px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin: 5px; }
        .btn:hover { background: #005fa3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .coupons-table { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin: 20px 0; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; color: #333; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
        .coupon-code { font-family: 'Courier New', monospace; background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
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
    <h1><a href="../index.php">BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">Firma: <?= htmlspecialchars($company['name']) ?></span>
        <a href="company_panel.php">Firma Panel</a>
        <a href="manage_trips.php">Seferlerim</a>
        <a href="../index.php">Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="company-container">
    <h1>İndirim Kuponları</h1>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'başarıyla') !== false || strpos($message, 'silindi') !== false || strpos($message, 'aktif') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Yeni Kupon Ekleme -->
    <div class="form-container">
        <h3>Yeni İndirim Kuponu Oluştur</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="code">Kupon Kodu:</label>
                    <input type="text" id="code" name="code" placeholder="Örn: YILBASI2024" required 
                           value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" style="text-transform: uppercase;">
                </div>
                
                <div class="form-group">
                    <label for="discount_percent">İndirim Yüzdesi (%):</label>
                    <input type="number" id="discount_percent" name="discount_percent" min="1" max="100" required
                           value="<?= htmlspecialchars($_POST['discount_percent'] ?? '') ?>" placeholder="Örn: 20">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="max_uses">Kullanım Limiti:</label>
                    <input type="number" id="max_uses" name="max_uses" min="1" required
                           value="<?= htmlspecialchars($_POST['max_uses'] ?? '100') ?>" placeholder="Örn: 100">
                    <small style="color: #666;">Kaç kez kullanılabilir</small>
                </div>
                
                <div class="form-group">
                    <label for="expiry_date">Son Kullanım Tarihi:</label>
                    <input type="date" id="expiry_date" name="expiry_date" required
                           value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <button type="submit" name="add_coupon" class="btn btn-success" style="width: 100%;">Kupon Oluştur</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Mevcut Kuponlar -->
    <div class="coupons-table">
        <h3 style="padding: 20px; margin: 0; background: #f8f9fa; border-bottom: 1px solid #ddd;">
            Mevcut Kuponlar (<?= count($coupons) ?>)
        </h3>
        
        <?php if (empty($coupons)): ?>
            <div style="padding: 40px; text-align: center; color: #666;">
                Henüz kupon oluşturmadınız.
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
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
                                    <br><div style="background: #f0f0f0; border-radius: 10px; height: 8px; margin-top: 3px;">
                                        <div style="background: <?= $usage_percent < 80 ? '#28a745' : ($usage_percent < 100 ? '#ffc107' : '#dc3545') ?>; height: 8px; border-radius: 10px; width: <?= $usage_percent ?>%;"></div>
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
                                    <button type="submit" name="delete_coupon" class="btn btn-danger btn-sm">Sil</button>
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
