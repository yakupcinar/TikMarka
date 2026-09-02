#!/bin/bash
#
# HOOK DAVRANIŞ TESTİ — host'ta koşar.
#
# ⚠️ Pest süiti KONTEYNERDE koşuyor ve orada `jq`, `python3`, `pgrep` YOK;
#    yani hook'lar orada çalıştırılamıyor. Bu betik davranışı ölçüyor,
#    `tests/Feature/HookKurulumuTest.php` ise bu betiğin her hook'u
#    kapsadığını ölçüyor — biri eklenip testi yazılmazsa CI kırmızı olur.
#
# Kullanım:  ./.claude/hooks/hook-testi.sh

cd "$(dirname "$0")/../.." || exit 1
H=.claude/hooks
GECEN=0; KALAN=0

karar() {  # karar <betik> <komut>
  local cikti
  cikti=$(printf '{"tool_input":{"command":%s}}' "$(printf '%s' "$2" | jq -Rs .)" | "$H/$1")
  if [ -z "$cikti" ]; then echo "izin"; else
    printf '%s' "$cikti" | jq -r '.hookSpecificOutput.permissionDecision // "bozuk"'
  fi
}

bekle() {  # bekle <açıklama> <beklenen> <betik> <komut>
  local g; g=$(karar "$3" "$4")
  if [ "$g" = "$2" ]; then
    GECEN=$((GECEN+1)); printf '  ✓ %s\n' "$1"
  else
    KALAN=$((KALAN+1)); printf '  ✗ %s — beklenen "%s", gelen "%s"\n' "$1" "$2" "$g"
  fi
}

echo "── git-checkout-engel.sh"
bekle "dosya geri alma engelleniyor"      deny  git-checkout-engel.sh "git checkout app/Models/Cart.php"
bekle "-- ile geri alma engelleniyor"     deny  git-checkout-engel.sh "git checkout -- config/logging.php"
bekle "git restore engelleniyor"          deny  git-checkout-engel.sh "git restore tests/Pest.php"
bekle "commit'ten geri alma engelleniyor" deny  git-checkout-engel.sh "git checkout abc123 -- README.md"
bekle "DAL değiştirme serbest"            izin  git-checkout-engel.sh "git checkout main"
bekle "DAL oluşturma serbest"             izin  git-checkout-engel.sh "git checkout -b yeni"
bekle "alakasız komut serbest"            izin  git-checkout-engel.sh "git status"

echo "── suit-kilidi.sh"
bekle "koşan yokken serbest"              izin  suit-kilidi.sh "php artisan test"
sh -c 'exec -a "php artisan test --hook-testi" sleep 30' &
SAHTE=$!
sleep 2
bekle "koşan VARKEN engelleniyor"         deny  suit-kilidi.sh "php artisan test"
bekle "koşan varken bile alakasız serbest" izin suit-kilidi.sh "git status"
kill "$SAHTE" 2>/dev/null; wait "$SAHTE" 2>/dev/null; sleep 1
bekle "süit bitince tekrar serbest"       izin  suit-kilidi.sh "php artisan test"

echo "── pint-kapisi.sh"
bekle "commit dışı komut serbest"         izin  pint-kapisi.sh "git status"
bekle "biçim temizken commit serbest"     izin  pint-kapisi.sh "git commit -m x"

# ★ ENGELLEME VAKASI — testin kendi eksiği olarak yakalandı.
#   İlk hâlde yalnızca izin veren yollar sınanmıştı; yani kapının
#   GERÇEKTEN engelleyip engellemediği hiç ölçülmemişti.
#
# ⚠️ Geçici dosya `trap` ile temizleniyor: yarıda kesilirse depoda
#    biçimsiz bir dosya kalır ve o andan sonra HER commit engellenir.
GECICI="_hook-testi-gecici.php"
trap 'rm -f "$GECICI"' EXIT INT TERM
printf '<?php\n$x   =   1;\nif($x){echo "a";}\n' > "$GECICI"
bekle "biçim BOZUKKEN commit engelleniyor" deny  pint-kapisi.sh "git commit -m x"
rm -f "$GECICI"
bekle "dosya silinince tekrar serbest"    izin  pint-kapisi.sh "git commit -m x"

echo
echo "geçen: $GECEN   kalan: $KALAN"
[ "$KALAN" -eq 0 ]
