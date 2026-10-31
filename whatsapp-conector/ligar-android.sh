#!/data/data/com.termux/files/usr/bin/bash
# Liga o conector no celular (Termux). Mantém o celular acordado e religa se parar.
cd "$(dirname "$0")"
command -v node >/dev/null || pkg install -y nodejs-lts
[ -d node_modules ] || npm install --omit=dev
command -v termux-wake-lock >/dev/null && termux-wake-lock
while true; do
  node server.js
  echo "O conector parou. Religando em 5 segundos..."
  sleep 5
done
