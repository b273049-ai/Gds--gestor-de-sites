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

## WhatsApp automático (a plataforma manda as mensagens sozinha)
O cliente final recebe no WhatsApp: agendamento recebido, **confirmado**, **lembrete** antes do horário, cancelado,
pedido de avaliação; orçamento recebido, **orçamento com o valor**, aprovado; **pedido do delivery** recebido,
em preparo, **saiu para entrega** / pronto para retirada, entregue. A empresa também é avisada no WhatsApp dela
a cada agendamento, orçamento ou pedido novo.

1. **Admin → WhatsApp**: escolha o serviço — **Evolution API** (gratuita; roda num VPS à parte, ex.: Hostinger VPS
   com o modelo Evolution API), **Z-API** (paga) ou **Webhook** (n8n/Make). Cole a URL e a chave, salve,
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
- **Mercado Pago**: preencha `MERCADOPAGO_ACCESS_TOKEN`. A tela
  `admin/financeiro.php` hoje permite marcar pagamento manualmente; a geração
  automática de cobrança PIX via Mercado Pago precisa da sua conta de vendedor
  configurada (posso implementar o endpoint assim que você tiver o token).
- **WhatsApp automático** (envio de lembretes): precisa de um provedor tipo
  WhatsApp Cloud API ou Twilio — hoje o site já tem o botão "Falar no WhatsApp"
  (link direto), mas envio automático de mensagens requer token configurado.

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
