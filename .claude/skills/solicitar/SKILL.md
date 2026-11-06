---
name: solicitar
description: Interpretador de solicitações do dono do GDS. Use quando o usuário digitar /solicitar seguido de um pedido (muitas vezes transcrito por voz, informal ou desorganizado) — interpreta, organiza os requisitos, investiga o projeto, monta o plano, executa seguindo o CLAUDE.md, testa e apresenta os resultados.
argument-hint: <o que você quer, do seu jeito — pode ser texto ditado por voz>
---

# /solicitar — do pedido falado ao trabalho entregue

Pedido recebido:

$ARGUMENTS

Siga as regras do `CLAUDE.md` da raiz (Parte 1: como trabalhar; Parte 2: referência técnica). Responda em português
brasileiro. Se o pedido acima estiver vazio, pergunte o que o dono quer e pare.

## 1. Interpretar
- Corrija erros de transcrição por voz (nomes do projeto: GDS, sitegestor.com, Hostinger, Supabase, Mercado Pago,
  WhatsApp, prévia, prospecção, painel da empresa…) sem mudar a intenção.
- Mostre ao dono, em poucas linhas, **"Entendi assim:"** com:
  - **Objetivo principal**
  - **Requisitos** (o que precisa existir/mudar)
  - **Restrições** (o que não pode mudar, prazos, "sem pedir permissão", etc.)
  - **Dúvidas** — só as que mudam o resultado; se houver alguma assim, pergunte e espere antes de executar.

## 2. Investigar o projeto
- Localize os arquivos envolvidos (`grep` por funções, colunas, rotas em `gds/`); leia o suficiente para entender o
  fluxo atual. Use a Parte 2 do `CLAUDE.md` como mapa.
- Verifique: funcionalidades que já fazem algo parecido (para não duplicar), banco/migrações afetados, integrações e
  APIs, impacto nos 4 modos de atendimento (`agendamento`, `orcamento`, `catalogo`, `contato`), na prévia e no
  visual Limpo/Completo/IA.
- Confira `git status` (mudanças pendentes) e o branch de trabalho.

## 3. Planejar
Apresente:
- **Objetivo**
- **Diagnóstico** (como está hoje, com `arquivo:linha` quando útil)
- **Problemas identificados**
- **Solução recomendada** (e alternativa, se houver troca relevante)
- **Etapas de implementação**
- **Testes necessários**
- **Melhorias proativas** classificadas em Essenciais / Recomendadas / Opcionais

Tamanho da tarefa:
- **Pequena** (1–2 arquivos, sem mudar comportamento existente): mostre o plano em poucas linhas e já execute.
- **Grande** (vários arquivos, migração de banco, mudança de fluxo ou visual amplo, publicação em produção):
  **pare e peça aprovação** — a não ser que o pedido diga explicitamente para seguir sem pedir permissão.
- Antes de refatoração grande, recomende um ponto de restauração (commit/branch).

## 4. Executar
- Crie a lista de tarefas e marque o progresso etapa por etapa, na ordem das dependências.
- Siga os padrões do projeto (PHP puro, helpers de `includes/`, fragmentos `frag_*`, editores com botões no painel,
  skills de design para qualquer interface). Nada de arquivo desnecessário ou função duplicada.
- Migração nova = arquivo `gds/sql/adicional_NN_*.sql` idempotente + entrada em `gds_migracoes()` + subir
  `GDS_SCHEMA_VERSAO`. Lembre que publicá-la altera o banco de produção (precisa de autorização).
- Implemente as melhorias **Essenciais**; as demais só com aprovação.

## 5. Testar (nunca declarar que funciona sem verificar)
- `php -l` em todo PHP alterado.
- Ambiente local + Playwright: o fluxo afetado em celular (390px) e computador (1366px), sem erro de JS nem warning
  de PHP; axe nas páginas públicas alteradas quando houver interface.
- Pense em regressões: outros modos de atendimento, empresa sem foto, tema escuro, prévia, painel admin x empresa.
- Revise o `git diff` final; confira que nenhum segredo entrou (`gds/config/local.php` nunca vai para o Git).

## 6. Apresentar o resultado
Responda com:
- **O que foi feito** (em linguagem do dono, não de programador)
- **Arquivos alterados/criados**
- **O que foi testado** — com o resultado real de cada teste
- **O que ainda precisa de atenção** (pendências, riscos, o que depende do dono)
- **Próximos passos sugeridos** (as melhorias Recomendadas/Opcionais que ficaram de fora)

Commit e push no branch de trabalho só ao final, com mensagem em português. Publicar na Hostinger apenas se o dono
pediu ou já autorizou nesta conversa.
