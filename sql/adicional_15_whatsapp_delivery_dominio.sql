-- ============================================================
-- ADICIONAL 15: WhatsApp automático, pedidos de delivery pelo site
-- e verificação de domínio próprio. Também é aplicado sozinho pelo sistema.
-- ============================================================

-- WhatsApp da empresa
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_modo VARCHAR(20) DEFAULT 'plataforma'; -- desligado | plataforma | proprio
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_instancia VARCHAR(100);      -- nome/ID da instância (Evolution ou Z-API)
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_instancia_token VARCHAR(255);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_status VARCHAR(20);          -- conectado | desconectado | aguardando
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_numero_conectado VARCHAR(30);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_mensagens TEXT;              -- JSON: {evento: {ativo, texto}}
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_avisar_empresa BOOLEAN DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS wa_lembrete_horas INT DEFAULT 24;

-- Delivery / pedidos pelo site
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pedido_online BOOLEAN DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS aceita_entrega BOOLEAN DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS aceita_retirada BOOLEAN DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS taxa_entrega NUMERIC(10,2) DEFAULT 0;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pedido_minimo NUMERIC(10,2) DEFAULT 0;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS formas_pagamento VARCHAR(255) DEFAULT 'Pix, Cartão na entrega, Dinheiro';
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS tempo_entrega VARCHAR(60);

-- Domínio próprio
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS dominio_status VARCHAR(20);     -- pendente | dns_ok | ativo | erro
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS dominio_detalhe VARCHAR(255);
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS dominio_verificado_em TIMESTAMP;

-- Pedidos: tipo, itens do delivery, resposta de orçamento, acompanhamento
ALTER TABLE pedidos DROP CONSTRAINT IF EXISTS pedidos_status_check;
ALTER TABLE pedidos ALTER COLUMN status TYPE VARCHAR(20);
ALTER TABLE pedidos ADD CONSTRAINT pedidos_status_check
    CHECK (status IN ('novo','orcado','aprovado','em_andamento','pronto','saiu_entrega','concluido','cancelado'));
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) DEFAULT 'manual';  -- manual | orcamento | delivery
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS itens TEXT;                        -- JSON [{id,nome,qtd,preco}]
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS tipo_entrega VARCHAR(20);          -- entrega | retirada
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS endereco_entrega VARCHAR(255);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(60);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS troco_para NUMERIC(10,2);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS taxa_entrega NUMERIC(10,2) DEFAULT 0;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS observacoes TEXT;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS resposta TEXT;                     -- mensagem enviada com o orçamento
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS token VARCHAR(40);                 -- link de acompanhamento do cliente
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS atualizado_em TIMESTAMP DEFAULT NOW();

ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS lembrete_enviado_em TIMESTAMP;

-- Fila e histórico das mensagens de WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_mensagens (
    id SERIAL PRIMARY KEY,
    empresa_id INT REFERENCES empresas(id) ON DELETE CASCADE,
    telefone VARCHAR(30) NOT NULL,
    texto TEXT NOT NULL,
    evento VARCHAR(40),
    para VARCHAR(10) DEFAULT 'cliente',          -- cliente | empresa
    ref_tipo VARCHAR(20),                        -- agendamento | pedido | lead
    ref_id INT,
    status VARCHAR(15) DEFAULT 'pendente',        -- pendente | enviado | erro | manual
    canal VARCHAR(20),                           -- proprio | plataforma
    tentativas INT DEFAULT 0,
    erro VARCHAR(255),
    criado_em TIMESTAMP DEFAULT NOW(),
    enviado_em TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_wa_msg_empresa ON whatsapp_mensagens (empresa_id, criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_wa_msg_pendente ON whatsapp_mensagens (status) WHERE status = 'pendente';
CREATE INDEX IF NOT EXISTS idx_pedidos_token ON pedidos (token);
