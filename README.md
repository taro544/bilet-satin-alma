
# BiletAl - Otobüs Bilet Rezervasyon Sistemi

Modern ve kullanıcı dostu otobüs bilet rezervasyon sistemi. PHP 8.2, SQLite ve Docker ile geliştirilmiştir.

## 🚀 Özellikler

### 👤 Kullanıcı Özellikleri
- **Hesap Yönetimi**: Kullanıcı kaydı, giriş ve profil yönetimi
- **Bilet Arama**: Şehir, tarih ve saate göre sefer arama
- **Bilet Satın Alma**: Güvenli ödeme ve koltuk seçimi
- **Bilet İptal**: Kalkıştan 1 saat öncesine kadar iptal imkanı
- **PDF Fatura**: Türkçe karakter desteği ile fatura oluşturma
- **İndirim Kuponları**: Firma özel kupon kodları
- **Bakiye Sistemi**: Para yükleme ve harcama takibi
- **Bildirimler**: Sistem bildirimleri ve işlem onayları

### 🏢 Firma Özellikleri
- **Sefer Yönetimi**: Yeni sefer ekleme, düzenleme ve iptal
- **Kupon Yönetimi**: Firma özel indirim kuponları oluşturma
- **Kapasite Takibi**: Gerçek zamanlı koltuk durumu
- **Gelir Raporları**: Satış ve gelir istatistikleri

### ⚙️ Admin Özellikleri
- **Firma Yönetimi**: Firma ekleme, düzenleme ve silme
- **Kullanıcı Yönetimi**: Kullanıcı hesapları ve bakiye kontrolü
- **Sistem İstatistikleri**: Genel sistem metrikleri
- **Kupon Kontrolü**: Tüm kuponları görüntüleme ve yönetme

## 🛠️ Teknolojiler

- **Backend**: PHP 8.2
- **Veritabanı**: SQLite
- **PDF Oluşturma**: TCPDF
- **Containerization**: Docker & Docker Compose
- **Frontend**: HTML5, CSS3, JavaScript
- **Paket Yönetimi**: Composer

## 📦 Kurulum

### Docker ile Kurulum (Önerilen)

1. **Projeyi klonlayın:**
```bash
git clone <repository-url>
cd biletal
```

2. **Docker container'ı başlatın:**
```bash
docker-compose up -d
```

3. **Uygulamaya erişin:**
```
http://localhost:8080
```

### Manuel Kurulum

1. **Gereksinimler:**
   - PHP 8.2 veya üzeri
   - SQLite3 desteği
   - Composer

2. **Bağımlılıkları yükleyin:**
```bash
composer install
```

3. **Yerel sunucuyu başlatın:**
```bash
php -S localhost:8080
```

## 🎯 Demo Hesapları

### Admin Hesabı
- **Email**: admin@admin.com
- **Şifre**: password

### Şirket Hesapları
- **Metro Turizm**: metro@admin.com / password
- **Pamukkale Turizm**: pamukkale@admin.com / password
- **Varan Turizm**: varan@admin.com / password

### Kullanıcı Hesapları (Hepsinin bakiyesi 1000₺)
- **Ahmet Yılmaz**: ahmet@test.com / password
- **Ayşe Demir**: ayse@test.com / password
- **Mehmet Kaya**: mehmet@test.com / password
- **Fatma Özkan**: fatma@test.com / password
- **Ali Çelik**: ali@test.com / password

## 📁 Proje Yapısı

```
biletal/
├── admin/                 # Admin paneli dosyaları
├── company/              # Şirket paneli dosyaları
├── db/                   # SQLite veritabanı
├── vendor/               # Composer bağımlılıkları
├── composer.json         # PHP bağımlılıkları
├── config.php           # Veritabanı konfigürasyonu
├── docker-compose.yml    # Docker Compose konfigürasyonu
├── Dockerfile           # Docker image tanımı
├── index.php            # Ana sayfa
├── login.php            # Giriş sayfası
├── register.php         # Kayıt sayfası
├── account.php          # Kullanıcı hesap sayfası
├── cart.php             # Sepet ve ödeme
├── cancel_ticket.php    # Bilet iptal
├── pdf_ticket.php       # PDF fatura oluşturma
└── style.css            # Ana stil dosyası
```

## 🔧 Konfigürasyon

### Veritabanı
Sistem SQLite kullanır ve otomatik olarak `db/database.sqlite` dosyasını oluşturur.

### PDF Yapılandırması
TCPDF kütüphanesi ile Türkçe karakter desteği mevcuttur.

### Docker Ayarları
`docker-compose.yml` dosyasında port ve volume ayarları yapılabilir.

## 🚦 Kullanım

1. **Admin olarak giriş yapın** ve firma hesapları oluşturun
2. **Firma hesabı ile** seferler ekleyin
3. **Kullanıcı hesabı ile** bilet arama ve satın alma işlemleri yapın
4. **PDF faturalar** otomatik olarak oluşturulur
5. **Kupon sistemi** ile indirimli satışlar yapılabilir

## 🛡️ Güvenlik

- Password hash'leme (PHP password_hash)
- SQL injection koruması (PDO prepared statements)
- Session yönetimi
- CSRF koruması (form validation)
- Input sanitization

## 📈 Performans

- SQLite optimize edilmiş sorgular
- Composer autoloader optimizasyonu
- Docker multi-stage build (production ready)
- Static asset caching

## 🐛 Sorun Giderme

### Yaygın Sorunlar

1. **Port 8080 kullanımda hatası:**
```bash
docker-compose down
docker-compose up -d
```

2. **Veritabanı izin hatası:**
```bash
chmod 777 db/
```

3. **Composer bağımlılık hatası:**
```bash
docker-compose build --no-cache
```

## 🤝 Katkıda Bulunma

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Commit edin (`git commit -m 'Add amazing feature'`)
4. Push edin (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## 📝 Lisans

Bu proje MIT lisansı altında lisanslanmıştır.

## 📞 İletişim

Sorularınız için issue oluşturabilirsiniz.

---

**Geliştirici Notu**: Bu sistem eğitim amaçlı geliştirilmiştir ve production ortamında kullanılmadan önce ek güvenlik önlemleri alınmalıdır.
