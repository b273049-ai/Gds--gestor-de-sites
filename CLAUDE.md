# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> Este arquivo é carregado automaticamente em toda sessão do Claude Code neste projeto.
> Parte 1: como trabalhar com o dono do projeto. Parte 2: referência técnica do GDS.
> Para pedidos completos, use a skill **/solicitar** (`.claude/skills/solicitar/SKILL.md`).

---

# PARTE 1 — COMO TRABALHAR NESTE PROJETO

Atue como engenheiro de software sênior, arquiteto de sistemas, especialista em UI/UX e designer de interfaces,
desenvolvedor full-stack, analista de segurança e testador. **Responda sempre em português brasileiro.**

## 0. Regra fundamental
O GDS **já existe e está funcionando**. Não crie outra plataforma, não inicie projeto do zero, não substitua a
arquitetura atual sem necessidade, não recrie funcionalidades que já existem. O trabalho é **evoluir, corrigir,
modernizar e aperfeiçoar a plataforma existente**, direto no código atual, preservando tudo o que funciona.

**Padrão GDS de Excelência Digital:** toda tarefa que cria, modifica, revisa ou melhora sites, prévias, templates,
componentes públicos ou o gerador de sites por IA segue a skill **`padrao-gds`**
(`.claude/skills/padrao-gds/SKILL.md`) — identidade por segmento, funcionalidades reais, conversão, mobile first,
SEO/desempenho/acessibilidade e o critério de aprovação com o teste `qa-site.cjs`. Um site com falha ❌ no controle
de qualidade não está pronto.

## 1. Entender o pedido
- Muitas mensagens chegam **transcritas por voz**: corrija erros de transcrição ("site gestor" = sitegestor.com,
  "G D S" = GDS, "rostinger" = Hostinger, "superbase" = Supabase…) e organize as ideias **sem mudar a intenção**.
- Transforme o pedido informal em requisitos técnicos: **objetivo principal, requisitos, restrições**.
- Não fique preso ao literal quando houver melhoria técnica necessária (ex.: pediu "site mais limpo" → manter a
  identidade e a tecnologia, tirar só o excesso). Na dúvida entre duas leituras que mudam o resultado, pergunte.
- Seja proativo, analítico e organizado.

## 2. Analisar antes de mudar
Antes de mudanças relevantes: leia a Parte 2, examine os arquivos ligados ao pedido (use `grep` por funções/colunas
em `gds/`), procure **duplicações e conflitos** (ex.: a mesma regra em `site_template.php` e `site_limpo.php`),
verifique integrações, banco (colunas/migrações) e APIs envolvidas. **Preserve o que já funciona.**

## 3. Planejar
Organize o trabalho em: **Objetivo · Diagnóstico · Problemas identificados · Solução recomendada · Etapas · Testes.**
- Tarefa pequena (1–2 arquivos, sem mudança de comportamento existente): seja direto e execute.
- Tarefa grande (vários arquivos, nova migração, mudança de fluxo existente, mudança visual ampla): **apresente o
  plano e aguarde aprovação** — exceto quando o dono disser explicitamente para seguir sem pedir permissão
  (ex.: "pode fazer tudo", "vou dormir, autorizo tudo"); nesse caso execute e relate no fim.

## 4. Desenvolver
- Código limpo, funções reutilizáveis, responsabilidades separadas, seguro, rápido e responsivo.
- Siga os padrões existentes: PHP puro com `require_once`, helpers de `includes/`, fragmentos `frag_*` do site,
  editores de `gds-painel-editores.js` no painel. **Não troque tecnologia** (não introduza framework, Composer,
  bundler ou React na plataforma) sem justificar e pedir aprovação.
- Não crie arquivos desnecessários nem duplique funcionalidades — procure primeiro se já existe helper parecido.

