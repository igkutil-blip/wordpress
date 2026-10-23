#!/bin/sh
# Pakira dodatak iz wp-dodaci/<naziv> u izdanja/proba/<naziv>-<verzija>.zip (verzija iz zaglavlja dodatka).
# Upotreba: alati/zip-dodatak.sh domestic-provjera-dobi
set -e
cd "$(dirname "$0")/.."
N="$1"
[ -f "wp-dodaci/$N/$N.php" ] || { echo "Nema wp-dodaci/$N/$N.php"; exit 1; }
V=$(sed -n 's/^ \* Version: *//p' "wp-dodaci/$N/$N.php" | head -1)
if grep -rqiE 'claude|anthropic|chatgpt|openai' "wp-dodaci/$N"; then echo "U dodatku se spominje alat – ukloni prije pakiranja."; exit 1; fi
Z="izdanja/proba/$N-$V.zip"
rm -f "$Z"
(cd wp-dodaci && zip -qr "../$Z" "$N" -x '*.DS_Store')
echo "$Z"
