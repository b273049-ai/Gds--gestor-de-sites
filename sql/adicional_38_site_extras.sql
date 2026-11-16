-- ============================================================
-- TEMA DE ÉPOCA E CÓDIGO PERSONALIZADO DO SITE (migração 38) — só acrescenta
--   • tema de época (Natal, Ano Novo, Black Friday, Dia das Mães, Dia dos Namorados) com data para sair sozinho
--   • CSS e HTML próprios do site, aplicados por cima do design (o agendamento e os pedidos continuam funcionando)
-- ============================================================
ALTER TABLE sites ADD COLUMN IF NOT EXISTS tema_epoca VARCHAR(20);
ALTER TABLE sites ADD COLUMN IF NOT EXISTS tema_epoca_ate DATE;
ALTER TABLE sites ADD COLUMN IF NOT EXISTS tema_epoca_msg VARCHAR(160);
ALTER TABLE sites ADD COLUMN IF NOT EXISTS codigo_css TEXT;
ALTER TABLE sites ADD COLUMN IF NOT EXISTS codigo_html TEXT;
