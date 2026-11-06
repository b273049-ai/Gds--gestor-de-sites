# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

GDS (Gestor de Sites) is a multi-tenant PHP SaaS for small Brazilian businesses (barbershops, pet shops, restaurants, DJs, real estate…). Each tenant (`empresas` row) gets a public site at `/slug` (or its own domain), an admin panel, online booking/orders/quotes, WhatsApp automations, AI site builder, marketing and lead prospecting. Production: sitegestor.com on Hostinger shared hosting, database on Supabase Postgres.

- `gds/` — **the platform** (plain PHP 8, no framework, no Composer). Everything that matters lives here.
- `whatsapp-conector/` — small Node.js WhatsApp bridge (`@whiskeysockets/baileys`, QR-code login) that runs on an Android phone/PC and *polls* the site (`gds/api/whatsapp_ponte.php`); see its `LEIA-ME.md`.
- `src/`, `public/`, `package.json`, `vite.config.ts` — an old React/Vite prototype. **Not used by the platform**; don't extend it.
- `entregas/` — zip builds handed to the user.

Everything (code identifiers, UI text, comments, commit messages, docs) is in **Brazilian Portuguese**; keep it that way. `gds/LEIA-ME.md` is the living product/ops manual — add a section there when you ship a feature.

## Running locally

```bash
cd gds
GDS_DB_HOST=127.0.0.1 GDS_DB_PORT=5432 GDS_DB_NAME=gds GDS_DB_USER=postgres GDS_DB_PASS=senha \
GDS_DB_SSLMODE=disable GDS_DEBUG=1 php -S 127.0.0.1:8080
# first run: http://127.0.0.1:8080/install.php creates the admin
```

- Create the DB with `gds/sql/schema_postgres.sql`; later `adicional_*.sql` files are applied automatically (see Migrations).
- The built-in server ignores `gds/.htaccess`, so pretty URLs don't work: use `site/index.php?slug=X`, `site/previa.php?t=TOKEN`, `site/indica.php?c=CODE`, or write a small router script mapping those rewrites.
- `php -l file.php` is the only "build" step. There is no test suite, linter or CI test job in the repo; verification is done by driving the app (Playwright/Chromium is available in cloud sessions) and auditing pages with axe-core.
- Every external API base URL can be pointed at a local mock via env vars (`GDS_GEMINI_BASE_URL`, `GDS_ANTHROPIC_BASE_URL`, `GDS_MP_BASE_URL`, `GDS_IG_GRAPH`, `GDS_IG_API`, `GDS_FB_GRAPH`, `GDS_TT_API`, `GDS_PLACES_BASE_URL`, `GDS_OPENVERSE_BASE_URL`, `GDS_PEXELS_BASE_URL`, `GDS_WA_CLOUD_BASE_URL`, …; `grep -rho "getenv('GDS_[A-Z_]*')" gds` lists them).

## Configuration and secrets

- `gds/config/db.php` connects with PDO/pgsql and defines `get_config()`/`set_config()` (key-value table `configuracoes`, cached per request). Almost all integration keys (AI providers, Mercado Pago, WhatsApp, Meta/TikTok, photo banks) are stored there via **Admin → Configurações**, not in code.
- DB credentials come from env vars `GDS_DB_*` or `gds/config/local.php` (copy of `local.exemplo.php`). `local.php` and `gds/uploads/` are gitignored and must never be committed, zipped into partial builds, or overwritten on the server.
- Timezone is forced to `America/Sao_Paulo` in `db.php` (booking logic depends on it).

## Architecture (big picture)

**Entry points by area** (each page is a standalone PHP script that `require_once`s what it needs):
- `gds/admin/*` — platform owner (role `admin`): tenants, plans, finance, segments, AI studio, marketing, prospecting, WhatsApp, settings.
- `gds/empresa/*` — tenant panel (role `empresa`): agenda, orders "gestor"/PDV, catalog (`servicos.php`), clients, quotes, imóveis, caixa, settings. Pages call `exigir_login('empresa')` and `exigir_modulo(...)`.
- `gds/site/*` — public pages: `index.php` (tenant site), `previa.php` (prospect demo site), `pedido.php`, `agendamento.php`, `horarios.php` (JSON slots), etc.
- `gds/api/*` — webhooks/bridges: Mercado Pago, Instagram, WhatsApp receive/bridge.
- `gds/cron.php?chave=…` — every 5 min: WhatsApp queue, Pix checks, reminders, club renewals, AI photos, marketing auto-posting, MP charges, prospecting.

**Auth/requests** (`includes/auth.php`): session roles `admin`/`empresa`; `exigir_login()` also runs `gds_garantir_schema()`, the background WhatsApp routine and **CSRF validation for every POST** (and for GET actions listed in `GDS_ACOES_GET`) — forms must include `csrf_campo()`, links with actions `csrf_qs()`. Helpers: `e()` for escaping, `flash()`, `responder_json()`, `slug_unico()`.

