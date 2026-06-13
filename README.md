# OJS Akademik Belge Yöneticisi  
# Academic Certificate Manager for OJS

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![OJS](https://img.shields.io/badge/OJS-3.3%20%7C%203.4%20%7C%203.5-green.svg)](https://pkp.sfu.ca/ojs/)
[![Version](https://img.shields.io/badge/version-1.8.0.0-orange.svg)](CHANGELOG.md)

**Sürüm:** 1.8.0.0 · **Klasör:** `academicCertificate` · **Depo:** [github.com/cyasar/academicCertificate](https://github.com/cyasar/academicCertificate)

---

## İçindekiler

1. [Genel bakış](#genel-bakış)
2. [Kimlik bilgileri](#kimlik-bilgileri)
3. [Özellikler](#özellikler)
4. [Sistem gereksinimleri](#sistem-gereksinimleri)
5. [Kurulum](#kurulum)
6. [İlk yapılandırma](#ilk-yapılandırma)
7. [Belge türleri ve şablonlar](#belge-türleri-ve-şablonlar)
8. [Belgelerim menüsü](#belgelerim-menusu)
9. [Kullanım kılavuzu](#kullanım-kılavuzu)
10. [Doğrulama ve API](#doğrulama-ve-api)
11. [Geliştirme ve test](#geliştirme-ve-test)
12. [Sorun giderme](#sorun-giderme)
13. [Lisans ve atıf](#lisans-ve-atıf)

---

## Genel bakış

**OJS Akademik Belge Yöneticisi** (*Academic Certificate Manager for OJS*), [Open Journal Systems](https://pkp.sfu.ca/ojs/) (OJS) için geliştirilmiş bir **generic eklentidir**. Derginizde hakemlik, makale kabul, yazar yayın ve editörlük süreçlerine ilişkin **kişiselleştirilmiş PDF belgeler** üretir, kullanıcıların **Belgelerim** sayfasından indirmesini sağlar ve her belgeye **herkese açık doğrulama kodu** atar.

Eklenti, Serhiy O. Semerikov’un açık kaynak [Reviewer Certificate](https://github.com/ssemerikov/academicCertificate) projesinden türetilmiş; [Holistence Publication](https://holistence.com/) tarafından çok türlü akademik belge yöneticisi olarak genişletilmiştir.

### Kısa özet (English)

Open Journal Systems plugin that generates PDF certificates for **reviewers**, **authors** (acceptance & publication), and **editors**. Includes a visual A4 landscape template designer, **My Certificates / Belgelerim** page, batch issuance, QR verification, and 32-language UI.

---

## Kimlik bilgileri

| Alan | Değer |
|------|--------|
| **Görünen ad (TR)** | OJS Akademik Belge Yöneticisi |
| **Görünen ad (EN)** | Academic Certificate Manager for OJS |
| **Yayıncı** | [Holistence Publication](https://holistence.com/) |
| **Geliştiriciler** | Cumali Yaşar, Mehmet Sahin |
| **İletişim** | contact@holistence.com |
| **GitHub** | https://github.com/cyasar/academicCertificate |
| **OJS product** | `academicCertificate` |
| **PHP sınıfı** | `AcademicCertificatePlugin` |
| **Namespace** | `APP\plugins\generic\academicCertificate` |
| **Eklenti anahtarı (DB)** | `academiccertificateplugin` |
| **Lisans** | GNU GPL v3.0 |

---

## Özellikler

### Belge türleri

| Tür | Kimler için | Ne zaman |
|-----|-------------|----------|
| **Hakemlik belgesi** | Hakemler | Tamamlanmış hakemlik sonrası |
| **Makale kabul belgesi** | Yazarlar | Editöryal kabul kararı sonrası |
| **Yazar sertifikası** | Yazarlar | Makale yayımlandıktan sonra |
| **Editörlük belgesi** | Editörler | Editöryal katkı kayıtları için |

Her tür için ayrı **başlık / gövde / alt bilgi** şablonu, isteğe bağlı **arka plan görseli** ve **sürükle-bırak düzen** tanımlanabilir.

### Görsel şablon tasarımcısı (v1.8+)

- **Yatay A4** ön izleme (297 × 210 mm)
- Önerilen arka plan: **3508 × 2480 px** (300 DPI)
- Başlık, gövde, alt bilgi ve doğrulama kodu alanlarını **sürükleyerek** konumlandırma
- Yazı tipi, boyut ve renk ön izlemesi
- Yalnızca **dergi yöneticileri** ayarlar ekranına erişir

### Belgelerim / My Certificates

- Rol bazlı belge listesi (hakem, yazar, editör)
- Tür filtresi ve PDF indirme
- Navigasyon menüsüne eklenebilir özel menü öğesi (**Belgelerim**)

### Şablon ve PDF

- Arka plan görseli (JPG/PNG, tür başına veya varsayılan)
- Dinamik değişkenler: `{{$reviewerName}}`, `{{$articleTitle}}`, `{{$journalName}}`, `{{$certificateCode}}` vb.
- **Unicode** desteği: Kiril, Arapça, CJK metinlerde otomatik DejaVu Sans
- İsteğe bağlı **QR kod** ile doğrulama
- Belge numaralandırma: `ACM-REV-2026-000001` formatı

### Yönetim araçları

- Toplu hakem belgesi üretimi
- Manuel kabul belgesi düzenleme (gönderi ID ile)
- İstatistikler: toplam belge, indirme, benzersiz hakem sayısı
- Herkese açık doğrulama sayfası

### Çok dillilik

- **32 dil** (TR, EN, UK, RU, AR, ZH, JA, KO ve diğerleri)
- OJS arayüz diline göre otomatik çeviri
- `.xml` ve `.po` locale dosyaları (OJS 3.3–3.5 uyumu)

### Güvenlik

- Kullanıcı yalnızca kendi belgelerini indirebilir
- Çok dergili kurulumlarda `context_id` izolasyonu
- CSRF koruması, dosya yükleme doğrulaması
- Arka plan dosyaları yalnızca `files/journals/` altında servis edilir

---

## Sistem gereksinimleri

| Bileşen | Gereksinim |
|---------|------------|
| **OJS** | 3.3.x, 3.4.x veya 3.5.x |
| **PHP** | ≥ 7.3 (OJS 3.5 için ≥ 8.0 önerilir) |
| **Uzantılar** | `mbstring`, `zip`, `gd` veya `imagick` |
| **TCPDF** | Eklenti paketinde **dahil** (`vendor/tecnickcom/tcpdf/`) |
| **Bellek** | En az 128 MB (`memory_limit`; büyük arka planlar için 256 MB) |
| **Veritabanı** | MySQL/MariaDB, UTF-8 (`utf8mb4` önerilir) |

### OJS sürüm uyumluluğu

| OJS | PHP | Durum |
|-----|-----|--------|
| 3.3.x | 7.3 – 8.2 | Tam destek (`compat_autoloader.php` gerekir) |
| 3.4.x | 7.4 – 8.2 | Tam destek |
| 3.5.x | 8.0 – 8.2 | Tam destek |

---

## Kurulum

Kurulumdan önce **veritabanı ve `files/` yedeği** alın.

### Yöntem 1 — OJS yönetici panelinden paket yükleme (önerilen)

Bu yöntem sunucuda `composer` veya `git` bilgisi gerektirmez.

1. [GitHub Releases](https://github.com/cyasar/academicCertificate/releases) sayfasından OJS sürümünüze uygun paketi indirin:

   | OJS sürümünüz | İndirilecek dosya |
   |---------------|-------------------|
   | 3.3.x | `academicCertificate-1.8.0-3_3.tar.gz` |
   | 3.4.x | `academicCertificate-1.8.0-3_4.tar.gz` |
   | 3.5.x | `academicCertificate-1.8.0-3_5.tar.gz` |

   > Release henüz yoksa aşağıdaki [Yöntem 3](#yöntem-3--gitten-manuel-kurulum-geliştirici) ile kurabilir veya `release.sh` ile paket üretebilirsiniz.

2. OJS’e **dergi yöneticisi** veya **site yöneticisi** olarak giriş yapın.

3. **Ayarlar → Web Sitesi Ayarları → Eklentiler** sayfasına gidin.

4. **Yeni Eklenti Yükle** (*Upload a New Plugin*) düğmesine tıklayın.

5. İndirdiğiniz `.tar.gz` dosyasını seçin ve yükleyin.

6. Yükleme tamamlandığında listede **OJS Akademik Belge Yöneticisi** görünür.

7. **Etkinleştir** (*Enable*) düğmesine tıklayın. Veritabanı tabloları otomatik oluşturulur.

8. **Ayarlar** (*Settings*) ile şablonları yapılandırın.

9. Tarayıcı önbelleğini temizleyin (**Ctrl+F5**).

---

### Yöntem 2 — Dosyaları doğrudan kopyalama (FTP / XAMPP / VPS)

Sunucuya FTP, SFTP veya dosya yöneticisi ile erişiminiz varsa:

1. Bu depoyu bilgisayarınıza indirin veya klonlayın:

   ```bash
   git clone https://github.com/cyasar/academicCertificate.git
   ```

2. OJS kurulumunuzun generic eklentiler klasörüne kopyalayın:

   ```text
   /path/to/ojs/plugins/generic/academicCertificate/
   ```

   **Windows (XAMPP) örneği:**

   ```text
   C:\xampp\htdocs\ojs\plugins\generic\academicCertificate\
   ```

3. TCPDF bağımlılığını yükleyin (paket dışı kurulumda zorunlu):

   ```bash
   cd /path/to/ojs/plugins/generic/academicCertificate
   composer install --no-dev
   ```

   Windows PowerShell (XAMPP PHP):

   ```powershell
   cd C:\xampp\htdocs\ojs\plugins\generic\academicCertificate
   C:\xampp\php\php.exe C:\path\to\composer.phar install --no-dev
   ```

4. Klasör izinlerini ayarlayın (Linux):

   ```bash
   chown -R www-data:www-data plugins/generic/academicCertificate
   chmod -R 755 plugins/generic/academicCertificate
   ```

5. OJS yönetici panelinde **Eklentiler** listesini yenileyin.

6. **OJS Akademik Belge Yöneticisi** → **Etkinleştir**.

7. OJS önbelleğini temizleyin: `cache/` içindeki `fc-*` dosyalarını silin veya OJS yönetim araçlarını kullanın.

---

### Yöntem 3 — Git’ten manuel kurulum (geliştirici)

Geliştirme ortamı veya sürekli güncelleme için:

```bash
cd /var/www/ojs/plugins/generic
git clone https://github.com/cyasar/academicCertificate.git academicCertificate
cd academicCertificate
composer install
```

Geliştirme makinesinden canlı OJS’e senkron (Windows örneği, bu depoda `temp/sync_to_ojs.ps1`):

```powershell
powershell -ExecutionPolicy Bypass -File temp\sync_to_ojs.ps1
```

---

### Yöntem 4 — `reviewerCertificate` eklentisinden geçiş

Eski **Reviewer Certificate** (`reviewerCertificate`) kuruluysa:

1. Yeni `academicCertificate` klasörünü `plugins/generic/` altına kurun (Yöntem 1 veya 2).
2. Eski eklentiyi **devre dışı bırakın**; mümkünse `reviewerCertificate` klasörünü kaldırın veya yeniden adlandırın.
3. Veritabanında `plugin_settings` ve `versions` tablolarında yalnızca `academicCertificate` / `academiccertificateplugin` kayıtlarının etkin olduğundan emin olun.
4. Mevcut belge kayıtları `reviewer_certificates` tablosunda kalır; **veri kaybı olmaz**.
5. OJS önbelleğini temizleyin.

> Ayrıntılı geçiş betikleri geliştirme ortamında `temp/` altında bulunabilir; canlı sunucuda çalıştırmadan önce yedek alın.

---

### Kurulum sonrası kontrol listesi

- [ ] Eklenti listede **etkin** görünüyor
- [ ] **Ayarlar** modalı hatasız açılıyor
- [ ] `http://DERGI-URL/certificate/verify/PREVIEW` veya ayarlardaki **Önizleme** çalışıyor
- [ ] **Belgelerim** menü öğesi eklendi (aşağıya bakın)
- [ ] Test kullanıcısı ile belge indirilebiliyor

---

## İlk yapılandırma

1. **Ayarlar → Web Sitesi Ayarları → Eklentiler → OJS Akademik Belge Yöneticisi → Ayarlar**

2. **Varsayılan arka plan** yükleyin (tüm türler için yedek görsel).

3. Sekmelerden belge türünü seçin:
   - Hakemlik belgesi
   - Makale kabul belgesi
   - Yazar sertifikası
   - Editörlük belgesi

4. Her sekmede:
   - Tür özel arka plan (isteğe bağlı)
   - Başlık, gövde, alt bilgi metinleri
   - Ön izlemede metin bloklarını **sürükleyerek** konumlandırma
   - **Kaydet**

5. **Görünüm ayarları:**
   - Yazı tipi (Latin dışı diller için DejaVu Sans önerilir)
   - Yazı boyutu, metin rengi
   - Sayfa yönü: **Yatay (Landscape)** — varsayılan A4

6. **Belge türleri:** Hangi belgelerin üretileceğini işaretleyin.

7. **Uygunluk:** Minimum hakemlik sayısı, belge numarası öneki (`ACM`), QR kod.

8. **Önizleme** bağlantısı ile PDF’i kontrol edin.

---

## Belge türleri ve şablonlar

### Hakemlik — örnek değişkenler

`{{$reviewerName}}`, `{{$submissionTitle}}`, `{{$journalName}}`, `{{$reviewDate}}`, `{{$certificateCode}}`, `{{$certificateNumber}}`

### Makale kabul — örnek değişkenler

`{{$articleTitle}}`, `{{$authors}}`, `{{$acceptanceDate}}`, `{{$journalName}}`, `{{$certificateCode}}`

### Yazar yayın — örnek değişkenler

`{{$articleTitle}}`, `{{$authors}}`, `{{$publicationDate}}`, `{{$publicationYear}}`, `{{$journalName}}`

### Örnek gövde metni (hakemlik)

```text
Bu belge, {{$reviewerName}} adlı hakemin
{{$journalName}} dergisi için
"{{$submissionTitle}}" başlıklı çalışmayı
{{$reviewDate}} tarihinde değerlendirdiğini onaylar.
```

---

## Belgelerim menüsü

Eklenti menü öğesini otomatik eklemez; yönetici istediği menüye ekler:

1. **Ayarlar → Web Sitesi Ayarları → Navigasyon menüleri**
2. **Kullanıcı** veya **Birincil** menüyü düzenleyin
3. **Menü öğesi ekle** → tür: **Belgelerim / My Certificates**
4. Sırayı ayarlayın (ör. Profil altına)
5. Kaydedin

Sayfa adresi: `https://DERGI-URL/index.php/DERGİ-YOLU/certificate/myCertificates`

---

## Kullanım kılavuzu

### Hakemler ve yazarlar

1. İlgili iş tamamlandığında (hakemlik, kabul, yayın) belge uygun hale gelir.
2. **Belgelerim** sayfasından veya gösterge panelindeki bağlantıdan PDF indirin.
3. Belgedeki kod veya QR ile doğrulama yapılabilir.

### Dergi yöneticileri

- **Ayarlar:** Şablon, arka plan, düzen, uygunluk kuralları
- **Toplu üretim:** Ayarlar sayfası altında uygun hakemler için toplu belge
- **Kabul belgesi:** Gönderi ID girerek manuel kabul belgesi düzenleme
- **İstatistikler:** Üretilen ve indirilen belge sayıları

---

## Doğrulama ve API

### Herkese açık doğrulama

```text
https://DERGI-URL/index.php/DERGİ-YOLU/certificate/verify/BELGE-KODU
```

### Önemli uç noktalar

| Uç nokta | Açıklama | Erişim |
|----------|----------|--------|
| `/certificate/download/{reviewId}` | Hakem belgesi indir | İlgili hakem |
| `/certificate/myCertificates` | Belgelerim listesi | Giriş yapmış kullanıcı |
| `/certificate/verify/{code}` | Doğrulama | Herkese açık |
| Eklenti `manage` → `preview` | Şablon önizleme | Yönetici |
| Eklenti `manage` → `generateBatch` | Toplu üretim | Yönetici |

---

## Geliştirme ve test

```bash
composer install
composer test              # Birim + entegrasyon
composer test:all          # Tüm PHP test paketleri
composer test:compatibility  # OJS 3.3 / 3.4 / 3.5
```

Sürüm paketi oluşturma (Git Bash / WSL):

```bash
./release.sh 1.8.0
```

E2E testler için Docker ile OJS konteynerleri gerekir; ayrıntılar `TESTING.md` ve `CLAUDE.md` dosyalarında.

### Veritabanı tabloları

| Tablo | Açıklama |
|-------|----------|
| `reviewer_certificates` | Düzenlenen belge kayıtları |
| `reviewer_certificate_templates` | Şablon yapılandırmaları |
| `reviewer_certificate_settings` | Yerelleştirilmiş ayarlar |

> Tablo adları geriye dönük uyumluluk için `reviewer_*` önekini korur.

---

## Sorun giderme

| Sorun | Olası çözüm |
|-------|-------------|
| Eklenti listede görünmüyor | Klasör adı `academicCertificate` olmalı; `version.xml` ve `AcademicCertificatePlugin.php` mevcut olmalı |
| Ayarlar açılmıyor | OJS `cache/` temizleyin; yönetici rolüyle giriş yapın |
| PDF’te `??????` karakterler | DejaVu Sans seçin veya Unicode otomatik geçişi bekleyin; DB `utf8mb4` olsun |
| Arka plan görünmüyor | JPG/PNG, max 5 MB; `files/journals/{contextId}/academicCertificate/` yazılabilir olmalı |
| Belgelerim boş | Menü öğesi eklendi mi? Kullanıcının tamamlanmış işi var mı? Eklenti etkin mi? |
| Sınıf bulunamadı hatası | `composer install` çalıştırın; OJS 3.3’te `compat_autoloader.php` pakette olmalı |

Hata bildirimi: [GitHub Issues](https://github.com/cyasar/academicCertificate/issues) — OJS sürümü, PHP sürümü, hata mesajı ve adımları ekleyin.

---

## Lisans ve atıf

Bu proje **GNU General Public License v3.0** ile lisanslanmıştır.

- **Holistence Publication** — Cumali Yaşar, Mehmet Sahin — https://holistence.com/
- **Orijinal proje:** Serhiy O. Semerikov — [Reviewer Certificate Plugin](https://github.com/ssemerikov/academicCertificate)

### Teşekkürler

Dr. Olha Pinchuk, Dr. Uğur Koçak, Pedro Felipe Rocha, Dr. Pavlo Nechypurenko ve PKP topluluğuna katkıları için teşekkürler.

---

## Ek kaynaklar

- [OJS Dokümantasyonu](https://docs.pkp.sfu.ca/learning-ojs/)
- [PKP Forum](https://forum.pkp.sfu.ca/)
- [Değişiklik günlüğü](CHANGELOG.md)
- [Plugin Gallery rehberi](PLUGIN_GALLERY.md) (PKP galerisi için)

---

**Holistence Publication** · contact@holistence.com · [holistence.com](https://holistence.com/)
