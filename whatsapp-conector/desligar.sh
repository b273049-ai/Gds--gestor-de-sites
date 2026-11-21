#!/data/data/com.termux/files/usr/bin/bash
# Desliga o conector DE VEZ (o vigia e o celular reiniciando não ligam de novo).
# Para voltar a deixar sempre ligado: bash sempre-ligado.sh
cd "$(dirname "$0")" || exit 1
# É o laço do conector ("bash .../ligar-android.sh") ou o conector ("node server.js")? Compara o programa e o
# arquivo exatos (não basta o nome aparecer no comando: "nano ligar-android.sh" não é o conector)
eh_processo() { # eh_processo <pid> laco|node
  local a0 a1
  a0=$(tr '\0' '\n' < "/proc/$1/cmdline" 2>/dev/null | sed -n 1p); a1=$(tr '\0' '\n' < "/proc/$1/cmdline" 2>/dev/null | sed -n 2p)
  case "$2:${a0##*/}:${a1##*/}" in laco:bash:ligar-android.sh|laco:sh:ligar-android.sh|node:node:server.js) return 0 ;; esac
  return 1
}
mkdir -p dados
touch dados/desligado
rm -f "$HOME/.termux/boot/gds-conector"
command -v termux-job-scheduler >/dev/null && timeout 15 termux-job-scheduler --cancel --job-id 4747 >/dev/null 2>&1
for d in /proc/[0-9]*; do pid="${d#/proc/}"; [ "$pid" != "$$" ] && eh_processo "$pid" laco && kill "$pid" 2>/dev/null; done
sleep 1
for d in /proc/[0-9]*; do pid="${d#/proc/}"; eh_processo "$pid" node && kill "$pid" 2>/dev/null; done
command -v termux-wake-unlock >/dev/null && termux-wake-unlock
echo "Conector desligado. Para ligar de novo e deixar sempre ligado: bash sempre-ligado.sh"
