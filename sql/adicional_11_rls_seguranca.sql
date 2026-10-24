-- ============================================================
-- ADICIONAL 11 (MUITO RECOMENDADO): fecha as tabelas para a API pública do Supabase.
--
-- Por quê: todo projeto Supabase expõe as tabelas do schema "public" numa API REST
-- que aceita a "anon key" — e essa chave é pública (vai no código de qualquer front-end).
-- Sem RLS ligado, qualquer pessoa com a anon key consegue LER e APAGAR dados, incluindo
-- hashes de senha (usuarios), chaves de IA/Mercado Pago (configuracoes, empresas) e
-- dados dos clientes finais.
--
-- O GDS em PHP conecta direto no Postgres com o usuário dono das tabelas ("postgres"),
-- que ignora o RLS — então o sistema continua funcionando normalmente depois disto.
--
-- ATENÇÃO: se você usa o protótipo React (pasta src/ da raiz do repositório) apontando
-- para o MESMO projeto Supabase, ele deixa de conseguir ler/gravar essas tabelas.
-- ============================================================
DO $$
DECLARE t record;
BEGIN
    FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename IN (
        'planos','segmentos','templates','empresas','usuarios','sites','profissionais','servicos','clientes',
        'agendamentos','pedidos','leads','avaliacoes','pagamentos_assinaturas','ia_logs','cupons','configuracoes',
        'site_blocos','password_reset_tokens','pagamentos_clientes','login_tentativas','funcionalidades',
        'site_funcionalidades','site_versoes','ia_mensagens')
    LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t.tablename);
    END LOOP;
END $$;
