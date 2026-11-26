# Yayına alma (deploy/)

Bölüm 10'da Kumanda ve Anahtarcı için kurduğumuz düzenin şablondaki hâli. Derleme bir kez yapılır
(CI ya da kendi bilgisayarınız), sunucuya yalnızca imzalı paket gider: sunucuda Node ve Composer gerekmez.

## 1. Adınızı verin

Dosyalarda yer tutucu ad **`uygulama`**. Kendi adınızla (küçük harf, tire yok; ör. `stok`) değiştirin:

```bash
cd deploy
grep -rl uygulama . | xargs sed -i 's/uygulama/stok/g; s/Uygulama/Stok/g; s/UYGULAMA/STOK/g'
for f in $(find . -name '*uygulama*'); do mv "$f" "${f//uygulama/stok}"; done
```

Sonra elle: `nginx/*.conf` içindeki `server_name`, `cpanel/kur.sh` başındaki `SITE_DIR` ve
`RELEASE_URL` (`KULLANICI/DEPO` → GitHub kullanıcı adınız/deponuz; değiştirilmezse kur.sh çalışmaz),
`targets/*.env` içindeki `VITE_APP_NAME`.

## 2. İmza anahtarı (bir kez)

```bash
php deploy/keygen.php     # gizli: ~/.deploy-keys/uygulama.key (depo DIŞI), açık: deploy/deploy.pub
```

Gizli anahtarın içeriğini GitHub'da **Settings → Secrets and variables → Actions** altında
`DEPLOY_SIGNING_KEY` adıyla kaydedin. `deploy/deploy.pub` depoya girer ve sunucuya kopyalanır.
Birden çok proje aynı anahtarı kullanacaksa `DEPLOY_KEY_NAME=ortak php deploy/keygen.php`.

## 3. Paket

```bash
bash deploy/build.sh wsl          # build/uygulama-wsl-<sürüm>.tar.gz + .sig
```

`targets/<hedef>.env` yalnızca derleme anında JS'e gömülen, gizli OLMAYAN `VITE_*` değerlerini taşır.

## 4a. Kendi sunucunuz (VPS: Nginx + PHP-FPM + MySQL + Supervisor)

| Dosya | Sunucudaki yeri |
|---|---|
| `nginx/uygulama.conf` | `/etc/nginx/sites-available/uygulama` (+ `sites-enabled` bağı) |
| `php-fpm/uygulama.conf` | `/etc/php/8.5/fpm/pool.d/uygulama.conf` |
| `supervisor/uygulama.conf` | `/etc/supervisor/conf.d/uygulama.conf` (kuyruk + `pulse:check`) |
| `cron/uygulama` | `/etc/cron.d/uygulama` (zamanlayıcı: yedekler) |
| `deploy.sh`, `verify.php` | `/home/deploy/` |
| `deploy.pub` | `/var/www/uygulama/shared/deploy.pub` |

`/var/www/uygulama/shared/.env` dosyasını sunucuda siz oluşturursunuz (`APP_ENV=production`,
`APP_DEBUG=false`, MySQL, `ALERT_EMAIL`, `PULSE_ADMINS`, `BACKUP_*`). deploy kullanıcısına yalnızca
`systemctl reload php8.5-fpm` için sudo izni verilir. Dağıtım:

```bash
scp build/uygulama-wsl-*.tar.gz* deploy@sunucu:
ssh deploy@sunucu bash ./deploy.sh uygulama-wsl-<sürüm>.tar.gz
```

`deploy.sh`: imza → `releases/<sürüm>` → `migrate` + `optimize` → `current` atomik çevrilir →
`/up` denetimi; başarısızsa önceki sürüme döner (veritabanı geri alınmaz: migration'lar geriye uyumlu olmalı).

## 4b. Paylaşımlı hosting (cPanel, SSH yok)

Sunucu paketi kendisi çeker: CI paketi GitHub Releases'a koyar (`uygulama-cpanel`), `cpanel/kur.sh`
cron'la 2 dakikada bir yeni sürüm var mı diye bakar. Sunucuya FTP/Dosya Yöneticisi ile:

```
~/apps/uygulama/bin/kur.sh  bin/verify.php  bin/deploy.pub  bin/php.d/uygulama-cli.ini
~/apps/uygulama/shared/.env         (cpanel/env.ornek'ten; izin 600)
```

Cron'lar (PHP yolunu hostinginize göre düzeltin; `cpanel/kesif.sh` bulur):

```
*/2 * * * * bash ~/apps/uygulama/bin/kur.sh >> ~/apps/uygulama/log/kur.log 2>&1
* * * * * cd ~/apps/uygulama/current && PHP_INI_SCAN_DIR=/opt/cpanel/ea-php85/root/etc/php.d:$HOME/apps/uygulama/bin/php.d /opt/cpanel/ea-php85/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/apps/uygulama/current && PHP_INI_SCAN_DIR=/opt/cpanel/ea-php85/root/etc/php.d:$HOME/apps/uygulama/bin/php.d /opt/cpanel/ea-php85/root/usr/bin/php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
```

`PHP_INI_SCAN_DIR`: hosting CLI'da `proc_open` gibi fonksiyonları kapatır; zamanlayıcı ve yedekleme
(mysqldump) bunlara muhtaçtır. Ek ini yalnızca bu cron süreçlerini etkiler, web'i değil.

İlk yönetici hesabı: `cpanel/komut.sh.ornek`'i düzenleyip `bin/komut.sh` olarak yükleyin; kur.sh bir
kez çalıştırır, çıktıyı `log/kur.log`'a yazar ve dosyayı siler. Belge kökü (`public_html/<alt-alan>`)
ilk kurulumda gerçek bir klasöre + köprü `index.php`'ye çevrilir (symlink'li belge kökü LiteSpeed'de
eski sürümü dakikalarca sundu). Sunucuda PHP'nin `sodium` uzantısı olmalı (imza doğrulaması).

Reverb ve `pulse:check` paylaşımlı hostingte çalışmaz: `BROADCAST_CONNECTION=log`, Pulse'un sunucu kartı boş kalır.
