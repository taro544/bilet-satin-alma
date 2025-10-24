<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $db->prepare("SELECT balance, full_name, email FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$discount_percent = 0;
$coupon_code = '';
$discount_amount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    
    if (empty($coupon_code)) {
        $message = "Lütfen kupon kodunu girin.";
    } else {
        // Sepetteki firma listesini al
        $stmt = $db->prepare("
            SELECT DISTINCT tr.company_id 
            FROM cart c 
            JOIN trips tr ON c.trip_id = tr.id 
            WHERE c.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $cart_companies = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($cart_companies)) {
            $message = "Sepetinizde ürün yok!";
        } else {
            // Kupon kontrol - basit yöntem
            $sql = "
                SELECT c.id, c.discount_percent, c.expiry_date, c.company_id,
                       COALESCE(c.max_uses, 0) as max_uses,
                       COALESCE(c.used_count, 0) as used_count
                FROM coupons c
                WHERE c.code = :code 
                AND (c.company_id IS NULL OR c.company_id IN (" . implode(',', array_map('intval', $cart_companies)) . "))
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([':code' => $coupon_code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                $message = "Bu kupon sepetinizdeki ürünler için geçerli değil!";
            } elseif (strtotime($coupon['expiry_date']) < time()) {
                $message = "Bu kupon kodunun süresi dolmuş!";
            } elseif ($coupon['max_uses'] > 0 && $coupon['used_count'] >= $coupon['max_uses']) {
                $message = "Bu kupon kodu kullanım limitine ulaştı!";
            } else {
                $stmt = $db->prepare("SELECT id FROM user_coupons WHERE user_id = :user_id AND coupon_id = :coupon_id");
                $stmt->execute([':user_id' => $_SESSION['user_id'], ':coupon_id' => $coupon['id']]);
                $used_coupon = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($used_coupon) {
                    $message = "Bu kupon kodunu daha önce kullandınız!";
                } else {
                    $discount_percent = $coupon['discount_percent'];
                    $_SESSION['applied_coupon'] = [
                        'id' => $coupon['id'],
                        'code' => $coupon_code,
                        'discount_percent' => $discount_percent
                    ];
                    $coupon_type = $coupon['company_id'] ? 'Firma' : 'Genel';
                    $message = "Kupon kodu uygulandı! %{$discount_percent} indirim ($coupon_type kupon).";
                }
            }
        }
    }
}

if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    $message = "Kupon kodu kaldırıldı.";
}

if (isset($_SESSION['applied_coupon'])) {
    $discount_percent = $_SESSION['applied_coupon']['discount_percent'];
    $coupon_code = $_SESSION['applied_coupon']['code'];
}

