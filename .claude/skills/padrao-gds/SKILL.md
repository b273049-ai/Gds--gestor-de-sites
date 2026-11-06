---
name: padrao-gds
description: Padrão GDS de Excelência Digital — regras obrigatórias de design, identidade por segmento, funcionalidades comerciais reais, conversão, mobile first, desempenho, SEO, acessibilidade e critérios de aprovação para TODO site gerado pela plataforma GDS (prévias, visual Limpo, visual Completo, sites feitos pela IA, componentes do site). Use sempre que for criar, modificar, revisar ou melhorar sites, prévias, templates ou componentes públicos do GDS — inclusive dentro de /solicitar.
argument-hint: <url ou slug do site a revisar, ou a mudança de site a fazer>
---

# Padrão GDS de Excelência Digital

Pedido/alvo (se houver): $ARGUMENTS

Regra fundamental: o GDS já existe e funciona. **Evolua a plataforma existente** — nunca crie outra plataforma, não
recomece do zero, não troque a arquitetura sem necessidade, não recrie o que já existe. Toda melhoria entra no
sistema compartilhado (motor de sites, fragmentos, CSS/JS) para valer para **todas** as empresas, preservando a
personalização de cada uma e sem alterar dados de clientes.

Objetivo: cada site gerado pelo GDS deve ser um **produto digital profissional, comercial, rápido, funcional e de alto
padrão** — transmitir profissionalismo, credibilidade, modernidade, exclusividade, organização e confiança, com
identidade própria do segmento e do cliente.

---

## 1. Onde fica cada coisa (mapa dos templates)

Antes de mexer, saiba qual motor desenha o site (a escolha está em `site_render_template()` / `site/index.php`):

| Motor | Quando é usado | Arquivos |
|---|---|---|
| **Limpo** (padrão) | todo site sem IA total e sem `sites.visual='completo'`; **e todas as prévias** | `gds/includes/site_limpo.php`, `gds/assets/css/site-limpo.css`, `gds/assets/js/site-vivo.js` |
| Completo (antigo "premium") | `sites.visual = 'completo'` | `gds/includes/site_template.php`, `assets/css/site-premium.css`, `assets/js/gds-motion.js`, `gds-cinema.js` |
| Legado | `sites.layout = 'legado'` (reserva) | `site_render_template()` em `site_render.php`, `assets/css/site-publico.css` |
| Site feito pela IA | `sites.ia_modo_total` + `html_gerado` | `gds/includes/ia_site_engine.php` (`ia_regras_tecnicas()`, `ia_direcoes_visuais()`), `site_render_ia()` |
| Prévia de prospecção | `/previa/TOKEN` | `gds/site/previa.php` → `site_render_limpo(..., preview=true)` + faixa "Quero este site" |

Peças compartilhadas por todos os motores:
- **Componentes vivos** (serviços/cardápio + carrinho, agendamento, orçamento, avaliações, equipe, galeria, clube,
  cupons, horários, mapa, contato, imóveis): `gds/includes/site_fragmentos.php` (`frag_*`) +
  `assets/css/gds-componentes.css` + `assets/js/gds-site.js`, injetados por `site_fragmentos_marcadores()`.
- **Identidade por nicho** (17 nichos: barbearia, tatuagem, beleza, gastronomia, café, saúde, eventos, fitness, pet,
  auto, casa, profissional, tecnologia, educação, varejo, turismo, geral): `gds/includes/site_nichos.php`
  (`site_nichos()`, `site_nicho_da_empresa()`, `site_headline_segmento()`).
- **Personalização no Limpo**: 8 tons (`site_limpo_tons()`) × 4 composições do topo (`site_limpo_composicoes()`),
  perfil por nicho (`site_limpo_perfil_nicho()`), escolha final em `site_limpo_escolhas()`; o dono fixa em
  Configurações → "Visual do meu site" (`sites.visual`, `sites.tom`, `sites.composicao`).
- **Fotos**: `gds/includes/fotos.php` (bancos grátis com filtro de assunto) e `imagens_ia.php` (fotos por IA).
- **Dados**: `site_carregar_dados()` em `site_render.php` (serviços, equipe, agenda, status aberto/fechado,
  avaliações + nota do Google, módulos, imóveis).

