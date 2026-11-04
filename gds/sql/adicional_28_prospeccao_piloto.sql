-- ============================================================
-- ADICIONAL 28: prospecção no piloto automático
-- - Conversa com cada lead (mensagens que saíram e respostas que chegaram)
-- - Leitura da IA (intenção + respostas sugeridas), pedido para não receber mais
-- - Mensagens agendadas (resposta automática sai com um atraso humano)
-- ============================================================
CREATE TABLE IF NOT EXISTS prospect_conversa (
    id SERIAL PRIMARY KEY,
    prospect_id INT NOT NULL REFERENCES prospects(id) ON DELETE CASCADE,
    direcao VARCHAR(3) NOT NULL,              -- in = o lead mandou · out = a plataforma mandou
    autor VARCHAR(10) DEFAULT 'lead',         -- lead · auto (cadência) · ia (resposta da IA) · humano
    texto TEXT NOT NULL,
    wa_id VARCHAR(120),                       -- id da mensagem no WhatsApp (evita duplicar)
    msg_id INT,                               -- whatsapp_mensagens.id (envios)
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_prospect_conversa ON prospect_conversa (prospect_id, id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_prospect_conversa_wa ON prospect_conversa (wa_id) WHERE wa_id IS NOT NULL;

ALTER TABLE prospects ADD COLUMN IF NOT EXISTS optout_em TIMESTAMP;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ia_intencao VARCHAR(20);
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ia_leitura TEXT;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ia_sugestoes TEXT;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS nao_lidas INT DEFAULT 0;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS ultima_resposta_em TIMESTAMP;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS auto_respostas INT DEFAULT 0;
ALTER TABLE prospects ADD COLUMN IF NOT EXISTS envio_msg_id INT;

ALTER TABLE whatsapp_mensagens ADD COLUMN IF NOT EXISTS enviar_apos TIMESTAMP;
CREATE INDEX IF NOT EXISTS idx_wa_msg_ref ON whatsapp_mensagens (ref_tipo, ref_id);