if (isset($_GET['remove'])) {
    $trip_id = (int)$_GET['remove'];
    $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id AND trip_id = :trip_id");
    $stmt->execute([':user_id' => $_SESSION['user_id'], ':trip_id' => $trip_id]);
    $message = "Ürün sepetten kaldırıldı.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $trip_id => $quantity) {
        $quantity = (int)$quantity;
        
        $stmt = $db->prepare("SELECT seat_numbers FROM cart WHERE user_id = :user_id AND trip_id = :trip_id");
        $stmt->execute([':user_id' => $_SESSION['user_id'], ':trip_id' => $trip_id]);
        $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cart_item && !empty($cart_item['seat_numbers'])) {
            if ($quantity == 0) {
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id AND trip_id = :trip_id");
                $stmt->execute([':user_id' => $_SESSION['user_id'], ':trip_id' => $trip_id]);
            }
        } else {
            if ($quantity > 0) {
                $stmt = $db->prepare("UPDATE cart SET quantity = :quantity WHERE user_id = :user_id AND trip_id = :trip_id");
                $stmt->execute([':quantity' => $quantity, ':user_id' => $_SESSION['user_id'], ':trip_id' => $trip_id]);
            } else {
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id AND trip_id = :trip_id");
                $stmt->execute([':user_id' => $_SESSION['user_id'], ':trip_id' => $trip_id]);
            }
        }
    }
    $message = "Sepet güncellendi.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $stmt = $db->prepare("
        SELECT c.trip_id, c.quantity, c.seat_numbers, t.price, t.capacity, t.departure_city, t.arrival_city, comp.name as company_name
        FROM cart c 
        JOIN trips t ON c.trip_id = t.id 
        JOIN companies comp ON t.company_id = comp.id
        WHERE c.user_id = :user_id
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        $message = "Sepetiniz boş!";
    } else {
        $total_price = 0;
        $capacity_error = false;
        
        foreach ($cart_items as $item) {
            if ($item['quantity'] > $item['capacity']) {
                $message = "'{$item['company_name']} - {$item['departure_city']} → {$item['arrival_city']}' seferi için yeterli koltuk yok! İstenen: {$item['quantity']}, Mevcut: {$item['capacity']}";
                $capacity_error = true;
                break;
            }
            $total_price += $item['quantity'] * $item['price'];
        }

        if (!$capacity_error) {
            $final_price = $total_price;
            $discount_amount = 0;
            
            if (isset($_SESSION['applied_coupon'])) {
                $discount_amount = ($total_price * $_SESSION['applied_coupon']['discount_percent']) / 100;
                $final_price = $total_price - $discount_amount;
            }
            
            if ($user['balance'] < $final_price) {
                $message = "Bakiyeniz yetersiz! Toplam: " . number_format($final_price, 2) . " ₺, Bakiyeniz: " . number_format($user['balance'], 2) . " ₺";
            } else {
                $db->beginTransaction();
                try {
                    $coupon_code_used = isset($_SESSION['applied_coupon']) ? $_SESSION['applied_coupon']['code'] : null;
                    $discount_percent_used = isset($_SESSION['applied_coupon']) ? $_SESSION['applied_coupon']['discount_percent'] : 0;
                    
                    $stmt = $db->prepare("INSERT INTO orders (user_id, total_amount, discount_amount, coupon_code, discount_percent, final_amount) VALUES (:user_id, :total_amount, :discount_amount, :coupon_code, :discount_percent, :final_amount)");
                    $stmt->execute([
                        ':user_id' => $_SESSION['user_id'],
                        ':total_amount' => $total_price,
                        ':discount_amount' => $discount_amount,
                        ':coupon_code' => $coupon_code_used,
                        ':discount_percent' => $discount_percent_used,
                        ':final_amount' => $final_price
                    ]);
                    
                    $order_id = $db->lastInsertId();
                    
                    foreach ($cart_items as $item) {
                        if (!empty($item['seat_numbers'])) {
                            $selected_seats = explode(',', $item['seat_numbers']);
                            foreach ($selected_seats as $seat_number) {
                                $stmt = $db->prepare("INSERT INTO tickets (user_id, trip_id, seat_number, price, order_id) VALUES (:user_id, :trip_id, :seat_number, :price, :order_id)");
                                $stmt->execute([
                                    ':user_id' => $_SESSION['user_id'],
                                    ':trip_id' => $item['trip_id'],
                                    ':seat_number' => (int)$seat_number,
                                    ':price' => $item['price'],
                                    ':order_id' => $order_id
                                ]);
                            }
                        } else {
                            for ($i = 0; $i < $item['quantity']; $i++) {
                                $seat_number = $item['capacity'] - $item['quantity'] + $i + 1;
                                $stmt = $db->prepare("INSERT INTO tickets (user_id, trip_id, seat_number, price, order_id) VALUES (:user_id, :trip_id, :seat_number, :price, :order_id)");
                                $stmt->execute([
                                    ':user_id' => $_SESSION['user_id'],
                                    ':trip_id' => $item['trip_id'],
                                    ':seat_number' => $seat_number,
                                    ':price' => $item['price'],
                                    ':order_id' => $order_id
                                ]);
                            }
                        }
                        
                        $stmt = $db->prepare("UPDATE trips SET available_seats = available_seats - :quantity WHERE id = :trip_id");
                        $stmt->execute([':quantity' => $item['quantity'], ':trip_id' => $item['trip_id']]);
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET balance = balance - :total WHERE id = :user_id");
                    $stmt->execute([':total' => $final_price, ':user_id' => $_SESSION['user_id']]);
                    
                    if (isset($_SESSION['applied_coupon'])) {
                        // Kupon kullanım sayısını artır
                        $stmt = $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = :coupon_id");
                        $stmt->execute([':coupon_id' => $_SESSION['applied_coupon']['id']]);
                        
                        // Kullanıcının bu kuponu kullandığını kaydet
                        $stmt = $db->prepare("INSERT INTO user_coupons (user_id, coupon_id) VALUES (:user_id, :coupon_id)");
                        $stmt->execute([':user_id' => $_SESSION['user_id'], ':coupon_id' => $_SESSION['applied_coupon']['id']]);
                        unset($_SESSION['applied_coupon']);
                    }
                    
                    $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id");
                    $stmt->execute([':user_id' => $_SESSION['user_id']]);
                    
                    $db->commit();
                    $user['balance'] -= $final_price;
                    
                    $success_message = "Satın alma başarılı! ";
                    if ($discount_amount > 0) {
                        $success_message .= "Toplam: " . number_format($total_price, 2) . " ₺, İndirim: " . number_format($discount_amount, 2) . " ₺, Ödenen: " . number_format($final_price, 2) . " ₺";
                    } else {
                        $success_message .= "Toplam: " . number_format($final_price, 2) . " ₺";
                    }
                    $message = $success_message;
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "Satın alma sırasında hata oluştu: " . $e->getMessage();
                }
            }
        }
    }
}

