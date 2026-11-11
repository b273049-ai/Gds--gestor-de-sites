#!/data/data/com.termux/files/usr/bin/bash
# Vigia do conector: o Android chama este arquivo a cada ~15 minutos (termux-job-scheduler,
# configurado pelo sempre-ligado.sh). Se o conector não estiver rodando, liga de novo.
cd "$(dirname "$0")" || exit 0
# É o laço do conector ("bash .../ligar-android.sh") ou o conector ("node server.js")? Compara o programa e o
# arquivo exatos (não basta o nome aparecer no comando: "nano ligar-android.sh" não é o conector)
eh_processo() { # eh_processo <pid> laco|node
  local a0 a1
  a0=$(tr '\0' '\n' < "/proc/$1/cmdline" 2>/dev/null | sed -n 1p); a1=$(tr '\0' '\n' < "/proc/$1/cmdline" 2>/dev/null | sed -n 2p)
  case "$2:${a0##*/}:${a1##*/}" in laco:bash:ligar-android.sh|laco:sh:ligar-android.sh|node:node:server.js) return 0 ;; esac
  return 1
}
[ -f dados/desligado ] && exit 0   # desligado de propósito (desligar.sh)
p=$(cat dados/ligar.pid 2>/dev/null)
if [ -n "$p" ] && kill -0 "$p" 2>/dev/null && eh_processo "$p" laco; then
  exit 0   # está ligado: nada a fazer
fi
echo "$(date '+%F %T') vigia: o conector estava desligado; ligando de novo" >> conector.log
command -v termux-wake-lock >/dev/null && termux-wake-lock
nohup bash ./ligar-android.sh > /dev/null 2>&1 &
exit 0
