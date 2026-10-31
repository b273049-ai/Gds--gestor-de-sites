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

## Marketing com IA (Admin)
A IA escreve o post (título, legenda, hashtags, roteiro) e já sugere a foto e o estilo. A tela:
- gera **foto exclusiva com IA**, busca no **banco de fotos** ou usa a sua;
- monta a arte em **9 modelos** (foto + título, foto + bloco, pergunta, número gigante, impacto, lista, antes × depois,
  depoimento, oferta), **6 estilos** (Neon, Editorial, Bold, Pop, Minimal, Luxo) e **8 paletas**, com o botão
  **Variar visual** para nenhum post sair igual ao outro;
- baixa o **carrossel** completo (capa + 1 slide por item + fechamento);
- grava o **vídeo MP4 9:16** com gancho em letras gigantes, fotos em movimento e oferta no final.

## Central de prospecção (Admin → Prospecção de clientes)
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
   pequeno, sem Docker e sem banco, que liga os números por QR code. Deixe-o ligado num app Node.js da Hostinger,
   num VPS ou num computador sempre ligado (passo a passo em `whatsapp-conector/LEIA-ME.md`) e, em
   **Admin → WhatsApp**, escolha **Conector GDS** e cole o endereço e a chave.
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
