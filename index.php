<?php
require 'config.php'; // session_start() ve $db bağlantısı burada

$from_input = $_GET['from'] ?? '';
$to_input   = $_GET['to'] ?? '';

// Kullanıcı bilgisi
$user_role = $_SESSION['role'] ?? null;
$user_company_id = $_SESSION['company_id'] ?? null;

// Sepet sayısını al (sadece normal kullanıcılar için)
$cart_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'company') {
    $cart_stmt = $db->prepare("SELECT COUNT(*) FROM cart WHERE user_id = :user_id");
    $cart_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $cart_count = $cart_stmt->fetchColumn();
}

// Kalkış şehirleri
$from_cities_stmt = $db->query("SELECT DISTINCT departure_city FROM trips ORDER BY departure_city ASC");
$from_cities = $from_cities_stmt->fetchAll(PDO::FETCH_COLUMN);

// Varış şehirleri
$to_cities = [];
if (!empty($from_input)) {
    $to_stmt = $db->prepare("SELECT DISTINCT arrival_city FROM trips WHERE departure_city = :from ORDER BY arrival_city ASC");
    $to_stmt->execute([':from' => $from_input]);
    $to_cities = $to_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Seferleri sorgula
$query = "SELECT trips.*, companies.name AS company_name
          FROM trips 
          JOIN companies ON trips.company_id = companies.id 
          WHERE trips.status = 'active'";

$params = [];

// Şehir filtreleri (ÖNCE bunlar)
if (!empty($from_input)) {
    $query .= " AND trips.departure_city = :from_search";
    $params[':from_search'] = $from_input;
}

if (!empty($to_input)) {
    $query .= " AND trips.arrival_city = :to_search";
    $params[':to_search'] = $to_input;
}

// Company filtresi (SON olarak)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'company') {
    // Company ID kontrolü
    if (isset($_SESSION['company_id']) && !empty($_SESSION['company_id'])) {
        $query .= " AND trips.company_id = :company_id";
        $params[':company_id'] = $_SESSION['company_id'];
    } else {
        // Eğer company_id yoksa hiçbir şey gösterme
        die("HATA: Company ID bulunamadı! Session'da company_id yok. Login.php'yi kontrol et!");
    }
}
$query .= " ORDER BY departure_time ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Bilet Satış Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search { text-align: center; padding: 30px 20px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin: 20px auto; max-width: 700px; border-radius: 10px; }
        .search input { padding: 12px; width: 200px; margin: 5px; border-radius: 5px; border: 1px solid #ccc; }
        .search button { padding: 12px 20px; border: none; background: #0077cc; color: #fff; border-radius: 5px; cursor: pointer; }
        .search button:hover { background: #005fa3; }

        .trips { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; padding: 20px; }
        .trip-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); display: flex; flex-direction: column; justify-content: space-between; }
        .trip-card h3 { margin: 0 0 10px; color: #0077cc; }
        .trip-card p { margin: 5px 0; color: #333; }
        .price { font-size: 20px; font-weight: bold; margin: 10px 0; color: #28a745; }
        .trip-card a { text-align: center; display: block; padding: 10px; background: #28a745; color: #fff; text-decoration: none; border-radius: 8px; margin-top: 10px; }
        .trip-card a:hover { background: #218838; }
        .no-trip { text-align: center; font-size: 18px; color: #777; padding: 50px 0; }
        .admin-actions a { display: inline-block; margin-top: 5px; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .edit-btn { background: #ffc107; color: #000; }
        .edit-btn:hover { background: #e0a800; }
        .delete-btn { background: #dc3545; color: #fff; }
        .delete-btn:hover { background: #c82333; }
        .new-trip-btn { display: inline-block; margin: 20px auto; padding: 12px 20px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 8px; text-align: center; }
        .new-trip-btn:hover { background: #005fa3; }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php">BiletAl</a></h1>
            <div class="nav-buttons">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?>!</span>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin/admin_panel.php">Admin Panel</a>
                <?php elseif ($_SESSION['role'] === 'company'): ?>
                    <a href="company/company_panel.php">Sirket Paneli</a>
                    <a href="new_trip.php">Sefer Ekle</a>
                <?php else: ?>
                    <a href="cart.php">🛒 Sepetim <?= $cart_count > 0 ? "($cart_count)" : '' ?></a>
                    <a href="account.php">👤 Hesabım</a>
                <?php endif; ?>
                <a href="logout.php">Çıkış Yap</a>
            <?php else: ?>
                <a href="login.php">Giriş Yap</a>
                <a href="register.php">Kayıt Ol</a>
            <?php endif; ?>
        </div>
</header>

<div class="search">
    <form method="GET" action="">
        <input type="text" name="from" list="from-list" placeholder="Kalkış Şehri" value="<?= htmlspecialchars($from_input) ?>" autocomplete="off">
        <datalist id="from-list">
            <?php foreach ($from_cities as $city): ?>
                <option value="<?= htmlspecialchars($city) ?>">
            <?php endforeach; ?>
        </datalist>

        <input type="text" name="to" list="to-list" placeholder="Varış Şehri" value="<?= htmlspecialchars($to_input) ?>" autocomplete="off">
        <datalist id="to-list">
            <?php foreach ($to_cities as $city): ?>
                <option value="<?= htmlspecialchars($city) ?>">
            <?php endforeach; ?>
        </datalist>

        <button type="submit">🔎 Sefer Ara</button>
    </form>
</div>

<?php if ($user_role === 'company'): ?>
    <div style="text-align:center;">
        <a href="new_trip.php" class="new-trip-btn"> Yeni Sefer Ekle</a>
    </div>
<?php endif; ?>

<div class="trips">
    <?php if (count($trips) > 0): ?>
        <?php foreach ($trips as $trip): ?>
            <div class="trip-card">
                <h3><?= htmlspecialchars($trip['company_name']) ?></h3>
                <p> <?= htmlspecialchars($trip['departure_city']) ?> ➝ <?= htmlspecialchars($trip['arrival_city']) ?></p>
                <p> <?= date("d.m.Y H:i", strtotime($trip['departure_time'])) ?> → <?= date("H:i", strtotime($trip['arrival_time'])) ?></p>
                <p>🪑 Kalan Koltuk: <?= $trip['available_seats'] ?>/<?= $trip['capacity'] ?></p>
                <div class="price"><?= number_format($trip['price'], 2) ?> ₺</div>

                <?php if ($user_role === 'company'): ?>
                    <div class="admin-actions">
                        <a href="edit_trip.php?id=<?= $trip['id'] ?>" class="edit-btn"> Düzenle</a>
                        <a href="delete_trip.php?id=<?= $trip['id'] ?>" class="delete-btn" onclick="return confirm('Bu seferi silmek istediğine emin misin?');"> Sil</a>
                    </div>
                <?php elseif ($user_role === 'admin'): ?>
                    <div class="admin-actions">
                        <span style="color: #0077cc; font-weight: bold;"> Admin Görünümü</span>
                    </div>
                <?php else: ?>
                    <a href="trip.php?id=<?= $trip['id'] ?>"> Bilet Al</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-trip">📭 Şu anda kriterlere uygun sefer bulunamadı.</p>
    <?php endif; ?>
</div>

</body>
</html>
