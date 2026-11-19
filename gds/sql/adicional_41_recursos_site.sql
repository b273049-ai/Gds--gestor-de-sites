-- ============================================================
-- RECURSOS DO SITE (migração 41) — só acrescenta
--   • sites.recursos: o que cada empresa ligou (cartão fidelidade, app instalável, stories,
--     antes e depois, cupom de aniversário, vídeo no topo) e a configuração de cada um (JSON)
--   • fidelidade_mov: selos dados à mão no painel e prêmios resgatados
--   • aniversario_envios: quem já recebeu o cupom de aniversário no ano (não manda duas vezes)
-- ============================================================
ALTER TABLE sites ADD COLUMN IF NOT EXISTS recursos TEXT;
CREATE TABLE IF NOT EXISTS fidelidade_mov (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    telefone VARCHAR(20) NOT NULL,
    selos INT NOT NULL,
    motivo VARCHAR(30) NOT NULL DEFAULT 'manual',
    criado_em TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_fidelidade_mov ON fidelidade_mov (empresa_id, telefone);
CREATE TABLE IF NOT EXISTS aniversario_envios (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    ano INT NOT NULL,
    cupom VARCHAR(30),
    criado_em TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_aniversario_envios ON aniversario_envios (empresa_id, cliente_id, ano);
