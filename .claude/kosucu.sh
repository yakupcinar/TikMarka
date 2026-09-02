#!/usr/bin/env bash
#
# GÖZETİMSİZ KOŞUCU — task listesini sırayla headless Claude'a verir.
#
# Kullanım:  .claude/kosucu.sh [gorev-dosyasi]
#            .claude/kosucu.sh --kuru        (ne koşacağını yazar, koşmaz)
#
# Varsayılan liste: .claude/gorevler.txt  ·  satır başına bir görev, # yorum.
#
# ⚠️ TASARIM KARARI: her görev AYRI bir `claude -p` koşusu. Sebebi, tek uzun
# oturumun bu projede ölçülmüş iki sorunu: bağlam baskısında skill'ler
# kırpılıyor ve uzun oturum diski dolduruyor (A4'te ENOSPC sonrası hiçbir
# komut çalışmadı). Ayrı koşu, her görevden sonra temiz bir durak veriyor.

set -uo pipefail

KOK="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LISTE="${1:-$KOK/.claude/gorevler.txt}"
KURU=0
[ "${1:-}" = "--kuru" ] && { KURU=1; LISTE="$KOK/.claude/gorevler.txt"; }

GUNLUK_KLASOR="$KOK/.claude/kosu"
DAMGA="$(date +%Y%m%d-%H%M%S)"
EN_AZ_DISK_GB="${EN_AZ_DISK_GB:-10}"

# ⚠️ `--bare` HİÇBİR KOŞULDA kullanılmaz: hook'ları atlıyor ve bu projenin üç
# kilidi (git checkout engeli · süit kilidi · pint kapısı) tam da gözetimsiz
# koşu için var.
YASAK_ARAC=(
  "Bash(git push:*)"
  "Bash(git reset:*)"
  "Bash(rm -rf:*)"
  "Bash(docker system prune:*)"
  "Bash(docker compose down:*)"
)

yaz() { printf '%s\n' "$*"; }

# ⚠️ macOS'ta `timeout` KOMUTU YOK (GNU coreutils'te). İlk hâlinde
# `timeout 20 docker version` yazılıydı: komut bulunamıyor, çıkış kodu
# sıfırdan farklı oluyor ve denetim "docker cevap vermiyor" diyerek
# HER KOŞUYU durduruyordu — daemon sapasağlamken. Ölçüldü, sonra yazıldı.
zaman_asimiyla() {
  local sinir="$1"; shift
  "$@" >/dev/null 2>&1 &
  local pid=$!
  local gecen=0

  while kill -0 "$pid" 2>/dev/null; do
    [ "$gecen" -ge "$sinir" ] && { kill -9 "$pid" 2>/dev/null; return 124; }
    sleep 1
    gecen=$((gecen + 1))
  done

  wait "$pid"
}

# ── Koşu öncesi denetimler ────────────────────────────────────────────────
# Hepsi "koşmayan bir şeyi koşmuş sanma" ilkesinden: eksik varsa DURUYOR.
denetle() {
  local hata=0

  # 1 · Disk. A4'te doldu ve araç kendi çıktı dosyasını bile açamadı;
  #     belirti "ortam bozuldu" gibi görünüyor.
  local bos
  bos=$(df -g / | awk 'NR==2 {print $4}')
  if [ "${bos:-0}" -lt "$EN_AZ_DISK_GB" ]; then
    yaz "DUR · disk ${bos}GB (en az ${EN_AZ_DISK_GB}GB gerekiyor)"
    hata=1
  fi

  # 2 · Docker. Disk dolunca daemon ölüyor; ölü daemon'la koşan görev
  #     testleri koşamadan "bitti" der.
  if ! zaman_asimiyla 20 docker version; then
    yaz "DUR · docker daemon cevap vermiyor"
    hata=1
  fi

  # 3 · Süit koşuyor mu. İki süit aynı test veritabanında çöküyor.
  if pgrep -f "artisan test" >/dev/null 2>&1; then
    yaz "DUR · zaten bir süit koşuyor"
    hata=1
  fi

  # 4 · Çalışma alanı temiz mi. Kirli ağaçta başlayan görevin ürettiği
  #     değişiklik ayırt edilemiyor — kırma denemesi yarıda kalmışsa
  #     (B5'te oldu) kırık kod göreve karışır.
  if [ -n "$(git -C "$KOK" status --porcelain)" ]; then
    yaz "DUR · çalışma alanı kirli:"
    git -C "$KOK" status --short | sed 's/^/       /'
    hata=1
  fi

  return $hata
}

