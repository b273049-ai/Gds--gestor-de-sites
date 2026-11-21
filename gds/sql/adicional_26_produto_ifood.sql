-- ============================================================
-- ADICIONAL 26: tela do produto como no iFood
-- - "Serve X pessoas" no item do cardápio
-- - Avaliação feita na página do pedido (liga a avaliação ao pedido)
-- ============================================================
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS serve SMALLINT;
ALTER TABLE avaliacoes ADD COLUMN IF NOT EXISTS pedido_id INT;
