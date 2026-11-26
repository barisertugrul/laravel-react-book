#!/bin/bash
# cPanel KEŞİF: SSH yoksa sunucuda ne olduğunu cron ile öğreniriz. Bir kez çalıştırılır.
#
#   cron (tek sefer):  bash ~/deploy/kesif.sh > ~/deploy/log/kesif.txt 2>&1
#   sonuç:             FTP ile deploy/log/kesif.txt indirilir
#
# Hiçbir şeyi değiştirmez; yalnızca iki geçici deneme dosyası koyar (bilgi-*.php kendini siler, symtest elle silinir).
SITE_DIR="$HOME/public_html/${SITE:-uygulama.ornek.com}"
INFO_NAME="bilgi-8ad0861027bee5c3.php"   # tahmin edilemeyen ad; ilk istekten sonra kendini siler

section() { echo; echo "===== $1"; }

section "zaman / kimlik"
date; id; echo "HOME=$HOME  SHELL=$SHELL  bash=$BASH_VERSION  pwd=$(pwd)"
echo "PATH=$PATH"
uname -a

section "PHP adayları (CLI)"
for p in php /usr/local/bin/php /usr/bin/php /usr/local/bin/ea-php85 /opt/cpanel/ea-php85/root/usr/bin/php /opt/alt/php85/usr/bin/php; do
    if command -v "$p" >/dev/null 2>&1; then
        echo "$p -> $(command -v "$p")  |  $("$p" -v 2>/dev/null | head -1)"
    fi
done
ls -d /opt/cpanel/ea-php* /opt/alt/php* 2>/dev/null | tr '\n' ' '; echo

PHP85=""
for p in /opt/cpanel/ea-php85/root/usr/bin/php /usr/local/bin/ea-php85 /opt/alt/php85/usr/bin/php; do
    [ -x "$p" ] && { PHP85="$p"; break; }
done
section "PHP 8.5 CLI: $PHP85"
if [ -n "$PHP85" ]; then
    "$PHP85" -v
    echo "--- modüller:"; "$PHP85" -m | tr '\n' ' '; echo
    "$PHP85" -r 'foreach (["memory_limit","max_execution_time","disable_functions","open_basedir","opcache.enable_cli"] as $k) echo $k, " = ", ini_get($k), PHP_EOL;'
    "$PHP85" --ini | head -3
fi

section "araçlar"
for t in tar gzip curl wget ln readlink flock mysql mysqldump unzip zip git composer node; do
    printf '%-10s %s\n' "$t" "$(command -v "$t" || echo YOK)"
done
tar --version 2>/dev/null | head -1

section "disk"
quota -s 2>/dev/null || echo "quota komutu yok"
du -sh "$HOME" 2>/dev/null
df -h "$HOME" 2>/dev/null | tail -1

section "dizinler"
ls -la "$HOME"
echo "--- public_html:"; ls -la "$HOME/public_html"
echo "--- $SITE_DIR:"; ls -la "$SITE_DIR"

section "symlink denemesi (Apache izliyor mu?)"
mkdir -p "$HOME/deploy/symtest"
echo "symlink calisiyor" > "$HOME/deploy/symtest/index.html"
ln -sfn "$HOME/deploy/symtest" "$SITE_DIR/symtest" && ls -la "$SITE_DIR/symtest"
echo "deneme adresi: http://${SITE:-uygulama.ornek.com}/symtest/index.html"

section "web PHP bilgisi"
cp "$HOME/deploy/bilgi.php" "$SITE_DIR/$INFO_NAME" && echo "deneme adresi: http://${SITE:-uygulama.ornek.com}/$INFO_NAME (tek kullanımlık)"

section "crontab"
crontab -l 2>&1

echo; echo "===== bitti"
