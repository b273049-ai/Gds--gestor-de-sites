-- ============================================================
-- ADICIONAL 34: visual "Limpo" dos sites (novo padrão) e escolhas do dono
-- visual: limpo (padrão quando vazio) · completo (o visual antigo, cheio de seções)
-- composicao: foto · lado · moldura · tipografia (vazio = automático, combina com o ramo)
-- tom: personalidade de fontes e fundo (vazio = a do ramo)
-- ============================================================
ALTER TABLE sites ADD COLUMN IF NOT EXISTS visual VARCHAR(12);
ALTER TABLE sites ADD COLUMN IF NOT EXISTS composicao VARCHAR(12);
ALTER TABLE sites ADD COLUMN IF NOT EXISTS tom VARCHAR(12);
