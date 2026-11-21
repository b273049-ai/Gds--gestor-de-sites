# GDS — Gestor de Sites

Plataforma SaaS (PHP + PostgreSQL/Supabase) para criar e gerenciar sites de pequenos negócios
(barbearias, clínicas, oficinas…) com agendamento online, CRM simples, cobrança PIX e um
**Estúdio IA** que cria e edita o site inteiro conversando — no estilo Lovable.

- `gds/` — a plataforma (painel admin, painel da empresa, site público). Instalação em [`gds/LEIA-ME.md`](gds/LEIA-ME.md).
- `src/` — protótipo antigo em React/Vite que falava direto com o Supabase. **Não é usado pela plataforma PHP.**
  Atenção: o login dele aceita qualquer e-mail/senha e ele grava no Supabase com a anon key; se ele apontar para
  o mesmo projeto do GDS, rode `gds/sql/adicional_11_rls_seguranca.sql` (ele deixa de funcionar com essas tabelas).

## Rodando localmente (teste)

```bash
# Postgres local, depois rode os .sql de gds/sql na ordem do LEIA-ME
cd gds
GDS_DB_HOST=127.0.0.1 GDS_DB_PORT=5432 GDS_DB_NAME=gds GDS_DB_USER=postgres GDS_DB_PASS=senha \
GDS_DB_SSLMODE=disable GDS_DEBUG=1 php -S 127.0.0.1:8080
# abra http://127.0.0.1:8080/install.php para criar o admin
```
(O servidor embutido do PHP não lê `.htaccess`; em produção Apache/LiteSpeed cuidam das URLs `/nome-da-empresa`.)
