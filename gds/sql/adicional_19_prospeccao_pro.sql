-- ============================================================
-- ADICIONAL 19: central de prospecção — caçadas em massa (vários segmentos
-- × várias regiões, em grade), prévia de site por lead com rastreamento,
-- concorrência, cadência de contato e mapa. Também é aplicado sozinho.
-- ============================================================
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS segmento_slug VARCHAR(60);
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS bairro VARCHAR(120);
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS lat DOUBLE PRECISION;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS lng DOUBLE PRECISION;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS tem_site BOOLEAN DEFAULT FALSE;   -- guardado para comparar concorrentes
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS fotos TEXT;                       -- JSON: referências de fotos do Google
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS avaliacoes_texto TEXT;            -- JSON: até 3 avaliações reais
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS horario TEXT;                     -- JSON: horário de funcionamento
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS token VARCHAR(40);                -- link da prévia
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS previa_visitas INT DEFAULT 0;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS previa_aberta_em TIMESTAMP;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS quer_site_em TIMESTAMP;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS passo INT DEFAULT 0;              -- quantas mensagens da cadência já foram
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS proximo_contato DATE;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS cacada_id INT;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ultimo_envio_em TIMESTAMP;
CREATE UNIQUE INDEX IF NOT EXISTS idx_prospects_token ON prospects (token);
CREATE INDEX IF NOT EXISTS idx_prospects_fila ON prospects (proximo_contato, pontuacao DESC);

CREATE TABLE IF NOT EXISTS prospect_cacadas (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(150),
    segmentos TEXT,                 -- JSON [{slug, nome}]
    areas TEXT,                     -- JSON ["Moema, São Paulo", ...]
    densidade INT DEFAULT 1,        -- 1 = cidade inteira, 4/9/16 = quadrantes
    fonte VARCHAR(20),
    status VARCHAR(20) DEFAULT 'rodando',   -- rodando | pausada | concluida
    total INT DEFAULT 0, feitas INT DEFAULT 0, encontrados INT DEFAULT 0, novos INT DEFAULT 0, sem_site INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT NOW(), concluida_em TIMESTAMP
);
CREATE TABLE IF NOT EXISTS prospect_tarefas (
    id SERIAL PRIMARY KEY,
    cacada_id INT REFERENCES prospect_cacadas(id) ON DELETE CASCADE,
    segmento_slug VARCHAR(60), termo VARCHAR(120), area VARCHAR(150),
    caixa TEXT,                     -- JSON {s,w,n,e} do quadrante
    status VARCHAR(20) DEFAULT 'pendente',  -- pendente | feita | erro
    resultado VARCHAR(255), feita_em TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_prospect_tarefas ON prospect_tarefas (cacada_id, status, id);