**Migrations** (`includes/migracoes.php`): schema changes ship as `gds/sql/adicional_NN_nome.sql` (idempotent: `IF NOT EXISTS`). To add one: create the file, add `NN => [file, SQL check that is true when applied]` to `gds_migracoes()`, and bump `GDS_SCHEMA_VERSAO`. They are applied automatically on page load, statement by statement; failures are stored in config `schema_erro` and retried at most every 10 min. Code that reads new columns should tolerate them missing (see the legacy fallback query in `site_render.php`).

**Business mode drives everything.** Each segment (`segmentos` table) has a `modo_atendimento`: `agendamento` (booking), `orcamento` (quotes), `catalogo` (orders/delivery) or `contato`. The mode picks the default modules (`includes/modulos.php`: agenda, equipe, orcamentos, delivery, cupons, avaliacoes, pix, imoveis), which panel menus appear, the site CTA, and which site sections render. Niche-specific copy/colors live in `includes/site_nichos.php`.

**Public site rendering** (`includes/site_render.php`):
1. `site_buscar_empresa()` (by custom domain or slug) → `site_carregar_dados()` builds `$dados` (services, team, agenda config, status "aberto agora", reviews summary incl. Google rating, modules…).
2. POSTs go through `site_processar_post()` (bookings, leads, reviews, orders via `site_processar_pedido()`), result stored in session, then 303 redirect (PRG).
3. Render: if `sites.ia_modo_total` and `html_gerado` → `site_render_ia()` (AI-written full HTML with `{{MARCADORES}}` replaced by live components). Otherwise `site_render_template()` → **`site_render_limpo()`** in `includes/site_limpo.php` (default "Limpo" design) unless `sites.visual = 'completo'` (older `site_template.php` premium template) or `layout = 'legado'`.
4. Interactive components (services list/cart, booking wizard, quote form, reviews, map, hours) are shared fragments from `includes/site_fragmentos.php` (`frag_*`, CSS `assets/css/gds-componentes.css`, JS `assets/js/gds-site.js`), injected through `site_fragmentos_marcadores()`.

**Limpo design system** (`site_limpo.php`, `assets/css/site-limpo.css`, `assets/js/site-vivo.js`): per-tenant variation = 8 "tons" (font pairs + palette) × 4 hero compositions (`foto`, `lado`, `moldura`, `tipografia`), chosen from niche + `crc32(nome)` unless the owner fixes `sites.tom`/`sites.composicao`; accent = brand color adjusted for contrast (`frag_cor_legivel`, `frag_cor_contraste`). The hero has one "painel ao vivo" with real data (open status, next free slots, delivery fee, rating). Motion is gated by an `lp-js` class added in `<head>` and removed if `site-vivo.js` doesn't load; `prefers-reduced-motion` disables it. Bump the `?v=` query on CSS/JS when changing them (shared hosting caches aggressively).

**Other subsystems worth knowing before editing**:
- AI: `includes/ia_helper.php` `chamar_ia()` abstracts Gemini/Anthropic/OpenAI-compatible providers with quota backoff; site generation in `ia_site_engine.php`, studio in `estudio_*.php`; images `imagens_ia.php` (provider chain) and stock photos `fotos.php` (Pexels/Pixabay/Unsplash/Openverse with relevance filter, cached in `sites.fotos_banco`).
- WhatsApp: `includes/whatsapp.php` (queue `whatsapp_mensagens`, multiple providers: conector GDS, UltraMsg, Meta Cloud API) and `whatsapp_ponte.php` (the polling bridge used by `whatsapp-conector`).
- Prospecting: `includes/prospeccao*.php` — find businesses (Places/Overpass), build a `/previa/TOKEN` demo site, AI seller conversations, "virar cliente" turns a prospect into a tenant.
- Payments: Mercado Pago (`mercadopago_helper.php`, `mp_assinaturas.php`, webhook with HMAC signature check) and Pix for tenant customers (`pagamentos.php`).
- Marketing/social: `marketing_ia.php`, `redes_sociais.php` (Instagram/TikTok OAuth, auto-posting, comment-keyword → lead).

## Project rules

- **Panel UX: everything with buttons.** No panel setting may require typing syntax or comma-separated lists; use the editors in `gds/assets/js/gds-painel-editores.js` (`data-editor-opcoes`, `-bairros`, `-lista`, `-chips`, `data-preset-*`) that write JSON into a hidden input, and validate server-side (e.g. `opc_normalizar()`).
- **Design:** follow the project skills in `.claude/skills/` (`frontend-design`, `web-design-guidelines`): one memorable element per page, no ALL-CAPS eyebrows / "01-02-03" / arrows on every button, WCAG AA contrast, visible focus, reduced motion respected, mobile-first. Public sites must stay uncluttered but modern.
- `.mcp.json` configures chrome-devtools, shadcn-ui and 21st.dev Magic MCP servers; the Magic key comes from env `API_KEY_21ST` — never write the key into the file.
- **Deploy:** pushing changes under `gds/**` to `main`/`master`/the working branch triggers `.github/workflows/hostinger.yml`, which `git subtree split`s `gds/` into the `hostinger` branch that Hostinger pulls. Direct file uploads to Hostinger must never touch `config/local.php` or `uploads/`.
