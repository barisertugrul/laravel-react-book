#!/bin/bash
# PAYLAŞIMLI HOSTİNG (cPanel) dağıtımı: SSH yok, FTP'de TLS yok → sunucu paketi KENDİSİ çeker.
# CI imzalı paketi GitHub Releases'a koyar; bu betik cron'la birkaç dakikada bir çalışır:
#   yeni sürüm var mı? → indir → imzayı doğrula → kur → sürümü çevir → yeni sürüm yanıt veriyor mu? → değilse geri dön
#
#   cron:  */2 * * * *  bash ~/apps/anahtarci/bin/kur.sh >> ~/apps/anahtarci/log/kur.log 2>&1
#
#   ~/apps/anahtarci/
#     bin/kur.sh  bin/verify.php  bin/deploy.pub   ← yalnızca yönetici koyar (paketten gelmez)
#     bin/komut.sh                                 ← isteğe bağlı, tek seferlik komut (çalışınca silinir)
#     releases/<sürüm>/   shared/.env   shared/storage/
#     current.txt      ← web'in kullandığı sürüm (köprü index.php okur)
#     current -> releases/<sürüm>   ← yalnızca komut satırı (cron: schedule:run, queue:work)
#   ~/public_html/<alt-alan-adı>/   ← GERÇEK klasör: köprü index.php + public/ içindeki statik dosyalar
#
# Neden symlink'li belge kökü değil? Kalıcı PHP süreçleri (LiteSpeed lsphp) symlink'in çözülmüş
# hedefini realpath önbelleğinde tutar; php-fpm reload yetkimiz yok. Symlink çevrildikten sonra
# dakikalarca eski sürüm sunuldu (kurulum-sorunlari 28-29). Köprü, her sürümü symlink'siz,
# kendine özgü bir yoldan çağırır: önbellekte eski bir hedef kalamaz.
set -euo pipefail

# --- ayarlar ---------------------------------------------------------------
PHP="${PHP:-/opt/cpanel/ea-php85/root/usr/bin/php}"   # DİKKAT: düz "php" bu sunucuda PHP 7.4
APP_DIR="${APP_DIR:-$HOME/apps/anahtarci}"
SITE_DIR="${SITE_DIR:-$HOME/public_html/lisans.demoistasyonu.com}"
RELEASE_URL="${RELEASE_URL:-https://github.com/barisertugrul/laravel-react-book/releases/download/anahtarci-cpanel}"
KEEP_RELEASES="${KEEP_RELEASES:-3}"   # paylaşımlı hostingte disk kotası: daha az sürüm tutulur
WAIT_SECONDS="${WAIT_SECONDS:-60}"    # köprüyle yeni sürüm hemen görülmeli; bu süre yalnızca güvenlik payı
# LiteSpeed: bu dosyaya dokunmak kullanıcının kalıcı lsphp süreçlerini yeniden başlatır (opcache'i de boşaltır)
LSPHP_RESTART="${LSPHP_RESTART:-$HOME/.lsphp_restart.txt}"
BRIDGE_MARK="ANAHTARCI-KOPRU"           # site klasöründeki index.php'nin köprü olduğunu tanımak için

BIN="$APP_DIR/bin"
# İlk çalıştırmada kalıcı klasörler (yüklenen dosyalar, oturumlar, önbellek, günlükler)
mkdir -p "$APP_DIR"/{releases,log} "$APP_DIR"/shared/storage/{app/public,framework/{cache/data,sessions,views},logs}
log() { echo "[$(date '+%F %T')] $*"; }

# Aynı anda iki kurulum çalışmasın (önceki cron hâlâ sürüyorsa bu çalıştırma sessizce çıkar)
exec 9>"$APP_DIR/kur.lock"
flock -n 9 || exit 0

