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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);
    $admin_email = trim($_POST['admin_email']);
    $admin_password = trim($_POST['admin_password']);
    $admin_name = trim($_POST['admin_name']);

    if (empty($name) || empty($contact_email) || empty($admin_email) || empty($admin_password) || empty($admin_name)) {
        $message = "Firma adı, iletişim e-postası, admin adı, admin e-postası ve şifresi zorunludur.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Geçerli bir iletişim e-posta adresi girin.";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Geçerli bir admin e-posta adresi girin.";
    } elseif (strlen($admin_password) < 6) {
        $message = "Admin şifresi en az 6 karakter olmalıdır.";
    } else {
        // Firma adı kontrolü
        $stmt = $db->prepare("SELECT COUNT(*) FROM companies WHERE name = :name");
        $stmt->execute([':name' => $name]);
        $company_count = $stmt->fetchColumn();
        
        // Admin email kontrolü
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $admin_email]);
        $user_count = $stmt->fetchColumn();
        
        if ($company_count > 0) {
            $message = "Bu isimde bir firma zaten mevcut.";
        } elseif ($user_count > 0) {
            $message = "Bu e-posta adresi ile zaten bir kullanıcı kayıtlı.";
        } else {
            try {
                $db->beginTransaction();
                
                // Firma ekle
                $stmt = $db->prepare("INSERT INTO companies (name, phone, address, created_at) VALUES (:name, :phone, :address, datetime('now'))");
                $stmt->execute([
                    ':name' => $name,
                    ':phone' => $contact_phone,
                    ':address' => $contact_email
                ]);
                
                $company_id = $db->lastInsertId();
                
                // Firma adminini ekle
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (full_name, email, password, role, company_id, balance, created_at) VALUES (:full_name, :email, :password, 'company', :company_id, 0, datetime('now'))");
                $stmt->execute([
                    ':full_name' => $admin_name,
                    ':email' => $admin_email,
                    ':password' => $hashed_password,
                    ':company_id' => $company_id
                ]);
                
                $db->commit();
                
                $message = "Firma ve admin kullanıcısı başarıyla eklendi! Admin giriş bilgileri: " . $admin_email;
                $_POST = [];
                
            } catch (Exception $e) {
                $db->rollback();
                $message = "Firma eklenirken hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Firma Ekle - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .form-container { max-width: 600px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .form-group textarea { height: 100px; resize: vertical; }
        .btn { display: inline-block; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin-right: 10px; }
        .btn:hover { background: #005fa3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .tips { background: #e2f3ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #0077cc; }
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

<div class="form-container">
    <h1> Yeni Firma Ekle</h1>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="tips">
        <h4>💡 Firma Ekleme İpuçları:</h4>
        <ul>
            <li>Firma adı benzersiz olmalıdır</li>
            <li>İletişim e-postası geçerli bir e-posta adresi olmalıdır</li>
            <li>Admin e-postası ile firma admini otomatik oluşturulur</li>
            <li>Admin şifresi en az 6 karakter olmalıdır</li>
        </ul>
    </div>

    <form method="POST">
        <div class="form-group">
            <label for="name">Firma Adı: *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>



        <div class="form-group">
            <label for="contact_email">İletişim E-postası: *</label>
            <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="contact_phone">İletişim Telefonu:</label>
            <input type="tel" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>" placeholder="05XXXXXXXXX">
        </div>

        <hr style="margin: 30px 0; border: 1px solid #ddd;">
        <h3 style="color: #0077cc; margin-bottom: 20px;">Firma Admin Bilgileri</h3>

        <div class="form-group">
            <label for="admin_name">Admin Adı Soyadı: *</label>
            <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required placeholder="Ahmet Yılmaz">
        </div>

        <div class="form-group">
            <label for="admin_email">Admin E-postası: *</label>
            <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required placeholder="admin@firma.com">
        </div>

        <div class="form-group">
            <label for="admin_password">Admin Şifresi: *</label>
            <input type="password" id="admin_password" name="admin_password" required placeholder="En az 6 karakter" minlength="6">
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn">Firma ve Admin Ekle</button>
            <a href="manage_companies.php" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>

</body>
</html>
