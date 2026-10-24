-- ============================================================
-- ADICIONAL 8: Segurança — bloqueio por tentativas de login
-- Cole no SQL Editor do Supabase e Run.
-- ============================================================

CREATE TABLE IF NOT EXISTS login_tentativas (
    id SERIAL PRIMARY KEY,
    identificador VARCHAR(255) NOT NULL UNIQUE, -- e-mail em minúsculo
    tentativas INT DEFAULT 1,
    bloqueado_ate TIMESTAMP NULL,
    atualizado_em TIMESTAMP DEFAULT NOW()
);
