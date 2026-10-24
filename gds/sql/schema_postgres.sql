-- ============================================================
-- GDS - GESTOR DE SITES - BANCO DE DADOS COMPLETO
-- Versão PostgreSQL / SUPABASE
-- Cole este script inteiro no Supabase: SQL Editor > New Query > Run
-- ============================================================

-- ============================================================
-- 1. PLANOS
-- ============================================================
CREATE TABLE planos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    valor NUMERIC(10,2) NOT NULL,
    ciclo VARCHAR(10) DEFAULT 'mensal' CHECK (ciclo IN ('mensal','anual')),
    max_profissionais INT DEFAULT 1,
    max_agendamentos_mes INT DEFAULT 0,
    permite_pix BOOLEAN DEFAULT TRUE,
    permite_ia BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 2. SEGMENTOS
-- ============================================================
CREATE TABLE segmentos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icone VARCHAR(50),
    ativo BOOLEAN DEFAULT TRUE
);

INSERT INTO segmentos (nome, slug, icone) VALUES
('Barbearia', 'barbearia', 'scissors'),
('Clínica', 'clinica', 'stethoscope'),
('Mecânica', 'mecanica', 'wrench'),
('Restaurante', 'restaurante', 'utensils'),
('Imobiliária', 'imobiliaria', 'home'),
('Academia', 'academia', 'dumbbell'),
('Salão', 'salao', 'sparkles'),
('Odontologia', 'odontologia', 'tooth'),
('Pet Shop', 'petshop', 'paw'),
('Hotel/Pousada', 'hotel', 'bed'),
('Advogado', 'advogado', 'gavel'),
('Fotografia', 'fotografia', 'camera');

-- ============================================================
-- 3. TEMPLATES
-- ============================================================
CREATE TABLE templates (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    segmento_id INT REFERENCES segmentos(id) ON DELETE SET NULL,
    preview_imagem VARCHAR(255),
    cor_primaria VARCHAR(7) DEFAULT '#1a1a2e',
    cor_secundaria VARCHAR(7) DEFAULT '#16213e',
    ativo BOOLEAN DEFAULT TRUE
);

-- ============================================================
-- 4. EMPRESAS
-- ============================================================
CREATE TABLE empresas (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    segmento_id INT REFERENCES segmentos(id) ON DELETE SET NULL,
    plano_id INT REFERENCES planos(id) ON DELETE SET NULL,
    email VARCHAR(150) NOT NULL,
    telefone VARCHAR(20),
    whatsapp VARCHAR(20),
    endereco VARCHAR(255),
    logo VARCHAR(255),
    status VARCHAR(10) DEFAULT 'pendente' CHECK (status IN ('ativo','pendente','suspenso','cancelado')),
    vencimento DATE,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 5. USUARIOS
-- ============================================================
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    empresa_id INT REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'empresa' CHECK (role IN ('admin','empresa')),
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_login TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 6. SITES
-- ============================================================
CREATE TABLE sites (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    template_id INT REFERENCES templates(id) ON DELETE SET NULL,
    titulo VARCHAR(150),
    subtitulo VARCHAR(255),
    sobre TEXT,
    banner_imagem VARCHAR(255),
    publicado BOOLEAN DEFAULT FALSE,
    seo_titulo VARCHAR(150),
    seo_descricao VARCHAR(255),
    atualizado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 7. PROFISSIONAIS
-- ============================================================
CREATE TABLE profissionais (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(150) NOT NULL,
    especialidade VARCHAR(150),
    foto VARCHAR(255),
    ativo BOOLEAN DEFAULT TRUE
);

-- ============================================================
-- 8. SERVICOS
-- ============================================================
CREATE TABLE servicos (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco NUMERIC(10,2) DEFAULT 0,
    duracao_minutos INT DEFAULT 30,
    ativo BOOLEAN DEFAULT TRUE
);

-- ============================================================
-- 9. CLIENTES
-- ============================================================
CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    telefone VARCHAR(20),
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 10. AGENDAMENTOS
-- ============================================================
CREATE TABLE agendamentos (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
    profissional_id INT REFERENCES profissionais(id) ON DELETE SET NULL,
    servico_id INT REFERENCES servicos(id) ON DELETE SET NULL,
    data_hora TIMESTAMP NOT NULL,
    status VARCHAR(15) DEFAULT 'agendado' CHECK (status IN ('agendado','confirmado','concluido','cancelado')),
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 11. PEDIDOS / ORCAMENTOS
-- ============================================================
CREATE TABLE pedidos (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
    descricao TEXT,
    valor NUMERIC(10,2) DEFAULT 0,
    status VARCHAR(15) DEFAULT 'novo' CHECK (status IN ('novo','em_andamento','concluido','cancelado')),
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 12. LEADS
-- ============================================================
CREATE TABLE leads (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(150),
    email VARCHAR(150),
    telefone VARCHAR(20),
    mensagem TEXT,
    origem VARCHAR(50) DEFAULT 'site',
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 13. AVALIACOES
-- ============================================================
CREATE TABLE avaliacoes (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
    nota SMALLINT NOT NULL CHECK (nota BETWEEN 1 AND 5),
    comentario TEXT,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 14. PAGAMENTOS
-- ============================================================
CREATE TABLE pagamentos_assinaturas (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    plano_id INT REFERENCES planos(id) ON DELETE SET NULL,
    valor NUMERIC(10,2) NOT NULL,
    vencimento DATE NOT NULL,
    pago_em TIMESTAMP NULL,
    status VARCHAR(10) DEFAULT 'pendente' CHECK (status IN ('pendente','pago','atrasado','cancelado')),
    metodo VARCHAR(50),
    referencia_gateway VARCHAR(150),
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 15. LOG DE IA
-- ============================================================
CREATE TABLE ia_logs (
    id SERIAL PRIMARY KEY,
    empresa_id INT REFERENCES empresas(id) ON DELETE CASCADE,
    usuario_id INT REFERENCES usuarios(id) ON DELETE SET NULL,
    prompt TEXT,
    resposta TEXT,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- PLANOS DE EXEMPLO
-- ============================================================
INSERT INTO planos (nome, valor, ciclo, max_profissionais, permite_ia) VALUES
('Básico', 69.90, 'mensal', 2, FALSE),
('Profissional', 99.90, 'mensal', 5, TRUE),
('Premium', 149.90, 'mensal', 15, TRUE);

-- ============================================================
-- USUARIO ADMIN: NAO é criado aqui.
-- Depois de rodar este script, acesse install.php no navegador
-- UMA VEZ para criar o admin com senha real (hash gerado no seu
-- próprio servidor). Depois disso, DELETE o arquivo install.php.
-- ============================================================