Regra de arquitetura: **uma melhoria de padrão entra no motor Limpo e nos fragmentos** (atinge sites e prévias de uma
vez). Se a regra também valer para o site da IA, atualize `ia_regras_tecnicas()`/`ia_direcoes_visuais()`. O motor
Completo só recebe correção de defeito, salvo pedido.

---

## 2. Identidade digital por segmento

- Cada site precisa de identidade própria: tom (tipografia + paleta), composição do topo e cor da marca do cliente.
  Nunca a mesma estrutura visual para todos os segmentos; duas empresas do mesmo ramo também devem se diferenciar
  (o hash do nome já varia tom/fontes/composição — mantenha e amplie esse mecanismo, não o substitua).
- A cor da marca vem do cliente (logo/Instagram/escolha); se for "do meio" (sem contraste com branco nem preto),
  escureça só o necessário (`frag_cor_legivel`), sem trocar a identidade.
- Tipografia como personalidade (pares do `site_limpo_tons()`), escala clara, linhas com menos de ~80 caracteres.
- Imagens: só as da empresa, do Google da empresa, por IA coerente com o ramo, ou de banco **que combinem com o
  negócio** (`fotos_combina()`). Sem foto boa → composição "tipografia" (nome grande), nunca foto aleatória.

## 3. Design profissional obrigatório

Todo site precisa ter: cabeçalho profissional (marca + menu + ação principal), menu intuitivo (máx. 4–5 itens),
seção principal com impacto e **um** elemento marcante (hoje: o painel ao vivo + luz da marca), tipografia moderna,
paleta harmoniosa, imagens de qualidade, seções bem distribuídas e com respiro, botões de ação destacados,
**rodapé completo** (nome, contato/WhatsApp, endereço, horário, redes, links úteis), responsividade e consistência.

Evite (e remova quando encontrar): layout genérico de template; gradientes exagerados; animação sem propósito
(uma entrada orquestrada + reações à ação do usuário; respeitar "reduzir movimento"); excesso de elementos na
primeira tela; textos repetidos (a mesma frase no topo, no sobre e no rodapé); espaçamentos irregulares;
**botões sem função**; rótulos em CAIXA ALTA sobre cada título; "01/02/03" sem sequência real; "→" em todo botão;
creme+terracota, preto+verde-limão e outros clichês de site feito por IA (ver skill `frontend-design`).

Referências de qualidade (pesquisar padrões, **nunca copiar** layout, marca ou conteúdo): Awwwards, Webflow,
Framer, Land-book, Dribbble, Behance, Apple, Stripe, Linear, Airbnb. Ao pesquisar, use WebSearch/WebFetch ou o agente
`web-fetch`; registre o padrão observado (layout, tipografia, hierarquia, navegação, animação, conversão) e como ele
foi adaptado ao segmento. Se a pesquisa não for possível (ferramenta ausente, site bloqueia robôs), diga isso — não
invente referências.

## 4. Funcionalidades comerciais por segmento (todas reais)

**Não exiba funcionalidade fictícia.** Cada seção só aparece se houver dado real e a função funcionar de ponta a
ponta. Mapa do que o GDS já tem para cada tipo de negócio (use antes de criar algo novo):

