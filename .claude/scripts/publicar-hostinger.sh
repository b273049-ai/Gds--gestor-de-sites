#!/usr/bin/env bash
# ============================================================
# Publica arquivos do GDS na Hostinger (sitegestor.com) pelo protocolo TUS.
# Envia a versão do ÚLTIMO COMMIT (não o arquivo solto da pasta), confere cada envio
# e no fim esvazia o arquivo de credenciais.
#
# Uso:
#   .claude/scripts/publicar-hostinger.sh <arquivo-credenciais> <caminho relativo a gds/> [...]
#   ex.: .claude/scripts/publicar-hostinger.sh "$SCRATCH/up.env" includes/whatsapp_ponte.php admin/whatsapp.php
#
# O arquivo de credenciais (criado com umask 077, fora do repositório) tem 3 linhas, com os
# valores de hosting_files_generate-upload-url:
#   URL='https://…/api/tus/public_html'
#   AK='<auth_key>'
#   RK='<rest_auth_key>'
#
# Regras (CLAUDE.md, regra 11): antes, compare o tamanho ao vivo com a versão anterior no Git;
# nunca envie config/ nem uploads/ (este script recusa).
# ============================================================
set -u
CRED="${1:-}"
[ -n "$CRED" ] && [ -f "$CRED" ] || { echo "Uso: $0 <arquivo-credenciais> <arquivos…>"; exit 2; }
shift
[ $# -gt 0 ] || { echo "Informe os arquivos (caminhos relativos a gds/)."; exit 2; }
# shellcheck disable=SC1090
. "$CRED"
: "${URL:?faltou URL no arquivo de credenciais}" "${AK:?faltou AK}" "${RK:?faltou RK}"
cd "$(git rev-parse --show-toplevel)" || exit 2
TMP="$(mktemp)"
trap ': > "$CRED"; rm -f "$TMP"' EXIT
falhas=0
for f in "$@"; do
  f="${f#gds/}"; f="${f#/}"
  case "$f" in
    config/*|uploads/*|*..*) echo "RECUSADO $f (config/ e uploads/ nunca sobem)"; falhas=$((falhas+1)); continue ;;
  esac
  if ! git show "HEAD:gds/$f" > "$TMP" 2>/dev/null; then echo "FALTA $f no último commit (faça o commit antes)"; falhas=$((falhas+1)); continue; fi
  n=$(wc -c < "$TMP" | tr -d ' ')
  c=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$URL/$f?override=true" -H "X-Auth: $AK" -H "X-Auth-Rest: $RK" \
      -H 'Tus-Resumable: 1.0.0' -H "Upload-Length: $n" -H 'Upload-Offset: 0')
  o=$(curl -s -D - -o /dev/null -X PATCH "$URL/$f" -H "X-Auth: $AK" -H "X-Auth-Rest: $RK" -H 'Tus-Resumable: 1.0.0' \
      -H 'Upload-Offset: 0' -H 'Content-Type: application/offset+octet-stream' --data-binary "@$TMP" \
      | grep -i '^upload-offset' | tr -d '\r' | awk '{print $2}')
  if [ "$c" = 201 ] && [ "$o" = "$n" ]; then echo "ok $f ($n bytes)"; else echo "FALHOU $f (criar=$c enviado=${o:-0}/$n)"; falhas=$((falhas+1)); fi
done
[ "$falhas" -eq 0 ] && echo "Tudo publicado." || echo "$falhas arquivo(s) com problema."
exit "$falhas"
