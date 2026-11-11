-- ============================================================
-- ADICIONAL 29: planejamento de marketing com os posts prontos
-- Um plano (semana ou mês) tem itens com data, horário e formato;
-- a IA preenche cada item (arte, legenda, stories, roteiro, narração).
-- ============================================================
CREATE TABLE IF NOT EXISTS marketing_planos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(120),
    publico VARCHAR(120),
    periodo VARCHAR(10) DEFAULT 'semana',
    inicio DATE,
    por_semana INT DEFAULT 5,
    stories_dia INT DEFAULT 2,
    redes VARCHAR(120),
    status VARCHAR(15) DEFAULT 'gerando',        -- gerando · pronto
    criado_em TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS marketing_itens (
    id SERIAL PRIMARY KEY,
    plano_id INT NOT NULL REFERENCES marketing_planos(id) ON DELETE CASCADE,
    data DATE NOT NULL,
    hora VARCHAR(5) NOT NULL,
    tipo VARCHAR(12) NOT NULL,                    -- reels · carrossel · feed · story
    objetivo VARCHAR(30),
    framework VARCHAR(20),
    tema TEXT,
    gancho TEXT,
    status VARCHAR(12) DEFAULT 'pendente',        -- pendente · gerando · pronto · erro · postado
    post TEXT,                                    -- JSON completo (arte, legenda, hashtags, roteiro, stories, narração)
    foto_url VARCHAR(255),
    erro TEXT,
    tentativas INT DEFAULT 0,
    lembrado_em TIMESTAMP,
    postado_em TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_mkt_itens_plano ON marketing_itens (plano_id, data, hora);
CREATE INDEX IF NOT EXISTS idx_mkt_itens_status ON marketing_itens (status, data);
