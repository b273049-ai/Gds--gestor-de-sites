-- ============================================================
-- LIGAÇÕES PARA QUEM JÁ É CLIENTE (migração 37) — só acrescenta, nada é apagado
-- O histórico de ligações (prosp_ligacoes) passa a aceitar uma empresa cliente no lugar do lead.
-- ============================================================
ALTER TABLE prosp_ligacoes ADD COLUMN IF NOT EXISTS empresa_id INT REFERENCES empresas(id) ON DELETE CASCADE;
ALTER TABLE prosp_ligacoes ADD COLUMN IF NOT EXISTS motivo VARCHAR(20);          -- cobranca | renovacao | ativacao | sumido | relacionamento | retorno
ALTER TABLE prosp_ligacoes ALTER COLUMN prospect_id DROP NOT NULL;
CREATE INDEX IF NOT EXISTS idx_prosp_ligacoes_empresa ON prosp_ligacoes(empresa_id, criado_em);

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS ligar_em TIMESTAMP;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS ligar_avisado BOOLEAN DEFAULT FALSE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS ultima_ligacao_em TIMESTAMP;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS ultimo_resultado_lig VARCHAR(20);