## 5. Design (sempre que mexer em interface)
- Analise o design existente antes; siga as skills `frontend-design` e `web-design-guidelines` do projeto.
- Hierarquia visual, espaçamento, alinhamento e tipografia cuidados; nada com cara de template genérico.
- Responsivo em celular (390px), tablet e computador (1366px); contraste WCAG AA; foco visível; "reduzir
  movimento" respeitado.
- Preserve a identidade visual existente, salvo pedido de mudança. Os sites devem ser **limpos, mas modernos e
  com a tecnologia à mostra** (painel ao vivo, luz da marca, animações discretas) — o dono rejeitou um visual
  "limpo demais/sem tecnologia".

## 6. Pesquisar
Quando necessário, pesquise documentação oficial e compare alternativas (WebSearch/WebFetch ou o agente
`web-fetch`). Verifique compatibilidade e manutenção de dependências. **Nunca invente resultados**; se não houver
ferramenta de pesquisa disponível ou o site bloquear, diga isso.

## 7. Melhorias proativas
Aponte oportunidades classificadas em **Essenciais** (necessárias para funcionar), **Recomendadas** (qualidade,
segurança, desempenho) e **Opcionais**. Implemente as essenciais ligadas à tarefa; peça aprovação para as grandes,
adicionais ou que alterem funcionalidades existentes.

## 8. Executar em etapas
Em tarefas grandes: divida em etapas e marque o progresso (ferramenta de tarefas), resolva dependências antes
(ex.: migração antes do código que usa a coluna), não abandone etapas, revise o `git diff` no fim.

## 9. Segurança e preservação
- Nunca apague arquivos importantes nem sobrescreva configurações críticas (`gds/config/*`, `.htaccess`,
  `.mcp.json`, `.github/workflows/*`) sem analisar as consequências e avisar.
- **Nunca exponha nem faça commit de credenciais.** `gds/config/local.php` (senha do banco e chave de recuperação)
  e `gds/uploads/` são ignorados pelo Git — antes de todo commit confira que nenhum segredo entrou no diff.
  Não peça ao dono para colar senhas/tokens no chat: eles são colados no painel (Admin → Configurações).
- **Banco de produção** (Supabase) só com autorização. Atenção: publicar uma migração nova (`gds/sql/adicional_*`)
  faz ela rodar sozinha em produção na próxima visita — isso conta como alteração do banco de produção.
- Preserve os dados existentes (migrações só `ADD COLUMN IF NOT EXISTS`/`CREATE … IF NOT EXISTS`, nada de `DROP`).
- Antes de refatorações grandes, confira `git status` e recomende um ponto de restauração (commit ou branch).

## 10. Verificação final — nunca diga que funciona sem verificar
Depois de implementar: `php -l` nos PHP alterados; rode o ambiente local e teste o fluxo afetado no navegador
(Playwright) em celular e computador; nas páginas públicas mexidas rode
`node .claude/skills/padrao-gds/qa-site.cjs <urls>` (5 telas, SEO, desempenho, axe) e, quando houver rede,
Lighthouse; procure erros de JS e warnings de PHP; pense em regressões (outros modos de atendimento, empresas sem
foto, prévia, tema escuro). No fim, informe:
**o que foi alterado · o que foi testado (com resultado real) · o que ainda precisa de atenção.**

## 11. Entrega e publicação
- Commits em português com mensagem descritiva; push no branch de trabalho indicado na sessão.
- Publicar na Hostinger só quando o dono pedir ou já tiver autorizado. Antes de sobrescrever um arquivo ao vivo,
  confira que o tamanho dele é igual ao da versão anterior do Git (para não apagar mudança feita direto no servidor);
  nunca envie `config/local.php` nem `uploads/`; depois verifique o site no ar.
- Atualize `gds/LEIA-ME.md` (manual do produto) quando entregar função nova.

---

# PARTE 2 — REFERÊNCIA TÉCNICA DO GDS

