-- ============================================================
-- ADICIONAL 4: Modularidade real do site (layouts diferentes,
-- banner, blocos de conteúdo próprios, ordem de seções)
-- Cole no SQL Editor do Supabase e Run.
-- ============================================================

ALTER TABLE templates ADD COLUMN IF NOT EXISTS layout VARCHAR(20) DEFAULT 'classico';
UPDATE templates SET layout = 'classico' WHERE nome = 'Escuro Moderno';
UPDATE templates SET layout = 'editorial' WHERE nome = 'Elegante Vinho';
UPDATE templates SET layout = 'grid' WHERE nome = 'Verde Natureza';
UPDATE templates SET layout = 'bold' WHERE nome = 'Azul Corporativo';

ALTER TABLE sites ADD COLUMN IF NOT EXISTS layout VARCHAR(20) DEFAULT 'classico';
ALTER TABLE sites ADD COLUMN IF NOT EXISTS secoes TEXT; -- ordem/visibilidade das seções, separado por vírgula
ALTER TABLE sites ADD COLUMN IF NOT EXISTS diferenciais TEXT; -- o que torna ESSE negócio único (alimenta a IA)

CREATE TABLE IF NOT EXISTS site_blocos (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    titulo VARCHAR(150),
    conteudo TEXT,
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT NOW()
);
