-- ============================================================
-- LIGAÇÕES DE PROSPECÇÃO (migração 36) — só acrescenta, nada é apagado
--   • histórico de cada ligação (resultado, duração, anotação)
--   • no lead: retorno agendado com hora, contagem de ligações e o roteiro feito pela IA
-- ============================================================
CREATE TABLE IF NOT EXISTS prosp_ligacoes (
    id SERIAL PRIMARY KEY,
    prospect_id INT NOT NULL REFERENCES prospects(id) ON DELETE CASCADE,
    resultado VARCHAR(20) NOT NULL,          -- nao_atendeu | ocupado | numero_errado | sem_interesse | retornar | interessado | negociando | fechou
    duracao_seg INT,
    canal VARCHAR(12),                       -- whatsapp | telefone
    anotacao TEXT,
    retorno_em TIMESTAMP,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_prosp_ligacoes_lead ON prosp_ligacoes(prospect_id, criado_em);
CREATE INDEX IF NOT EXISTS idx_prosp_ligacoes_dia ON prosp_ligacoes(criado_em);

ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ligar_em TIMESTAMP;              -- retorno combinado (dia e hora)
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ligar_avisado BOOLEAN DEFAULT FALSE;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ligacoes INT DEFAULT 0;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ultima_ligacao_em TIMESTAMP;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ultimo_resultado VARCHAR(20);
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ligacao_roteiro TEXT;           -- roteiro personalizado (JSON) feito pela IA
CREATE INDEX IF NOT EXISTS idx_prospects_ligar_em ON prospects(ligar_em) WHERE ligar_em IS NOT NULL;
