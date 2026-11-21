-- ============================================================
-- ADICIONAL 30: Instagram e TikTok conectados (postar sozinho),
-- comentário com palavra-chave que vira lead e métricas dos posts.
-- ============================================================
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS meta VARCHAR(12);            -- viralizar · lead
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS midia TEXT;                  -- JSON: imagens/stories/video/capa já no servidor
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS publicar BOOLEAN DEFAULT false; -- postar sozinho na hora do item
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS pub TEXT;                    -- JSON por rede: status, ids, link, erro
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS ig_media_id VARCHAR(40);
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS metricas TEXT;               -- JSON do Insights (alcance, envios, salvos…)
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS nota_real NUMERIC(8,2);      -- quanto o post rendeu (por 100 contas alcançadas)
ALTER TABLE marketing_itens ADD COLUMN IF NOT EXISTS metricas_em TIMESTAMP;
CREATE INDEX IF NOT EXISTS idx_mkt_itens_ig ON marketing_itens (ig_media_id);

CREATE TABLE IF NOT EXISTS marketing_leads (
    id SERIAL PRIMARY KEY,
    rede VARCHAR(12) NOT NULL DEFAULT 'instagram',
    origem VARCHAR(12) NOT NULL DEFAULT 'comentario',  -- comentario · direct
    externo_id VARCHAR(80) UNIQUE,                     -- id do comentário ou da mensagem (não responde 2 vezes)
    usuario VARCHAR(80),
    usuario_id VARCHAR(40),
    texto TEXT,
    item_id INT REFERENCES marketing_itens(id) ON DELETE SET NULL,
    media_id VARCHAR(40),
    respondido BOOLEAN DEFAULT false,
    erro TEXT,
    etapa VARCHAR(15) DEFAULT 'novo',                  -- novo · conversando · cliente · perdido
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_mkt_leads_data ON marketing_leads (criado_em);
