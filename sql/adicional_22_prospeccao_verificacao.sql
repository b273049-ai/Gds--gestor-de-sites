-- ============================================================
-- ADICIONAL 22: prospecção mais certeira
-- - teste A/B da 1ª mensagem e registro de quem respondeu
-- - verificação de site (WhatsApp comercial, Instagram, busca, domínio) antes de chamar
-- - cor da marca (logo do WhatsApp/Instagram ou fotos) para a prévia combinar com a empresa
-- ============================================================
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS variante VARCHAR(2);           -- a | b (versão da 1ª mensagem)
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS respondeu_em TIMESTAMP;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS verificado_em TIMESTAMP;       -- quando a verificação terminou
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS verif_pedido_em TIMESTAMP;     -- entregue ao conector para verificar
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS verificacao TEXT;              -- JSON com o que foi achado em cada fonte
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS site_fonte VARCHAR(20);        -- google | whatsapp | instagram | busca | dominio | cliente
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS wa_existe BOOLEAN;             -- o número tem WhatsApp?
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS instagram VARCHAR(80);
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS logo_url VARCHAR(600);         -- foto do perfil (WhatsApp/Instagram)
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS cor VARCHAR(7);                -- cor da marca para a prévia
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS cor_fonte VARCHAR(20);
CREATE INDEX IF NOT EXISTS idx_prospects_verif ON prospects (verificado_em) WHERE NOT tem_site;
