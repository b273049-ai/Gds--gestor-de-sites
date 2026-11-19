-- ============================================================
-- RESTAURANTE (migração 42) — só acrescenta
--   • reservas: pedido de reserva de mesa pelo site (confirmada pelo painel, aviso no WhatsApp)
--   • fila_espera: fila virtual com posição ao vivo e aviso "sua mesa está pronta" no WhatsApp
--   • mesa_chamados: "chamar garçom" e "pedir a conta" pelo QR Code da mesa
-- ============================================================
CREATE TABLE IF NOT EXISTS reservas (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data DATE NOT NULL,
    hora VARCHAR(5) NOT NULL,
    pessoas INT NOT NULL DEFAULT 2,
    observacoes VARCHAR(300),
    status VARCHAR(15) NOT NULL DEFAULT 'pendente',
    token VARCHAR(40) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_reservas_empresa ON reservas (empresa_id, data);
CREATE TABLE IF NOT EXISTS fila_espera (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(20),
    pessoas INT NOT NULL DEFAULT 2,
    status VARCHAR(15) NOT NULL DEFAULT 'aguardando',
    token VARCHAR(40) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    chamado_em TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fila_espera ON fila_espera (empresa_id, status, criado_em);
CREATE UNIQUE INDEX IF NOT EXISTS idx_fila_token ON fila_espera (token);
CREATE TABLE IF NOT EXISTS mesa_chamados (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    mesa VARCHAR(10) NOT NULL,
    tipo VARCHAR(10) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'aberto',
    criado_em TIMESTAMP NOT NULL DEFAULT NOW(),
    atendido_em TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_mesa_chamados ON mesa_chamados (empresa_id, status);