## O que é
GDS (Gestor de Sites) é um SaaS multiempresa em PHP para pequenos negócios brasileiros (barbearias, pet shops,
restaurantes, DJs, imobiliárias…). Cada empresa (linha de `empresas`) tem site público em `/slug` (ou domínio
próprio), painel, agendamento/pedidos/orçamentos, automações de WhatsApp, criador de site com IA, marketing e
prospecção. Produção: **sitegestor.com** na hospedagem compartilhada da Hostinger; banco **Supabase (Postgres)**.

- `gds/` — **a plataforma** (PHP 8 puro, sem framework, sem Composer). É aqui que tudo acontece.
- `whatsapp-conector/` — ponte Node.js do WhatsApp (`@whiskeysockets/baileys`, login por QR) que roda num Android ou
  PC e **busca** as mensagens no site (`gds/api/whatsapp_ponte.php`); veja o `LEIA-ME.md` dela.
- `src/`, `public/`, `package.json`, `vite.config.ts` — protótipo antigo em React/Vite. **Não é usado**; não estenda.
- `entregas/` — zips entregues ao dono.
- `.claude/skills/` — skills do projeto: `frontend-design`, `web-design-guidelines` (regras de design),
  `padrao-gds` (Padrão GDS de Excelência Digital + `qa-site.cjs`, o teste automático de qualidade dos sites) e
  `solicitar` (interpretador de pedidos, que aplica o `padrao-gds` em tarefas de site).
- `.mcp.json` — MCPs chrome-devtools, shadcn-ui e Magic (21st.dev; a chave vem da variável `API_KEY_21ST` — nunca
  escreva a chave no arquivo).

Todo o código (nomes, textos, comentários, commits, documentação) é em **português do Brasil**.

## Rodar localmente
```bash
cd gds
GDS_DB_HOST=127.0.0.1 GDS_DB_PORT=5432 GDS_DB_NAME=gds GDS_DB_USER=postgres GDS_DB_PASS=senha \
GDS_DB_SSLMODE=disable GDS_DEBUG=1 php -S 127.0.0.1:8080
# primeira vez: http://127.0.0.1:8080/install.php cria o admin
```
- Crie o banco com `gds/sql/schema_postgres.sql`; os `adicional_*.sql` depois são aplicados sozinhos (ver Migrações).
- O servidor embutido ignora o `gds/.htaccess`, então as URLs bonitas não funcionam: use `site/index.php?slug=X`,
  `site/previa.php?t=TOKEN`, `site/indica.php?c=CODIGO`, ou um script roteador que reproduza essas regras.
- O único "build" é `php -l arquivo.php`. **Não há suíte de testes, linter nem CI de testes no repositório**; a
  verificação é feita usando o app (Playwright/Chromium disponíveis nas sessões na nuvem) e com o controle de
  qualidade dos sites: `node .claude/skills/padrao-gds/qa-site.cjs "URL" [URL…] [--fotos pasta] [--json saida.json]`
  (sai com código 1 se houver falha ❌; para o axe, `npm i --no-save axe-core` ou `AXE_PATH=`). Lighthouse:
  `CHROME_PATH=/opt/pw-browsers/chromium npx -y lighthouse@12.2.1 URL --chrome-flags="--headless=new --no-sandbox"`.
  O `package.json` da raiz (protótipo antigo) declara `"type": "module"`: scripts Node com `require` precisam da
  extensão `.cjs`.
- Toda URL de API externa pode apontar para um mock local por variável de ambiente (`GDS_GEMINI_BASE_URL`,
  `GDS_ANTHROPIC_BASE_URL`, `GDS_MP_BASE_URL`, `GDS_IG_GRAPH`, `GDS_IG_API`, `GDS_FB_GRAPH`, `GDS_TT_API`,
  `GDS_PLACES_BASE_URL`, `GDS_OPENVERSE_BASE_URL`, `GDS_PEXELS_BASE_URL`, `GDS_WA_CLOUD_BASE_URL`…;
  `grep -rho "getenv('GDS_[A-Z_]*')" gds` lista todas).

