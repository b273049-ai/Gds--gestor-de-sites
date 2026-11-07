#!/data/data/com.termux/files/usr/bin/bash
# ============================================================
# CONECTOR WHATSAPP GDS SEMPRE LIGADO — celular Android com Termux
#
# Rode UMA vez (o endereço e a chave estão em GDS → Admin → WhatsApp):
#     bash sempre-ligado.sh https://SEU-SITE SUA_CHAVE
#
# Ele:
#   1) para qualquer conector que já esteja rodando (não deixa dois se derrubando);
#   2) instala o que falta (Node.js, termux-api) e grava o endereço/chave no ponte.txt;
#   3) liga o conector SOZINHO quando o celular reinicia (app Termux:Boot);
#   4) agenda um vigia no Android a cada 15 min que religa o conector se o Android o fechar (app Termux:API);
#   5) liga o conector agora, em segundo plano (pode fechar a tela do Termux).
# Pode rodar de novo quantas vezes quiser (por exemplo depois de atualizar o conector).
# O login do WhatsApp fica na pasta "dados": não apague.
# ============================================================
set -u
cd "$(dirname "$0")" || exit 1
PASTA="$(pwd)"
SITE="${1:-}"; CHAVE="${2:-}"

echo "== Conector WhatsApp GDS: deixando sempre ligado =="

# É o laço do conector ("bash .../ligar-android.sh") ou o conector ("node server.js")? Compara o programa e o
# arquivo exatos (não basta o nome aparecer no comando: "nano ligar-android.sh" não é o conector)
eh_processo() { # eh_processo <pid> laco|node
  local a0 a1
  a0=$(tr '\0' '\n' < "/proc/$1/cmdline" 2>/dev/null | sed -n 1p); a1=$(tr '\0' '\n' < "/proc/$1/cmdline" 2>/dev/null | sed -n 2p)
  case "$2:${a0##*/}:${a1##*/}" in laco:bash:ligar-android.sh|laco:sh:ligar-android.sh|node:node:server.js) return 0 ;; esac
  return 1
}

# 1) Para o que estiver rodando (versões antigas não tinham trava)
for d in /proc/[0-9]*; do pid="${d#/proc/}"; [ "$pid" != "$$" ] && eh_processo "$pid" laco && kill "$pid" 2>/dev/null; done
sleep 1
for d in /proc/[0-9]*; do pid="${d#/proc/}"; eh_processo "$pid" node && kill "$pid" 2>/dev/null; done
sleep 2
mkdir -p dados && rm -f dados/desligado

# 2) Endereço e chave
if [ -n "$SITE" ] && [ -n "$CHAVE" ]; then
  printf '# Preenchido pelo sempre-ligado.sh (GDS → Admin → WhatsApp)\nSITE=%s\nCHAVE=%s\n' "$SITE" "$CHAVE" > ponte.txt
fi
if ! grep -q '^SITE=http' ponte.txt 2>/dev/null || grep -q 'COLE-AQUI' ponte.txt 2>/dev/null; then
  echo "Falta o endereço do site e a chave. Copie o comando pronto em GDS → Admin → WhatsApp, ou rode:"
  echo "    bash sempre-ligado.sh https://seusite.com.br SUA_CHAVE"
  exit 1
fi

echo "1/4 Instalando o que falta (só na primeira vez)…"
command -v node >/dev/null || pkg install -y nodejs-lts
command -v termux-job-scheduler >/dev/null || pkg install -y termux-api
[ -d node_modules ] || npm install --omit=dev
chmod +x ligar-android.sh vigia.sh 2>/dev/null

echo "2/4 Ligando junto com o celular (Termux:Boot)…"
mkdir -p "$HOME/.termux/boot"
cat > "$HOME/.termux/boot/gds-conector" <<EOF
#!/data/data/com.termux/files/usr/bin/sh
# Criado pelo sempre-ligado.sh do Conector WhatsApp GDS: liga o conector quando o celular reinicia
termux-wake-lock
sleep 20
(timeout 30 termux-job-scheduler --job-id 4747 --script "$PASTA/vigia.sh" --period-ms 900000 --network any >/dev/null 2>&1 &)
cd "$PASTA" && exec bash ./ligar-android.sh
EOF
chmod +x "$HOME/.termux/boot/gds-conector"

echo "3/4 Vigia a cada 15 minutos (religa se o Android fechar o conector)…"
VIGIA=nao
if command -v termux-job-scheduler >/dev/null && timeout 30 termux-job-scheduler --job-id 4747 --script "$PASTA/vigia.sh" --period-ms 900000 --network any >/dev/null 2>&1; then
  VIGIA=sim
fi

echo "4/4 Ligando o conector agora…"
command -v termux-wake-lock >/dev/null && termux-wake-lock
nohup bash ./ligar-android.sh > /dev/null 2>&1 &
sleep 8

echo
if grep -q "Conectado ao site" conector.log 2>/dev/null; then echo "✅ Conector ligado e falando com o site."; else echo "⚠ O conector ligou, mas ainda não falou com o site. Veja: tail -f $PASTA/conector.log"; fi
echo "✅ Liga sozinho quando o celular reinicia — se o app Termux:Boot estiver instalado (F-Droid) e tiver sido aberto 1 vez."
if [ "$VIGIA" = sim ]; then echo "✅ Vigia a cada 15 minutos ativo."; else echo "⚠ Vigia não ativou: instale o app Termux:API pelo F-Droid e rode este comando de novo."; fi
echo
echo "Para nada desligar o conector, no celular:"
echo "  • Configurações → Apps → Termux, Termux:Boot e Termux:API → Bateria → \"Sem restrições\"."
echo "  • Xiaomi/Samsung/Motorola: permita \"Inicialização automática\" para o Termux:Boot."
sdk=$(getprop ro.build.version.sdk 2>/dev/null)
if [ "${sdk:-0}" -ge 34 ] 2>/dev/null; then
  echo "  • Android 14+: Opções do desenvolvedor → \"Desativar restrições de processos filhos\" → reinicie."
elif [ "${sdk:-0}" -ge 31 ] 2>/dev/null; then
  echo "  • Android 12/13: veja \"Se o WhatsApp cair muito\" no LEIA-ME.md (comando adb)."
fi
echo
echo "Ver o que o conector está fazendo:  tail -f $PASTA/conector.log"
echo "Desligar de vez:  bash $PASTA/desligar.sh"
