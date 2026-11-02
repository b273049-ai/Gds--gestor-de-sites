-- ============================================================
-- ADICIONAL 24: delivery completo
-- - Entregadores (motoboys): quem levou cada pedido e o acerto do dia
-- - Cupom de desconto no carrinho do delivery (código e valor descontado no pedido)
-- - Pedido de avaliação depois da entrega (marca para não mandar duas vezes)
-- ============================================================
CREATE TABLE IF NOT EXISTS entregadores (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    valor_entrega NUMERIC(10,2) DEFAULT 0,   -- quanto a loja paga por entrega (0 = repassa a taxa cobrada do cliente)
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_entregadores_empresa ON entregadores(empresa_id);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS entregador_id INT REFERENCES entregadores(id) ON DELETE SET NULL;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS cupom_codigo VARCHAR(30);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS desconto NUMERIC(10,2) DEFAULT 0;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS avaliacao_pedida BOOLEAN DEFAULT FALSE;
