<?php
require 'config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (empty($full_name) || empty($email) || empty($phone) || empty($_POST['password'])) {
        $message = "❌ Tüm alanları doldurun.";
    } else {
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $message = "❌ Bu e-posta ile zaten bir hesap var.";
        } else {
            $stmt = $db->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->execute([$full_name, $email, $phone, $password]);
            $message = "✅ Kayıt başarılı! Şimdi giriş yapabilirsiniz.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kayıt Ol - BiletAl</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .register-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            border-color: #0077cc;
            outline: none;
        }
        .btn-register {
            width: 100%;
            padding: 12px;
            background: #0077cc;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-register:hover {
            background: #005fa3;
        }
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #0077cc;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        header {
            background: #0077cc;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a {
            color: #fff;
            text-decoration: none;
            margin-left: 15px;
            padding: 8px 12px;
            border-radius: 5px;
            background: rgba(255,255,255,0.1);
        }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        body { padding-top: 80px; }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">BiletAl</a></h1>
    <div class="nav-buttons">
        <a href="index.php">Ana Sayfa</a>
        <a href="login.php">Giriş Yap</a>
    </div>
</header>

<div class="register-container">
    <h2 style="text-align: center; color: #0077cc; margin-bottom: 30px;">Yeni Hesap Oluştur</h2>
    
    <?php if ($message): ?>
        <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="full_name">Ad Soyad:</label>
            <input type="text" id="full_name" name="full_name" required 
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                   placeholder="Adınızı ve soyadınızı girin">
        </div>

        <div class="form-group">
            <label for="email">E-posta:</label>
            <input type="email" id="email" name="email" required 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                   placeholder="ornek@email.com">
        </div>

        <div class="form-group">
            <label for="phone">Telefon:</label>
            <input type="tel" id="phone" name="phone" required 
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                   placeholder="0532-123-4567">
        </div>

        <div class="form-group">
            <label for="password">Şifre:</label>
            <input type="password" id="password" name="password" required 
                   placeholder="En az 6 karakter">
        </div>

        <button type="submit" class="btn-register">Kayıt Ol</button>
    </form>

    <div class="login-link">
        <p>Zaten hesabınız var mı? <a href="login.php">Giriş yapın</a></p>
    </div>
</div>

</body>
</html>
