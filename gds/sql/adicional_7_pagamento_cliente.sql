-- ============================================================
-- ADICIONAL 7: Pagamento real do cliente final (PIX)
-- O dinheiro cai na conta Mercado Pago DA PRÓPRIA EMPRESA, não da
-- plataforma — por isso cada empresa cola o próprio Access Token.
-- Cole no SQL Editor do Supabase e Run.
-- ============================================================

ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pix_ativo BOOLEAN DEFAULT FALSE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS mercadopago_access_token VARCHAR(255);

CREATE TABLE IF NOT EXISTS pagamentos_clientes (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    agendamento_id INT REFERENCES agendamentos(id) ON DELETE SET NULL,
    cliente_id INT REFERENCES clientes(id) ON DELETE SET NULL,
    valor NUMERIC(10,2) NOT NULL,
    status VARCHAR(15) DEFAULT 'pendente' CHECK (status IN ('pendente','pago','cancelado')),
    referencia_gateway VARCHAR(150),
    criado_em TIMESTAMP DEFAULT NOW()
);
