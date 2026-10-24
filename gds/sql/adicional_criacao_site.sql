-- ============================================================
-- ADICIONAL: Criação de Site (assistente passo a passo do admin)
-- Cole este bloco no SQL Editor do Supabase e clique em Run.
-- Não apaga nada existente, só adiciona.
-- ============================================================

-- Novas colunas em "sites" para objetivo e identidade visual
ALTER TABLE sites ADD COLUMN IF NOT EXISTS objetivo VARCHAR(255);
ALTER TABLE sites ADD COLUMN IF NOT EXISTS cor_primaria VARCHAR(7) DEFAULT '#1a1a2e';
ALTER TABLE sites ADD COLUMN IF NOT EXISTS cor_secundaria VARCHAR(7) DEFAULT '#16213e';

-- Catálogo de funcionalidades disponíveis (algumas específicas de um
-- segmento, outras universais quando segmento_id é NULL)
CREATE TABLE IF NOT EXISTS funcionalidades (
    id SERIAL PRIMARY KEY,
    segmento_id INT REFERENCES segmentos(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    chave VARCHAR(50) NOT NULL
);

-- Funcionalidades que a empresa escolheu ativar no site dela
CREATE TABLE IF NOT EXISTS site_funcionalidades (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    funcionalidade_id INT NOT NULL REFERENCES funcionalidades(id) ON DELETE CASCADE,
    UNIQUE(empresa_id, funcionalidade_id)
);

-- Templates universais (servem para qualquer segmento)
INSERT INTO templates (nome, segmento_id, cor_primaria, cor_secundaria) VALUES
('Escuro Moderno', NULL, '#1a1a2e', '#16213e'),
('Elegante Vinho', NULL, '#3a1220', '#5c1a2e'),
('Verde Natureza', NULL, '#0f2e1d', '#164a30'),
('Azul Corporativo', NULL, '#0f1c3f', '#16295c');

-- Funcionalidades por segmento (baseado no infográfico)
INSERT INTO funcionalidades (segmento_id, nome, chave)
SELECT id, f.nome, f.chave FROM segmentos, (VALUES
    ('Agendamento','agendamento'),('Profissionais','profissionais'),('Serviços','servicos'),
    ('Cancelamento com motivo','cancelamento_motivo'),('Reagendamento','reagendamento'),
    ('Avisos (24h/2h/1h)','avisos'),('PIX/Pagamento','pix'),('Cupons','cupons'),
    ('Avaliações','avaliacoes'),('Galeria','galeria')
) AS f(nome, chave)
WHERE segmentos.slug = 'barbearia';

INSERT INTO funcionalidades (segmento_id, nome, chave)
SELECT id, f.nome, f.chave FROM segmentos, (VALUES
    ('Especialidades','especialidades'),('Profissionais','profissionais'),('Agendamento','agendamento'),
    ('Convênios','convenios'),('Formulários','formularios'),('Avisos','avisos'),
    ('Lembretes','lembretes'),('WhatsApp','whatsapp'),('Avaliações','avaliacoes'),('Localização','localizacao')
) AS f(nome, chave)
WHERE segmentos.slug = 'clinica';

INSERT INTO funcionalidades (segmento_id, nome, chave)
SELECT id, f.nome, f.chave FROM segmentos, (VALUES
    ('Serviços','servicos'),('Orçamentos','orcamentos'),('Agendamento','agendamento'),
    ('Veículo (marca/modelo/ano)','veiculo'),('Descrição do problema','descricao_problema'),
    ('Upload de fotos','upload_fotos'),('Leads','leads'),('WhatsApp','whatsapp'),
    ('Avaliações','avaliacoes'),('Localização','localizacao')
) AS f(nome, chave)
WHERE segmentos.slug = 'mecanica';

INSERT INTO funcionalidades (segmento_id, nome, chave)
SELECT id, f.nome, f.chave FROM segmentos, (VALUES
    ('Cardápio','cardapio'),('Categorias','categorias'),('Pedidos','pedidos'),
    ('Entrega/Retirada','entrega'),('Cupons','cupons'),('Formas de Pagamento','pagamento'),
    ('WhatsApp','whatsapp'),('Avaliações','avaliacoes')
) AS f(nome, chave)
WHERE segmentos.slug = 'restaurante';

-- Funcionalidades genéricas para os demais segmentos (imobiliária,
-- academia, salão, odontologia, pet shop, hotel, advogado, fotografia)
INSERT INTO funcionalidades (segmento_id, nome, chave)
SELECT id, f.nome, f.chave FROM segmentos, (VALUES
    ('Agendamento','agendamento'),('Serviços','servicos'),('Leads','leads'),
    ('WhatsApp','whatsapp'),('Avaliações','avaliacoes'),('Localização','localizacao'),
    ('Galeria','galeria'),('Cupons','cupons')
) AS f(nome, chave)
WHERE segmentos.slug IN ('imobiliaria','academia','salao','odontologia','petshop','hotel','advogado','fotografia');
