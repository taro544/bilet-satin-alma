<?php
require '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'company') {
    header('Location: ../login.php');
    exit;
}

$filter_city = $_GET['city'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = ['t.company_id = :company_id'];
$params = [':company_id' => $_SESSION['company_id']];

if (!empty($filter_city)) {
    $where_conditions[] = "(t.departure_city LIKE :city OR t.arrival_city LIKE :city)";
    $params[':city'] = "%$filter_city%";
}

if (!empty($search)) {
    $where_conditions[] = "(t.departure_city LIKE :search OR t.arrival_city LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter_status === 'upcoming') {
    $where_conditions[] = "t.departure_time > datetime('now')";
} elseif ($filter_status === 'past') {
    $where_conditions[] = "t.departure_time <= datetime('now')";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $stmt = $db->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'active') as sold_tickets,
               (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id) as total_tickets,
               c.name as company_name
        FROM trips t 
        LEFT JOIN companies c ON t.company_id = c.id 
        $where_clause
        ORDER BY t.departure_time DESC
    ");
    $stmt->execute($params);
    $trips = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT DISTINCT departure_city FROM trips WHERE company_id = :company_id UNION SELECT DISTINCT arrival_city FROM trips WHERE company_id = :company_id ORDER BY departure_city");
    $stmt->execute([':company_id' => $_SESSION['company_id']]);
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $error_message = "Seferler yüklenirken hata oluştu: " . $e->getMessage();
    $trips = [];
    $cities = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sefer Yönetimi - BiletAl</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .management-container { max-width: 1400px; margin: 20px auto; padding: 20px; }
        .filters { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-row input, .filter-row select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; }
        .filter-row button { padding: 8px 16px; background: #0077cc; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        .filter-row button:hover { background: #005fa3; }
        .trips-table { background: #fff; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-upcoming { background: #d4edda; color: #155724; }
        .status-past { background: #f8d7da; color: #721c24; }
        .status-active { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #6c757d; color: #fff; }
        .btn { display: inline-block; padding: 6px 12px; background: #0077cc; color: #fff; text-decoration: none; border-radius: 4px; font-size: 12px; margin: 2px; }
        .btn:hover { background: #005fa3; }
        .btn-edit { background: #28a745; }
        .btn-edit:hover { background: #218838; }
        .btn-delete { background: #dc3545; }
        .btn-delete:hover { background: #c82333; }
        .btn-new { background: #17a2b8; padding: 10px 20px; font-size: 14px; }
        .btn-new:hover { background: #138496; }
        .no-trips { text-align: center; padding: 40px; color: #666; }
        header { background: #0077cc; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        h1 { margin: 0; font-size: 26px; }
        h1 a { color: inherit; text-decoration: none; }
        .nav-buttons a { color: #fff; text-decoration: none; margin-left: 15px; padding: 8px 12px; border-radius: 5px; background: rgba(255,255,255,0.1); }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .user-welcome { margin-right: 15px; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .trip-actions { display: flex; gap: 5px; }
        @media (max-width: 768px) {
            .trips-table { overflow-x: auto; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-row > * { width: 100%; }
        }
    </style>
</head>
<body>

<header>
    <h1><a href="../index.php"> BiletAl</a></h1>
    <div class="nav-buttons">
        <span class="user-welcome">👋 Hoş geldin, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email']) ?>!</span>
        <a href="../index.php"> Ana Sayfa</a>
        <a href="company_panel.php"> Panel</a>
        <a href="../logout.php">Çıkış Yap</a>
    </div>
</header>

<div class="management-container">
    <div class="header-actions">
        <h1> Sefer Yönetimi</h1>
        <a href="../new_trip.php" class="btn btn-new"> Yeni Sefer Ekle</a>
    </div>

    <?php if (isset($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Filtreleme -->
    <div class="filters">
        <form method="GET" class="filter-row">
            <input type="text" name="search" placeholder="Şehir ara..." value="<?= htmlspecialchars($search) ?>">
            
            <select name="city">
                <option value="">Tüm Şehirler</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?= htmlspecialchars($city) ?>" <?= $filter_city === $city ? 'selected' : '' ?>>
                        <?= htmlspecialchars($city) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status">
                <option value="">Tüm Seferler</option>
                <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>Gelecek Seferler</option>
                <option value="past" <?= $filter_status === 'past' ? 'selected' : '' ?>>Geçmiş Seferler</option>
            </select>
            
            <button type="submit"> Filtrele</button>
            <a href="manage_trips.php" style="padding: 8px 16px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 5px;">↻ Temizle</a>
        </form>
    </div>

    <!-- Seferler Tablosu -->
    <div class="trips-table">
        <?php if (empty($trips)): ?>
            <div class="no-trips">
                <h3>😔 Sefer bulunamadı</h3>
                <p>Filtreleme kriterlerinize uygun sefer yok.</p>
                <a href="../new_trip.php" class="btn btn-new"> İlk Seferinizi Ekleyin</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Güzergah</th>
                        <th>Tarih & Saat</th>
                        <th>Fiyat</th>
                        <th>Kapasite</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trips as $trip): 
                        $is_past = strtotime($trip['departure_time']) <= time();
                        $is_full = ($trip['capacity'] > 0) ? ($trip['sold_tickets'] >= $trip['capacity']) : false;
                        $is_cancelled = ($trip['status'] ?? 'active') === 'cancelled';
                        
                        if ($is_cancelled) {
                            $status_class = 'status-cancelled';
                            $status_text = 'İptal Edildi';
                        } elseif ($is_past) {
                            $status_class = 'status-past';
                            $status_text = 'Geçmiş';
                        } elseif ($is_full) {
                            $status_class = 'status-active';
                            $status_text = 'Dolu';
                        } else {
                            $status_class = 'status-upcoming';
                            $status_text = 'Aktif';
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['arrival_city']) ?></strong>
                            </td>
                            <td>
                                <div> <?= date('d.m.Y', strtotime($trip['departure_time'])) ?></div>
                                <div> <?= date('H:i', strtotime($trip['departure_time'])) ?> - <?= date('H:i', strtotime($trip['arrival_time'])) ?></div>
                            </td>
                            <td>
                                <strong><?= number_format($trip['price'], 2) ?> ₺</strong>
                            </td>
                            <td>
                                <?php if ($is_cancelled): ?>
                                    <span style="color: #6c757d; text-decoration: line-through;">
                                         <?= $trip['total_tickets'] ?>/<?= $trip['capacity'] ?> (İptal)
                                    </span>
                                <?php else: ?>
                                    <span style="<?= $is_full ? 'color: #dc3545; font-weight: bold;' : '' ?>">
                                         <?= $trip['sold_tickets'] ?>/<?= $trip['capacity'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                            <td>
                                <div class="trip-actions">
                                    <?php if ($is_cancelled): ?>
                                        <span class="btn btn-edit" style="opacity: 0.5; cursor: not-allowed;" title="İptal edilmiş sefer düzenlenemez"></span>
                                        <span class="btn btn-delete" style="opacity: 0.5; cursor: not-allowed;" title="İptal edilmiş sefer"></span>
                                    <?php else: ?>
                                        <a href="/edit_trip.php?id=<?= $trip['id'] ?>" class="btn btn-edit" title="Düzenle">✏️</a>
                                        <?php if ($trip['sold_tickets'] == 0): ?>
                                            <a href="/delete_trip.php?id=<?= $trip['id'] ?>" class="btn btn-delete" title="Sil" onclick="return confirm('Bu seferi silmek istediğinizden emin misiniz?')">🗑️</a>
                                        <?php else: ?>
                                            <span class="btn btn-delete" style="opacity: 0.5; cursor: not-allowed;" title="Bilet satılmış, silinemez">🗑️</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="margin-top: 20px; text-align: center;">
        <p><strong>Toplam <?= count($trips) ?> sefer</strong></p>
        <a href="company_panel.php" class="btn">← Panele Dön</a>
    </div>
</div>

</body>
</html>
