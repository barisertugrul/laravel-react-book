#!/bin/bash
# PAYLAŞIMLI HOSTİNG (cPanel) dağıtımı: SSH yok, FTP'de TLS yok → sunucu paketi KENDİSİ çeker.
# CI imzalı paketi GitHub Releases'a koyar; bu betik cron'la birkaç dakikada bir çalışır:
#   yeni sürüm var mı? → indir → imzayı doğrula → kur → symlink'i çevir → yeni sürüm yanıt veriyor mu? → değilse geri dön
#
#   cron:  */2 * * * *  bash ~/apps/kumanda/bin/kur.sh >> ~/apps/kumanda/log/kur.log 2>&1
#
#   ~/apps/kumanda/
#     bin/kur.sh  bin/verify.php  bin/deploy.pub   ← yalnızca yönetici koyar (paketten gelmez)
#     releases/<sürüm>/   shared/.env   shared/storage/   current -> releases/<sürüm>
#   ~/public_html/<alt-alan-adı>  -> ~/apps/kumanda/current/public   (ilk kurulumda symlink'e çevrilir)
set -euo pipefail

# --- ayarlar ---------------------------------------------------------------
PHP="${PHP:-/opt/cpanel/ea-php85/root/usr/bin/php}"   # DİKKAT: düz "php" bu sunucuda PHP 7.4
APP_DIR="${APP_DIR:-$HOME/apps/kumanda}"
SITE_DIR="${SITE_DIR:-$HOME/public_html/kumanda.demoistasyonu.com}"
RELEASE_URL="${RELEASE_URL:-https://github.com/barisertugrul/laravel-react-book/releases/download/kumanda-cpanel}"
KEEP_RELEASES="${KEEP_RELEASES:-3}"   # paylaşımlı hostingte disk kotası: daha az sürüm tutulur
WAIT_SECONDS="${WAIT_SECONDS:-180}"   # realpath önbelleği (realpath_cache_ttl=120) eski sürümü bu kadar tutabilir

BIN="$APP_DIR/bin"
# İlk çalıştırmada kalıcı klasörler (yüklenen dosyalar, oturumlar, önbellek, günlükler)
mkdir -p "$APP_DIR"/{releases,log} "$APP_DIR"/shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
log() { echo "[$(date '+%F %T')] $*"; }

# Aynı anda iki kurulum çalışmasın (önceki cron hâlâ sürüyorsa bu çalıştırma sessizce çıkar)
exec 9>"$APP_DIR/kur.lock"
flock -n 9 || exit 0

# --- 1. yeni sürüm var mı? ---------------------------------------------------
# releases/download adresleri GitHub API'sinin hız sınırına girmez (paylaşımlı IP'de önemli)
LATEST="$(curl -fsSL --max-time 20 "$RELEASE_URL/surum.txt" | tr -dc '0-9')" || { log "✗ surum.txt alınamadı"; exit 1; }
CURRENT="$(cat "$APP_DIR/current/VERSION" 2>/dev/null || echo yok)"
[ -n "$LATEST" ] && [ "$LATEST" != "$CURRENT" ] || exit 0
# Bir kez başarısız olan sürüm her 2 dakikada yeniden denenmez (yenisi gelene kadar)
grep -qx "$LATEST" "$APP_DIR/basarisiz.txt" 2>/dev/null && exit 0

log "→ Yeni sürüm: $LATEST (yayında: $CURRENT)"
INBOX="$APP_DIR/gelen"; mkdir -p "$INBOX"
PACKAGE="$INBOX/kumanda-$LATEST.tar.gz"
curl -fsSL --max-time 300 -o "$PACKAGE" "$RELEASE_URL/kumanda-cpanel.tar.gz"
curl -fsSL --max-time 20 -o "$PACKAGE.sig" "$RELEASE_URL/kumanda-cpanel.tar.gz.sig"

# --- 2. imza: paket açılmadan, içindeki hiçbir şey çalıştırılmadan ----------
if ! "$PHP" "$BIN/verify.php" "$PACKAGE" "$BIN/deploy.pub"; then
    log "✗ İmza doğrulanamadı: dağıtım durduruldu"
    echo "$LATEST" >> "$APP_DIR/basarisiz.txt"; rm -f "$PACKAGE" "$PACKAGE.sig"; exit 1
fi
VERSION="$(tar -xzOf "$PACKAGE" ./VERSION)"
[ "$VERSION" = "$LATEST" ] || { log "✗ Paket sürümü ($VERSION) surum.txt ile uyuşmuyor"; exit 1; }

