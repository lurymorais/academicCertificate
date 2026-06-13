# OJS Plugin Gallery — Yayın Rehberi

**Eklenti:** Academic Certificate Manager for OJS (`academicCertificate` v1.8.0.0)  
**Geliştiren kurum:** [Holistence Publication](https://holistence.com/)  
**Geliştirenler:** Cumali Yaşar, Mehmet Sahin  
**İletişim:** contact@holistence.com  
**Web:** https://holistence.com/

Bu rehber, eklentiyi [PKP Plugin Gallery](https://github.com/pkp/plugin-gallery) üzerinden OJS yöneticilerinin **Eklentiler → Eklenti Galerisi** menüsünden kurabilmesi için gereken adımları özetler.

Resmi PKP dokümantasyonu: [Plugin Guide — Release](https://docs.pkp.sfu.ca/dev/plugin-guide/en/release#get-the-plugin-into-the-plugin-gallery)

---

## 1. Ön koşullar

- GitHub’da **public** bir depo (veya release dosyalarının herkese açık indirilebilir URL’leri)
- OJS **3.3**, **3.4** ve **3.5** için ayrı paketler (bu projede `release.sh` bunu üretir)
- Paket içinde `vendor/tecnickcom/tcpdf/` (TCPDF OJS ile gelmez)
- OJS 3.4/3.5 paketlerinde **`compat_autoloader.php` olmamalı** (Issue #68 — fatal error)
- OJS 3.3 paketinde `compat_autoloader.php` **olmalı**

---

## 2. Sürüm paketlerini oluşturma

Git Bash veya WSL’de proje kökünden:

```bash
./release.sh 1.8.0
```

Üretilen dosyalar:

| Dosya | Hedef OJS |
|-------|-----------|
| `academicCertificate-1.8.0-3_3.tar.gz` | OJS 3.3.x |
| `academicCertificate-1.8.0-3_4.tar.gz` | OJS 3.4.x |
| `academicCertificate-1.8.0-3_5.tar.gz` | OJS 3.5.x |

Her arşivin kök klasörü `academicCertificate/` olmalıdır (OJS `plugins/generic/academicCertificate/` altına açılır).

**Windows’ta** WSL veya Git Bash yoksa: `composer install --no-dev`, ardından `release.sh` içindeki kopyalama adımlarını elle uygulayın.

---

## 3. GitHub Release yükleme

1. GitHub’da **Releases → New release**
2. Etiketler (PKP galerisi için ayrı tag’ler):
   - `v1.8.0-3.3`
   - `v1.8.0-3.4`
   - `v1.8.0-3.5`
3. Her etikete ilgili `.tar.gz` dosyasını ekleyin
4. Release notlarında: Holistence Publication, yeni belge türleri (kabul, yazar, vb.), OJS sürüm uyumluluğu

---

## 4. MD5 özetleri

Her paket için:

```bash
md5sum academicCertificate-1.8.0-3_3.tar.gz
md5sum academicCertificate-1.8.0-3_4.tar.gz
md5sum academicCertificate-1.8.0-3_5.tar.gz
```

Windows PowerShell:

```powershell
Get-FileHash academicCertificate-1.8.0-3_3.tar.gz -Algorithm MD5
```

Bu değerler `plugins.xml` girdisinde zorunludur.

---

## 5. Plugin Gallery PR (plugins.xml)

1. [pkp/plugin-gallery](https://github.com/pkp/plugin-gallery) deposunu fork edin
2. `plugins.xml` dosyasını düzenleyin
3. Örnek şablon: `plugin-gallery-entry.example.xml` (bu depoda)
4. Pull Request açın — PKP ekibi inceleyip birleştirir

**Maintainer bloğu (Holistence):**

```xml
<maintainer>
    <name>Cumali Yaşar</name>
    <institution>Holistence Publication</institution>
    <email>contact@holistence.com</email>
</maintainer>
```

İkinci geliştirici adını açıklama (`<description>`) veya release notlarında belirtebilirsiniz; `maintainer` XML şemasında tek kişi alanı vardır.

**Homepage:** `https://holistence.com/` veya GitHub repo URL’niz

---

## 6. Manuel kurulum (galeri onayı öncesi test)

1. OJS → **Ayarlar → Web Sitesi → Eklentiler**
2. **Yükle ve etkinleştir** → `.tar.gz` seçin
3. Veya dosyaları `plugins/generic/academicCertificate/` altına kopyalayın
4. Eklentiyi etkinleştirin → şema otomatik güncellenir

---

## 7. Menüye “Belgelerim” ekleme (yönetici)

Otomatik menü ekleme kaldırıldı. Dergi yöneticisi istediği menüye ekler:

1. **Ayarlar → Web Sitesi → Navigasyon menüleri**
2. **Kullanıcı** veya **Birincil** menüyü düzenleyin
3. **Menü öğesi ekle** → tür: **Belgelerim / My Certificates** (`NMI_TYPE_ACM_MY_CERTIFICATES`)
4. Konumu sürükleyerek ayarlayın (ör. “Profil Görüntüle” altına)
5. Kaydedin

Sadece **giriş yapmış** kullanıcılara görünür.

---

## 8. Kontrol listesi (PR öncesi)

- [ ] `composer test` veya en azından `php -l` tüm PHP dosyalarında
- [ ] Üç OJS sürümünde paket smoke testi
- [ ] `version.xml` → `release` = `1.8.0.0`, `application` = `academicCertificate`
- [ ] Release URL’leri HTTP 200 döndürüyor
- [ ] MD5 değerleri `plugins.xml` ile eşleşiyor
- [ ] `compat_autoloader.php` yalnızca 3.3 paketinde
- [ ] README / CHANGELOG güncel
- [ ] Maintainer: Holistence Publication, contact@holistence.com

---

## 9. Mevcut upstream PR

Orijinal `ssemerikov/academicCertificate` için açılmış galeri PR’si: [pkp/plugin-gallery#473](https://github.com/pkp/plugin-gallery/pull/473)

Holistence sürümü için **yeni bir fork + yeni PR** veya mevcut PR’yi Holistence maintainer bilgisiyle güncellemek gerekir. Repo adı `academicCertificate` olarak kalmalı (OJS klasör adı değişmez).

---

## 10. İletişim

- **Holistence Publication:** https://holistence.com/
- **E-posta:** contact@holistence.com
- **Geliştirenler:** Cumali Yaşar, Mehmet Sahin
