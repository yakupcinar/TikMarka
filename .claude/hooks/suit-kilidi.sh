#!/bin/bash
#
# İKİNCİ EŞZAMANLI TEST SÜİTİ ENGELİ.
#
# ★ İki test süreci aynı test veritabanında koşarsa süit ÇÖKÜYOR.
#   Belirti yanıltıcı: `relation "orders" does not exist` ya da
#   `schema … does not exist` — yani veri hatası gibi görünüyor, oysa
#   sebep eşzamanlılık. Bu oturumda İKİ KEZ yaşandı; ikincisinde 142 test
#   birden kırmızı oldu ve toparlamak şemaları düşürüp merkez tabloları
#   boşaltmayı gerektirdi.
#
# ⚠️ KİLİT DOSYASI KULLANILMIYOR: yarıda kesilen bir koşu bayat kilit
#    bırakır ve sonrasında HİÇBİR test koşamaz. Bunun yerine gerçekten
#    koşan süreç aranıyor — kendini iyileştiren ölçüm.
#
# ⚠️ Docker'a sorulmuyor: `docker compose exec` süreci HOST'ta görünüyor
#    ve host araması hem hızlı hem de Docker takıldığında da çalışıyor
#    (bu oturumda Docker iki kez takıldı).

KOMUT=$(jq -r '.tool_input.command // ""')

case "$KOMUT" in
  *"artisan test"*|*"composer test"*) ;;
  *) exit 0 ;;
esac

KOSAN=$(pgrep -f "artisan test" 2>/dev/null | wc -l | tr -d ' ')

if [ "${KOSAN:-0}" -gt 0 ]; then
  jq -n '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: "Zaten koşan bir test süiti var — ikincisi engellendi.\n\nSebep: iki süreç aynı test veritabanında koşarsa süit çöker. Belirti veri hatası gibi görünür (relation … does not exist) ama sebep eşzamanlılıktır. Bu projede iki kez yaşandı, ikincisinde 142 test birden kırmızı oldu.\n\nKoşan süit bitene kadar bekle. Gerçekten takıldıysa:  pkill -f \"artisan test\""
    }
  }'
fi

exit 0
