---
name: solicitar
description: Interpretador de solicitações do dono do GDS. Use quando o usuário digitar /solicitar seguido de um pedido (muitas vezes transcrito por voz, informal ou desorganizado) — interpreta, organiza os requisitos, investiga o projeto, monta o plano, executa seguindo o CLAUDE.md (e o Padrão GDS em qualquer tarefa de site), testa e apresenta os resultados.
argument-hint: <o que você quer, do seu jeito — pode ser texto ditado por voz>
---

# /solicitar — do pedido falado ao trabalho entregue

Pedido recebido:

$ARGUMENTS

Siga as regras do `CLAUDE.md` da raiz (Parte 1: como trabalhar; Parte 2: referência técnica). Responda em português
brasileiro. Se o pedido acima estiver vazio, pergunte o que o dono quer e pare.

**Regra fundamental:** o GDS já existe e funciona. Evolua, corrija, modernize e aperfeiçoe o código atual —
nunca crie outra plataforma, não recomece do zero, não troque a arquitetura sem necessidade, não recrie o que já
existe.

**Tarefa de site?** Se o pedido envolve criar, modificar, revisar ou melhorar sites, prévias, templates, componentes
públicos (`frag_*`), fotos, SEO ou o gerador de sites por IA: **carregue a skill `padrao-gds` antes de planejar**
(ferramenta Skill com `padrao-gds`; se não estiver disponível, leia `.claude/skills/padrao-gds/SKILL.md`) e aplique
todas as regras e o critério de aprovação dela. Isso vale mesmo que o pedido não cite o padrão.

## 1. Interpretar a mensagem
- Corrija erros de transcrição por voz (GDS, sitegestor.com, Hostinger, Supabase, Mercado Pago, WhatsApp, prévia,
  prospecção, painel da empresa…) sem mudar a intenção.
- Mostre, em poucas linhas, **"Entendi assim:"** com **Objetivo principal**, **Requisitos**, **Restrições** e
  **Dúvidas** (só as que mudam o resultado — se houver, pergunte e espere).

## 2. Entender o objetivo comercial
Qual resultado de negócio o dono quer (vender mais prévias, converter visitantes, reduzir trabalho manual,
transmitir confiança…)? Isso orienta as prioridades do plano.

## 3. Identificar o segmento do cliente
Quando houver um cliente/site: segmento, nicho em `site_nichos.php`, modo de atendimento (`agendamento`,
`orcamento`, `catalogo`, `contato`), módulos ligados e o motor que desenha o site (Limpo, Completo, IA, prévia).

## 4. Analisar o projeto existente
- Localize os arquivos envolvidos (`grep` por funções, colunas, rotas em `gds/`); use a Parte 2 do `CLAUDE.md` e o
  mapa de templates do `padrao-gds` como guia.
- Procure o que já faz algo parecido (para não duplicar), banco/migrações afetados, integrações e APIs, impacto nos
  4 modos, nas prévias e nos 3 motores.
- Confira `git status` e o branch de trabalho.

## 5. Pesquisar referências quando necessário
Documentação oficial para APIs/bibliotecas; para design, referências de qualidade (Awwwards, Webflow, Framer,
Land-book, Dribbble, Behance, Apple, Stripe, Linear, Airbnb) — extrair padrões, nunca copiar. Use
WebSearch/WebFetch ou o agente `web-fetch`; se não for possível, diga. Nunca invente resultados de pesquisa.

## 6. Planejar
Apresente: **Objetivo · Diagnóstico** (com `arquivo:linha` quando útil) **· Problemas identificados · Solução
recomendada** (e alternativa, se relevante) **· Etapas · Testes necessários.**
- **Pequena** (1–2 arquivos, sem mudar comportamento existente): plano em poucas linhas e já execute.
- **Grande** (vários arquivos, migração de banco, mudança de fluxo ou visual amplo da plataforma, publicação em
  produção): **pare e peça aprovação** — salvo se o pedido disser explicitamente para seguir sem pedir permissão.
- Antes de refatoração grande, recomende um ponto de restauração (commit/branch).

## 7. Identificar melhorias adicionais
Liste oportunidades em **Essenciais** (necessárias para funcionar), **Recomendadas** (qualidade, segurança,
desempenho, conversão) e **Opcionais**. Implemente as Essenciais ligadas à tarefa; as demais só com aprovação.

## 8. Implementar
- Lista de tarefas com progresso marcado, na ordem das dependências.
- Padrões do projeto: PHP puro, helpers de `includes/`, fragmentos `frag_*`, editores com botões no painel, skills de
  design (`frontend-design`, `web-design-guidelines`, `padrao-gds`). Nada de arquivo desnecessário ou função duplicada.
- Melhoria de padrão de site entra no **motor compartilhado** (Limpo + fragmentos) para valer para todas as empresas
  e prévias, mantendo a personalização de cada uma; nunca altere dados de clientes.
- Migração nova = `gds/sql/adicional_NN_*.sql` idempotente + entrada em `gds_migracoes()` + subir
  `GDS_SCHEMA_VERSAO` (publicá-la altera o banco de produção: precisa de autorização).

## 9. Executar testes (nunca declarar que funciona sem verificar)
- `php -l` em todo PHP alterado; ambiente local + Playwright no fluxo afetado; sem erro de JS nem warning de PHP.
- Tarefa de site: `node .claude/skills/padrao-gds/qa-site.cjs <urls>` (zero ❌) e Lighthouse quando possível, em
  empresas de modos diferentes + uma prévia (ver critério de aprovação do `padrao-gds`).
- Regressões: outros modos, empresa sem foto, tema escuro, prévia, painel admin × empresa.
- Revise o `git diff`; confira que nenhum segredo entrou (`gds/config/local.php` nunca vai para o Git).

## 10. Verificar o resultado visual
Quando houver interface: screenshots em 390px e 1366px (e 768px se o layout mudar), **olhados de verdade** —
hierarquia, espaçamento, alinhamento, identidade do segmento, nada genérico, nada quebrado.

## 11. Relatório final
- **O que foi feito** (linguagem do dono, não de programador)
- **Arquivos criados/alterados**
- **O que foi testado** — resultado real de cada teste (para sites: a lista de verificação do Padrão GDS preenchida)
- **O que ainda precisa de atenção** (pendências, riscos, o que depende do dono)
- **Próximos passos sugeridos** (Recomendadas/Opcionais que ficaram de fora)

Commit e push no branch de trabalho ao final, com mensagem em português. Publicar na Hostinger apenas se o dono
pediu ou já autorizou nesta conversa.
