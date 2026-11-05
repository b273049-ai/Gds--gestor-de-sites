# React + TypeScript + Vite

This template provides a minimal setup to get React working in Vite with HMR and some Oxlint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Oxc](https://oxc.rs)
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/)

## React Compiler

The React Compiler is not enabled on this template because of its impact on dev & build performances. To add it, see [this documentation](https://react.dev/learn/react-compiler/installation).

## Expanding the Oxlint configuration

If you are developing a production application, we recommend enabling type-aware lint rules by installing `oxlint-tsgolint` and editing `.oxlintrc.json`:

```json
{
  "$schema": "./node_modules/oxlint/configuration_schema.json",
  "plugins": ["react", "typescript", "oxc"],
  "options": {
    "typeAware": true
  },
  "rules": {
    "react/rules-of-hooks": "error",
    "react/only-export-components": ["warn", { "allowConstantExport": true }]
  }
}
```

See the [Oxlint rules documentation](https://oxc.rs/docs/guide/usage/linter/rules) for the full list of rules and categories.

---

# 🤖 Vendedora IA + Mercado Pago

Cada site publicado ganha uma **Vendedora IA** (chat no canto da página pública) que apresenta os
serviços, responde dúvidas (preços, horário, formas de pagamento) e fecha a venda com
**Mercado Pago Checkout Pro** (Pix, cartão e boleto). As vendas aparecem no painel ADMIN em
"Vendas da Vendedora IA".

## Como funciona

1. O cliente escolhe um serviço no chat e informa nome/e-mail.
2. O front chama a Edge Function `mp-criar-pagamento`, que lê o preço **do banco** (nunca do navegador),
   cria um registro em `pedidos` e uma preferência no Mercado Pago, devolvendo o `init_point`.
3. O cliente paga no Checkout Pro e volta para `?site=ID&pagamento=sucesso|pendente|falha`.
4. O Mercado Pago notifica `mp-webhook`, que valida a assinatura `x-signature` (HMAC-SHA256),
   consulta o pagamento na API e atualiza o status do pedido (`pago`, `pendente`, `recusado`...).

O Access Token **nunca** vai para o front — fica só nos secrets das Edge Functions.

## Configuração

```bash
# 1. Tabela de pedidos
supabase db push            # ou rode supabase/migrations/*_mercado_pago_vendedora_ia.sql no SQL Editor

# 2. Secrets (Mercado Pago > Suas integrações > Credenciais / Webhooks)
supabase secrets set MP_ACCESS_TOKEN=APP_USR-xxxxx
supabase secrets set MP_WEBHOOK_SECRET=xxxxx
supabase secrets set SITE_PUBLIC_URL=https://seu-dominio.com   # opcional, usada nas URLs de retorno

# 3. Deploy das funções
supabase functions deploy mp-criar-pagamento
supabase functions deploy mp-webhook --no-verify-jwt
```

No painel do Mercado Pago, em **Webhooks**, cadastre a URL
`https://<projeto>.supabase.co/functions/v1/mp-webhook` com o evento **Pagamentos**.

Para testar, use credenciais de teste e usuários/cartões de teste do Mercado Pago.

No editor de cada site é possível ativar/desativar a Vendedora IA e trocar o nome dela.
