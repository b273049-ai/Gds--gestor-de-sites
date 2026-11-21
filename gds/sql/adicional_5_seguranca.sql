-- ============================================================
-- ADICIONAL 5: Segurança (recuperação de senha) e Segmentos
-- editáveis. Cole no SQL Editor do Supabase e Run.
-- ============================================================

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id SERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL UNIQUE,
    expira_em TIMESTAMP NOT NULL,
    usado BOOLEAN DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT NOW()
);
