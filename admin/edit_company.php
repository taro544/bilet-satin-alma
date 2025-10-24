<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$company_id = $_GET['id'] ?? null;
if (!$company_id) {
    header('Location: manage_companies.php');
    exit;
}

$message = '';

// Firma bilgilerini çek
try {
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = :id");
    $stmt->execute([':id' => $company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        header('Location: manage_companies.php');
        exit;
    }
} catch (Exception $e) {
    $message = "Firma bilgileri alınırken hata oluştu: " . $e->getMessage();
}

// Form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company'])) {
    $name = trim($_POST['name']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);
    
    if (empty($name)) {
        $message = "Firma adı boş olamaz.";
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE companies 
                SET name = :name, address = :address, phone = :phone
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':address' => $contact_email,
                ':phone' => $contact_phone,
                ':id' => $company_id
            ]);
            
            $message = "Firma bilgileri başarıyla güncellendi.";
            
            // Güncellenmiş bilgileri tekrar çek
            $stmt = $db->prepare("SELECT * FROM companies WHERE id = :id");
            $stmt->execute([':id' => $company_id]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $message = "Güncelleme sırasında hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Düzenle - Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .form-container { background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group textarea { height: 100px; resize: vertical; }
        .btn { display: inline-block; padding: 12px 20px; margin: 5px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #005fa3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-button { margin-bottom: 20px; }
    </style>
</head>
<body>

<header>
    <h1><a href="../index.php">BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">Admin: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?></span>
        <a href="admin_panel.php">Admin Panel</a>
        <a href="../index.php">Ana Sayfa</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="admin-container">
    <div class="back-button">
        <a href="manage_companies.php" class="btn btn-secondary">← Firma Listesine Dön</a>
    </div>
    
    <div class="form-container">
        <h1>Firma Düzenle</h1>
        
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'başarıyla') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="name">Firma Adı *</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($company['name'] ?? '') ?>" required>
            </div>
            

            
            <div class="form-group">
                <label for="contact_email">İletişim E-posta</label>
                <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($company['address'] ?? '') ?>" placeholder="info@firma.com">
            </div>
            
            <div class="form-group">
                <label for="contact_phone">İletişim Telefonu</label>
                <input type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($company['phone'] ?? '') ?>" placeholder="0212 123 45 67">
            </div>
            
            <div class="form-group">
                <button type="submit" name="update_company" class="btn btn-success">Güncelle</button>
                <a href="manage_companies.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
