-- ============================================================
-- PRÉVIA QUE VENDE (migração 40) — só acrescenta
--   • quando o lead pediu o "exemplo no meu WhatsApp" pela prévia (limite de 1 por dia)
-- ============================================================
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS teste_wa_em TIMESTAMP;
