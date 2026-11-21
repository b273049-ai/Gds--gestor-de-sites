-- ============================================================
-- ADICIONAL 3: Configurações do sistema (API keys guardadas no
-- banco, editáveis por uma tela, sem precisar editar arquivo PHP)
-- Cole no SQL Editor do Supabase e Run.
-- ============================================================

CREATE TABLE IF NOT EXISTS configuracoes (
    chave VARCHAR(50) PRIMARY KEY,
    valor TEXT
);
