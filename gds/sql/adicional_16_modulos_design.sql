-- ============================================================
-- ADICIONAL 16: painel modular por empresa e novos estilos de site.
-- Também é aplicado sozinho pelo sistema.
-- ============================================================

-- Módulos ligados/desligados pela empresa (JSON). Vazio = padrão do tipo de negócio.
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS modulos TEXT;

-- Estilo visual do site em modo template (moderno, elegante, vibrante, minimalista, escuro...)
ALTER TABLE sites ADD COLUMN IF NOT EXISTS estilo VARCHAR(30);
ALTER TABLE sites ADD COLUMN IF NOT EXISTS fotos_banco TEXT; -- JSON com fotos profissionais (Pexels) escolhidas para o site

-- Posts de marketing gerados pela IA (admin)
CREATE TABLE IF NOT EXISTS marketing_posts (
    id SERIAL PRIMARY KEY,
    rede VARCHAR(20),                -- instagram | tiktok | ambos
    formato VARCHAR(20),             -- feed | story | reels | carrossel
    tema VARCHAR(150),
    conteudo TEXT,                   -- JSON gerado (título, texto da arte, legenda, hashtags, roteiro)
    criado_em TIMESTAMP DEFAULT NOW()
);
