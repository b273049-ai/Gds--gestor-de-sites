-- ============================================================
-- ADICIONAL 10: Estúdio IA (conversa + versões), avaliações pelo
-- site com moderação, horário de atendimento e índices de desempenho.
-- Cole no SQL Editor do Supabase e Run. Não apaga nada; pode rodar
-- de novo sem problema (IF NOT EXISTS).
-- ============================================================

-- Histórico de versões do site gerado por IA (restaurar = um clique)
CREATE TABLE IF NOT EXISTS site_versoes (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    html TEXT NOT NULL,
    origem VARCHAR(20) NOT NULL DEFAULT 'geracao', -- geracao | ajuste | manual | restauracao
    descricao VARCHAR(500),
    meta JSONB,
    usuario_id INT REFERENCES usuarios(id) ON DELETE SET NULL,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_site_versoes_empresa ON site_versoes (empresa_id, id DESC);

-- Conversa com a IA no Estúdio (o "chat" estilo Lovable)
CREATE TABLE IF NOT EXISTS ia_mensagens (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    papel VARCHAR(10) NOT NULL CHECK (papel IN ('usuario','ia')),
    texto TEXT NOT NULL,
    versao_id INT REFERENCES site_versoes(id) ON DELETE SET NULL,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ia_mensagens_empresa ON ia_mensagens (empresa_id, id);

-- Avaliações deixadas pelo próprio cliente no site (entram pendentes de aprovação)
ALTER TABLE avaliacoes ADD COLUMN IF NOT EXISTS nome_autor VARCHAR(150);
ALTER TABLE avaliacoes ADD COLUMN IF NOT EXISTS aprovado BOOLEAN DEFAULT TRUE;
ALTER TABLE avaliacoes ADD COLUMN IF NOT EXISTS origem VARCHAR(20) DEFAULT 'painel';

-- Horário de atendimento (o calendário do site respeita isso)
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS horario_abertura TIME DEFAULT '08:00';
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS horario_fechamento TIME DEFAULT '19:00';
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS dias_funcionamento VARCHAR(20) DEFAULT '1,2,3,4,5,6'; -- 0=domingo ... 6=sábado
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS intervalo_agenda INT DEFAULT 30;          -- de quantos em quantos minutos
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS antecedencia_max_dias INT DEFAULT 60;     -- até quantos dias à frente aceita

-- Índices (as telas com muitos registros ficavam lentas sem eles)
CREATE INDEX IF NOT EXISTS idx_agendamentos_empresa_data ON agendamentos (empresa_id, data_hora);
CREATE INDEX IF NOT EXISTS idx_clientes_empresa ON clientes (empresa_id);
CREATE INDEX IF NOT EXISTS idx_servicos_empresa ON servicos (empresa_id);
CREATE INDEX IF NOT EXISTS idx_profissionais_empresa ON profissionais (empresa_id);
CREATE INDEX IF NOT EXISTS idx_leads_empresa ON leads (empresa_id, criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_avaliacoes_empresa ON avaliacoes (empresa_id, criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_pedidos_empresa ON pedidos (empresa_id, criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_ia_logs_empresa ON ia_logs (empresa_id, criado_em);
CREATE UNIQUE INDEX IF NOT EXISTS idx_sites_empresa_unico ON sites (empresa_id);