# Web'in kullandığı sürümü yazar: önce geçici dosya, sonra mv (yarım yazılmış dosya okunmaz)
set_web_version() {
    echo "$1" > "$APP_DIR/current.txt.new"
    mv -f "$APP_DIR/current.txt.new" "$APP_DIR/current.txt"
}
# Komut satırının kullandığı current symlink'i (atomik)
set_cli_release() {
    ln -sfn "$1" "$APP_DIR/current.new"
    mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"
}
# Sürümün public/ klasöründeki statik dosyaları site klasörüne EKLER (index.php hariç).
# build/ altındaki dosya adları içerik özetli (hash): eski sürümün dosyaları silinmez, çakışmaz.
copy_public() {
    tar -C "$1/public" --exclude=./index.php -cf - . | tar -C "$2" -xf -
}
write_bridge() {
    cat > "$1/index.php" <<PHP
<?php
// $BRIDGE_MARK: kur.sh üretir, elle değiştirmeyin.
// Sürüm current.txt'den okunur ve symlink'siz yoldan çağrılır (realpath önbelleği eski sürümü tutamaz).
\$app = '$APP_DIR';
\$version = trim((string) @file_get_contents(\$app.'/current.txt'));
\$index = \$app.'/releases/'.\$version.'/public/index.php';
if (! preg_match('/^[0-9]{14}\$/', \$version) || ! is_file(\$index)) {
    http_response_code(503);
    exit('Bakım çalışması: birazdan tekrar deneyin.');
}
require \$index;
PHP
}

# --- 0a. tek seferlik komut kutusu (SSH yok) ---------------------------------
# Yönetici bin/komut.sh dosyasını koyar (ör. license:keygen, user:create); bir sonraki çalıştırmada
# yayındaki sürümün klasöründe BİR KEZ çalışır, çıktısı bu günlüğe yazılır ve dosya SİLİNİR
# (içinde şifre olabilir). bin/ yalnızca yöneticinin yazabildiği klasördür; paketten gelmez.
if [ -f "$BIN/komut.sh" ] && [ -d "$APP_DIR/current" ]; then
    log "→ bin/komut.sh çalıştırılıyor (bir kez)"
    mv -f "$BIN/komut.sh" "$BIN/komut.sh.calisiyor"   # yarıda kalsa bile ikinci kez çalışmasın
    if ( cd "$APP_DIR/current" && PHP="$PHP" bash "$BIN/komut.sh.calisiyor" ) 2>&1 | sed 's/^/  │ /'; then
        log "✓ komut.sh bitti"
    else
        log "✗ komut.sh hata verdi"
    fi
    rm -f "$BIN/komut.sh.calisiyor"
fi

# --- 0. .env değişti mi? (yeni sürüm olmasa da) -------------------------------
# Canlıda config önbelleği var: .env'e eklenen bir değer config:cache çalışana kadar okunmaz.
# SSH olmadığı için bunu elle yapamayız; .env önbellekten yeniyse önbelleği burada yenileriz.
CONFIG_CACHE="$APP_DIR/current/bootstrap/cache/config.php"
if [ -f "$CONFIG_CACHE" ] && [ "$APP_DIR/shared/.env" -nt "$CONFIG_CACHE" ]; then
    log "→ .env değişmiş: config:cache + queue:restart"
    ( cd "$APP_DIR/current" && "$PHP" artisan config:cache && "$PHP" artisan queue:restart ) \
        || log "✗ config:cache başarısız (.env'i denetleyin)"
fi

# --- 1. yeni sürüm var mı? ---------------------------------------------------
# releases/download adresleri GitHub API'sinin hız sınırına girmez (paylaşımlı IP'de önemli)
LATEST="$(curl -fsSL --max-time 20 "$RELEASE_URL/surum.txt" | tr -dc '0-9')" || { log "✗ surum.txt alınamadı"; exit 1; }
CURRENT="$(cat "$APP_DIR/current.txt" 2>/dev/null || cat "$APP_DIR/current/VERSION" 2>/dev/null || echo yok)"
[ -n "$LATEST" ] && [ "$LATEST" != "$CURRENT" ] || exit 0
# Bir kez başarısız olan sürüm her 2 dakikada yeniden denenmez (yenisi gelene kadar)
grep -qx "$LATEST" "$APP_DIR/basarisiz.txt" 2>/dev/null && exit 0

