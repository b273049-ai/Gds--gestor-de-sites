-- ============================================================
-- ADICIONAL 12: componentes do site (serviços, agendamento, contato,
-- WhatsApp) mais completos. Também é aplicado sozinho pelo sistema
-- no primeiro acesso ao painel. Pode rodar de novo sem problema.
-- ============================================================

-- Serviços: categoria (agrupa no site), destaque, foto e ordem de exibição
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS categoria VARCHAR(80);
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS destaque BOOLEAN DEFAULT FALSE;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS imagem VARCHAR(500);
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS ordem INT DEFAULT 0;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS preco_a_partir BOOLEAN DEFAULT FALSE; -- mostra "a partir de R$ X"

-- Agendamentos: link para o cliente ver/cancelar, valor cobrado, cupom e origem
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS token VARCHAR(40);
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS valor NUMERIC(10,2);
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS cupom_codigo VARCHAR(30);
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS origem VARCHAR(20) DEFAULT 'painel';
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS cancelado_motivo VARCHAR(255);
CREATE UNIQUE INDEX IF NOT EXISTS idx_agendamentos_token ON agendamentos (token) WHERE token IS NOT NULL;

-- Leads: assunto escolhido no formulário de contato
ALTER TABLE leads ADD COLUMN IF NOT EXISTS assunto VARCHAR(80);

-- Empresa: pausa de almoço, mensagem/visibilidade do botão do WhatsApp e aviso por e-mail
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pausa_inicio TIME;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pausa_fim TIME;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS whatsapp_mensagem VARCHAR(255);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS whatsapp_flutuante BOOLEAN DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS notificar_email BOOLEAN DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS instagram VARCHAR(255);

-- Folgas, feriados e dias bloqueados (a agenda do site não oferece esses dias)
CREATE TABLE IF NOT EXISTS agenda_bloqueios (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    data DATE NOT NULL,
    profissional_id INT REFERENCES profissionais(id) ON DELETE CASCADE, -- vazio = empresa toda
    motivo VARCHAR(120),
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_agenda_bloqueios ON agenda_bloqueios (empresa_id, data);

-- Cores do design gerado pela IA (os componentes do site se adaptam a elas)
ALTER TABLE sites ADD COLUMN IF NOT EXISTS ia_tema TEXT;
