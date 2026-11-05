-- ============================================================
-- ADICIONAL 31: lead que fechou vira empresa (liga o prospect à empresa criada)
-- ============================================================
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS empresa_id INT REFERENCES empresas(id) ON DELETE SET NULL;
-- Nota e total de avaliações do Google (o site mostra até ter mais avaliações próprias)
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS google_nota NUMERIC(2,1);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS google_avaliacoes INT;
