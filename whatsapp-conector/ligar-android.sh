#!/data/data/com.termux/files/usr/bin/bash
# Liga o conector no celular (Termux). Mantém o celular acordado, religa se parar
# e (com o app Termux:Boot instalado) liga sozinho quando o celular reinicia.
cd "$(dirname "$0")"
command -v node >/dev/null || pkg install -y nodejs-lts
[ -d node_modules ] || npm install --omit=dev
command -v termux-wake-lock >/dev/null && termux-wake-lock
# Termux:Boot: cria o atalho para ligar junto com o celular
if [ ! -f "$HOME/.termux/boot/gds-conector" ]; then
  mkdir -p "$HOME/.termux/boot"
  printf '#!/data/data/com.termux/files/usr/bin/sh\ntermux-wake-lock\ncd "%s" && bash ./ligar-android.sh >> conector.log 2>&1\n' "$(pwd)" > "$HOME/.termux/boot/gds-conector"
  chmod +x "$HOME/.termux/boot/gds-conector"
fi
echo "Dica: em Configurações → Apps → Termux → Bateria, escolha \"Sem restrições\" (senão o Android desliga o conector)."
# Android 12 em diante fecha processos do Termux em segundo plano ("signal 9"): mostra o ajuste certo
sdk=$(getprop ro.build.version.sdk 2>/dev/null)
if [ "${sdk:-0}" -ge 34 ] 2>/dev/null; then
  echo "Dica (Android 14+): Opções do desenvolvedor → ative \"Desativar restrições de processos filhos\" e reinicie o celular."
elif [ "${sdk:-0}" -ge 31 ] 2>/dev/null; then
  echo "Dica (Android 12/13): leia \"Se o WhatsApp cair muito\" no LEIA-ME.md (um comando adb evita que o Android feche o conector)."
fi
while true; do
  node server.js
  echo "O conector parou. Religando em 5 segundos..."
  sleep 5
done
