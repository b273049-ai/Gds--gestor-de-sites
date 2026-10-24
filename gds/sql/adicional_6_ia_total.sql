-- ============================================================
-- ADICIONAL 6: Site Total IA — a IA desenha o site inteiro
-- (HTML/CSS/JS único, com animações), e o sistema injeta as
-- partes funcionais (agendamento, serviços, avaliações) dentro
-- do design que ela criar.
-- Cole no SQL Editor do Supabase e Run.
-- ============================================================

ALTER TABLE sites ADD COLUMN IF NOT EXISTS html_gerado TEXT;      -- o HTML completo que a IA gerou
ALTER TABLE sites ADD COLUMN IF NOT EXISTS ia_modo_total BOOLEAN DEFAULT FALSE; -- liga/desliga esse modo
ALTER TABLE sites ADD COLUMN IF NOT EXISTS ia_briefing TEXT;      -- o texto que o dono escreveu sobre o negócio