# --- 3. aç ve hazırla (ziyaretçiler hâlâ eski sürümde) -----------------------
RELEASE="$APP_DIR/releases/$VERSION"
rm -rf "$RELEASE"; mkdir -p "$RELEASE"
tar -xzf "$PACKAGE" -C "$RELEASE"
ln -s "$APP_DIR/shared/.env" "$RELEASE/.env"
ln -s "$APP_DIR/shared/storage" "$RELEASE/storage"
# storage:link burada çalışmaz: CLI'da PHP'nin symlink() fonksiyonu kapalı (disable_functions). Kabuk ln'i kullanılır.
ln -s "$APP_DIR/shared/storage/app/public" "$RELEASE/public/storage"

# Buradan sonra bir komut hata verirse (ör. migrate): sürüm işaretlenir, her 2 dakikada yeniden indirilmez
trap 'log "✗ $VERSION hazırlanırken hata (satır $LINENO): kurulmadı"; echo "$VERSION" >> "$APP_DIR/basarisiz.txt"; rm -rf "$RELEASE"' ERR

cd "$RELEASE"
"$PHP" artisan migrate --force --no-interaction
"$PHP" artisan optimize

trap - ERR   # current çevrildikten sonra hata yönetimini sağlık denetimi üstlenir

# --- 4. current'ı çevir (atomik) --------------------------------------------
log "→ current yeni sürüme çevriliyor"
PREVIOUS="$(readlink "$APP_DIR/current" || true)"
ln -sfn "$RELEASE" "$APP_DIR/current.new"
mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"
# Kuyruk işçisi cron'la kısa ömürlü çalışır (bkz. cron); yine de çalışan varsa kod değişti sinyali verilir
"$PHP" artisan queue:restart

# --- 5. ilk kurulum: alt alan adının klasörünü symlink'e çevir --------------
if [ ! -L "$SITE_DIR" ]; then
    log "→ İlk kurulum: $SITE_DIR symlink'e çevriliyor (cPanel'in dosyaları $APP_DIR/cpanel-ilk/ altına)"
    mkdir -p "$APP_DIR/cpanel-ilk"
    mv "$SITE_DIR" "$APP_DIR/cpanel-ilk/$(basename "$SITE_DIR")-$(date +%s)"
    ln -s "$APP_DIR/current/public" "$SITE_DIR"
fi

# --- 6. sağlık: YENİ sürüm yanıt veriyor mu? --------------------------------
# Sunucuda php-fpm reload yetkimiz yok; LiteSpeed'in PHP süreçleri symlink'in eski hedefini
# realpath önbelleğinde bir süre tutar. /up tek başına yetmez (eski sürüm de 200 döner): /surum beklenir.
APP_URL="$(grep -E '^APP_URL=' "$APP_DIR/shared/.env" | cut -d= -f2- | tr -d '"')"
START=$(date +%s); SERVED=""
while [ $(( $(date +%s) - START )) -lt "$WAIT_SECONDS" ]; do
    SERVED="$(curl -fsS --max-time 10 "$APP_URL/surum" 2>/dev/null || true)"
    [ "$SERVED" = "$VERSION" ] && break
    sleep 5
done
WAITED=$(( $(date +%s) - START ))

if [ "$SERVED" = "$VERSION" ] && curl -fsS -o /dev/null --max-time 10 "$APP_URL/up"; then
    log "✓ Yayında: $VERSION (yeni sürüm ${WAITED} sn sonra yanıt verdi)"
else
    log "✗ $VERSION yanıt vermedi (${WAITED} sn; görülen: ${SERVED:-yanıt yok}): önceki sürüme dönülüyor"
    if [ -n "$PREVIOUS" ]; then
        ln -sfn "$PREVIOUS" "$APP_DIR/current.new"
        mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"
        "$PHP" "$APP_DIR/current/artisan" queue:restart
    fi
    rm -rf "$RELEASE"
    echo "$VERSION" >> "$APP_DIR/basarisiz.txt"
    # DİKKAT: migrate edilen veritabanı değişiklikleri geri alınmadı (geriye uyumlu migration şart)
    exit 1
fi

# --- 7. temizlik -------------------------------------------------------------
rm -f "$PACKAGE" "$PACKAGE.sig"
ls -1d "$APP_DIR"/releases/* | sort -r | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf
