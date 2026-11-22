# Laravel + React ile Gerçek Projeler — Kitap Deposu

Kitaptaki her bölümün çalışan projesi bu depodadır.

| Bölüm | Proje | Klasör | Etiket |
|---|---|---|---|
| 1 | QR Menü | `projects/01-qr-menu` | `bolum-01` |

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

Tarayıcıda `http://localhost:8000` adresini açın. Giriş: `test@example.com` / `password`.

Belirli bir bölümün bittiği hali görmek için: `git checkout bolum-01`

## Klasörler
- `projects/` — bölüm projeleri
- `packages/` — bölümlerle büyüyen ortak başlangıç kiti (Bölüm 2'den itibaren)
- `kitap/` — kitap kaynağı (Markdown) ve görseller
- `tools/screenshots/` — kitaptaki ekran görüntülerini yeniden üreten betikler
