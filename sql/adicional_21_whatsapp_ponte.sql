-- ============================================================
-- WhatsApp "ponte": o Conector GDS roda num celular Android ou num computador
-- e BUSCA as mensagens no site (não precisa de servidor, domínio nem túnel).
-- ============================================================
CREATE TABLE IF NOT EXISTS wa_ponte (
    instancia VARCHAR(80) PRIMARY KEY,
    estado VARCHAR(15) DEFAULT 'close',          -- close | connecting | open
    qr TEXT,                                     -- QR code (data:image/png;base64,...)
    pareamento VARCHAR(20),                      -- código para "Conectar com número de telefone"
    numero VARCHAR(30),                          -- número conectado
    pedido VARCHAR(15),                          -- conectar | desconectar | NULL
    numero_pareamento VARCHAR(20),
    pedido_em TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT NOW()
);
ALTER TABLE whatsapp_mensagens ADD COLUMN IF NOT EXISTS ponte_em TIMESTAMP;   -- quando o conector pegou para enviar
