-- ============================================================
-- ADICIONAL 18: prospecção de clientes (empresas do Google Maps / OpenStreetMap
-- sem site próprio), com pontuação, funil e mensagem pronta de WhatsApp.
-- Também é aplicado sozinho pelo sistema.
-- ============================================================
CREATE TABLE IF NOT EXISTS prospects (
    id SERIAL PRIMARY KEY,
    origem_id VARCHAR(200) UNIQUE,          -- id do lugar no Google / OSM
    fonte VARCHAR(20),                      -- google | osm
    nome VARCHAR(200) NOT NULL,
    categoria VARCHAR(120),
    endereco VARCHAR(300),
    cidade VARCHAR(120),
    telefone VARCHAR(40),
    whatsapp VARCHAR(20),                   -- celular com DDI (55...), quando dá para chamar no WhatsApp
    site VARCHAR(300),                      -- site atual (vazio = não tem; Instagram/Facebook contam como "sem site próprio")
    nota NUMERIC(2,1),
    avaliacoes INT DEFAULT 0,
    maps_url VARCHAR(400),
    pontuacao INT DEFAULT 0,
    motivos TEXT,                           -- JSON com os motivos da pontuação
    status VARCHAR(20) DEFAULT 'novo',      -- novo | contatado | respondeu | negociando | fechado | descartado
    observacoes TEXT,
    busca VARCHAR(200),
    contatado_em TIMESTAMP,
    criado_em TIMESTAMP DEFAULT NOW(),
    atualizado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_prospects_status ON prospects (status, pontuacao DESC);
