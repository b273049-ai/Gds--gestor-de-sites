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

## Quem recebe o dinheiro (Marketplace)

- **Empresa conectada** (botão "🔗 Conectar MP" no painel): o pagamento cai **direto na conta
  Mercado Pago da empresa**. A plataforma (GDS) retém automaticamente a comissão `MP_COMISSAO_PERCENT`.
- **Empresa não conectada**: cai na conta da plataforma (`MP_ACCESS_TOKEN`). Para bloquear vendas de
  empresas não conectadas, defina `MP_EXIGIR_CONTA_EMPRESA=true`.

A conexão usa o OAuth do Mercado Pago: a empresa autoriza a sua aplicação e nós guardamos o token dela
na tabela `empresas_mercadopago` (inacessível pelo front). O token é renovado automaticamente.

## Como conectar no Mercado Pago (passo a passo)

### 1. Criar a aplicação da plataforma (uma vez só, com a SUA conta)

1. Entre em <https://www.mercadopago.com.br/developers/panel/app> → **Criar aplicação**.
2. Tipo de solução: **Pagamentos online** → **Checkout Pro**. Marque que é uma **plataforma/marketplace**
   (integração com OAuth).
3. Em **Configurações / OAuth**, cadastre a URL de redirecionamento:
   `https://<projeto>.supabase.co/functions/v1/mp-oauth-callback` (deixe PKCE desativado).
4. Em **Webhooks**, cadastre `https://<projeto>.supabase.co/functions/v1/mp-webhook`,
   marque o evento **Pagamentos** e copie a **assinatura secreta**.
5. Em **Credenciais de produção** (ou de teste), copie: **Client ID**, **Client Secret** e **Access Token**.

### 2. Configurar o Supabase

```bash
supabase db push   # ou rode os arquivos de supabase/migrations/ no SQL Editor, em ordem

supabase secrets set MP_CLIENT_ID=<client id>
supabase secrets set MP_CLIENT_SECRET=<client secret>
supabase secrets set MP_WEBHOOK_SECRET=<assinatura secreta do webhook>
supabase secrets set MP_ACCESS_TOKEN=<access token da sua conta>   # opcional: fallback
supabase secrets set MP_COMISSAO_PERCENT=10                          # opcional: comissão da plataforma
supabase secrets set SITE_PUBLIC_URL=https://seu-dominio.com         # URL onde o painel/sites rodam
supabase secrets set GDS_ADMIN_KEY=<a senha que você usa no login do painel>

supabase functions deploy mp-criar-pagamento
supabase functions deploy mp-oauth
supabase functions deploy mp-oauth-callback --no-verify-jwt
supabase functions deploy mp-webhook --no-verify-jwt
```

> `GDS_ADMIN_KEY` protege o botão "Conectar MP": sem ele, qualquer pessoa poderia ligar a própria
> conta a uma empresa e desviar as vendas. Use no login do painel a mesma senha definida aqui.

### 3. Conectar cada empresa

1. No painel, em **Empresas & Pagamentos**, clique em **🔗 Conectar MP** na empresa.
2. Envie o link gerado ao dono da empresa (botão **Enviar no WhatsApp**). O link vale 7 dias.
3. Ele abre o link, entra com a conta Mercado Pago **dele** e clica em **Permitir**.
4. Ele é levado para uma página "Conta Mercado Pago conectada!" e no painel a empresa aparece como
   **✅ Mercado Pago conectado**. Pronto: as próximas vendas da Vendedora IA caem na conta dele.

### Testando sem dinheiro de verdade

- Crie usuários de teste em **Suas integrações → Contas de teste** (um "vendedor" e um "comprador").
- Use as credenciais de teste da aplicação e defina `MP_OAUTH_TEST_TOKEN=true` para que a conexão
  OAuth gere tokens de teste. Conecte a empresa entrando com o usuário de teste vendedor.
- Pague com o usuário comprador e os cartões de teste do Mercado Pago.

No editor de cada site é possível ativar/desativar a Vendedora IA e trocar o nome dela.
