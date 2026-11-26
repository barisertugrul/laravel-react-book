#!/usr/bin/env bash
# SUNUCUDA çalışır (deploy kullanıcısıyla). build.sh'nin ürettiği paketi yayına alır.
#
#   Kullanım:  deploy.sh <paket.tar.gz>     (yanında <paket.tar.gz>.sig olmalı; verify.php bu betiğin yanında)
#
#   /var/www/uygulama/
#     releases/20261124200007/   ← her dağıtım yeni bir klasör
#     shared/.env                ← dağıtımlar arasında kalıcı
#     shared/storage/            ← yüklenen dosyalar, günlükler, oturumlar
#     current -> releases/...    ← Nginx, Supervisor ve cron yalnızca bunu bilir
set -euo pipefail

PACKAGE="${1:?Paket yolu verin: deploy.sh <paket.tar.gz>}"
APP_DIR="${APP_DIR:-/var/www/uygulama}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.5-fpm}"

# Önce imza: paket açılmadan, içindeki hiçbir şey çalıştırılmadan. Açık anahtar sunucuda durur, paketten okunmaz.
php "$(dirname "$0")/verify.php" "$PACKAGE" "$APP_DIR/shared/deploy.pub" || { echo "Dağıtım durduruldu." >&2; exit 1; }

VERSION="$(tar -xzOf "$PACKAGE" ./VERSION)"
RELEASE="$APP_DIR/releases/$VERSION"
[ -e "$RELEASE" ] && { echo "Bu sürüm zaten var: $RELEASE" >&2; exit 1; }

echo "→ $VERSION açılıyor"
mkdir -p "$RELEASE"
tar -xzf "$PACKAGE" -C "$RELEASE"

echo "→ Kalıcı dosyalar bağlanıyor"
ln -s "$APP_DIR/shared/.env" "$RELEASE/.env"
ln -s "$APP_DIR/shared/storage" "$RELEASE/storage"

cd "$RELEASE"
# Buradan sonraki her şey YENİ klasörde; ziyaretçiler hâlâ eski sürümü görüyor
php artisan storage:link --no-interaction
php artisan migrate --force --no-interaction
php artisan optimize          # config, route, view ve event önbellekleri

echo "→ current yeni sürüme çevriliyor"
PREVIOUS="$(readlink "$APP_DIR/current" || true)"   # sağlık denetimi başarısız olursa geri dönülecek sürüm
# ln + mv -T: symlink tek bir sistem çağrısıyla (rename) değişir; arada "current yok" anı olmaz
ln -sfn "$RELEASE" "$APP_DIR/current.new"
mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"

# PHP-FPM'in opcache'i eski klasörün dosyalarını tutar; reload yeni yolu okutur
sudo /usr/bin/systemctl reload "$PHP_FPM_SERVICE"
# Uzun süre çalışan süreçler eski kodu bellekte tutar: kibarca bitirilir, Supervisor yeniden başlatır
php artisan queue:restart
php artisan pulse:restart

# Sağlık denetimi + ısınma: ilk isteği (boş opcache, soğuk bağlantı) ziyaretçi değil betik karşılar.
# /up Laravel'in hazır sağlık adresi; uygulama açılamıyorsa 500 döner.
APP_URL="$(grep -E '^APP_URL=' "$APP_DIR/shared/.env" | cut -d= -f2- | tr -d '"')"
if ! curl -fsS -o /dev/null --retry 3 --retry-delay 1 --retry-all-errors "$APP_URL/up"; then
    echo "✗ $APP_URL/up yanıt vermiyor: önceki sürüme dönülüyor" >&2
    if [ -n "$PREVIOUS" ]; then
        ln -sfn "$PREVIOUS" "$APP_DIR/current.new"
        mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"
        sudo /usr/bin/systemctl reload "$PHP_FPM_SERVICE"
        php "$APP_DIR/current/artisan" queue:restart
        php "$APP_DIR/current/artisan" pulse:restart
    fi
    rm -rf "$RELEASE"   # bozuk sürüm "son N sürüm" arasında yer kaplamasın
    # DİKKAT: kod geri döndü ama migrate edilen veritabanı değişiklikleri GERİ ALINMADI.
    # Bu yüzden migration'lar önceki sürümle de çalışacak biçimde yazılmalı (sütun ekle, hemen silme).
    exit 1
fi

echo "→ Eski sürümler temizleniyor (son $KEEP_RELEASES kalır)"
ls -1d "$APP_DIR"/releases/* | sort -r | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf

echo "✓ Yayında: $VERSION"
