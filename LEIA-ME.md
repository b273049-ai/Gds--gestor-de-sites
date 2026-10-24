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
   Se algum já tiver sido rodado antes, rodar de novo não quebra nada (os comandos usam `IF NOT EXISTS`).

## 2. Configurar conexão
No Supabase: **Project Settings → Database → Connection string** (aba "URI" ou "Connection parameters").
Como sua hospedagem PHP provavelmente não suporta IPv6, use o **Connection Pooling** (Session/Transaction pooler),
não a conexão direta.

Abra `config/db.php` e troque:
- `DB_HOST` → o host do pooler, algo como `aws-0-sa-east-1.pooler.supabase.com`
- `DB_PORT` → `6543` (transaction pooler) — deixe como está se não souber qual usar
- `DB_USER` → formato `postgres.SEU_PROJECT_REF` (o Supabase mostra isso pronto na tela de conexão)
- `DB_PASS` → a senha do banco que você definiu ao criar o projeto (se esqueceu, dá pra resetar em Database → Settings)
- (opcional, depois) `IA_API_KEY`, `MERCADOPAGO_ACCESS_TOKEN`, `WHATSAPP_API_TOKEN`.

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

Se quiser URLs bonitas tipo `/barbearia-x` sem `index.php?slug=`, adicione
um arquivo `.htaccess` na pasta `site/` com regra de rewrite (posso gerar se quiser).

## O que já funciona 100%
- Cadastro/login separado por papel (admin x empresa), com CSRF, recuperação de senha por e-mail e troca de senha.
- Isolamento de dados: cada empresa só vê seus próprios clientes/agenda/serviços.
- Verificação de conflito de horário (evita agendamento duplo do mesmo profissional).
- CRUD de empresas, clientes, serviços, profissionais, pedidos/orçamentos, cupons, agendamentos.
- Site público com 4 estilos de template (clássico/bold/editorial/grid), banner, blocos de conteúdo livres,
  ordem de seções configurável, SEO, mapa, Analytics — **e pré-visualização ao vivo** em toda tela de edição.
- **Criador de Site com IA (modo total)**: a empresa escreve um briefing livre sobre o negócio, a IA desenha o
  HTML/CSS/JS inteiro do site (com animações, layout e cores 100% dela), e o sistema injeta por baixo dos panos
  as partes que precisam ser reais (agendamento que grava no banco, lista de serviços, avaliações). Fica em
  Empresa → 🚀 Criador de Site IA.
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
- Senhas são armazenadas com hash (`password_hash`), nunca em texto puro.
- Todas as queries usam prepared statements (proteção contra SQL Injection).
- Saída de dados do usuário é escapada (`htmlspecialchars`) contra XSS.
- **Apague `install.php` depois de usar.**