| Segmento | Obrigatório | Recursos existentes no GDS |
|---|---|---|
| Restaurantes, lanchonetes, pizzarias, delivery | cardápio digital, pedido, WhatsApp, fotos dos pratos, horários, endereço | modo `catalogo`: cardápio com categorias/busca/foto, tela do produto com opções e observação, carrinho, entrega/retirada, bairros e taxa, cupom, acompanhamento do pedido, avaliação pós-pedido |
| Clínicas, saúde, beleza, barbearia, pet | agendamento, profissionais, especialidades/serviços, informações de atendimento, contato | modo `agendamento`: agenda com horários livres reais, equipe, serviços com duração/preço, lembrete e confirmação no WhatsApp, Pix/sinal, lista de espera, clube/planos |
| Lojas e catálogos | catálogo, categorias, busca, WhatsApp, carrinho | modo `catalogo` + estoque, variações/opções, carrinho |
| Prestadores de serviço (obras, eventos, DJ, técnicos) | portfólio, serviços, depoimentos reais, orçamento, formulário, WhatsApp | modo `orcamento`: pedido de orçamento com perguntas e fotos, galeria (portfólio), avaliações aprovadas |
| Hotéis e pousadas (nicho turismo) | acomodações, galeria, informações de hospedagem, pedido de reserva | serviços como acomodações + galeria + pedido via orçamento/WhatsApp (reserva com disponibilidade real ainda **não existe** — não simular) |
| Imobiliárias | vitrine de imóveis, filtros, ficha, visita | módulo `imoveis` (filtros, ficha, simulador, agendar visita) |

Se o segmento pede algo que o GDS não tem, proponha a funcionalidade (com plano e aprovação) em vez de exibir um
botão que não funciona.

## 5. Conversão

- Uma ação principal clara por site (agendar / pedir / orçamento / falar), repetida no topo, na barra fixa do
  celular e no fechamento; WhatsApp sempre acessível.
- Formulários curtos e objetivos; confirmação clara do que acontece depois ("a confirmação chega no seu WhatsApp").
- Prova social **somente verdadeira**: nota e avaliações do Google, avaliações aprovadas no painel, anos de casa
  informados pelo dono. **Nunca invente** avaliações, clientes, números, certificações ou depoimentos.
- Diferenciais e serviços apresentados com clareza; contato, endereço e horário fáceis de achar.

## 6. Mobile first

Projete primeiro para 390px e verifique 320 (celular pequeno), 390, 768 (tablet), 1366 (notebook) e 1920
(monitor). Proibido: rolagem horizontal, texto cortado, botões sobrepostos, menu quebrado, imagem deformada,
seção desorganizada. Área de toque ≥ 44px nos botões principais; barra de ação fixa no celular não pode cobrir
conteúdo importante.

## 7. Desempenho, SEO, acessibilidade e segurança

- HTML semântico (`header`, `nav`, `main`, `section` com título, `footer`), um único `h1`, títulos em ordem.
- `<title>` 10–70 caracteres no formato "Nome · Segmento em Bairro"; `meta description` 50–170; Open Graph
  (título, descrição, **imagem** — use logo ou uma imagem gerada da marca quando não houver foto); `canonical`;
  JSON-LD `LocalBusiness` (`site_json_ld()`).
- Imagens: `width`/`height` ou `aspect-ratio` (evitar CLS), `loading="lazy"` fora da primeira tela,
  `fetchpriority="high"` + `preload` só na imagem principal; fontes com `preconnect` e `display=swap`.
- Sem bibliotecas pesadas novas; CSS/JS próprios com `?v=` atualizado a cada mudança.
- WCAG 2.1 AA: contraste ≥ 4,5:1, foco visível, rótulos ligados aos campos, "Pular para o conteúdo",
  `prefers-reduced-motion`.
- Segurança: escapar tudo com `e()`, CSRF nos formulários do painel, anti-spam (honeypot) nos formulários públicos,
  links externos com `rel="noopener"`, nenhuma chave/token no HTML ou JS.

## 8. Regra especial: sites de prévia

A prévia (`/previa/TOKEN`) é o produto que vende o GDS — trate como vitrine.
- Usa o **mesmo motor** do site real (`site_render_limpo` com `preview=true`): toda melhoria do Limpo vale para as
  prévias. Não crie um template separado para prévia.
- Só dados reais do negócio (nome, nota e fotos do Google, horários, endereço); serviços de exemplo do segmento são
  permitidos apenas sem preço inventado; manter o aviso "Prévia de demonstração… não é o site oficial".
- Faixa "Quero este site" sempre funcional; nada na prévia pode fingir uma função que o cliente não terá.
- Verifique várias prévias de segmentos diferentes, não só uma.

## 9. Inteligência proativa

