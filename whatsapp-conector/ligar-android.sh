#!/data/data/com.termux/files/usr/bin/bash
# Liga o conector no celular (Termux) e religa sozinho se ele parar.
# Para deixar SEMPRE ligado (inclusive quando o celular reinicia), rode uma vez:  bash sempre-ligado.sh
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

# Um só por celular: rodar de novo não abre um segundo (dois ficam se derrubando)
TRAVA=dados/ligar.pid
p=$(cat "$TRAVA" 2>/dev/null)
if [ -n "$p" ] && [ "$p" != "$$" ] && kill -0 "$p" 2>/dev/null && eh_processo "$p" laco; then
  echo "O conector já está ligado (processo $p). Para ver o que ele está fazendo: tail -f $(pwd)/conector.log"
  exit 0
fi
echo $$ > "$TRAVA"
trap 'rm -f "$TRAVA"' EXIT
rm -f dados/desligado   # ligou na mão: volta a valer o vigia

command -v node >/dev/null || pkg install -y nodejs-lts
[ -d node_modules ] || npm install --omit=dev
command -v termux-wake-lock >/dev/null && termux-wake-lock
[ -f "$HOME/.termux/boot/gds-conector" ] || echo "Dica: para ligar sozinho quando o celular reinicia, rode uma vez: bash sempre-ligado.sh"
echo "Dica: em Configurações → Apps → Termux → Bateria, escolha \"Sem restrições\" (senão o Android desliga o conector)."
# Android 12 em diante fecha processos do Termux em segundo plano ("signal 9"): mostra o ajuste certo
sdk=$(getprop ro.build.version.sdk 2>/dev/null)
if [ "${sdk:-0}" -ge 34 ] 2>/dev/null; then
  echo "Dica (Android 14+): Opções do desenvolvedor → ative \"Desativar restrições de processos filhos\" e reinicie o celular."
elif [ "${sdk:-0}" -ge 31 ] 2>/dev/null; then
  echo "Dica (Android 12/13): leia \"Se o WhatsApp cair muito\" no LEIA-ME.md (um comando adb evita que o Android feche o conector)."
fi

LOG="$(pwd)/conector.log"
while true; do
  # o registro não cresce sem fim (guarda o atual e o anterior, até ~2 MB cada)
  if [ -f "$LOG" ] && [ "$(wc -c < "$LOG")" -gt 2000000 ]; then mv -f "$LOG" "$LOG.1"; fi
  if [ -t 1 ]; then node server.js 2>&1 | tee -a "$LOG"; else node server.js >> "$LOG" 2>&1; fi
  echo "$(date '+%F %T') O conector parou. Religando em 5 segundos..." >> "$LOG"
  [ -t 1 ] && echo "O conector parou. Religando em 5 segundos..."
  sleep 5
done
