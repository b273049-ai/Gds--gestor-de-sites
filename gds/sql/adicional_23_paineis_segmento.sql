-- ============================================================
-- ADICIONAL 23: painel da empresa com cara de plataforma do ramo
-- - Delivery: horário de cada etapa do pedido (tempo médio de preparo/entrega)
-- - Agenda: comandas (atendimento + produtos/serviços extras, desconto, pagamento) e caixa do dia
-- - Clientes: ficha (aniversário, alergias/observações de atendimento, etiquetas)
-- ============================================================
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS aceito_em TIMESTAMP;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS pronto_em TIMESTAMP;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS saiu_em TIMESTAMP;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS concluido_em TIMESTAMP;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS gestor_imprimir BOOLEAN DEFAULT FALSE;   -- imprimir a comanda ao aceitar o pedido

CREATE TABLE IF NOT EXISTS comandas (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    agendamento_id INT REFERENCES agendamentos(id) ON DELETE SET NULL,
    cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
    profissional_id INT REFERENCES profissionais(id) ON DELETE SET NULL,
    itens TEXT NOT NULL,                       -- JSON [{nome, qtd, preco, tipo: servico|produto|avulso}]
    subtotal NUMERIC(10,2) NOT NULL DEFAULT 0,
    desconto NUMERIC(10,2) NOT NULL DEFAULT 0,
    total NUMERIC(10,2) NOT NULL DEFAULT 0,
    forma_pagamento VARCHAR(30),               -- Pix | Dinheiro | Cartão de crédito | Cartão de débito | Clube/pacote
    observacao VARCHAR(300),
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_comandas_dia ON comandas (empresa_id, criado_em);
CREATE UNIQUE INDEX IF NOT EXISTS idx_comandas_ag ON comandas (agendamento_id) WHERE agendamento_id IS NOT NULL;

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS nascimento DATE;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS ficha TEXT;              -- alergias, preferências, observações de atendimento
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS etiquetas VARCHAR(200);  -- "VIP, prefere manhã"