# ── Koşu sonrası: tünel açık kaldı mı ─────────────────────────────────────
# Açık ngrok = makine internete açık. Kapatmak yazılı kuraldı; gözetimsiz
# koşuda kuralın tutmadığını varsayıyoruz.
tunel_denetle() {
  if docker ps --format '{{.Names}}' 2>/dev/null | grep -q ngrok; then
    yaz ""
    yaz "⚠️  NGROK TÜNELİ AÇIK — makine internete açık. Kapatılıyor."
    docker compose --profile tunel stop ngrok >/dev/null 2>&1 && yaz "   kapatıldı."
  fi
}

[ -f "$LISTE" ] || { yaz "görev listesi yok: $LISTE"; exit 1; }

# ⚠️ `mapfile` bash 4+ — macOS'ta /bin/bash 3.2.57 var, komut BULUNAMIYOR
# ve dizi boş kalıyor. Ölçüldü: betik "unbound variable" ile düştü.
GOREVLER=()
while IFS= read -r satir; do
  case "$satir" in ''|\#*) continue ;; esac
  GOREVLER+=("$satir")
done < "$LISTE"

[ "${#GOREVLER[@]}" -gt 0 ] || { yaz "listede görev yok"; exit 1; }

yaz "══ KOŞUCU · ${#GOREVLER[@]} görev · $DAMGA"

if [ "$KURU" = 1 ]; then
  denetle && yaz "denetimler: TEMİZ" || yaz "denetimler: ENGEL VAR"
  printf '  %s\n' "${GOREVLER[@]}"
  exit 0
fi

yasak_bayrak=()
for a in "${YASAK_ARAC[@]}"; do yasak_bayrak+=(--disallowedTools "$a"); done

sira=0
for gorev in "${GOREVLER[@]}"; do
  sira=$((sira + 1))
  gunluk="$GUNLUK_KLASOR/$DAMGA-$sira.log"

  yaz ""
  yaz "── $sira/${#GOREVLER[@]} · $gorev"

  if ! denetle; then
    yaz "   ATLANMADI, DURULDU — sonraki görevler koşmadı."
    exit 2
  fi

  # ⚠️ `acceptEdits`: dosya düzenlemeyi onaylıyor, tehlikeli araçları
  # YASAK_ARAC kapatıyor. `bypassPermissions` bilerek kullanılmıyor.
  if claude -p "$gorev" \
      --permission-mode acceptEdits \
      "${yasak_bayrak[@]}" \
      > "$gunluk" 2>&1; then
    yaz "   ✓ bitti · günlük: ${gunluk#"$KOK"/}"
  else
    yaz "   ✗ ÇIKIŞ KODU HATA · günlük: ${gunluk#"$KOK"/}"
    tail -5 "$gunluk" | sed 's/^/       /'
    tunel_denetle
    exit 3
  fi

  # Görev bir şey değiştirdiyse görünür olsun — commit etmek görevin işi.
  degisen=$(git -C "$KOK" status --porcelain | wc -l | tr -d ' ')
  [ "$degisen" != "0" ] && yaz "   ⚠️  $degisen dosya commit'lenmemiş"
done

tunel_denetle
yaz ""
yaz "══ BİTTİ · günlükler: .claude/kosu/$DAMGA-*.log"
