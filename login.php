<?php
require 'config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Kullanıcı doğrulama
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Session başlatma
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['company_id'] = $user['company_id'];

        header("Location: index.php");
        exit;
    } else {
        $message = " E-posta veya şifre hatalı.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Giriş Yap</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1><a href="index.php"> BiletAl</a></h1>
</header>

<div class="container">
    <h2>Giriş Yap</h2>

    <?php if ($message): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="email" name="email" placeholder="E-posta" required 
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <input type="password" name="password" placeholder="Şifre" required>
        <button type="submit">Giriş Yap</button>
    </form>

    <p><a href="register.php">Hesabın yok mu? Kayıt ol</a></p>
</div>

</body>
</html>
