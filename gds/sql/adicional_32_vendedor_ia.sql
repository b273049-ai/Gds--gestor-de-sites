-- ============================================================
-- ADICIONAL 32: IA vendedora (tom testado por lead) e "Indique e ganhe"
-- ============================================================
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ia_tom VARCHAR(12);           -- amigavel · consultivo · direto
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS indicado_por INT;             -- empresa (cliente) que indicou
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ia_fechou_em TIMESTAMP;       -- a IA fechou a venda sozinha
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS indica_codigo VARCHAR(16);
CREATE UNIQUE INDEX IF NOT EXISTS idx_empresas_indica ON empresas (indica_codigo) WHERE indica_codigo IS NOT NULL;
