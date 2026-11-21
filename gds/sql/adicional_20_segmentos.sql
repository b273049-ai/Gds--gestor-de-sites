-- ============================================================
-- ADICIONAL 20: melhorias por segmento (comparação com AppBarber, Trinks,
-- Doctoralia, Tecnofit, Anota AI, Goomer, Casamentos.com.br, GetNinjas...)
-- Pix com confirmação, sinal, adicionais no cardápio, lista de espera,
-- lembrete de retorno, clube de assinatura e pacotes, turmas, orçamento
-- com perguntas/fotos/aceite, etapas com foto e pedido na mesa.
-- Também é aplicado sozinho pelo sistema.
-- ============================================================

-- Pagamentos (Pix) de agendamento, pedido, orçamento, assinatura e pacote
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS pedido_id INT;
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS assinatura_id INT;
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) DEFAULT 'total';   -- sinal | total | mensalidade | pacote
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS descricao VARCHAR(200);
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS qr_code TEXT;
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS qr_base64 TEXT;
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS token VARCHAR(40);
ALTER TABLE pagamentos_clientes ADD COLUMN IF NOT EXISTS pago_em TIMESTAMP;
CREATE INDEX IF NOT EXISTS idx_pag_cli_token ON pagamentos_clientes (token);
CREATE INDEX IF NOT EXISTS idx_pag_cli_status ON pagamentos_clientes (status, criado_em);

-- Regras de cobrança da empresa
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS sinal_modo VARCHAR(15) DEFAULT 'nenhum';   -- nenhum | fixo | percentual | total
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS sinal_valor NUMERIC(10,2) DEFAULT 0;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS sinal_quem VARCHAR(15) DEFAULT 'todos';    -- todos | novos | faltosos
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS pix_pedido BOOLEAN DEFAULT FALSE;           -- delivery: oferecer Pix online no pedido
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS orc_sinal_percentual INT DEFAULT 0;         -- orçamento aprovado: % de sinal
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS lista_espera BOOLEAN DEFAULT TRUE;
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS aceita_agendado BOOLEAN DEFAULT TRUE;       -- loja fechada: aceitar pedido para quando abrir
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS eventos_por_dia INT DEFAULT 0;           -- orçamento de evento: quantos eventos por data (0 = não controla)

ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS pago BOOLEAN DEFAULT FALSE;
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS sinal_valor NUMERIC(10,2);
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS retorno_enviado_em TIMESTAMP;
ALTER TABLE agendamentos ADD COLUMN IF NOT EXISTS assinatura_id INT;

ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS pago BOOLEAN DEFAULT FALSE;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS aceite_em TIMESTAMP;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS aceite_ip VARCHAR(60);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS aceite_nome VARCHAR(150);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS anexos TEXT;          -- JSON com fotos enviadas pelo cliente
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS respostas TEXT;       -- JSON com as respostas das perguntas do serviço
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS mesa VARCHAR(10);
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS agendado_para VARCHAR(40);

-- Itens/serviços: adicionais, sugestão no carrinho, retorno, turmas, aula experimental, perguntas do orçamento
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS opcoes TEXT;          -- JSON [{nome, min, max, regra, itens:[{nome, preco}]}]
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS sugerir BOOLEAN DEFAULT FALSE;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS retorno_dias INT DEFAULT 0;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS vagas INT DEFAULT 1;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS experimental BOOLEAN DEFAULT FALSE;
ALTER TABLE servicos ADD COLUMN IF NOT EXISTS perguntas TEXT;       -- JSON ["pergunta 1", "pergunta 2"]

-- Lista de espera
CREATE TABLE IF NOT EXISTS lista_espera (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    servico_id INT, profissional_id INT,
    data DATE NOT NULL,
    nome VARCHAR(150) NOT NULL, telefone VARCHAR(30) NOT NULL,
    status VARCHAR(15) DEFAULT 'aguardando',    -- aguardando | avisado | agendou | expirou
    avisado_em TIMESTAMP,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_lista_espera ON lista_espera (empresa_id, data, status);

-- Clube de assinatura e pacotes de sessões
CREATE TABLE IF NOT EXISTS planos_cliente (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    tipo VARCHAR(15) DEFAULT 'assinatura',      -- assinatura (mensal) | pacote (N sessões)
    nome VARCHAR(120) NOT NULL,
    descricao VARCHAR(400),
    preco NUMERIC(10,2) NOT NULL DEFAULT 0,
    quantidade INT DEFAULT 0,                   -- usos por mês (assinatura; 0 = ilimitado) ou sessões do pacote
    validade_dias INT DEFAULT 30,
    servicos TEXT,                              -- JSON com ids dos serviços incluídos (vazio = todos)
    destaque BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS assinaturas (
    id SERIAL PRIMARY KEY,
    empresa_id INT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    plano_id INT REFERENCES planos_cliente(id) ON DELETE SET NULL,
    cliente_id INT,
    status VARCHAR(15) DEFAULT 'pendente',      -- pendente | ativa | vencida | cancelada | usada
    inicio DATE, vence_em DATE,
    usos INT DEFAULT 0,                         -- usados no período (assinatura) ou no total (pacote)
    token VARCHAR(40),
    aviso_vencimento_em TIMESTAMP,
    criado_em TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_assinaturas ON assinaturas (empresa_id, status, vence_em);

-- Etapas do serviço com foto (oficina, obra, estética automotiva...)
CREATE TABLE IF NOT EXISTS pedido_etapas (
    id SERIAL PRIMARY KEY,
    pedido_id INT NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    texto VARCHAR(500) NOT NULL,
    foto VARCHAR(300),
    criado_em TIMESTAMP DEFAULT NOW()
);

-- Novo status "faltou" na agenda (para cobrar sinal de quem falta)
ALTER TABLE agendamentos DROP CONSTRAINT IF EXISTS agendamentos_status_check;
ALTER TABLE agendamentos ADD CONSTRAINT agendamentos_status_check CHECK (status IN ('agendado','confirmado','concluido','cancelado','faltou'));
