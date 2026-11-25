#!/usr/bin/env bash
# Yayına alınacak paketi üretir: üretim bağımlılıkları (vendor) + derlenmiş ön yüz (public/build).
# Sunucuda Node gerekmez; aynı paket VPS'e de paylaşımlı hostinge de yüklenebilir.
#
#   Kullanım:  deploy/build.sh <hedef>        (ör. deploy/build.sh wsl)
#   Çıktı:     build/anahtarci-<hedef>-<sürüm>.tar.gz  (+ .sig imzası)
set -euo pipefail

TARGET="${1:?Hedef adı verin: deploy/targets/<hedef>.env}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_FILE="$ROOT/deploy/targets/$TARGET.env"
[ -f "$TARGET_FILE" ] || { echo "Bulunamadı: $TARGET_FILE" >&2; exit 1; }

# Sürüm = UTC zaman damgası; sunucuda releases/<sürüm> klasörünün adı olur
VERSION="$(date -u +%Y%m%d%H%M%S)"
WORK="$(mktemp -d)"
# mktemp -d klasörü 0700 açar ve tar bu izni paketin KÖKÜNE yazar: sunucuda release klasörüne
# Nginx (www-data) giremez, /up başarısız olur. Windows'ta (Git Bash) görünmez, Linux CI'da çıkar.
chmod 755 "$WORK"
trap 'rm -rf "$WORK"' EXIT

echo "→ Dosyalar kopyalanıyor (yalnızca git'in bildiği dosyalar; .env, vendor, node_modules hariç)"
cd "$ROOT"
git ls-files --cached --others --exclude-standard -z | tar --null -T - -cf - | tar -xf - -C "$WORK"

cd "$WORK"
echo "→ composer install --no-dev"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

echo "→ npm ci + npm run build ($TARGET)"
set -a; source "$TARGET_FILE"; set +a   # VITE_* değerleri derleme anında JS'e gömülür
npm ci --no-audit --no-fund
npm run build

echo "→ Sunucuda gerekmeyenler çıkarılıyor"
rm -rf node_modules tests .github build storage
echo "$VERSION" > VERSION

mkdir -p "$ROOT/build"
OUT="$ROOT/build/anahtarci-$TARGET-$VERSION.tar.gz"
tar -czf "$OUT" .
echo "✓ $OUT ($(du -h "$OUT" | cut -f1))"

# İmza: sunucu imzasız ya da değişmiş paketi kurmaz (bkz. deploy.sh). Anahtar: CI'da secret, yerelde keygen.php
php "$ROOT/deploy/sign.php" "$OUT"
