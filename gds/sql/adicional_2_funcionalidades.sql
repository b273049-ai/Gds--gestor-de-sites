-- ============================================================
-- ADICIONAL 2: Galeria, Cupons, Veículo (mecânica)
-- Cole no SQL Editor do Supabase e Run. Não apaga nada existente.
-- ============================================================

ALTER TABLE sites ADD COLUMN IF NOT EXISTS galeria_imagens TEXT; -- URLs separadas por vírgula
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS veiculo_info VARCHAR(150); -- marca/modelo/ano (mecânica)

CREATE TABLE IF NOT EXISTS cupons (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    codigo VARCHAR(30) NOT NULL,
    descricao VARCHAR(150),
    desconto_percentual NUMERIC(5,2) DEFAULT 0,
    validade DATE,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT NOW()
);
