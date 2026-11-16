-- ============================================================
-- FOTOS COM CARA DE PROFISSIONAL (migração 39) — só acrescenta
--   • fotos_fonte: de onde vêm as fotos do topo e do "Sobre" (automático, só fotos reais, sem fotos, ou incluir as antigas de IA)
--   • foto_tratamento: acabamento único em todas as fotos (natural, suave/filme, quente, preto e branco, cor da marca)
-- ============================================================
ALTER TABLE sites ADD COLUMN IF NOT EXISTS fotos_fonte VARCHAR(20);
ALTER TABLE sites ADD COLUMN IF NOT EXISTS foto_tratamento VARCHAR(20);
