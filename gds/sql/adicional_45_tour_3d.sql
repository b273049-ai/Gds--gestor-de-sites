-- ============================================================
-- TOUR 360° POR IMÓVEL E PRODUTO EM 3D (migração 45) — só acrescenta
--   • imoveis.tour360: JSON [{"img":"/uploads/...","titulo":"Sala"}] com as fotos 360° de cada imóvel
--   • servicos.modelo_3d: arquivo .glb do produto (visto em 3D e "no seu espaço" pelo celular)
-- ============================================================
ALTER TABLE imoveis ADD COLUMN IF NOT EXISTS tour360 TEXT;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS modelo_3d VARCHAR(255);
