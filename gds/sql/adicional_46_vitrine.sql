-- ============================================================
-- VISUAL VITRINE (migração 46) — só acrescenta
--   • servicos.imagem_recorte: a foto do produto sem fundo (PNG), feita no painel com "Tirar o fundo".
--     É ela que aparece "saindo" dos cartões e no topo do visual Vitrine; a foto original continua igual.
-- ============================================================
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS imagem_recorte VARCHAR(255);
