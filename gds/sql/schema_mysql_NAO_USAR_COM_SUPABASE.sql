-- ============================================================
-- GDS - GESTOR DE SITES - BANCO DE DADOS COMPLETO
-- Estrutura: ADMIN / EMPRESA / SITE PUBLICO (100% separados)
-- Compatível com MySQL 5.7+ / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS gds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gds;

-- ============================================================
-- 1. PLANOS (planos que o admin vende para as empresas)
-- ============================================================
CREATE TABLE planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    ciclo ENUM('mensal','anual') DEFAULT 'mensal',
    max_profissionais INT DEFAULT 1,
    max_agendamentos_mes INT DEFAULT 0, -- 0 = ilimitado
    permite_pix TINYINT(1) DEFAULT 1,
    permite_ia TINYINT(1) DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. SEGMENTOS (Barbearia, Clínica, Mecânica, Restaurante...)
-- ============================================================
CREATE TABLE segmentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icone VARCHAR(50),
    ativo TINYINT(1) DEFAULT 1
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
-- 3. TEMPLATES (modelos de site por segmento)
-- ============================================================
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    segmento_id INT,
    preview_imagem VARCHAR(255),
    cor_primaria VARCHAR(7) DEFAULT '#1a1a2e',
    cor_secundaria VARCHAR(7) DEFAULT '#16213e',
    ativo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (segmento_id) REFERENCES segmentos(id) ON DELETE SET NULL
);

-- ============================================================
-- 4. EMPRESAS (clientes do ADMIN - cada uma é um tenant isolado)
-- ============================================================
CREATE TABLE empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE, -- usado na URL do site público: /site/slug
    segmento_id INT,
    plano_id INT,
    email VARCHAR(150) NOT NULL,
    telefone VARCHAR(20),
    whatsapp VARCHAR(20),
    endereco VARCHAR(255),
    logo VARCHAR(255),
    status ENUM('ativo','pendente','suspenso','cancelado') DEFAULT 'pendente',
    vencimento DATE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (segmento_id) REFERENCES segmentos(id) ON DELETE SET NULL,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL
);

-- ============================================================
-- 5. USUARIOS (login unificado: admin, empresa, colaborador)
--    role define o painel que a pessoa acessa
-- ============================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NULL, -- NULL quando role = admin
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','empresa') NOT NULL DEFAULT 'empresa',
    ativo TINYINT(1) DEFAULT 1,
    ultimo_login TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

-- ============================================================
-- 6. SITES (configuração do site público de cada empresa)
-- ============================================================
CREATE TABLE sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    template_id INT,
    titulo VARCHAR(150),
    subtitulo VARCHAR(255),
    sobre TEXT,
    banner_imagem VARCHAR(255),
    publicado TINYINT(1) DEFAULT 0,
    seo_titulo VARCHAR(150),
    seo_descricao VARCHAR(255),
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
);

-- ============================================================
-- 7. PROFISSIONAIS (equipe de cada empresa)
-- ============================================================
CREATE TABLE profissionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    especialidade VARCHAR(150),
    foto VARCHAR(255),
    ativo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

-- ============================================================
-- 8. SERVICOS (o que a empresa vende/oferece)
-- ============================================================
CREATE TABLE servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) DEFAULT 0,
    duracao_minutos INT DEFAULT 30,
    ativo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

-- ============================================================
-- 9. CLIENTES (clientes finais de CADA empresa - isolado por empresa_id)
-- ============================================================
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    telefone VARCHAR(20),
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

-- ============================================================
-- 10. AGENDAMENTOS
-- ============================================================
CREATE TABLE agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT,
    profissional_id INT,
    servico_id INT,
    data_hora DATETIME NOT NULL,
    status ENUM('agendado','confirmado','concluido','cancelado') DEFAULT 'agendado',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE SET NULL,
    FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE SET NULL
);

-- ============================================================
-- 11. PEDIDOS / ORCAMENTOS (restaurante, mecânica, etc.)
-- ============================================================
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT,
    descricao TEXT,
    valor DECIMAL(10,2) DEFAULT 0,
    status ENUM('novo','em_andamento','concluido','cancelado') DEFAULT 'novo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- ============================================================
-- 12. LEADS (contatos captados pelo site público)
-- ============================================================
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(150),
    email VARCHAR(150),
    telefone VARCHAR(20),
    mensagem TEXT,
    origem VARCHAR(50) DEFAULT 'site',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

-- ============================================================
-- 13. AVALIACOES
-- ============================================================
CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT,
    nota TINYINT NOT NULL, -- 1 a 5
    comentario TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
);

-- ============================================================
-- 14. PAGAMENTOS (financeiro do ADMIN - assinaturas das empresas)
-- ============================================================
CREATE TABLE pagamentos_assinaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    plano_id INT,
    valor DECIMAL(10,2) NOT NULL,
    vencimento DATE NOT NULL,
    pago_em DATETIME NULL,
    status ENUM('pendente','pago','atrasado','cancelado') DEFAULT 'pendente',
    metodo VARCHAR(50), -- pix, cartao, boleto
    referencia_gateway VARCHAR(150), -- id da cobrança no Mercado Pago
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL
);

-- ============================================================
-- 15. LOG DE IA (histórico de uso da IA por empresa, opcional)
-- ============================================================
CREATE TABLE ia_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT,
    usuario_id INT,
    prompt TEXT,
    resposta TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ============================================================
-- USUARIO ADMIN: NAO é criado aqui.
-- Depois de importar este SQL, acesse install.php no navegador
-- UMA VEZ para criar o admin com senha real (hash gerado no seu
-- próprio servidor). Depois disso, DELETE o arquivo install.php.
-- ============================================================
-- PLANO DE EXEMPLO
-- ============================================================
INSERT INTO planos (nome, valor, ciclo, max_profissionais, permite_ia) VALUES
('Básico', 69.90, 'mensal', 2, 0),
('Profissional', 99.90, 'mensal', 5, 1),
('Premium', 149.90, 'mensal', 15, 1);