Ao encontrar oportunidade: analise o problema → avalie o benefício comercial → verifique compatibilidade com os
4 modos de atendimento e os 3 motores → implemente se estiver no escopo → peça aprovação se for grande, adicional ou
mudar funcionalidade existente. Classifique em **Essenciais / Recomendadas / Opcionais**.

## 10. Controle de qualidade GDS (critério de aprovação)

Um site **só está pronto** quando passar por tudo abaixo, com resultados **reais** registrados:

1. **Teste automático** — com o ambiente local rodando:
   ```bash
   node .claude/skills/padrao-gds/qa-site.cjs "http://127.0.0.1:8080/site/index.php?slug=EMPRESA" \
        "http://127.0.0.1:8080/site/previa.php?t=TOKEN" --fotos /tmp/qa-fotos
   ```
   Mede em 5 telas: rolagem lateral, elementos fora da tela, botões sobrepostos, área de toque, imagens
   (quebradas, sem alt, deformadas, sem tamanho), links mortos, erros de JS/rede, SEO (title, description, h1, lang,
   OG, canonical, JSON-LD, ordem dos títulos), desempenho (LCP, CLS, peso, requisições, DOM) e axe-core (instale com
   `npm i --no-save axe-core` na raiz, ou passe `AXE_PATH=`). **Zero ❌ é obrigatório**; cada ⚠️ precisa de
   justificativa ou correção.
2. **Lighthouse** (quando houver rede para o npx):
   ```bash
   CHROME_PATH=/opt/pw-browsers/chromium npx -y lighthouse@12.2.1 "URL" --quiet \
     --chrome-flags="--headless=new --no-sandbox" --only-categories=performance,accessibility,best-practices,seo \
     --output=json --output-path=/tmp/lh.json
   ```
   Meta: Desempenho ≥ 90, Acessibilidade ≥ 95, Boas práticas ≥ 95, SEO ≥ 95.
3. **Revisão visual** — screenshots em 390 e 1366 (as fotos do `--fotos`), olhadas de verdade: hierarquia,
   espaçamento, alinhamento, identidade do segmento, nada genérico, nada fora do lugar.
4. **Funcional** — o fluxo principal do modo funciona de ponta a ponta (agendar, pedir, orçar, contato), WhatsApp
   abre com a mensagem certa, todo botão leva a algum lugar.
5. **Regressões** — pelo menos 1 empresa de cada modo (`agendamento`, `orcamento`, `catalogo`, `contato`), uma sem
   foto, uma de tema escuro e uma prévia.

Lista de verificação (preencha no relatório com ✅/⚠️/❌ e o resultado medido):

| Área | Critério | Resultado |
|---|---|---|
| Design profissional | hierarquia, espaçamento, tipografia, nada genérico | |
| Identidade visual | tom/cores/fotos coerentes com o segmento e a marca | |
| Responsividade | 320/390/768/1366/1920 sem falhas | |
| Navegação | menu, âncoras e barra do celular funcionando | |
| Funcionalidades | fluxo principal testado de ponta a ponta | |
| Conversão | ação principal clara, WhatsApp, prova social real | |
| SEO | title, description, h1, OG, canonical, JSON-LD | |
| Desempenho | LCP, CLS ≤ 0,1, peso, Lighthouse | |
| Acessibilidade | axe 0 violações, contraste, foco | |
| Segurança | escape, CSRF/honeypot, sem segredos | |

Se houver falha importante, **o site não está finalizado** — corrija ou informe claramente o que falta.

## Linha de base medida (2026-11-06, ambiente local, motor Limpo)

Para comparar evoluções. Lighthouse (Puppy Spa teste): Desempenho 97 · Acessibilidade 100 · Boas práticas 96 · SEO 100.
Pendências encontradas pelo `qa-site.cjs`: CLS 0,143 no site com agenda (Puppy) — precisa investigar; contraste
baixo no indicador de etapas do agendamento (`div[data-gds-ind="data"] > span`) em 1366px; sem `og:image` quando a
empresa não tem foto; sem `canonical`; rodapé do Limpo mínimo (só nome e "Site feito com GDS"); botões
`gds-btn-mini` com 36px de altura no celular; sem `robots.txt`/`sitemap.xml` para os sites públicos.