log "→ Yeni sürüm: $LATEST (yayında: $CURRENT)"
INBOX="$APP_DIR/gelen"; mkdir -p "$INBOX"
PACKAGE="$INBOX/anahtarci-$LATEST.tar.gz"
curl -fsSL --max-time 300 -o "$PACKAGE" "$RELEASE_URL/anahtarci-cpanel.tar.gz"
curl -fsSL --max-time 20 -o "$PACKAGE.sig" "$RELEASE_URL/anahtarci-cpanel.tar.gz.sig"

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
# Bu bağın hedefi hiç değişmez (shared/), bu yüzden önbellek sorunu yaratmaz.
ln -s "$APP_DIR/shared/storage/app/public" "$RELEASE/public/storage"

# Buradan sonra bir komut hata verirse (ör. migrate): sürüm işaretlenir, her 2 dakikada yeniden indirilmez.
# Sürüm henüz hiç sunulmadı: klasörü silmek güvenli.
trap 'log "✗ $VERSION hazırlanırken hata (satır $LINENO): kurulmadı"; echo "$VERSION" >> "$APP_DIR/basarisiz.txt"; rm -rf "$RELEASE"' ERR

cd "$RELEASE"
"$PHP" artisan migrate --force --no-interaction
"$PHP" artisan optimize

# --- 4. site klasörü: köprü + statik dosyalar --------------------------------
PREVIOUS="$(cat "$APP_DIR/current.txt" 2>/dev/null || basename "$(readlink "$APP_DIR/current" 2>/dev/null)" 2>/dev/null || true)"
if [ -z "$PREVIOUS" ] || [ ! -d "$APP_DIR/releases/$PREVIOUS" ]; then PREVIOUS=""; fi

if [ ! -L "$SITE_DIR" ] && grep -qs "$BRIDGE_MARK" "$SITE_DIR/index.php"; then
    # Köprü zaten kurulu: yeni sürümün statik dosyaları ÖNCE eklenir (sayfa yeni build/ adlarını isteyince hazır olsun)
    copy_public "$RELEASE" "$SITE_DIR"
    trap - ERR   # buradan sonra hata yönetimini sağlık denetimi üstlenir
    log "→ Sürüm çevriliyor"
    set_web_version "$VERSION"
else
    # İlk kurulum (cPanel'in boş klasörü) ya da eski düzen (current/public symlink'i): klasör yanda kurulur, sonra yer değiştirir
    log "→ Site klasörü köprü düzenine çevriliyor: $SITE_DIR"
    rm -rf "$SITE_DIR.new"; mkdir -p "$SITE_DIR.new"
    copy_public "$RELEASE" "$SITE_DIR.new"
    write_bridge "$SITE_DIR.new"
    trap - ERR
    log "→ Sürüm çevriliyor"
    set_web_version "$VERSION"
    if [ -L "$SITE_DIR" ]; then
        rm -f "$SITE_DIR"   # yalnızca bağ silinir; hedefi (releases/...) yerinde kalır
    elif [ -e "$SITE_DIR" ]; then
        mkdir -p "$APP_DIR/cpanel-ilk"
        mv "$SITE_DIR" "$APP_DIR/cpanel-ilk/$(basename "$SITE_DIR")-$(date +%s)"
    fi
    mv "$SITE_DIR.new" "$SITE_DIR"
fi
set_cli_release "$RELEASE"
# Kuyruk işçisi cron'la kısa ömürlü çalışır (bkz. cron); yine de çalışan varsa kod değişti sinyali verilir
"$PHP" artisan queue:restart
# opcache'teki eski derlenmiş dosyalar için kalıcı PHP süreçlerinin yeniden başlatılması istenir (zararsız)
touch "$LSPHP_RESTART" 2>/dev/null || true
log "  web: current.txt=$(cat "$APP_DIR/current.txt"); cli: current → $(readlink "$APP_DIR/current")"

