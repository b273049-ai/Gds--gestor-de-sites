# GDS - Gestor de Sites — Como instalar

## 1. Banco de dados (Supabase)
1. Entre no seu projeto em supabase.com → menu lateral **SQL Editor** → **New query**.
2. Cole todo o conteúdo do arquivo `sql/schema_postgres.sql` e clique em **Run**.
   (Ignore o arquivo `schema_mysql_NAO_USAR_COM_SUPABASE.sql` — é só referência antiga, não use com Supabase.)
3. Depois do schema principal, rode os incrementais **nesta ordem** (cada um só adiciona, não apaga nada):
   - `sql/adicional_criacao_site.sql`
   - `sql/adicional_2_funcionalidades.sql`
   - `sql/adicional_3_configuracoes.sql`
   - `sql/adicional_4_modularidade.sql`
   - `sql/adicional_5_seguranca.sql`
   - `sql/adicional_6_ia_total.sql`
   - `sql/adicional_7_pagamento_cliente.sql`
   - `sql/adicional_8_seguranca.sql`
   - `sql/adicional_9_dominio.sql`
   - `sql/adicional_10_estudio_ia.sql` ← **novo**: Estúdio IA (conversa + versões), avaliações pelo site, horário de atendimento e índices
   - `sql/adicional_12_conteudo_site.sql` ← **novo**: categorias/fotos de serviço, folgas, cupom e cancelamento pelo cliente (aplicado sozinho pelo sistema)
   - `sql/adicional_13_segmentos.sql` ← **novo**: segmentos inteligentes, forma de atendimento e pedidos de orçamento pelo site (aplicado sozinho pelo sistema)
   - `sql/adicional_14_mais_segmentos.sql` ← **novo**: 161 segmentos organizados em 18 grupos (aplicado sozinho pelo sistema)
   - `sql/adicional_15_whatsapp_delivery_dominio.sql` ← **novo**: WhatsApp automático, pedidos de delivery e verificação de domínio (aplicado sozinho pelo sistema)
   - `sql/adicional_16_modulos_design.sql` ← **novo**: painel modular, estilos de site e posts de marketing (aplicado sozinho pelo sistema)
   Se algo der erro, abra **Admin → Diagnóstico**: mostra o que falta (arquivo, extensão do PHP, atualização do banco) e tem o botão "Atualizar banco agora".
   - `sql/adicional_11_rls_seguranca.sql` ← **novo e muito recomendado**: fecha as tabelas para a API pública do Supabase (veja "Segurança")
   Se algum já tiver sido rodado antes, rodar de novo não quebra nada (os comandos usam `IF NOT EXISTS`).
   O `adicional_10` também é aplicado sozinho pelo sistema no primeiro acesso ao painel, se tiver faltado.

## 2. Configurar conexão
No Supabase: **Project Settings → Database → Connection string** (aba "URI" ou "Connection parameters").
Como sua hospedagem PHP provavelmente não suporta IPv6, use o **Connection Pooling** (Session/Transaction pooler),
não a conexão direta.

**Jeito mais fácil:** copie `config/local.exemplo.php` para `config/local.php` e preencha. Esse arquivo não vai
para o GitHub, então a senha não fica exposta.

Ou abra `config/db.php` e troque:
- `DB_HOST` → o host do pooler, algo como `aws-0-sa-east-1.pooler.supabase.com`
- `DB_PORT` → `6543` (transaction pooler) — deixe como está se não souber qual usar
- `DB_USER` → formato `postgres.SEU_PROJECT_REF` (o Supabase mostra isso pronto na tela de conexão)
- `DB_PASS` → a senha do banco que você definiu ao criar o projeto (se esqueceu, dá pra resetar em Database → Settings)
- (opcional, depois) `IA_API_KEY`, `MERCADOPAGO_ACCESS_TOKEN`, `WHATSAPP_API_TOKEN` — o melhor é configurar tudo pela tela Admin → Configurações.

Também dá pra configurar por variáveis de ambiente (`GDS_DB_HOST`, `GDS_DB_PORT`, `GDS_DB_NAME`, `GDS_DB_USER`,
`GDS_DB_PASS`, `GDS_DB_SSLMODE`, `GDS_DEBUG=1` para ver erros na tela durante testes).

## 3. Subir os arquivos
Envie TODO o conteúdo desta pasta (menos este LEIA-ME) para a raiz do seu domínio
via FTP ou Gerenciador de Arquivos (geralmente a pasta `public_html`).

## 4. Criar o usuário admin
Acesse `https://seudominio.com/install.php` no navegador, preencha nome/e-mail/senha
e clique em criar. Depois disso, **apague o arquivo install.php do servidor**
(por segurança — ele não deve ficar acessível).

## 5. Login
Acesse `https://seudominio.com/login.php` com o e-mail/senha criados.
Você entra automaticamente no Painel Admin.

## 6. Criar uma empresa (tenant)
No Admin, vá em "Empresas" → cadastre a empresa, escolha segmento e plano, e
defina o e-mail/senha de login dela. Essa empresa poderá logar no mesmo
`login.php` e vai cair automaticamente no Painel Empresa (separado do admin).

## 7. Publicar o site da empresa
Dentro do Painel Empresa → "Meu Site", preencha título/sobre e marque "Publicar".
O site fica disponível em: `https://seudominio.com/site/index.php?slug=nome-da-empresa`

Com o `.htaccess` da raiz (já incluso) o endereço fica bonito: `https://seudominio.com/nome-da-empresa`.
Domínio próprio do cliente: preencha em Admin → Empresas → Editar e aponte o DNS do domínio para o servidor.

## Estúdio IA (criação de site no estilo Lovable)
Admin → Empresas → **Estúdio** (ou a própria empresa, se o plano tiver "Permite IA" e você ligar
"Estúdio IA para as empresas" em Configurações):
- **Novo site**: descreva o negócio (o botão "Melhorar descrição com IA" ajuda), escolha um estilo visual
  ou "Surpreenda-me" e gere. A IA usa os dados reais cadastrados — serviços e preços, equipe, fotos, endereço,
  horário — e cria as páginas com agendamento, avaliações e contato funcionando de verdade.
