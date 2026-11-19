-- ============================================================
-- TREINO DA SEMANA E VALE-PRESENTE (migração 44) — só acrescenta
--   • treinos / treino_checkins: ficha semanal do aluno (academia, personal) e os check-ins
--   • vales_presente: vale-presente comprado pelo site (Pix) e mandado no WhatsApp de quem ganha
-- ============================================================
CREATE TABLE IF NOT EXISTS treinos (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
    aluno_nome VARCHAR(120) NOT NULL,
    aluno_tel VARCHAR(20),
    titulo VARCHAR(80),
    plano TEXT NOT NULL,
    token VARCHAR(40) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_treinos_token ON treinos (token);
CREATE TABLE IF NOT EXISTS treino_checkins (
    id SERIAL PRIMARY KEY,
    treino_id INT NOT NULL REFERENCES treinos(id) ON DELETE CASCADE,
    data DATE NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_treino_checkin ON treino_checkins (treino_id, data);
CREATE TABLE IF NOT EXISTS vales_presente (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    codigo VARCHAR(20) NOT NULL,
    valor NUMERIC(10,2) NOT NULL,
    saldo NUMERIC(10,2) NOT NULL,
    comprador_nome VARCHAR(120) NOT NULL,
    comprador_tel VARCHAR(20) NOT NULL,
    para_nome VARCHAR(120) NOT NULL,
    para_tel VARCHAR(20),
    mensagem VARCHAR(300),
    status VARCHAR(12) NOT NULL DEFAULT 'pendente',
    pag_token VARCHAR(40),
    token VARCHAR(40) NOT NULL,
    validade DATE,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    ativado_em TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_vales_codigo ON vales_presente (empresa_id, codigo);
CREATE UNIQUE INDEX IF NOT EXISTS idx_vales_token ON vales_presente (token);
