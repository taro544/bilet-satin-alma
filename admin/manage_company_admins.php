<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Veritabanı bağlantısını yeniden kontrol et
if (!isset($db)) {
    $dsn = "sqlite:" . __DIR__ . "/../db/database.sqlite";
    try {
        $db = new PDO($dsn);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Veritabanı bağlantı hatası: " . $e->getMessage());
    }
}

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

// Yeni admin ekleme
if (isset($_POST['add_admin']) && $company_id) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (empty($full_name) || empty($email) || empty($_POST['password'])) {
        $message = "Tüm alanları doldurun.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Geçerli bir e-posta adresi girin.";
    } else {
        // E-posta kontrolü
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $message = "Bu e-posta adresi zaten kullanılıyor.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO users (full_name, email, password, role, company_id, created_at) VALUES (:full_name, :email, :password, 'company', :company_id, datetime('now'))");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':password' => $password,
                    ':company_id' => $company_id
                ]);
                
                $message = "Firma admini başarıyla eklendi!";
                $_POST = [];
                
            } catch (Exception $e) {
                $message = "Admin eklenirken hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Admin silme
if (isset($_POST['delete_admin']) && isset($_POST['admin_id'])) {
    $admin_id = (int)$_POST['admin_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :admin_id AND role = 'company'");
        $stmt->execute([':admin_id' => $admin_id]);
        
        $message = "Firma admini başarıyla silindi.";
        
    } catch (Exception $e) {
        $message = "Admin silinirken hata oluştu: " . $e->getMessage();
    }
}

// Firma bilgilerini al
$company = null;
if ($company_id) {
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = :company_id");
    $stmt->execute([':company_id' => $company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Firma adminlerini al
$admins = [];
if ($company_id) {
    try {
        // Manuel sorgu kullan - parameter binding sorunu var!
        $stmt = $db->query("SELECT * FROM users WHERE company_id = {$company_id} AND role = 'company' ORDER BY created_at DESC");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Adminler yüklenirken hata oluştu: " . $e->getMessage();
    }
}

// Tüm firmaları al (eğer company_id yoksa)
$companies = [];
if (!$company_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM companies ORDER BY name ASC");
        $stmt->execute();
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Firmalar yüklenirken hata oluştu: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Adminleri - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn { display: inline-block; padding: 8px 15px; margin: 2px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #005fa3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .form-container { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin: 20px 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .admins-table { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin: 20px 0; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; color: #333; }
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
    <h1><a href="../index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome"> Admin: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?></span>
        <a href="admin_panel.php"> Admin Panel</a>
        <a href="manage_companies.php"> Firmalar</a>
        <a href="../index.php"> Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="admin-container">
    
    <?php if (!$company_id): ?>
        <!-- Firma Seçimi -->
        <h1> Firma Adminleri</h1>
        
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <p>Yönetmek istediğiniz firmayı seçin:</p>
        
        <div class="companies-grid">
            <?php foreach ($companies as $comp): ?>
                <div class="company-card">
                    <h3><?= htmlspecialchars($comp['name']) ?></h3>
                    <a href="?company_id=<?= $comp['id'] ?>" class="btn"> Adminleri Yönet</a>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php else: ?>
        <!-- Firma Admin Yönetimi -->
        <a href="manage_company_admins.php" class="btn btn-secondary"> Firma Seçimine Dön</a>
        
        <?php if ($company): ?>
            <div class="company-info">
                <h2> <?= htmlspecialchars($company['name']) ?> - Firma Adminleri</h2>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>





        <!-- Yeni Admin Ekleme Formu -->
        <div class="form-container">
            <h3> Yeni Firma Admini Ekle</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="full_name">Ad Soyad:</label>
                    <input type="text" id="full_name" name="full_name" required 
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">E-posta:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Şifre:</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>

                <button type="submit" name="add_admin" class="btn btn-success"> Admin Ekle</button>
            </form>
        </div>

        <!-- Mevcut Adminler -->
        <div class="admins-table">
            <h3 style="padding: 20px; margin: 0; background: #f8f9fa; border-bottom: 1px solid #ddd;">
                Mevcut Firma Adminleri (<?= count($admins) ?>)
            </h3>
            
            <?php if (empty($admins)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    Henüz bu firmada admin bulunmuyor.
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ad Soyad</th>
                            <th>E-posta</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?= $admin['id'] ?></td>
                                <td><?= htmlspecialchars($admin['full_name']) ?></td>
                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($admin['created_at'])) ?></td>
                                <td>
                                    <form method="POST"
                                          onsubmit="return confirm('Bu admini silmek istediğinizden emin misiniz?')">
                                        <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                                        <button type="submit" name="delete_admin"> Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
</div>

</body>
</html>