- **Conversa**: peça ajustes em português ("troque a cor para verde", "adicione uma seção de perguntas
  frequentes") — cada pedido vira uma nova versão.
- **Versões**: volte para qualquer versão anterior com um clique.
- **Pré-visualização** em computador, tablet e celular; editor de código; botão Publicar.
- Provedores: Google Gemini (plano gratuito), Groq / OpenRouter / Mistral (planos gratuitos, formato
  "compatível com OpenAI") ou Anthropic Claude (pago, modelo padrão `claude-opus-5-5`).
- **IA reserva**: se a principal estourar o limite grátis ou cair, a reserva assume sozinha. Com IAs grátis,
  configure duas (ex.: Gemini + Groq). No Gemini dá também para definir um "modelo reserva" (ex.: um Flash-Lite).
- Em Configurações, cada IA tem os botões **"Ver modelos disponíveis"** (lista os modelos que a SUA chave pode usar
  — evita erro de nome de modelo) e **"Testar"**. Os erros aparecem explicados em português, com o que fazer.

### Problemas comuns com a IA
| Mensagem | O que fazer |
|---|---|
| limite de uso do plano grátis atingido | Espere, troque para um modelo com mais cota (Flash-Lite) ou configure uma IA reserva |
| o modelo "..." não existe | Clique em "Ver modelos disponíveis" e escolha um da lista |
| a chave de API foi recusada | Copie a chave de novo, inteira, e confira se é do serviço certo |
| A hospedagem encerrou a requisição por tempo | Sua hospedagem corta requisições longas; use uma IA mais rápida (Groq) ou peça mais tempo ao suporte |

### Conversa no estilo Lovable (ajustes do site)
- **Primeiro entende, depois mexe**: a cada pedido a IA mostra no chat o que entendeu e o plano (o que muda e o que
  fica) e só então aplica. Referências como "modelo de SaaS", "estilo Apple", "igual ao Nubank" ou "igual a este site"
  mudam o **visual**, nunca o negócio; se o nome do negócio sumir sem você pedir, o sistema refaz sozinho.
- **Edição cirúrgica**: pedidos localizados (um texto, uma cor, um botão, uma seção) trocam só o trecho certo do código,
  sem reescrever o site; mudanças de visual do site inteiro redesenham mantendo textos, serviços e componentes.
- **Memória da conversa**: entende "volta como estava", "a cor de antes", "isso aí".
- **Pergunta quando é ambíguo**, com opções clicáveis, e responde perguntas ("o que você acha?") sem mexer no site.
- **🎯 Selecionar elemento**: clique em qualquer parte do preview e peça a mudança só para ela.
- **📎 Site de referência**: cole o código de um site (HTML ou React/Tailwind, como o que a Lovable entrega) no chat ou na
  aba Novo site; a IA copia o estilo (cores, fontes, espaçamentos, layout) com o conteúdo do seu negócio.
- Depois de cada ajuste aparecem sugestões do que pedir em seguida, e toda versão pode ser restaurada.

## Segmentos e forma de atendimento
Cada negócio conquista cliente de um jeito, e o site segue isso:
| Forma | Para quem | Botão principal / dos serviços | Componente |
|---|---|---|---|
| **Agendamento** | barbearia, salão, clínica, pet shop… | "Agendar" (abre o agendamento com o serviço escolhido) | `{{AGENDAMENTO}}` |
| **Orçamento** | DJ, fotógrafo, eventos, obras, oficina, agência… | "Pedir orçamento" (abre o formulário com o serviço escolhido, data, local e detalhes) | `{{ORCAMENTO}}` |
| **Contato** | imobiliária, software, cursos, hotel… | WhatsApp / formulário | `{{CONTATO}}` |
| **Catálogo** | restaurante, loja, delivery | "Pedir" pelo WhatsApp com o item | `{{SERVICOS}}` |

- **Admin → Segmentos**: cada segmento tem forma de atendimento, texto do botão, orientação para a IA e serviços típicos.
  O botão **✨ Preencher com IA** preenche tudo a partir do nome. Já vêm **161 segmentos prontos em 18 grupos**
  (Alimentação — hamburgueria, pizzaria, fast food, delivery, açaí, marmitaria…; Beleza; Saúde; Esporte; Pets; Eventos;
  Casa e Construção; Automotivo; Serviços Profissionais; Tecnologia e Marketing — incluindo venda de sites; Educação;
  Turismo; Comércio e Lojas; Imóveis; Religião e ONGs; Agro e Indústria; Pessoal e Criativo) e o "Outro"
  (a empresa descreve o tipo de negócio). Nas listas eles aparecem separados por grupo; na tela de Segmentos dá para buscar e filtrar.
- A forma vale nesta ordem: escolha da empresa (Configurações → "Como seus clientes contratam", ou no admin/Estúdio) > segmento > agendamento.
- **No Estúdio IA a IA estuda o negócio antes de criar** (tipo, público, tom, forma de atendimento, serviços e perguntas frequentes).
  Se a empresa não tem serviços, ela pode cadastrar os sugeridos. Se a forma mudar depois, o site troca o componente sozinho.
- Os botões que a IA escreve usam `{{LINK_PRINCIPAL}}`, `{{TEXTO_PRINCIPAL}}` e `{{LINK_WHATSAPP}}` — o número real do WhatsApp
  é sempre o cadastrado (links de WhatsApp com número inventado são corrigidos), e o WhatsApp abre em nova aba.
- Pedidos de orçamento chegam em **Pedidos/Orçamentos** (com serviço, data do evento, local e "pelo site") e por e-mail.

## Painel modular (cada negócio vê só o que usa)
O menu e as telas da empresa mudam conforme a forma de atendimento: barbearia vê Agenda, Equipe e Cupons;
hamburgueria vê Pedidos e Cardápio; DJ/fotógrafo vê Orçamentos e "Serviços e pacotes". A empresa pode ligar módulos
extras em **Configurações → Módulos do painel** (ex.: barbearia que também vende produtos). Módulo desligado some do menu e do site.

## Design dos sites
- **Identidade por tipo de negócio** (`includes/site_nichos.php`): barbearia abre escura com dourado, letras altas
  (Bebas Neue), listras de barbearia e tabela de preços estilo cardápio; salão/estética claro e elegante; hamburgueria
  quente e vibrante; clínica calma; DJ/eventos com cara de festa. São 17 identidades com cores, fontes, textura, ícone
  e frase de impacto. A empresa pode trocar por um dos 5 estilos (Aurora, Elegante, Vibrante, Minimalista, Noite) em
  Configurações → Visual do meu site.
- **Primeira impressão que gera confiança**: barra no topo com "aberto agora", nota das avaliações, endereço e
  telefone; cartões flutuantes com a nota e um depoimento real e o **próximo horário livre** ("hoje às 15:30");
  "Por que a gente" montado só com dados reais; **perguntas frequentes** com horário, endereço, entrega e pagamento
  (também vão para o Google); chamada final com foto.
- **Celular**: barra fixa embaixo com **Agendar/Pedir + WhatsApp + Como chegar**.
- **Extensão de animações "Cinema"** (`assets/js/gds-cinema.js`, padrão): rolagem suave, topo que sai de cena,
  luz seguindo o mouse, faixa que entorta com a velocidade, passos que acendem e itens entrando em cascata.
  Usa GSAP 3.12.5 + Lenis 1.1.13 (versões fixas); se não carregarem, o site continua com o kit normal
  (`gds-motion.js`). Escolha **Cinema / Suave / Leve** em Configurações → Visual do meu site.
- **Fotos exclusivas por IA**: Admin → Configurações → "Fotos exclusivas geradas por IA" (Gemini, OpenAI ou
  Pollinations grátis). No Estúdio IA há o botão **Gerar fotos com IA**, e a rotina automática (cron) gera sozinha
  3 fotos para cada site publicado sem foto. Sem chave, o Pollinations libera 1 foto a cada 45s e coloca uma
  marquinha no canto; com chave do Gemini ou do Pollinations as fotos saem limpas.
- **Fotos profissionais (Pexels, grátis)**: cole a chave em Admin → Configurações.
- **Sites feitos pelo Estúdio IA** recebem a mesma direção de arte do ramo e podem usar as bibliotecas de animação
  liberadas (as demais são removidas por segurança).

## Desempenho do site (painel da empresa)
Menu **Desempenho**: visitas reais, pessoas, cliques no WhatsApp, pedidos/agendamentos feitos pelo site, taxa de
conversão, celular × computador e **de onde vieram** (Instagram, Google, WhatsApp… use `?utm_source=instagram` no
link da bio). Mais: faturamento, ticket médio, mais vendidos e **comissão por profissional** (defina o % em Equipe).

## Funções novas do painel
- **Agenda → Grade do dia**: uma coluna por profissional, linha do "agora" e clique no espaço vazio para marcar.
- **Clientes**: **cartão fidelidade** (a cada N atendimentos/pedidos ganha um prêmio, com botão para avisar no
  WhatsApp) e filtro **Sumidos** (não voltam há 45 dias) com mensagem pronta "sentimos sua falta".
- **Delivery estilo iFood**: botão **Loja aberta / pausar**, item **Esgotou** (some o botão de pedir),
  **taxa por bairro** (Configurações → Pedidos pelo site), **alerta sonoro** de pedido novo e **comanda para
  impressora térmica** (botão Imprimir em cada pedido).

## Central de inteligência (Admin)
Funciona sem chave de IA. Com a IA ligada, o briefing fica mais detalhado.
- **Dashboard**: *briefing do dia*, *próximas ações* em ordem do dinheiro em jogo (mensalidades atrasadas, quem
  clicou "Quero este site", quem abriu a prévia, quem respondeu, clientes em risco, sites em rascunho, falhas de
  WhatsApp), *previsão de receita* para 30/60/90 dias e *saúde dos clientes*.
- **Saúde do cliente (0–100)**: uso do painel, site no ar, visitas, agendamentos/pedidos/contatos pelo site,
  pagamento em dia e recursos ligados. Mostra o risco de cancelar, o motivo, uma venda extra sugerida (domínio,
  Pix, WhatsApp) e uma mensagem pronta para chamar o cliente. Filtro por saúde em Empresas.
- **Relatórios**: funil de vendas (empresas vistas → chamadas → abriram a prévia → responderam → clientes) e previsão.
- **Prospecção que aprende**: taxa de resposta por segmento, por horário e por dia ("seus leads respondem mais
  entre 10h e 12h"). A 2ª mensagem cita o que os clientes mais elogiam nas avaliações do Google.

## Marketing com IA (Admin)
Sem chave de IA, os posts saem de um **banco de posts prontos** (8 objetivos, com arte, legenda, hashtags e
roteiro) e do objetivo **"Dados reais da sua base"**, com números verdadeiros da prospecção (ex.: "80% das
empresas de Jundiaí não têm site"). Com a IA ligada, cada post é escrito sob medida.
A IA escreve o post (título, legenda, hashtags, roteiro) e já sugere a foto e o estilo. A tela:
- gera **foto exclusiva com IA**, busca no **banco de fotos** ou usa a sua;
- monta a arte em **9 modelos** (foto + título, foto + bloco, pergunta, número gigante, impacto, lista, antes × depois,
  depoimento, oferta), **6 estilos** (Neon, Editorial, Bold, Pop, Minimal, Luxo) e **8 paletas**, com o botão
  **Variar visual** para nenhum post sair igual ao outro;
- baixa o **carrossel** completo (capa + 1 slide por item + fechamento);
- grava o **vídeo MP4 9:16** com gancho em letras gigantes, fotos em movimento e oferta no final.
- (v18) **Modelos de copy**: AIDA, PAS, Antes → Depois → Ponte, 4U, história curta, mito × verdade e lista.
- (v18) **Versões A/B/C** do título em cada post (um clique troca a arte) e **texto adaptado** para Facebook,
  LinkedIn, Status do WhatsApp e Perfil da Empresa no Google (marque as redes nos botões).
- (v18) **Calendário do mês**: 4 semanas com 3, 5 ou 7 posts por semana, pilares alternados (educativo, prova,
  dor/objeção, oferta) e progressão atrair → educar → provar → converter. "Criar" em cada linha gera o post.

### Plano com os posts prontos (v19)
- **⚡ Gerar meu plano pronto** (Admin → Marketing): escolha semana ou mês, posts por semana (3, 5 ou todo dia) e
  stories por dia. A IA monta o calendário no **melhor horário de cada formato** (Reels à noite, carrossel no almoço,
  stories de manhã/almoço/noite — médias do Brasil; o Insights do seu perfil vale mais) e cria cada item: arte com
  foto feita por IA, legenda com gancho nas primeiras 125 letras, 3–5 hashtags, roteiro e **narração** do Reels,
  stories com o adesivo certo (enquete, pergunta, quiz, link) e o **prompt para animar a foto** no Kling/Dreamina.
- O plano continua sozinho com a tela fechada (cron ou carona do conector). Cada post tem os botões **Baixar arte /
  Carrossel / Stories**, **🎬 Vídeo narrado** (MP4 com a voz), **Legenda**, **Animar no Kling**, **Postei** e
  **Refazer**; e o **ZIP com tudo** (artes, slides, stories, legendas e roteiros em pastas por dia).
- **⏰ Lembrete na hora de postar**: 15 min antes chega no seu WhatsApp a legenda pronta e o link do post.
- **Voz dos Reels**: Azure Speech grátis (500 mil caracteres/mês, vozes pt-BR) em Configurações → Voz dos vídeos.
  Sem ela, usa a voz grátis do Puter no navegador.
- **Vídeo com IA de verdade**: não existe API grátis confiável hoje (Veo, Seedance etc. são pagos). O caminho grátis:
  baixe a capa, cole o prompt pronto em klingai.com → Image to Video (créditos grátis por dia).
- O quadro **⚡ IAs do marketing** mostra o que já está ligado e o passo a passo do que falta.

### Instagram e TikTok conectados: posta sozinho e comentário vira cliente (v20)
- **Conexão oficial e grátis** (Admin → Marketing → 🔗 Instagram e TikTok), sem senha e sem robô:
  - **Instagram**: "Instagram API com login do Instagram" da Meta. Precisa de conta **profissional**; não precisa de
    Página do Facebook. Cadastre a sua conta como **Testadora do Instagram** no seu app: assim ela publica sem a
    análise da Meta. A tela mostra o endereço de retorno, o webhook e o token para copiar.
    **Apps novos da Meta (2026)** não têm mais o "login do Instagram" (dá "Invalid platform app"): use o
    **API setup with Unified login** (pede um portfólio empresarial grátis) e cole o ID do app, a chave secreta
    (Configurações → Básico) e a **Identificação da configuração**. Aí o login passa pelo facebook.com e tudo usa o
    graph.facebook.com; se a conta tiver Página ligada, o direct sai pela Página.
  - **TikTok**: "Content Posting API". Sem a auditoria do TikTok, post direto sai **privado**; por isso o padrão é
    **rascunho no app** (chega com a legenda; você põe uma música em alta e publica com 1 toque). Depois de auditado,
    troque para "Postar direto".
- **🤖 Postar sozinho**: o navegador desenha as artes em JPEG e grava o vídeo do Reels, sobe tudo para
  `uploads/mkt/` e o cron (ou a carona do conector, ou a rotina do painel) publica na hora do plano:
  Post, Carrossel (até 10), Reels (MP4) e Stories no Instagram; Reels e Carrossel (modo foto) no TikTok.
  Stories com enquete/pergunta/quiz/link não existem na API: esses continuam no lembrete do WhatsApp.
  Se algo não puder sair sozinho (ex.: vídeo WebM no Instagram), chega o aviso para postar à mão. Trava no banco
  impede postar o mesmo post duas vezes. **🚀 Postar agora** publica na hora (e refaz o que deu erro).
- **Cada post tem uma meta**: 🔥 **Viralizar** (feito para ser MANDADO para outro dono de negócio e salvo — envios
  e tempo assistido são o que o Instagram mais usa para mostrar a quem não te segue) ou 🎯 **Trazer cliente**
  (termina com "Comente QUERO que eu te mando…"). Nota de 0 a 100 antes de postar, com o que falta para chegar a 100
  (gancho curto, 1ª linha até 125 letras, sem link na legenda, 3–5 hashtags, termos de busca, chamada certa).
- **🎯 Comentário vira cliente** (webhook `api/instagram_webhook.php`, assinado pela Meta): quem comenta a palavra-chave
  (ou "quanto custa", "preço", "tenho interesse"…) recebe na hora a **resposta privada no direct** com a sua oferta e o
  link do seu WhatsApp, e uma resposta curta no comentário. Se a pessoa responder no direct, a IA continua a conversa
  (24 h, regra da Meta); se mandar o número, vira **prospect quente na Prospecção** e você recebe o aviso.
- **Métricas que ensinam a IA**: 1 dia depois de cada post o Insights traz alcance, envios, salvos e comentários; a
  "nota real" (por 100 contas alcançadas) monta o ranking **🏆 O que funcionou**, e a IA usa os melhores e os piores
  para planejar o próximo plano. O topo da tela mostra o placar dos últimos 30 dias (posts, alcance, envios+salvos, leads).
- Política de privacidade pública em `/privacidade.php` (a Meta pede para ativar o app).

### Fotos para os sites e para o marketing (v18)
- **O plano grátis do Google Gemini não gera mais imagem** (só com faturamento ativo) e o **Pexels pausou as chaves
  novas** (set/2026). Por isso:
- **Fotos com IA grátis: Cloudflare Workers AI** (FLUX, cerca de 200 fotos por dia, sem cartão). Em Configurações →
  Fotos exclusivas: crie a conta em dash.cloudflare.com → AI → Workers AI → Use REST API, copie o *Account ID* e crie
  o token. Reserva: **Pollinations** (chave `sk_` grátis em enter.pollinations.ai). No modo automático a plataforma
  tenta um por um até gerar — se acabar a cota do dia de um, o próximo assume — e recorta no formato do post.
- **Banco de fotos**: **Openverse sem chave** (domínio público), **Pixabay** e **Unsplash** com chave grátis. Quem já
  tem chave do Pexels continua usando. O botão "Testar" em Configurações mostra quem respondeu.
- Reserva no navegador: **Gerar no navegador (Puter)** no Marketing (pede um login grátis do Puter na 1ª vez).

## Mercado Pago: cobrança das assinaturas sem baixa manual
- **Configurar (uma vez):** Admin → Configurações → Pagamentos tem o passo a passo: criar a aplicação (Checkout Pro) em
  mercadopago.com.br/developers, ativar as credenciais de produção, colar o **Access Token** (`APP_USR-…`), cadastrar o
  webhook `https://SEU-DOMINIO/api/mercadopago_webhook.php` (evento Pagamentos) e colar a **Assinatura secreta**.
  O botão **Testar Mercado Pago** confere a conta.
- **Link de pagamento** (Pix, cartão ou boleto) em cada cobrança do Financeiro, com "Mandar link" no WhatsApp.
- **Vendedora IA:** com o "Valor da 1ª cobrança" preenchido no Piloto, a mensagem de fechamento já leva o link. O botão
  "Fechou! Virar cliente" também gera o link (valor do plano escolhido ou da oferta).
- **Pagou → ativa sozinho:** o webhook (assinatura HMAC conferida) avisa, a plataforma confere o pagamento na API, dá
  baixa, ativa a empresa, avança o vencimento e avisa você e o cliente no WhatsApp. Se o webhook falhar, a rotina
  (cron ou carona do conector, a cada 10 min) procura os pagamentos aprovados. Baixa nunca acontece 2 vezes.

## Central de prospecção (Admin → Prospecção de clientes)
- **🤖 Vendedora IA** (Piloto → "A IA vende sozinha"): conversa no WhatsApp até fechar — garante que o dono viu a
  prévia, pergunta o que achou, mostra 1 benefício do ramo, usa a **oferta de entrada** (campo no Piloto), trata as
  objeções e fecha. Quando ele aceita, **cria a empresa e o site sozinha** e manda o link, o login, **como pagar**
  (campo "Como o cliente paga") e o link de indicação. Teto de respostas por lead configurável (padrão 12).
- **🧪 Tom testado**: cada lead recebe um tom (vizinho que ajuda, consultor ou direto); depois de 8 leads por tom a IA
  usa mais o que mais fecha e segue testando 20% das vezes. O placar aparece no Piloto.
- **🎁 Indique e ganhe**: cada cliente tem um link (`/indica/CODIGO`) no painel dele, com botão para mandar no
  WhatsApp. O indicado preenche e vira lead quente na Prospecção ("🎁 Indicação de …"); se fechar, quem indicou ganha
  **+1 mês grátis** automático e recebe o aviso no WhatsApp.
- **🎉 Fechou! Virar cliente** (no cartão do lead e na conversa): em 1 clique cria a empresa com o que o Google trouxe
  (nome, ramo, WhatsApp, telefone, endereço, horário de funcionamento → agenda, logo/cor, fotos e avaliações reais), o
  acesso do dono (login e senha provisória para mandar no WhatsApp), os serviços do ramo e o site publicado com a mesma
  cara da prévia. O site mostra a nota do Google (ex.: 4,7 · 86 avaliações) até juntar avaliações próprias. O lead vira
  "Fechado" e as mensagens da cadência param.
- **Caçada em massa**: marque vários segmentos (ou o atalho "os que mais compram site", ou todos) e várias cidades/bairros
  (um por linha). Com o Google, cada região é dividida em quadrantes (grade 2×2, 3×3, 4×4) para passar do limite de 60
  resultados por busca. A tela mostra antes quantas buscas serão feitas, roda com barra de progresso e, se você fechar,
  continua sozinha pela rotina automática (cron). Empresas repetidas são juntadas.
- **Só oportunidades**: quem tem site próprio fica guardado apenas para comparar; quem só tem Instagram/Facebook/Linktree
  é lead. Cada lead tem pontuação de 0 a 100 (🔥 quente, 🌤 morno, ❄ frio).
- **Prévia do site pronta para cada empresa** (`/previa/CODIGO`): o site do negócio montado na hora, com a identidade do
  ramo, o nome, as fotos e as avaliações reais do Google e o horário. A mensagem leva o link. A página não aparece no Google.
- **Alertas**: quando o dono abre a prévia, você recebe um aviso no WhatsApp e o lead sobe para o topo da fila; se ele
  clicar em "Quero este site", vai para Negociando e abre uma conversa com você. Robôs e você mesmo não contam.
- **Raio-X da concorrência** na mensagem: "Das 20 empresas de barbearia que encontrei aqui em Moema, 8 já têm site
  próprio" (dados reais das caçadas).
- **Fila de hoje (modo sprint)**: primeiro quem abriu a prévia, depois os retornos do dia e os novos mais quentes. Um
  clique abre o WhatsApp com a mensagem pronta e passa para o próximo. Cadência de 3 toques (abertura, dia 2, dia 5) com
  meta diária (padrão 25) para não bloquear o seu número.
- **Copiloto de objeções**: cole a resposta do cliente ("quanto custa?", "já tenho Instagram") e a IA sugere 2 respostas.
- **Mapa** das oportunidades, **funil por segmento** (onde vale caçar mais) e exportação para planilha.
- Configure em **Mensagens e alertas**: as 3 mensagens, o seu WhatsApp para os alertas, o limite diário e se traz fotos e
  avaliações do Google (custa um pouco mais por busca na API do Google).
- Chave: **Places API (New)** no Google Cloud, em Admin → Configurações → Mapas. Sem chave, usa o OpenStreetMap (grátis).

### Abordagem que faz o dono responder (em vez de "não, obrigado")
- A **1ª mensagem não tem link**: se apresenta (seu nome em Prospecção → Mensagens), mostra que você olhou a
  empresa e pede permissão para mandar a demonstração, ou faz uma pergunta fácil. Nunca diz "vocês não têm site".
- Duas versões da abertura (**teste A/B**). A tela mostra qual recebe mais respostas.
- **Respostas prontas** em cada lead: "Pode mandar" (vai o link), "Já tenho site", "Quanto custa?", "Quem é você?",
  "Agora não" e "Não tenho interesse". Elas abrem o WhatsApp com o texto e já anotam que ele respondeu.
- **Verificação de site antes de chamar**: confere o perfil do WhatsApp da empresa (o número tem WhatsApp? tem
  site ou Linktree no perfil?) e domínios com o nome dela. Quem já tem site sai da fila sozinho. Para olhar
  no Google ou no Instagram, use os botões 🔎 e 📸. A conferência do WhatsApp usa o número da plataforma
  conectado (Admin → WhatsApp).
- A **prévia usa a cor e o logo da empresa** (foto do perfil do WhatsApp, cor no nome ou cor das fotos do Google).

### Piloto automático e conversas (v18)
- **🤖 Piloto automático**: a plataforma manda a cadência sozinha pelo WhatsApp dela (Admin → WhatsApp → número da
  plataforma), no horário e nos dias escolhidos nos botões, uma mensagem por vez com intervalo aleatório. A IA escreve
  uma mensagem diferente para cada empresa (ou usa os seus modelos). Tudo fica registrado na prospecção.
- **Proteção do número**: aquecimento (10/dia na 1ª semana, 18, 28, 40…), limite por hora, só números conferidos no
  WhatsApp, nunca 2 vezes no mesmo dia para a mesma pessoa, nunca para quem já é cliente, para na hora quando a pessoa
  responde e tira da lista (com um "combinado") quem pede para parar. **Pausa sozinho** se muita gente pedir para parar,
  recusar, ou se 3 envios seguidos falharem — e avisa no seu WhatsApp. O WhatsApp não tem limite "seguro" oficial para
  contato frio: os freios diminuem o risco, não zeram. Use um chip só para a prospecção.
- **Perfis de risco** (botões no topo do piloto): 🧭 **Automático** (começa no Seguro e passa para o Equilibrado
  sozinho quando o número está saudável; volta se piorar), 🛡 **Seguro** (dias úteis, comercial, 8–15 min, até 40/dia),
  ⚖ **Equilibrado** (seg–sáb 8h–19h, 4–10 min, até 70/dia), 🔥 **Agressivo — 24 horas** (todo dia, inclusive de
  madrugada, 1–2 min, até 300/dia; não pausa por recusas e, se os envios falharem seguidos, espera 30 min e tenta de
  novo) e ✋ **Personalizado**. O WhatsApp não publica um número "seguro" por minuto ou por hora para contato frio:
  o que derruba o número são bloqueios e denúncias, texto igual para muitos e número novo disparando. Quem pede
  para parar sempre sai da lista (LGPD), em qualquer perfil.
- **💬 Conversas**: as respostas que chegam no número da plataforma aparecem com a **leitura da IA** (interessado,
  pediu o link, preço, já tem site, não quer…), o funil se move sozinho e vêm 2 respostas sugeridas. Responda pela
  plataforma com um clique, ou ligue "A IA responde sozinha" (casos simples, até 2 vezes por lead, com atraso humano).
- As respostas chegam pelo **Conector GDS v18** (atualize a pasta `whatsapp-conector` no celular/computador). Com
  WhatsApp em servidor (Evolution API ou conector em servidor), configure o webhook mostrado na aba Piloto
  (`/api/whatsapp_receber.php`).
- Para volume grande e oficial: API da Meta (WhatsApp Cloud) com modelo aprovado — cerca de R$ 0,30 a R$ 0,40 por
  mensagem de marketing no Brasil.

## E-mail e senhas
- **Admin → Configurações → E-mail (SMTP)**: na Hostinger, crie um e-mail em "E-mails" e use `smtp.hostinger.com`,
  porta 465, o e-mail completo e a senha dele. Use **Enviar teste**. Sem isso, a recuperação de senha e os avisos por
  e-mail dependem da função mail() da hospedagem, que costuma não entregar.
- **Esqueci minha senha** manda o link por esse SMTP (válido por 1 hora).
- **Administrador sem acesso**: abra `/recuperar_acesso.php` e use a chave `GDS_CHAVE_RECUPERACAO` que está em
  `config/local.php` (no Gerenciador de Arquivos da Hostinger). Empresas: o admin troca a senha em Empresas → editar.

## WhatsApp automático (a plataforma manda as mensagens sozinha)
O cliente final recebe no WhatsApp: agendamento recebido, **confirmado**, **lembrete** antes do horário, cancelado,
pedido de avaliação; orçamento recebido, **orçamento com o valor**, aprovado; **pedido do delivery** recebido,
em preparo, **saiu para entrega** / pronto para retirada, entregue. A empresa também é avisada no WhatsApp dela
a cada agendamento, orçamento ou pedido novo.

0. **Mais simples e grátis: Conector GDS.** A pasta `whatsapp-conector` (ao lado da pasta `gds`) é um programa
   pequeno que liga os números por QR code. **Funciona em hospedagem compartilhada**: rode-o num celular Android
   (Termux) ou num computador sempre ligado e ele **busca** as mensagens no site. Em **Admin → WhatsApp**,
   escolha **Conector GDS no celular ou computador**, salve e copie o endereço e a chave para o `ponte.txt`.
   Também roda em servidor (app Node.js ou VPS). Passo a passo em `whatsapp-conector/LEIA-ME.md`.
1. **Admin → WhatsApp**: escolha o serviço — **Evolution API** ou **WPPConnect** (gratuitas; rodam num VPS à parte,
   ex.: Hostinger VPS), **WhatsApp Cloud API oficial da Meta** (sem risco de bloqueio; usa um modelo "Utilidade"
   quando o cliente não falou com o número nas últimas 24h), **Z-API** ou **UltraMsg** (pagas, sem servidor) ou
   **Webhook** (n8n/Make). Cole a URL e a chave, salve,
   clique em **Gerar QR code** e leia com o WhatsApp do número da plataforma. Use **Enviar teste**.
2. Cada empresa, em **WhatsApp automático**, escolhe: usar o número da plataforma (zero configuração),
   **conectar o próprio WhatsApp por QR code**, ou desligar. Lá ela liga/desliga e edita cada mensagem (com prévia).
3. Sem integração nenhuma, nada se perde: a mensagem fica pronta no painel com o botão **Enviar pelo WhatsApp**.
4. Lembretes: rodam sozinhos com o uso do painel; para ficar pontual crie um **Cron Job** a cada 5 minutos
   com a URL mostrada em Admin → WhatsApp (`cron.php?chave=...`).

## Pedidos de delivery pelo site
Em negócios com a forma **Catálogo / delivery** (hamburgueria, pizzaria, açaí, loja…), cada item tem o botão
**Adicionar** e aparece o carrinho: entrega ou retirada, endereço, taxa de entrega, pedido mínimo, forma de
pagamento (com troco) e observações. O pedido chega em **Pedidos/Orçamentos** com botões de próximo passo
(Confirmar → Saiu para entrega → Entregue) e o cliente acompanha numa página própria (`/site/pedido.php`).
Configure em **Configurações → Pedidos pelo site**.

## Funções por segmento (inspiradas nas melhores plataformas de cada nicho)
Tudo entra sozinho no banco (migração 20). Cada empresa liga o que quiser no painel.

**Pagamento Pix com confirmação automática** (Mercado Pago da própria empresa — Configurações → Pagamento online)
- Agenda: sinal para reservar (valor fixo, % ou total), cobrado de todos, só de clientes novos ou só de quem já faltou.
  A tela do cliente confirma sozinha quando o Pix cai; o horário só é confirmado depois do pagamento.
- Delivery: opção "Pix agora pelo site" no carrinho, com QR Code e copia e cola.
- Orçamento: o cliente aprova pelo link (fica registrado nome, data/hora e IP) e paga o sinal (% configurável).
- O cron confere os pagamentos pendentes; a agenda ganhou o status **Faltou**.

**Cardápio profissional (delivery)** — em Serviços/Itens, campo "Opções" com uma linha por grupo:
- `Ponto* (1): Mal passado, Ao ponto, Bem passado` (o `*` torna obrigatório, o número é o máximo)
- `Adicionais (até 3): Bacon +4, Cheddar +3`
- `Sabores* (2, maior preço): Calabresa 45, Portuguesa 52` (meio a meio cobrando o maior)
- "Peça também": itens marcados como sugestão aparecem no carrinho. Fora do horário o site avisa e aceita o pedido
  "para quando abrir" (ou esconde o carrinho, se você desligar).
- **QR Code por mesa**: Painel → **QR Code das mesas** gera e imprime um QR por mesa. O cliente aponta a câmera, o
  carrinho já vem "na mesa 7" (sem entrega nem taxa, WhatsApp opcional) e o pedido chega com o selo da mesa.

**Agenda (barbearia, salão, estética, academia, pilates…)**
- **Lista de espera**: dia lotado → o cliente entra na lista; quando alguém cancela, o primeiro é avisado no WhatsApp.
- **Lembrete de retorno**: em cada serviço, "lembrar o cliente depois de X dias" (ex.: corte a cada 21 dias).
- **Clube de assinatura e pacotes** (Painel → Clube e pacotes): plano mensal com N usos ou pacote de sessões. O cliente
  assina pelo site com Pix, o uso sai do plano ao agendar (volta se cancelar) e a renovação é avisada 3 dias antes.
- **Turmas**: serviço com "vagas" vira aula em grupo (o horário mostra quantas vagas restam).
- **Aula experimental grátis**: botão em destaque no topo do site, uma por cliente.

**Orçamento profissional (eventos, obras, oficinas, assistência técnica)**
- Perguntas por serviço (ex.: "Quantos convidados?") que aparecem quando o cliente escolhe o serviço.
- O cliente pode mandar até 3 fotos (re-gravadas como JPEG em `uploads/pedidos/`).
- **Data livre/ocupada**: em Configurações → Pagamento online, "Eventos por data". Com 1, o site avisa "data já
  reservada" assim que o cliente digita a data, e o painel alerta se você aprovar dois eventos no mesmo dia.
- **Atualizar o cliente** (pedido aprovado/em andamento): texto + foto da etapa ("peça chegou", "pintura pronta"). O cliente
  recebe no WhatsApp e vê a linha do tempo com fotos no link do pedido.

### Painel por segmento (v12, migração 23)
Cada ramo abre um painel com o que as plataformas famosas do ramo mostram, só que tudo junto e ligado ao site:
- **Delivery → Gestor de pedidos** (estilo iFood/Anota AI): quadro ao vivo Novos → Em preparo → Prontos/a caminho →
  Concluídos, com som, cronômetro (amarelo 20 min, vermelho 40), recusar com motivo, pausar a loja e impressão
  automática ao aceitar. Cada etapa avisa o cliente no WhatsApp e guarda a hora (tempo médio de preparo e total).
- **Dashboard por ramo**: delivery (hoje x ontem, pedidos por hora, mais vendidos, recorrência); agenda (ocupação do
  dia, previsto, faltas, caixa, aniversariantes); orçamento (funil do mês e taxa de aprovação).
- **Agenda → Caixa e comandas** (estilo AppBarber/Trinks): "Fechar comanda" em cada horário (serviço + produtos do
  catálogo + itens avulsos, desconto, forma de pagamento), comanda avulsa para quem chegou sem marcar, caixa do dia
  por forma de pagamento e por profissional com a comissão (só serviços, com o desconto descontado) e estorno.
- **Ficha do cliente** (clique no nome em Clientes): total gasto, visitas, ticket médio, "volta a cada X dias",
  faltas, próximo horário, histórico de atendimentos/pedidos/comandas, aniversário, anotações (alergias,
  preferências) e etiquetas (VIP, indica amigos…) com filtro. Sugere a próxima ação (parabéns, chamar de volta).
- **Orçamento item a item**: em "Responder orçamento", linhas de descrição × qtd × valor; o total soma sozinho e o
  cliente vê o orçamento detalhado no link.

### Delivery completo (v13, migração 24)
Estudo do segmento delivery comparando com iFood, Anota AI e Saipos; cada tela foi revista:
- **Cardápio** (Painel → Cardápio): itens agrupados por categoria, busca, preço editado direto na lista, chave
  "Disponível/Esgotado" por item e por categoria inteira, foto enviada do celular (ou criada com IA), duplicar item
  e quanto cada item vendeu nos últimos 30 dias.
- **Novo pedido (balcão/telefone/mesa)** — PDV: toca nos itens (com opções e observação), o telefone puxa o cliente e
  o último endereço, desconto, troco e "já pago". Entra no Gestor já em preparo e pode imprimir a comanda.
- **Gestor**: mostra a origem (site, telefone, balcão), muda o tempo de entrega que aparece no site com um clique e,
  em "Saiu para entrega", pergunta qual entregador vai levar e abre o WhatsApp dele com o endereço, a rota no Maps e
  quanto cobrar. Sem WhatsApp automático, aparece o botão para enviar a mensagem ao cliente na hora.
- **Entregas e fechamento**: cadastro dos entregadores (valor por entrega ou repasse da taxa) e o fechamento do dia
  por forma de pagamento e por canal, taxas, descontos, acerto de cada entregador (a pagar e dinheiro com ele) e
  cancelados com o motivo. Imprime.
- **Site**: cupom de desconto no carrinho (módulo Cupons passa a vir ligado no delivery), "Peça também" não sugere o
  que já está no pedido, "Pedir de novo" no acompanhamento remonta o carrinho e o "Pedido entregue" pede a avaliação.

### Todos os segmentos no nível das plataformas do ramo (v14, migração 25)
**Delivery (comparado com iFood, Anota AI, 99Food, Goomer, Saipos):**
- Endereço como nos apps: **CEP** (preenche rua, bairro e cidade sozinho), **rua**, **número** obrigatório (ou "Sem
  número" + **ponto de referência obrigatório**), **bairro** obrigatório (ligado à taxa), complemento e referência.
  O site lembra os dados no aparelho para o próximo pedido. O PDV usa os mesmos campos e puxa o último endereço do cliente.
- Mensagem do entregador com rota pelo endereço limpo e a referência destacada.
- CPF na nota (validado), frete grátis acima de um valor ("faltam R$ X"), pedido agendado para um horário,
  preço promocional riscado com % off, estoque com baixa automática e "Esgotado" ao zerar, bloquear cliente (trote).
**Lojas (catálogo):** estoque por item, aviso de estoque baixo, preço "de/por".
**Agenda (barbearia, salão, estética, clínicas, academias):** o cliente **confirma presença** e **remarca** pelo link
(só horários livres; o lembrete pede a confirmação), aviso na agenda de cliente que costuma faltar, **anotações de
cada atendimento** na ficha (evolução/prontuário).
**Orçamento (eventos, obras, oficinas, serviços):** **validade** (vencido não pode ser aprovado), painel "Precisam de
atenção" com cobrança pelo WhatsApp, **orçamento em documento/PDF** para o cliente e para a empresa.
**Contato e imobiliária:**
- **Contatos e negociações (funil/CRM)** para todos: etapas (Novo → Em conversa → Visita → Proposta → Fechado/Perdido),
  arrastar cartões, valor, próximo contato com aviso, anotações, contato anotado à mão.
- **Imóveis** (imobiliária, construtora, loteamento): cadastro como nos portais (finalidade, tipo, preço, condomínio,
  IPTU, quartos, suítes, banheiros, vagas, áreas, características, até 20 fotos, vídeo, proprietário), vitrine no site
  com filtros, ficha com galeria, link de cada imóvel, contagem de visualizações e **"Agendar visita"** que cai no funil.

### Skills de design, auditoria e paridade entre segmentos (v17, migração 27)
**Instalado no projeto** (carrega em toda sessão do Claude Code nesta pasta):
- `.claude/skills/web-design-guidelines` — Web Interface Guidelines da Vercel (regras em `regras.md`, MIT).
- `.claude/skills/frontend-design` — guia de design da Anthropic (Apache 2.0).
- `.mcp.json` — **Chrome DevTools MCP** (inspecionar o site num Chrome real), **shadcn-ui MCP** (biblioteca de
  componentes) e **Magic MCP da 21st.dev** (gera componentes; precisa da chave grátis em 21st.dev/mcp na variável
  de ambiente `API_KEY_21ST` — nunca escreva a chave no arquivo).

**Aplicado nos sites criados pela IA** (`ia_site_engine.php`): identidade tirada do mundo do negócio, sem os
"sinais de site feito por IA" (creme+terracota, preto+verde-limão, CAIXA ALTA espaçada, "→" em todo botão, efeito em
toda seção), e o checklist de interface (alt/width/height nas imagens, um h1, "Pular para o conteúdo", foco visível,
sem `transition: all`, toque e área segura no celular, theme-color, "…").

**Auditoria automática (axe-core, WCAG 2.1 AA)** no painel das 4 empresas de teste e nos sites: de 17 regras / 1.497
ocorrências para **0**. Na raiz:
- Painel: cada rótulo é ligado ao seu campo, botão só com ícone ganha nome ("Editar", "Excluir"…), títulos em ordem,
  cabeçalho de tabela vazio vira "Ações", quadros com rolagem acessíveis pelo teclado, "Pular para o conteúdo"
  (`acessibilidade()` em `gds-painel-editores.js`); botões com `--primary-btn` (4,95:1), legendas `--text-mute` 6:1.
- Site: `frag_cor_contraste` com o limite certo (0,179) e `frag_cor_legivel()` — versão do acento para TEXTO com
  4,5:1 garantido (`--accent-txt` / `--gds-accent-txt`); site da IA sem `<main>` ganha um (`site_garantir_main`).

**O que um segmento tinha e os outros não:**
- Agenda: **avaliar o atendimento** na página do cliente e **"Agendar de novo"** (mesmo serviço e profissional).
- Orçamento: **avaliar o serviço concluído** e **"Pedir novo orçamento"** já com o serviço.
- Selo **🔥 Mais procurado** e **busca de serviços** também na agenda e no orçamento (inclusive tabela de preços).
- Cardápio: **"Esgotou" por opção** (ex.: acabou o bacon) no editor; o site mostra "Esgotado" e o servidor recusa.
- Caixa da agenda: **recibo impresso** de cada comanda (`comanda_imprimir.php`), como a comanda do delivery.
- Imóveis: **simulador de financiamento** (entrada, prazo e juros → parcela) e **compartilhar** o imóvel.

### Site limpo e com a cara de cada empresa (v18, migração 34)
**Padrão novo de todo site e da prévia** (`includes/site_limpo.php` + `assets/css/site-limpo.css`), aplicando as
skills `frontend-design` e `web-design-guidelines`:
- **Menos informação de cara:** topo com nome, uma frase e um botão; sem selos, setas, "01/02/03", contadores e
  efeitos em toda seção. Uma linha de confiança só (nota do Google, avaliações ou anos de casa). A página caiu de
  ~10 mil px para ~4 mil px.
- **Ordem:** serviços/cardápio → agendar/pedir → sobre → equipe (se houver mais de 1) → fotos (se houver 3+) →
  clube/cupons → avaliações → "Onde estamos" (endereço, horário, botões WhatsApp/Como chegar e mapa). O formulário
  de contato só aparece se a empresa não tem WhatsApp ou é do modo contato.
- **Cada empresa fica diferente:** 8 personalidades (Acolhedor, Elegante, Firme, Claro, Editorial, Noturno, Sabor,
  Loja), cada uma com 2 pares de fontes; 4 jeitos de topo (Foto inteira, Foto ao lado, Moldura, Só o nome). A escolha
  vem do nicho (barbearia → Firme; pet → Acolhedor; restaurante → Sabor…) e do nome da empresa, e a cor é a da marca.
  Sem foto, o topo vira "Só o nome" com a cor da marca.
- **No painel** (Configurações → Visual do meu site): "Jeito do site" (Limpo recomendado / Completo = modelo antigo),
  "Topo do site" e "Personalidade" (Automático por padrão). Colunas `sites.visual`, `sites.composicao`, `sites.tom`.
- No celular aparece uma barra fixa com o botão principal depois do topo (o botão flutuante do WhatsApp some).
- Sites feitos 100% pela IA (`ia_modo_total`) e o layout "legado" continuam como estão.
- Auditoria axe-core (WCAG AA) do modelo novo: **0 problemas**.

**Limpo, mas com a tecnologia à mostra (v18.1)** — `assets/js/site-vivo.js` (sem bibliotecas):
- **Painel ao vivo** no topo, o elemento marcante da página: "Aberto agora" com ponto pulsando, os **próximos
  horários livres de verdade** (agenda), tempo e taxa de entrega (delivery), quantos imóveis disponíveis
  (imobiliária) e a nota contando até o valor. Vidro fosco, borda de luz girando e brilho que segue o mouse.
- **Luz da cor da marca** atrás do título que acompanha o mouse, com grade/pontos e textura variando por
  personalidade (Firme: feixes na diagonal; Noturno: neon; Elegante: luz suave sem grade…).
- **Entrada orquestrada** (título palavra por palavra → texto → botões → painel → horários um a um), linha de
  progresso da leitura, cabeçalho de vidro que encolhe, títulos revelados ao rolar, itens em sequência,
  botão principal "imã" com brilho e faixa final escura com luz.
- Topo só com o nome tem 2 variações (lado a lado ou centralizado), para empresas sem foto não ficarem iguais.
- Cores de marca "do meio" (ex.: vermelho #e23b2a) são escurecidas só o necessário nos botões para o texto
  branco ter 4,5:1. "Reduzir movimento" do sistema: tudo aparece pronto, sem animação; se o script não
  carregar, o conteúdo aparece sozinho em 2,5 s.

### Tudo com botões no painel (v16)
**Regra do projeto:** nenhuma configuração do painel exige digitar "fórmula" ou lista separada por vírgula.
Use os editores de `assets/js/gds-painel-editores.js` (carregado em todo o painel por `painel_head()`), que gravam
num `<input type="hidden">`:
- `data-editor-opcoes` — opções do item: modelos prontos (Ponto da carne, Adicionais, Retirar ingredientes, Molhos,
  Tamanho, Sabores meio a meio, Bebida do combo, Acompanhamento, grupo em branco), chaves **Obrigatório**,
  **Cliente escolhe a quantidade (2x)** e **Meio a meio**, botões − / + de mínimo e máximo, "+ Adicionar opção",
  subir/descer/remover grupo e o resumo "O cliente vê: …". O servidor valida com `opc_normalizar()`.
- `data-editor-bairros` — bairros e taxa ("+ Adicionar bairro", "Mesma taxa para todos").
- `data-editor-lista` — perguntas do orçamento (sugestões prontas + "Escrever pergunta").
- `data-editor-chips` — formas de pagamento, etiquetas do cliente, características do imóvel ("+ Outra").
- `data-preset-alvo` / `data-preset` — botões que preenchem um campo (tempo de entrega).

### Tela do produto como no iFood (v15, migração 26)
- Todo item do cardápio abre a **tela do produto**: foto grande, descrição, "Serve X pessoas", opções, campo
  **"Alguma observação?"** (ex.: tirar a cebola, maionese à parte) e quantidade antes de adicionar. No celular abre
  como gaveta de baixo para cima.
- **Adicionais com quantidade** (2x bacon), **mínimo** de escolhas e grupo para **retirar ingredientes**: tudo
  montado com os botões do editor de opções (v16).
- O pedido grava as escolhas com o nome do grupo ("Retirar: Cebola") e a **observação de cada item**, que aparece no
  carrinho (com **editar**), no Gestor (⚠ em amarelo, uma escolha por linha), na comanda impressa (*** OBS ***), no
  WhatsApp e no acompanhamento. "Pedir de novo" mantém a observação.
- Cardápio no site: **busca** (sem precisar de acento), **categorias presas no topo** ao rolar e selo
  **🔥 Mais pedido** nos 3 itens que mais saíram em 30 dias.
- Acompanhamento do pedido: **previsão de entrega** ("até 20:35"), **cancelar** enquanto a loja não aceitou (devolve o
  estoque e avisa a loja) e **avaliar o pedido** com estrelas depois de entregue (vai para Avaliações, para aprovar).

## Domínio próprio do cliente
1. **Admin → Domínios**: confira o **IP do servidor** (na Hostinger: Sites → Painel → Detalhes do plano) e o endereço da plataforma.
2. A empresa (em **Meu domínio**) ou o admin cadastra o domínio. A tela mostra os registros **A** (`@` e `www`)
   para criar no Registro.br/GoDaddy/Hostinger, com passo a passo.
3. Na Hostinger: **Sites → Painel → Domínios → Domínios estacionados** → adicione o domínio (o admin recebe e-mail
   quando uma empresa cadastra um). O SSL sai sozinho em 1–2 h.
4. Clique em **Verificar**: o sistema testa DNS → ativação no servidor → HTTPS e explica o que falta.

## Componentes do site (agendamento, serviços, contato, WhatsApp…)
Os blocos funcionais do site têm design próprio, que se adapta às cores de qualquer site (claro ou escuro) —
a IA só decide onde eles ficam. No Estúdio IA, "Cor dos botões" troca a cor de destaque deles.
- **Serviços**: cartões com foto, descrição, duração, preço ("a partir de" opcional), selo de destaque,
  filtro por categoria e botão "Agendar" que já abre o agendamento com o serviço escolhido.
- **Agendamento passo a passo**: serviço → profissional → calendário só com horários livres (manhã/tarde/noite,
  respeitando pausa de almoço, folgas e feriados) → dados, com cupom de desconto e resumo do valor.
  Na confirmação: Google Agenda, arquivo para iPhone/Outlook, confirmar pelo WhatsApp e PIX.
  O cliente recebe um link para ver ou **cancelar** o agendamento sozinho.
- **Contato**: WhatsApp, telefone, e-mail, endereço com rota, Instagram e "aberto agora", mais formulário com assunto.
- **WhatsApp flutuante** com mensagem pronta configurável e balão de convite.
- **Equipe** ("Agendar com fulano"), **avaliações** (nota média, distribuição, estrelas clicáveis),
  **galeria** com ampliação, **cupons** com "copiar código", **mapa** com "Como chegar"/Waze e **horários** da semana.
- A empresa recebe **e-mail** a cada agendamento, cancelamento ou mensagem (desligável em Configurações).
- Dados estruturados para o Google (negócio local, serviços, horário, nota média).

## O que já funciona 100%
- Cadastro/login separado por papel (admin x empresa), com CSRF, recuperação de senha por e-mail e troca de senha.
- Isolamento de dados: cada empresa só vê seus próprios clientes/agenda/serviços.
- Agenda inteligente: horário de atendimento por empresa, calendário do site que esconde horários ocupados e
  checagem de conflito considerando a duração do serviço (não só a hora exata).
- CRUD de empresas, clientes, serviços, profissionais, pedidos/orçamentos, cupons, agendamentos.
- Site público com 4 estilos de template (clássico/bold/editorial/grid), banner, blocos de conteúdo livres,
  ordem de seções configurável, SEO, mapa, Analytics — **e pré-visualização ao vivo** em toda tela de edição.
- **Estúdio IA** (veja acima).
- Avaliações deixadas pelos próprios clientes no site, com aprovação da empresa antes de aparecer.
- Anti-spam nos formulários do site (campo invisível + limite de envios), sem captcha chato.
- IA (Gemini grátis ou Anthropic paga) usando diferenciais reais do negócio, não texto genérico.
- Financeiro com geração real de cobrança PIX via Mercado Pago (com token configurado).
- Relatórios de receita e ranking de empresas por faturamento.
- Templates e Segmentos editáveis pela própria tela do admin.
- Empresa vê a própria assinatura e histórico de pagamentos, e tem um indicador de "completude do site" no dashboard.

## O que precisa da SUA configuração (chaves de API)
- **IA real**: preencha `IA_API_KEY` em `config/db.php`. O arquivo
  `empresa/ia_gerar_texto.php` já mostra uma chamada real e funcional à API
  da Anthropic — é só ligar no seu editor de site.
- **Mercado Pago**: cada empresa cola o próprio Access Token em Configurações → Pagamento online para receber
  Pix dos clientes dela (sinal, delivery, orçamento, clube). A mensalidade que as empresas pagam à plataforma
  ainda é marcada à mão em `admin/financeiro.php`.
- **WhatsApp automático**: configure um dos serviços em Admin → WhatsApp (veja a seção acima).

## Segurança
- **Rode o `adicional_11_rls_seguranca.sql`.** Sem ele, qualquer pessoa com a "anon key" do Supabase
  (que é pública) consegue ler as tabelas pela API REST do Supabase — inclusive chaves de API e hashes de senha.
- Todo formulário e link de ação do painel tem proteção CSRF (verificada automaticamente em `exigir_login`).
- Pastas `includes/`, `config/` e `sql/` bloqueadas para acesso pelo navegador (`.htaccess`).
- Erros de banco não mostram mais host/usuário na tela (vão para o log do servidor).
- Senhas são armazenadas com hash (`password_hash`), nunca em texto puro (mínimo de 8 caracteres).
- Todas as queries usam prepared statements (proteção contra SQL Injection).
- Saída de dados do usuário é escapada (`htmlspecialchars`) contra XSS.
- **Apague `install.php` depois de usar.**