$stmt = $db->prepare("
    SELECT c.*, t.departure_city, t.arrival_city, t.departure_time, t.arrival_time, t.price, t.capacity, comp.name as company_name
    FROM cart c 
    JOIN trips t ON c.trip_id = t.id 
    JOIN companies comp ON t.company_id = comp.id
    WHERE c.user_id = :user_id 
    ORDER BY c.added_at DESC
");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['quantity'] * $item['price'];
}

$discount_amount = 0;
$final_price = $total_price;

if (isset($_SESSION['applied_coupon'])) {
    $discount_amount = ($total_price * $_SESSION['applied_coupon']['discount_percent']) / 100;
    $final_price = $total_price - $discount_amount;
    $discount_percent = $_SESSION['applied_coupon']['discount_percent'];
    $coupon_code = $_SESSION['applied_coupon']['code'];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sepetim - BiletAl</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .cart-item { background: #fff; margin: 15px 0; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .cart-item h3 { margin: 0 0 10px; color: #0077cc; }
        .cart-item p { margin: 5px 0; }
        .quantity-controls { display: flex; align-items: center; gap: 10px; margin: 10px 0; }
        .quantity-controls input { width: 60px; padding: 5px; text-align: center; border: 1px solid #ccc; border-radius: 5px; }
        .remove-btn { background: #dc3545; color: #fff; padding: 8px 12px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin-top: 10px; }
        .remove-btn:hover { background: #c82333; }
        .cart-summary { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 20px; text-align: center; }
        .checkout-btn { background: #28a745; color: #fff; padding: 15px 30px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; margin-top: 15px; }
        .checkout-btn:hover { background: #218838; }
        .update-btn { background: #0077cc; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 15px; }
        .update-btn:hover { background: #005fa3; }
        .empty-cart { text-align: center; padding: 50px 0; color: #777; }
        .message { margin: 15px 0; padding: 15px; border-radius: 8px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .coupon-section { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .coupon-input { display: flex; gap: 10px; align-items: center; margin-top: 10px; }
        .coupon-input input { padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 200px; }
        .coupon-btn { background: #17a2b8; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .coupon-btn:hover { background: #138496; }
        .coupon-applied { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-top: 10px; display: flex; justify-content: space-between; align-items: center; }
        .remove-coupon { background: #dc3545; color: #fff; padding: 5px 10px; border: none; border-radius: 3px; text-decoration: none; font-size: 12px; }
        .discount-info { color: #28a745; font-weight: bold; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome, .balance { margin-right: 15px; }
    </style>
</head>
<body>

<header>
    <h1><a href="index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($user['full_name'] ?? $user['email']) ?>!</span>
        <span class="balance"> Bakiye: <?= number_format($user['balance'],2) ?> ₺</span>
        <a href="cart.php">🛒 Sepetim</a>
        <a href="account.php">👤 Hesabım</a>
        <a href="logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="cart-container">
    <h1>🛒 Sepetim</h1>
    
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <h2>Sepetiniz boş</h2>
            <p>Henüz sepetinize ürün eklemediniz.</p>
            <a href="index.php" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 8px;">Seferleri Görüntüle</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <h3><?= htmlspecialchars($item['company_name']) ?></h3>
                    <p> <?= htmlspecialchars($item['departure_city']) ?> ➝ <?= htmlspecialchars($item['arrival_city']) ?></p>
                    <p> <?= date("d.m.Y H:i", strtotime($item['departure_time'])) ?> → <?= date("H:i", strtotime($item['arrival_time'])) ?></p>
                    <p> Bilet Fiyatı: <?= number_format($item['price'], 2) ?> ₺</p>
                    <p>🪑 Mevcut Koltuk: <?= $item['capacity'] ?></p>
                    
                    <?php if (!empty($item['seat_numbers'])): ?>
                        <p> Seçilen Koltuklar: <?= htmlspecialchars($item['seat_numbers']) ?></p>
                    <?php endif; ?>
                    
                    <div class="quantity-controls">
                        <label>Bilet Sayısı:</label>
                        <input type="number" name="quantities[<?= $item['trip_id'] ?>]" value="<?= $item['quantity'] ?>" min="0" max="<?= $item['capacity'] ?>" readonly style="background: #f8f9fa;">
                        <span>Toplam: <?= number_format($item['quantity'] * $item['price'], 2) ?> ₺</span>
                    </div>
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        💡 Koltuk seçimli biletlerde miktar değiştirilemez. Koltuk değiştirmek için ürünü kaldırıp tekrar seçin.
                    </p>
                    
                    <a href="cart.php?remove=<?= $item['trip_id'] ?>" class="remove-btn" onclick="return confirm('Bu ürünü sepetten kaldırmak istediğinize emin misiniz?')"> Kaldır</a>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" name="update_cart" class="update-btn"> Sepeti Güncelle</button>
        </form>
        
        <!-- Kupon Kodu Bölümü -->
        <div class="coupon-section">
            <h3> İndirim Kuponu</h3>
            
            <?php if (isset($_SESSION['applied_coupon'])): ?>
                <div class="coupon-applied">
                    <span> Kupon uygulandı: <strong><?= htmlspecialchars($coupon_code) ?></strong> (%<?= $discount_percent ?> indirim)</span>
                    <a href="cart.php?remove_coupon=1" class="remove-coupon">Kaldır</a>
                </div>
            <?php else: ?>
                <form method="POST" style="margin: 0;">
                    <div class="coupon-input">
                        <input type="text" name="coupon_code" placeholder="Kupon kodunu girin" maxlength="20">
                        <button type="submit" name="apply_coupon" class="coupon-btn">Uygula</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <form method="POST">
            <div class="cart-summary">
                <h2>Sepet Özeti</h2>
                <p>Ara Toplam: <?= number_format($total_price, 2) ?> ₺</p>
                
                <?php if ($discount_amount > 0): ?>
                    <p class="discount-info">İndirim (%<?= $discount_percent ?>): -<?= number_format($discount_amount, 2) ?> ₺</p>
                    <hr style="margin: 10px 0;">
                    <p><strong>Toplam Tutar: <?= number_format($final_price, 2) ?> ₺</strong></p>
                <?php else: ?>
                    <p><strong>Toplam Tutar: <?= number_format($total_price, 2) ?> ₺</strong></p>
                <?php endif; ?>
                
                <p>Bakiyeniz: <?= number_format($user['balance'], 2) ?> ₺</p>
                
                <?php if ($user['balance'] >= $final_price): ?>
                    <button type="submit" name="checkout" class="checkout-btn" onclick="return confirm('Satın alma işlemini onaylıyor musunuz?')"> Satın Al</button>
                <?php else: ?>
                    <p style="color: #dc3545; font-weight: bold;">Bakiyeniz yetersiz! <?= number_format($final_price - $user['balance'], 2) ?> ₺ eksik.</p>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
