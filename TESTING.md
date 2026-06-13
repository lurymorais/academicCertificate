# Test Plan — v1.8.0 (OJS 3.3 / 3.4 / 3.5)

**Plugin:** Academic Certificate Manager for OJS (`academicCertificate` v1.8.0.0)  
**Publisher:** Holistence Publication — https://holistence.com/

> **Supported OJS versions:** 3.3.x, 3.4.x, 3.5.x (declared in `version.xml`).  
> OJS 3.0–3.2 use a different plugin architecture and are **not** supported by this codebase.

---

## 1. Automated tests (before release)

From project root:

```bash
composer install
composer test:all
```

Per OJS version:

```bash
set OJS_VERSION=3.3 && vendor\bin\phpunit --testsuite "Compatibility Tests"
set OJS_VERSION=3.4 && vendor\bin\phpunit --testsuite "Compatibility Tests"
set OJS_VERSION=3.5 && vendor\bin\phpunit --testsuite "Compatibility Tests"
```

E2E (requires Docker OJS containers — see `ojs-test/docker-compose.yml`):

```bash
docker compose -f ojs-test/docker-compose.yml up -d
npx playwright test --project=ojs33 --project=ojs34 --project=ojs35
```

---

## 2. Manual smoke test matrix

Run on **each** OJS instance (3.3, 3.4, 3.5):

| # | Test | Pass |
|---|------|------|
| 1 | Upload/enable plugin — no 500 on journal homepage | ☐ |
| 2 | Settings open — certificate templates, types, backgrounds | ☐ |
| 3 | Add **Belgelerim** via Settings → Navigation Menus → User menu | ☐ |
| 4 | Login as reviewer — download reviewer certificate | ☐ |
| 5 | Login as author — **Belgelerim** shows acceptance certificate | ☐ |
| 6 | Published article — author certificate in list + PDF download | ☐ |
| 7 | `/certificate/myCertificates?type=acceptance` filter works | ☐ |
| 8 | `/certificate/myCertificates?type=author` filter works | ☐ |
| 9 | Public verify URL — `/certificate/verify/{code}` | ☐ |
| 10 | Turkish UI — displayName *OJS Akademik Belge Yöneticisi*, Belgelerim | ☐ |
| 11 | Disable plugin — site still loads | ☐ |

---

## 3. Package build verification

```bash
./release.sh 1.8.0
```

| Package | Check |
|---------|--------|
| `academicCertificate-1.8.0-3_3.tar.gz` | Contains `compat_autoloader.php` + TCPDF |
| `academicCertificate-1.8.0-3_4.tar.gz` | **No** `compat_autoloader.php`; has TCPDF |
| `academicCertificate-1.8.0-3_5.tar.gz` | **No** `compat_autoloader.php`; has TCPDF |

Install each `.tar.gz` on matching OJS via **Settings → Plugins → Upload**.

---

## 4. Regression focus (v1.8.0)

- [ ] OJS 3.3: `require_once` for service classes; no namespace autoload errors
- [ ] OJS 3.3: `Role::ROLE_*` fallbacks in handler authorization
- [ ] OJS 3.5: no `ReviewAssignmentDAO` usage without SQL fallback
- [ ] Cyrillic PDF — DejaVu font auto-switch
- [ ] Locale: no `##key##` on Belgelerim page (tr_TR, en_US)

---

## 5. Release checklist

- [ ] `version.xml` → `1.8.0.0`
- [ ] `CHANGELOG.md` updated
- [ ] GitHub Releases: `v1.8.0-3.3`, `v1.8.0-3.4`, `v1.8.0-3.5`
- [ ] MD5 hashes for Plugin Gallery PR
- [ ] `plugins.xml` PR to [pkp/plugin-gallery](https://github.com/pkp/plugin-gallery)
