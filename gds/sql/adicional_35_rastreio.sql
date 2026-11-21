-- ============================================================
-- RASTREAMENTO DE MOTOBOYS (migração 35) — só acrescenta, nada é apagado
-- Segmento "Rastreamento de motoboys" (e módulo "rastreio" para qualquer empresa):
--   • app do motoboy no celular (GPS ao vivo, saí / entreguei / voltei)
--   • painel e mapa ao vivo da empresa + mapa para a TV
--   • link do cliente que expira quando o motoboy volta para a loja
-- Reaproveita o cadastro de entregadores do delivery (tabela entregadores).
-- ============================================================

-- Entregadores: acesso ao app e última posição
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS app_token VARCHAR(40);
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS pin_hash VARCHAR(100);
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS veiculo VARCHAR(60);
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS cor VARCHAR(9);
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS situacao VARCHAR(12) DEFAULT 'loja';   -- loja | rota | voltando
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS saiu_em TIMESTAMP;
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS afastou BOOLEAN DEFAULT FALSE;         -- já se afastou da loja nesta saída
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS lat DOUBLE PRECISION;
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS lng DOUBLE PRECISION;
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS precisao REAL;
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS velocidade REAL;                       -- m/s
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS rumo REAL;                             -- graus
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS bateria SMALLINT;
ALTER TABLE entregadores ADD COLUMN IF NOT EXISTS posicao_em TIMESTAMP;
CREATE UNIQUE INDEX IF NOT EXISTS idx_entregadores_app_token ON entregadores(app_token);

-- Entregas acompanhadas (não depende de pedido do GDS: o cardápio pode ser de outro sistema)
CREATE TABLE IF NOT EXISTS rastreio_entregas (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    entregador_id INT REFERENCES entregadores(id) ON DELETE SET NULL,
    token VARCHAR(40) NOT NULL UNIQUE,                 -- o link do cliente: /r/TOKEN
    codigo VARCHAR(30),                                -- número do pedido no sistema da loja (opcional)
    cliente_nome VARCHAR(100),
    cliente_telefone VARCHAR(20),
    endereco VARCHAR(255),
    destino_lat DOUBLE PRECISION,
    destino_lng DOUBLE PRECISION,
    observacao VARCHAR(255),
    status VARCHAR(12) DEFAULT 'aguardando',           -- aguardando | rota | entregue | encerrada | cancelada
    criado_em TIMESTAMP DEFAULT NOW(),
    saiu_em TIMESTAMP,
    entregue_em TIMESTAMP,
    encerrada_em TIMESTAMP,
    expira_em TIMESTAMP                                -- segurança: o link morre mesmo se ninguém tocar em "Voltei"
);
CREATE INDEX IF NOT EXISTS idx_rastreio_entregas_empresa ON rastreio_entregas(empresa_id, status);
CREATE INDEX IF NOT EXISTS idx_rastreio_entregas_entregador ON rastreio_entregas(entregador_id, status);

-- Trajeto recente (rastro no mapa da empresa). Apagado sozinho depois de 3 dias.
CREATE TABLE IF NOT EXISTS rastreio_pontos (
    id BIGSERIAL PRIMARY KEY,
    entregador_id INT NOT NULL REFERENCES entregadores(id) ON DELETE CASCADE,
    lat DOUBLE PRECISION NOT NULL,
    lng DOUBLE PRECISION NOT NULL,
    em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_rastreio_pontos ON rastreio_pontos(entregador_id, em);

-- Empresa: onde fica a loja (para "voltou" automático) e o link do mapa da TV
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS loja_lat DOUBLE PRECISION;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS loja_lng DOUBLE PRECISION;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS rastreio_tv_token VARCHAR(40);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS rastreio_raio SMALLINT DEFAULT 80;          -- metros: chegou na loja
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS rastreio_validade SMALLINT DEFAULT 4;       -- horas: validade máxima do link

-- O segmento
INSERT INTO segmentos (nome, slug, grupo, modo_atendimento, cta, descricao_ia, exemplos_servicos)
SELECT 'Rastreamento de motoboys', 'rastreio-motoboy', 'Entregas e logística', 'contato', 'App do motoboy',
       'Rastreamento de entregas: o site é o app do motoboy (GPS ao vivo); a empresa acompanha todos no mapa e o cliente recebe um link que expira quando o motoboy volta.',
       E'Entrega acompanhada\nMapa ao vivo\nLink para o cliente'
WHERE NOT EXISTS (SELECT 1 FROM segmentos WHERE slug = 'rastreio-motoboy');