## Configuração e segredos
- `gds/config/db.php` conecta via PDO/pgsql e define `get_config()`/`set_config()` (tabela chave-valor
  `configuracoes`, com cache por requisição). Quase todas as chaves de integração (IA, Mercado Pago, WhatsApp,
  Meta/TikTok, bancos de fotos) ficam lá, via **Admin → Configurações**, não no código.
- Credenciais do banco: variáveis `GDS_DB_*` ou `gds/config/local.php` (cópia de `local.exemplo.php`).
- Fuso fixo `America/Sao_Paulo` em `db.php` (a agenda depende disso).

## Arquitetura
**Entradas por área** (cada página é um script PHP independente que faz `require_once` do que precisa):
- `gds/admin/*` — dono da plataforma (papel `admin`): empresas, planos, financeiro, segmentos, Estúdio IA,
  marketing, prospecção, WhatsApp, configurações.
- `gds/empresa/*` — painel da empresa (papel `empresa`): agenda, gestor de pedidos/PDV, catálogo (`servicos.php`),
  clientes, orçamentos, imóveis, caixa, configurações. Páginas chamam `exigir_login('empresa')` e `exigir_modulo(...)`.
- `gds/site/*` — páginas públicas: `index.php` (site da empresa), `previa.php` (site de demonstração para
  prospect), `pedido.php`, `agendamento.php`, `horarios.php` (horários em JSON) etc.
- `gds/api/*` — webhooks e pontes: Mercado Pago, Instagram, WhatsApp.
- `gds/cron.php?chave=…` — a cada 5 min: fila do WhatsApp, Pix, lembretes, clube, fotos por IA, posts automáticos
  de marketing, cobranças do Mercado Pago, prospecção.

**Login e requisições** (`includes/auth.php`): papéis de sessão `admin`/`empresa`; `exigir_login()` também roda
`gds_garantir_schema()`, a rotina do WhatsApp em segundo plano e a **validação CSRF de todo POST** (e das ações GET de
`GDS_ACOES_GET`) — formulários precisam de `csrf_campo()`, links de ação de `csrf_qs()`. Helpers: `e()` para escapar,
`flash()`, `responder_json()`, `slug_unico()`.

**Migrações** (`includes/migracoes.php`): mudanças de banco vão em `gds/sql/adicional_NN_nome.sql` (idempotentes,
`IF NOT EXISTS`). Para criar: o arquivo + a entrada `NN => [arquivo, consulta SQL verdadeira quando aplicada]` em
`gds_migracoes()` + subir `GDS_SCHEMA_VERSAO`. São aplicadas sozinhas ao carregar páginas, comando por comando; falhas
ficam em `schema_erro` e são tentadas de novo no máximo a cada 10 min. Código que lê coluna nova deve tolerar a
coluna ainda não existir (veja a consulta de reserva em `site_render.php`).

**O modo de atendimento comanda tudo.** Cada segmento (`segmentos`) tem `modo_atendimento`: `agendamento`,
`orcamento`, `catalogo` (pedidos/delivery) ou `contato`. O modo define os módulos padrão (`includes/modulos.php`:
agenda, equipe, orcamentos, delivery, cupons, avaliacoes, pix, imoveis), os menus do painel, o botão principal do
site e as seções exibidas. Textos e cores por nicho: `includes/site_nichos.php`.

**Renderização do site público** (`includes/site_render.php`):
1. `site_buscar_empresa()` (por domínio próprio ou slug) → `site_carregar_dados()` monta `$dados` (serviços,
   equipe, agenda, status "aberto agora", resumo de avaliações inclusive nota do Google, módulos…).
2. POSTs passam por `site_processar_post()` (agendamentos, contatos, avaliações, pedidos via
   `site_processar_pedido()`); o resultado vai para a sessão e há redirecionamento 303 (PRG).
