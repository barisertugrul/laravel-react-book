# Laravel + React ile Gerçek Projeler — Kitap Deposu

Kitaptaki her bölümün çalışan projesi bu depodadır.

| Bölüm | Proje | Klasör | Etiket |
|---|---|---|---|
| 1 | QR Menü | `projects/01-qr-menu` | `bolum-01` |
| 2 | Dijital Kartvizit SaaS | `projects/02-kartvizit` | `bolum-02` |
| 3 | Randevu Sistemi | `projects/03-randevu` | `bolum-03` |
| 4 | Servis Takip | `projects/04-servis-takip` | `bolum-04` |
| 5 | Ön Muhasebe ve Fatura | `projects/05-on-muhasebe` | `bolum-05` |
| 6 | E-Ticaret | `projects/06-e-ticaret` | `bolum-06` |
| 7 | Satın Alma Sonrası Lisans | `projects/07-lisans` | `bolum-07` |
| 8 | WP Ajans Paneli I (Kumanda) | `projects/08-ajans-paneli` | `bolum-08` |
| 9 | WP Ajans Paneli II (toplu işlem, lisans, abonelik) | `projects/09-ajans-paneli-2` | `bolum-09` |

## Bir projeyi çalıştırmak

```bash
cd projects/01-qr-menu
composer install
npm install
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
composer run dev
```

Tarayıcıda `http://localhost:8000` adresini açın. Giriş: `test@example.com` / `password` (Bölüm 2'de ayrıca `pro@example.com`).

Bölüm 7'de `migrate --seed` komutundan önce lisans imza anahtarını üretin: `php artisan license:keygen`.

Bölüm 8'deki WordPress eklentisi `projects/08-ajans-paneli/wp-plugin/ajans-asistani` klasöründe. Eklenti kurulu üç demo WordPress sitesi (WordPress Playground; yalnızca Node.js gerekir): `cd projects/08-ajans-paneli && npm install && npm run wp-demo`.

Bölüm 9'da demo siteler `http://kafe.localhost:9401`, `klinik.localhost:9402` ve `magaza.localhost:9403` adreslerinde açılır (`cd projects/09-ajans-paneli-2 && npm run wp-demo`). Lisansı denemek için Bölüm 7'nin Anahtarcı'sı da çalışmalı: `cd projects/07-lisans && npm run build && php artisan serve --host=127.0.0.1 --port=8001`.

Belirli bir bölümün bittiği hali görmek için: `git checkout bolum-01`

## Klasörler
- `projects/` — bölüm projeleri
- `packages/` — bölümlerle büyüyen ortak başlangıç kiti (Bölüm 2'den itibaren)
