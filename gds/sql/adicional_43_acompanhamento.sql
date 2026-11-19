-- ============================================================
-- ACOMPANHE O ATENDIMENTO (migração 43) — só acrescenta
--   • em que etapa está o atendimento (chegou → em atendimento → finalizando → pronto),
--     a última foto mandada pela equipe e quando mudou (o cliente vê no link do agendamento)
-- ============================================================
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS etapa SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS etapa_foto VARCHAR(300);
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS etapa_em TIMESTAMP;