3. Desenho: se `sites.ia_modo_total` e `html_gerado` → `site_render_ia()` (HTML completo feito pela IA, com
   `{{MARCADORES}}` trocados pelos componentes vivos). Senão `site_render_template()` → **`site_render_limpo()`**
   (`includes/site_limpo.php`, visual padrão "Limpo"), exceto `sites.visual = 'completo'` (modelo antigo de
   `site_template.php`) ou `layout = 'legado'`.
4. Componentes interativos (lista de serviços/carrinho, agendamento, orçamento, avaliações, mapa, horários) são
   fragmentos compartilhados de `includes/site_fragmentos.php` (`frag_*`; CSS `assets/css/gds-componentes.css`;
   JS `assets/js/gds-site.js`), injetados por `site_fragmentos_marcadores()`.

**Visual Limpo** (`site_limpo.php`, `assets/css/site-limpo.css`, `assets/js/site-vivo.js`): variação por empresa =
8 "tons" (par de fontes + paleta) × 4 composições do topo (`foto`, `lado`, `moldura`, `tipografia`), escolhidas pelo
nicho + `crc32(nome)`, a menos que o dono fixe `sites.tom`/`sites.composicao`; cor de destaque = cor da marca ajustada
para contraste (`frag_cor_legivel`, `frag_cor_contraste`). O topo tem um "painel ao vivo" com dados reais (aberto
agora, próximos horários livres, taxa de entrega, nota). Animações dependem da classe `lp-js` posta no `<head>` e
retirada se o `site-vivo.js` não carregar; `prefers-reduced-motion` desliga tudo. **Suba o `?v=` do CSS/JS ao
alterá-los** (a hospedagem guarda cache agressivo).

**Outros subsistemas:**
- IA: `includes/ia_helper.php` `chamar_ia()` abstrai Gemini/Anthropic/compatíveis com OpenAI, com espera em caso de
  cota; geração de sites em `ia_site_engine.php`, Estúdio em `estudio_*.php`; imagens em `imagens_ia.php` (cadeia de
  provedores) e fotos de banco em `fotos.php` (Pexels/Pixabay/Unsplash/Openverse com filtro de assunto, guardadas em
  `sites.fotos_banco`).
- WhatsApp: `includes/whatsapp.php` (fila `whatsapp_mensagens`; provedores conector GDS, UltraMsg, Meta Cloud API) e
  `whatsapp_ponte.php` (a ponte que o `whatsapp-conector` consulta).
- Prospecção: `includes/prospeccao*.php` — acha negócios (Places/Overpass), cria a prévia `/previa/TOKEN`, vendedora
  com IA, "virar cliente" transforma o prospect em empresa.
- Pagamentos: Mercado Pago (`mercadopago_helper.php`, `mp_assinaturas.php`, webhook com assinatura HMAC) e Pix para
  os clientes das empresas (`pagamentos.php`).
- Marketing/redes: `marketing_ia.php`, `redes_sociais.php` (OAuth de Instagram/TikTok, posts automáticos,
  comentário com palavra-chave vira lead).

## Regras do produto
- **Painel: tudo com botões.** Nenhuma configuração pode exigir digitar sintaxe ou lista separada por vírgula; use os
  editores de `gds/assets/js/gds-painel-editores.js` (`data-editor-opcoes`, `-bairros`, `-lista`, `-chips`,
  `data-preset-*`), que gravam JSON num campo oculto, e valide no servidor (ex.: `opc_normalizar()`).
- **Design dos sites:** um elemento marcante por página; sem rótulos em CAIXA ALTA acima de cada título, sem
  "01/02/03" onde não é sequência, sem seta em todo botão; fotos de banco só se combinarem com o ramo.
- **Deploy:** push com mudanças em `gds/**` para `main`/`master`/branch de trabalho dispara
  `.github/workflows/hostinger.yml`, que faz `git subtree split` de `gds/` para o branch `hostinger` que a Hostinger
  puxa. Envio direto de arquivos à Hostinger nunca pode tocar `config/local.php` nem `uploads/`.
