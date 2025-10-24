<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı login kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$trip_id = $_GET['id'] ?? null;
if (!$trip_id) {
    die("Geçersiz sefer ID");
}

// Sefer bilgilerini çek
$stmt = $db->prepare("SELECT trips.*, companies.name AS company_name 
                      FROM trips 
                      JOIN companies 
                      WHERE trips.id = :trip_id");
$stmt->execute([':trip_id' => $trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    die("Sefer bulunamadı");
}

// Kullanıcının bilgilerini çek
$stmt = $db->prepare("SELECT balance, full_name, email FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$_SESSION['full_name'] = $user['full_name'] ?? null;

// Dolu koltukları çek
$stmt = $db->prepare("SELECT seat_number FROM tickets WHERE trip_id = :trip_id AND status = 'active'");
$stmt->execute([':trip_id' => $trip_id]);
$occupied_seats = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Kalan kapasite hesapla
$available_seats = (int)$trip['capacity'] - count($occupied_seats);

// Toplam koltuk sayısı (her otobüs 40 koltuklu)
$total_seats = 40;

// Form submit - Koltuk seçimi ile sepete ekleme
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_seats = $_POST['selected_seats'] ?? [];
    
    if (empty($selected_seats)) {
        $message = "Lütfen en az bir koltuk seçin.";
    } else {
        // Seçilen koltuklar dolu mu kontrol et
        $seats_string = implode(',', array_map('intval', $selected_seats));
        $stmt = $db->prepare("SELECT seat_number FROM tickets WHERE trip_id = :trip_id AND seat_number IN ($seats_string) AND status = 'active'");
        $stmt->execute([':trip_id' => $trip_id]);
        $already_taken = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($already_taken)) {
            $message = "Seçtiğiniz koltuklar arasında dolu olanlar var: " . implode(', ', $already_taken);
        } else {
            try {
                // Sepetteki mevcut koltukları kontrol et
                $stmt = $db->prepare("SELECT seat_numbers FROM cart WHERE user_id = :user_id AND trip_id = :trip_id");
                $stmt->execute([':user_id' => $_SESSION['user_id'], ':trip_id' => $trip_id]);
                $existing_cart = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_cart) {
                    // Mevcut koltukları birleştir
                    $existing_seats = explode(',', $existing_cart['seat_numbers']);
                    $all_seats = array_unique(array_merge($existing_seats, $selected_seats));
                    $seat_numbers_str = implode(',', $all_seats);
                    
                    $stmt = $db->prepare("UPDATE cart SET quantity = :quantity, seat_numbers = :seat_numbers WHERE user_id = :user_id AND trip_id = :trip_id");
                    $stmt->execute([
                        ':quantity' => count($all_seats), 
                        ':seat_numbers' => $seat_numbers_str,
                        ':user_id' => $_SESSION['user_id'], 
                        ':trip_id' => $trip_id
                    ]);
                    $message = "Sepetiniz güncellendi! Toplam " . count($all_seats) . " adet koltuk seçildi.";
                } else {
                    // Yeni sepet kaydı oluştur
                    $seat_numbers_str = implode(',', $selected_seats);
                    $stmt = $db->prepare("INSERT INTO cart (user_id, trip_id, quantity, seat_numbers) VALUES (:user_id, :trip_id, :quantity, :seat_numbers)");
                    $stmt->execute([
                        ':user_id' => $_SESSION['user_id'], 
                        ':trip_id' => $trip_id, 
                        ':quantity' => count($selected_seats),
                        ':seat_numbers' => $seat_numbers_str
                    ]);
                    $message = "Sepete eklendi! " . count($selected_seats) . " adet koltuk seçildi.";
                }
            } catch (Exception $e) {
                $message = "Sepete eklerken hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Sepete Ekle - <?= htmlspecialchars($trip['company_name']) ?></title>
<link rel="stylesheet" href="style.css">

<style>
.info p { margin: 5px 0; }
.message { margin: 10px 0; padding: 10px; border-radius: 5px; background: #f0f0f0; }
button { padding: 10px 20px; border: none; background: #28a745; color: #fff; border-radius: 5px; cursor: pointer; }
button:hover { background: #218838; }

header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
h1 { margin: 0; font-size: 26px; }
h1 a { color: inherit; text-decoration: none; }

/* Koltuk Seçimi Stilleri */
.bus-container { max-width: 600px; margin: 20px auto; padding: 20px; background: #f8f9fa; border-radius: 10px; }
.bus-layout { display: flex; flex-direction: column; align-items: center; }
.driver-area { width: 100%; height: 40px; background: #6c757d; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-bottom: 20px; }
.bus-row { display: flex; justify-content: space-between; width: 100%; margin-bottom: 8px; }
.seat-pair { display: flex; gap: 5px; }
.aisle { width: 60px; }
.seat { width: 35px; height: 35px; border: 2px solid #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
.seat.available { background: #28a745; color: white; border-color: #28a745; }
.seat.available:hover { background: #218838; border-color: #218838; }
.seat.occupied { background: #dc3545; color: white; border-color: #dc3545; cursor: not-allowed; }
.seat.selected { background: #ffc107; color: #000; border-color: #ffc107; }
.seat-empty { width: 35px; height: 35px; margin: 2px; visibility: hidden; }
.seat-legend { display: flex; gap: 20px; justify-content: center; margin: 20px 0; }
.legend-item { display: flex; align-items: center; gap: 5px; }
.legend-seat { width: 20px; height: 20px; border-radius: 4px; }
.selected-info { text-align: center; margin: 15px 0; padding: 10px; background: #e9ecef; border-radius: 5px; }
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

<div class="container">
<h1>Sepete Ekle</h1>

<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="info">
    <p> Firma: <?= htmlspecialchars($trip['company_name']) ?></p>
    <p> Kalkış: <?= htmlspecialchars($trip['departure_city']) ?></p>
    <p> Varış: <?= htmlspecialchars($trip['arrival_city']) ?></p>
    <p> Kalkış: <?= date("d.m.Y H:i", strtotime($trip['departure_time'])) ?></p>
    <p> Varış: <?= date("d.m.Y H:i", strtotime($trip['arrival_time'])) ?></p>
    <p> Fiyat: <?= number_format($trip['price'],2) ?> ₺</p>
    <p>🪑 Kalan Koltuk: <?= $available_seats ?></p>
</div>

<?php if ($available_seats > 0): ?>

<!-- Koltuk Seçim Alanı -->
<div class="bus-container">
    <h3 style="text-align: center; margin-bottom: 20px;">🚌 Koltuk Seçimi</h3>
    
    <!-- Koltuk Renk Açıklaması -->
    <div class="seat-legend">
        <div class="legend-item">
            <div class="legend-seat available"></div>
            <span>Boş</span>
        </div>
        <div class="legend-item">
            <div class="legend-seat occupied"></div>
            <span>Dolu</span>
        </div>
        <div class="legend-item">
            <div class="legend-seat selected"></div>
            <span>Seçilen</span>
        </div>
    </div>

    <form method="POST" id="seatForm">
        <div class="bus-layout">
            <!-- Şoför Alanı -->
            <div class="driver-area">🚗 ŞOFÖR</div>
            
            <!-- Koltuk Düzeni -->
            <?php 
            $capacity = $trip['capacity'];
            $seats_per_row = 4; // 2+2 düzen
            $total_rows = ceil($capacity / $seats_per_row);
            
            for ($row = 1; $row <= $total_rows; $row++): 
                $seats_in_this_row = min($seats_per_row, $capacity - (($row - 1) * $seats_per_row));
            ?>
                <div class="bus-row">
                    <!-- Sol taraf -->
                    <div class="seat-pair">
                        <?php 
                        // Sol taraf koltukları (1. ve 2. koltuk)
                        for ($i = 1; $i <= min(2, $seats_in_this_row); $i++) {
                            $seat_number = ($row - 1) * $seats_per_row + $i;
                            $is_occupied = in_array($seat_number, $occupied_seats);
                            ?>
                            <div class="seat <?= $is_occupied ? 'occupied' : 'available' ?>" 
                                 data-seat="<?= $seat_number ?>" 
                                 <?= $is_occupied ? '' : 'onclick="toggleSeat(this)"' ?>><?= $seat_number ?></div>
                            <?php
                        }
                        // Sol tarafa 2 koltuktan az varsa boş alan ekle
                        for ($i = min(2, $seats_in_this_row) + 1; $i <= 2; $i++) {
                            echo '<div class="seat-empty"></div>';
                        }
                        ?>
                    </div>
                    
                    <!-- Koridor -->
                    <div class="aisle"></div>
                    
                    <!-- Sağ taraf -->
                    <div class="seat-pair">
                        <?php 
                        // Sağ taraf koltukları (3. ve 4. koltuk)
                        for ($i = 3; $i <= min(4, $seats_in_this_row); $i++) {
                            $seat_number = ($row - 1) * $seats_per_row + $i;
                            $is_occupied = in_array($seat_number, $occupied_seats);
                            ?>
                            <div class="seat <?= $is_occupied ? 'occupied' : 'available' ?>" 
                                 data-seat="<?= $seat_number ?>" 
                                 <?= $is_occupied ? '' : 'onclick="toggleSeat(this)"' ?>><?= $seat_number ?></div>
                            <?php
                        }
                        // Sağ tarafa koltuk yoksa boş alan ekle
                        for ($i = max(3, $seats_in_this_row + 1); $i <= 4; $i++) {
                            echo '<div class="seat-empty"></div>';
                        }
                        ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <!-- Seçilen Koltuklar Bilgisi -->
        <div class="selected-info">
            <div id="selectedSeats">Seçilen koltuklar: <span id="seatNumbers">Henüz koltuk seçilmedi</span></div>
            <div id="totalPrice">Toplam fiyat: <span id="priceAmount">0.00</span> ₺</div>
        </div>
        
        <!-- Gizli input alanları -->
        <div id="hiddenInputs"></div>
        
        <div style="text-align: center;">
            <button type="submit" id="addToCartBtn" disabled>🛒 Sepete Ekle</button>
        </div>
    </form>
</div>

<script>
let selectedSeats = [];
const seatPrice = <?= $trip['price'] ?>;

function toggleSeat(seatElement) {
    const seatNumber = parseInt(seatElement.dataset.seat);
    
    if (seatElement.classList.contains('selected')) {
        // Koltuk seçimini kaldır
        seatElement.classList.remove('selected');
        seatElement.classList.add('available');
        selectedSeats = selectedSeats.filter(s => s !== seatNumber);
    } else if (seatElement.classList.contains('available')) {
        // Koltuk seç
        seatElement.classList.remove('available');
        seatElement.classList.add('selected');
        selectedSeats.push(seatNumber);
    }
    
    updateSelectedInfo();
}

function updateSelectedInfo() {
    const seatNumbersSpan = document.getElementById('seatNumbers');
    const priceAmountSpan = document.getElementById('priceAmount');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const hiddenInputs = document.getElementById('hiddenInputs');
    
    if (selectedSeats.length === 0) {
        seatNumbersSpan.textContent = 'Henüz koltuk seçilmedi';
        priceAmountSpan.textContent = '0.00';
        addToCartBtn.disabled = true;
        hiddenInputs.innerHTML = '';
    } else {
        selectedSeats.sort((a, b) => a - b);
        seatNumbersSpan.textContent = selectedSeats.join(', ');
        priceAmountSpan.textContent = (selectedSeats.length * seatPrice).toFixed(2);
        addToCartBtn.disabled = false;
        
        // Hidden input alanlarını oluştur
        hiddenInputs.innerHTML = '';
        selectedSeats.forEach(seat => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_seats[]';
            input.value = seat;
            hiddenInputs.appendChild(input);
        });
    }
}
</script>

<?php else: ?>
<p>Tüm koltuklar dolu.</p>
<?php endif; ?>
</div>
</body>
</html>
