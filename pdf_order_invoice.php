<?php
require 'config.php';

require_once 'vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanici giris kontrolu
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    die("Gecersiz siparis ID");
}

// Siparis bilgilerini cek
$stmt = $db->prepare("
    SELECT o.*, u.full_name, u.email, u.phone AS user_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = :order_id AND o.user_id = :user_id
");
$stmt->execute([':order_id' => $order_id, ':user_id' => $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Siparis bulunamadi veya size ait degil");
}

// Siparisteki biletleri cek
$stmt = $db->prepare("
    SELECT t.id AS ticket_id, t.seat_number, t.price AS ticket_price, t.status, t.purchased_at,
           tr.departure_city, tr.arrival_city, tr.departure_time, tr.arrival_time, 
           c.name AS company_name
    FROM tickets t
    JOIN trips tr ON t.trip_id = tr.id
    JOIN companies c ON tr.company_id = c.id
    WHERE t.order_id = :order_id
    ORDER BY tr.departure_time ASC, t.seat_number ASC
");
$stmt->execute([':order_id' => $order_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDF olustur
class OrderPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'BiletAl - Siparis Faturasi', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(20);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Sayfa '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// PDF nesnesi olustur
$pdf = new OrderPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setFontSubsetting(true);

// PDF ayarlari
$pdf->SetCreator('BiletAl');
$pdf->SetAuthor('BiletAl');
$pdf->SetTitle('Siparis Faturasi - #' . $order['id']);
$pdf->SetSubject('Siparis Faturasi');

// Sayfa ayarlari
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Sayfa ekle
$pdf->AddPage();

// Font ayarla
$pdf->SetFont('helvetica', '', 12);

// Musteri bilgileri
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 0, 'MUSTERI BILGILERI', 0, 1, 'L');
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 0, 'Ad Soyad: ' . $order['full_name'], 0, 1, 'L');
$pdf->Ln(3);
$pdf->Cell(0, 0, 'E-posta: ' . $order['email'], 0, 1, 'L');
$pdf->Ln(3);
$pdf->Cell(0, 0, 'Telefon: ' . ($order['user_phone'] ?: 'Kayitli degil'), 0, 1, 'L');
$pdf->Ln(10);

// Siparis bilgileri
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 0, 'SIPARIS BILGILERI', 0, 1, 'L');
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 0, 'Siparis No: #' . $order['id'], 0, 1, 'L');
$pdf->Ln(3);
$pdf->Cell(0, 0, 'Siparis Tarihi: ' . date("d.m.Y H:i", strtotime($order['created_at'])), 0, 1, 'L');
$pdf->Ln(3);
$pdf->Cell(0, 0, 'Durum: ' . ucfirst($order['status']), 0, 1, 'L');
$pdf->Ln(3);
$pdf->Cell(0, 0, 'Toplam Bilet Sayisi: ' . count($tickets), 0, 1, 'L');

// Kupon bilgisi (varsa)
if ($order['discount_amount'] > 0) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(220, 53, 69); // Kirmizi renk
    $pdf->Cell(0, 0, 'Uygulanan Indirim: ' . $order['coupon_code'] . ' (%' . $order['discount_percent'] . ')', 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0); // Siyah renge don
    $pdf->SetFont('helvetica', '', 10);
}

$pdf->Ln(10);

// Bilet listesi
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 0, 'BILET DETAYLARI', 0, 1, 'L');
$pdf->Ln(5);

// Tablo basliklari
$pdf->SetFillColor(0, 119, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(20, 8, 'Bilet No', 1, 0, 'C', 1);
$pdf->Cell(30, 8, 'Sirket', 1, 0, 'C', 1);
$pdf->Cell(40, 8, 'Guzergah', 1, 0, 'C', 1);
$pdf->Cell(25, 8, 'Kalkis', 1, 0, 'C', 1);
$pdf->Cell(20, 8, 'Koltuk', 1, 0, 'C', 1);
$pdf->Cell(25, 8, 'Fiyat', 1, 0, 'C', 1);
$pdf->Cell(20, 8, 'Durum', 1, 1, 'C', 1);

// Bilet listesi
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 7);

foreach ($tickets as $index => $ticket) {
    $fill = ($index % 2 == 0) ? 1 : 0;
    $pdf->Cell(20, 6, '#'.$ticket['ticket_id'], 1, 0, 'C', $fill);
    $pdf->Cell(30, 6, substr($ticket['company_name'], 0, 12), 1, 0, 'C', $fill);
    $pdf->Cell(40, 6, $ticket['departure_city'] . '-' . $ticket['arrival_city'], 1, 0, 'C', $fill);
    $pdf->Cell(25, 6, date("d.m H:i", strtotime($ticket['departure_time'])), 1, 0, 'C', $fill);
    $pdf->Cell(20, 6, $ticket['seat_number'], 1, 0, 'C', $fill);
    $pdf->Cell(25, 6, number_format($ticket['ticket_price'], 2).' TL', 1, 0, 'R', $fill);
    $pdf->Cell(20, 6, ucfirst($ticket['status']), 1, 1, 'C', $fill);
}

$pdf->Ln(5);

// Fatura ozeti
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 0, 'FATURA OZETI', 0, 1, 'L');
$pdf->Ln(5);

// Ozet tablosu
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 10);

// Ara toplam
$pdf->Cell(120, 8, '', 0, 0, 'L');
$pdf->Cell(35, 8, 'ARA TOPLAM:', 1, 0, 'R', 1);
$pdf->Cell(35, 8, number_format($order['total_amount'], 2) . ' TL', 1, 1, 'R', 1);

// Indirim (varsa)
if ($order['discount_amount'] > 0) {
    $pdf->SetFillColor(255, 240, 240);
    $pdf->Cell(120, 8, '', 0, 0, 'L');
    $pdf->Cell(35, 8, 'INDIRIM (' . $order['coupon_code'] . '):', 1, 0, 'R', 1);
    $pdf->Cell(35, 8, '-' . number_format($order['discount_amount'], 2) . ' TL', 1, 1, 'R', 1);
}

// Toplam
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(40, 167, 69);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(120, 10, '', 0, 0, 'L');
$pdf->Cell(35, 10, 'TOPLAM:', 1, 0, 'R', 1);
$pdf->Cell(35, 10, number_format($order['final_amount'], 2) . ' TL', 1, 1, 'R', 1);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(10);

// Alt bilgi
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 0, 'Bu fatura elektronik olarak olusturulmustur.', 0, 1, 'L');
$pdf->Ln(5);

// Siparis dogrulama bilgileri
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 0, 'Siparis Dogrulama Bilgileri:', 0, 1, 'L');
$pdf->Ln(3);

$verification_text = "Siparis No: #" . $order['id'] . "\n";
$verification_text .= "Siparis Tarihi: " . date("d.m.Y H:i", strtotime($order['created_at'])) . "\n";
$verification_text .= "Toplam Bilet: " . count($tickets) . "\n";
$verification_text .= "Odenen Tutar: " . number_format($order['final_amount'], 2) . " TL";

$pdf->MultiCell(0, 3, $verification_text, 1, 'L', 0, 1, '', '', true);

// PDF'i cikti olarak ver
$filename = 'Siparis_' . $order['id'] . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D'); // D = Download
?>
