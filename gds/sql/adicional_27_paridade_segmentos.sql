-- ============================================================
-- ADICIONAL 27: o que um segmento tinha e os outros não
-- - Avaliação feita na página do agendamento (liga a avaliação ao atendimento)
-- ============================================================
ALTER TABLE avaliacoes ADD COLUMN IF NOT EXISTS agendamento_id INT;
