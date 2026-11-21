-- ============================================================
-- ADICIONAL 17: animações dos sites, análise de visitas e funções
-- novas do painel (loja aberta/fechada, esgotado, taxa por bairro,
-- fidelidade, comissão). Também é aplicado sozinho pelo sistema.
-- ============================================================

-- Pacote de animações do site: suave (padrão) | cinema | leve
ALTER TABLE sites ADD COLUMN IF NOT EXISTS animacoes VARCHAR(20);

-- Análise de verdade: cada visita e cada clique importante do site
CREATE TABLE IF NOT EXISTS site_eventos (
    id BIGSERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    tipo VARCHAR(30) NOT NULL,          -- visita | whatsapp | ligar | rota | agendar | orcamento | pedido | instagram
    visitante VARCHAR(40),              -- id anônimo (sem dados pessoais)
    origem VARCHAR(60),                 -- instagram, google, whatsapp, direto...
    dispositivo VARCHAR(10),            -- celular | computador
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_site_eventos_empresa ON site_eventos (empresa_id, criado_em);

-- Delivery: pausar a loja, taxa por bairro, item esgotado
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS loja_pausada BOOLEAN DEFAULT FALSE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS bairros_entrega TEXT;      -- JSON [{"bairro":"Centro","taxa":5}]
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS esgotado BOOLEAN DEFAULT FALSE;

-- Fidelidade (a cada N atendimentos/pedidos, o cliente ganha um) e comissão por profissional
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS fidelidade_meta INT DEFAULT 0;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS fidelidade_premio VARCHAR(120);
ALTER TABLE profissionais ADD COLUMN IF NOT EXISTS comissao NUMERIC(5,2) DEFAULT 0;
