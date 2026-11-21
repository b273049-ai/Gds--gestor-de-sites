-- ============================================================
-- ADICIONAL 33: link de pagamento do Mercado Pago (Checkout Pro) por cobrança
-- ============================================================
ALTER TABLE pagamentos_assinaturas ADD COLUMN IF NOT EXISTS link_pagamento TEXT;
ALTER TABLE pagamentos_assinaturas ADD COLUMN IF NOT EXISTS mp_preferencia VARCHAR(80);