# --- 5. sağlık: YENİ sürüm yanıt veriyor mu? --------------------------------
# /up tek başına yetmez (eski sürüm de 200 döner): /surum yeni sürümü döndürmeli VE /up 200 olmalı.
# İkisi BİRLİKTE ve süre boyunca tekrar denenir: paylaşımlı hostingte sunucunun kendi adresine bağlantısı
# arada bir zaman aşımına uğrar; tek bir başarısız deneme sağlam sürümü geri aldırmamalı (kurulum-sorunlari 33).
APP_URL="$(grep -E '^APP_URL=' "$APP_DIR/shared/.env" | cut -d= -f2- | tr -d '"')"
START=$(date +%s); SERVED=""; UP=""; HEALTHY=""; NEXT_NOTE=30
while [ $(( $(date +%s) - START )) -lt "$WAIT_SECONDS" ]; do
    SERVED="$(curl -fsS --max-time 10 "$APP_URL/surum" 2>/dev/null || true)"
    UP="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$APP_URL/up" 2>/dev/null || true)"
    if [ "$SERVED" = "$VERSION" ] && [ "$UP" = 200 ]; then HEALTHY=1; break; fi
    if [ $(( $(date +%s) - START )) -ge "$NEXT_NOTE" ]; then
        log "  ${NEXT_NOTE} sn: görülen ${SERVED:-yanıt yok}, /up ${UP:-yanıt yok}"; NEXT_NOTE=$(( NEXT_NOTE + 30 ))
    fi
    sleep 5
done
WAITED=$(( $(date +%s) - START ))

if [ -n "$HEALTHY" ]; then
    log "✓ Yayında: $VERSION (yeni sürüm ${WAITED} sn sonra yanıt verdi)"
else
    log "✗ $VERSION sağlıklı değil (${WAITED} sn; görülen: ${SERVED:-yanıt yok}, /up: ${UP:-yanıt yok}): önceki sürüme dönülüyor"
    if [ -n "$PREVIOUS" ]; then
        set_web_version "$PREVIOUS"
        set_cli_release "$APP_DIR/releases/$PREVIOUS"
        "$PHP" "$APP_DIR/current/artisan" queue:restart
        touch "$LSPHP_RESTART" 2>/dev/null || true
        log "  geri dönüldü: $PREVIOUS"
    fi
    # Başarısız sürümün klasörü HEMEN silinmez: bir PHP süreci onu hâlâ çalıştırıyor olabilir
    # (silinirse o süreç 500 verir; kurulum-sorunlari 29). Sonraki başarılı dağıtımda temizlenir.
    echo "$VERSION" >> "$APP_DIR/basarisiz.txt"
    # DİKKAT: migrate edilen veritabanı değişiklikleri geri alınmadı (geriye uyumlu migration şart)
    exit 1
fi

# --- 6. temizlik -------------------------------------------------------------
rm -f "$PACKAGE" "$PACKAGE.sig"
# DİKKAT: cPanel cron'unda (CageFS) /dev/fd yok → süreç ikamesi "<(...)" çalışmaz; düz dosya kullanılır.
# Başarısız sürümlerin klasörleri (artık hiçbir istek onları kullanmıyor)
if [ -f "$APP_DIR/basarisiz.txt" ]; then
    while read -r FAILED; do
        if [ -n "$FAILED" ] && [ "$FAILED" != "$VERSION" ]; then rm -rf "${APP_DIR:?}/releases/$FAILED"; fi
    done < "$APP_DIR/basarisiz.txt"
fi
ls -1d "$APP_DIR"/releases/* | sort -r | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf
# Site klasöründe, tutulan sürümlerin hiçbirinde olmayan eski build dosyaları
KEPT_LIST="$APP_DIR/kept-assets.txt"
find "$APP_DIR"/releases/*/public/build/assets -maxdepth 1 -type f -printf '%f\n' 2>/dev/null | sort -u > "$KEPT_LIST" || true
if [ -s "$KEPT_LIST" ] && [ -d "$SITE_DIR/build/assets" ]; then
    find "$SITE_DIR/build/assets" -maxdepth 1 -type f -printf '%f\n' \
        | { grep -vxF -f "$KEPT_LIST" || true; } \
        | while read -r OLD; do rm -f "$SITE_DIR/build/assets/$OLD"; done
fi
rm -f "$KEPT_LIST"
