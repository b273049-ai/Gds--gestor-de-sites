-- ============================================================
-- ADICIONAL 25: todos os segmentos no nível das plataformas do ramo
-- Delivery: endereço completo (CEP, número, bairro, complemento, referência), CPF na nota,
--           frete grátis acima de um valor, pedido agendado, cliente bloqueado (trote)
-- Lojas: preço promocional ("de/por") e estoque
-- Agenda: anotações de cada atendimento (evolução/prontuário)
-- Orçamento: validade
-- Contato: funil de contatos (CRM) e imóveis (imobiliária)
-- ============================================================
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS end_cep VARCHAR(9);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS end_rua VARCHAR(150);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS end_numero VARCHAR(15);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS end_bairro VARCHAR(100);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS end_cidade VARCHAR(100);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS end_complemento VARCHAR(100);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS end_referencia VARCHAR(150);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS cpf_nota VARCHAR(14);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS validade DATE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS frete_gratis_acima NUMERIC(10,2) DEFAULT 0;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pedido_agendar BOOLEAN DEFAULT FALSE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS cidade_entrega VARCHAR(100);
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS bloqueado BOOLEAN DEFAULT FALSE;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS preco_de NUMERIC(10,2);
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS estoque INT;

CREATE TABLE IF NOT EXISTS atendimento_notas (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    agendamento_id INT REFERENCES agendamentos(id) ON DELETE SET NULL,
    profissional_id INT REFERENCES profissionais(id) ON DELETE SET NULL,
    texto TEXT NOT NULL,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_atend_notas_cliente ON atendimento_notas(cliente_id);

ALTER TABLE leads ADD COLUMN IF NOT EXISTS etapa VARCHAR(20) DEFAULT 'novo';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS valor NUMERIC(12,2);
ALTER TABLE leads ADD COLUMN IF NOT EXISTS proximo_contato DATE;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS notas TEXT;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS imovel_id INT;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS atualizado_em TIMESTAMP;

CREATE TABLE IF NOT EXISTS imoveis (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    codigo VARCHAR(20),
    titulo VARCHAR(150) NOT NULL,
    finalidade VARCHAR(15) DEFAULT 'venda',      -- venda | aluguel | temporada
    tipo VARCHAR(20) DEFAULT 'casa',             -- casa | apartamento | terreno | comercial | sala | rural | chacara | galpao | kitnet | sobrado | cobertura
    preco NUMERIC(14,2) DEFAULT 0,
    condominio NUMERIC(10,2),
    iptu NUMERIC(10,2),
    quartos SMALLINT DEFAULT 0,
    suites SMALLINT DEFAULT 0,
    banheiros SMALLINT DEFAULT 0,
    vagas SMALLINT DEFAULT 0,
    area NUMERIC(10,2),
    area_terreno NUMERIC(10,2),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    endereco VARCHAR(200),                        -- não aparece no site (só para a equipe)
    descricao TEXT,
    caracteristicas TEXT,                         -- "Piscina, Churrasqueira, Portaria 24h" (vírgulas)
    fotos TEXT,                                   -- JSON ["/uploads/imoveis/..."]
    video VARCHAR(255),
    financiamento BOOLEAN DEFAULT FALSE,
    permuta BOOLEAN DEFAULT FALSE,
    mobiliado BOOLEAN DEFAULT FALSE,
    aceita_pet BOOLEAN DEFAULT FALSE,
    destaque BOOLEAN DEFAULT FALSE,
    situacao VARCHAR(15) DEFAULT 'disponivel',    -- disponivel | reservado | vendido | alugado
    ativo BOOLEAN DEFAULT TRUE,
    visualizacoes INT DEFAULT 0,
    proprietario VARCHAR(150),                    -- só para a equipe
    proprietario_tel VARCHAR(20),
    criado_em TIMESTAMP DEFAULT NOW(),
    atualizado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_imoveis_empresa ON imoveis(empresa_id, ativo);
