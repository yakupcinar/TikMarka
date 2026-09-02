#!/bin/bash
#
# COMMIT ÖNCESİ BİÇİM KAPISI.
#
# ★ CI bir kez bu yüzden kırmızı döndü: pint koşuldu, ardından PHPStan
#   düzeltmesi yapıldı ve pint TEKRAR koşulmadan commit atıldı.
#   "Yerel lint yeşil ≠ CI yeşil. Otorite CI." kuralının maliyeti.
#
# ⚠️ ÇIKTI BORULANMIYOR ve ÇIKIŞ KODUNA bakılıyor: `pint | tail -2`
#    yazmak, `pint.json` bozulduğunda (bilinen errno=35) BOŞ çıktı verir
#    ve "geçti" gibi görünür. Boş çıktı başarı değil, hata belirtisidir.
#
# ⚠️ ALTYAPI ARIZASINDA GEÇİRİYOR: Docker kapalıysa commit engellenmez.
#    Kapı biçim hatası için, altyapı sorunu için değil.

KOMUT=$(jq -r '.tool_input.command // ""')

case "$KOMUT" in
  *"git commit"*) ;;
  *) exit 0 ;;
esac

cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0

docker compose exec -T app test -f /var/www/html/vendor/laravel/pint/builds/pint >/dev/null 2>&1 || exit 0

CIKTI=$(docker compose exec -T app php vendor/laravel/pint/builds/pint \
          --config /var/www/html/pint.json --test 2>&1)
KOD=$?

if [ "$KOD" -ne 0 ]; then
  jq -n --arg c "$(printf '%s' "$CIKTI" | tail -20)" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: ("Biçim kontrolü DÜŞTÜ — commit engellendi.\n\n" + $c + "\n\nÖnce düzelt:  make lint")
    }
  }'
fi

exit 0
