<?php
// ============================================================
// Atualização automática do banco: aplica os scripts SQL novos que
// ainda não foram rodados (todos usam IF NOT EXISTS, então são seguros
// de repetir). Assim ninguém precisa lembrar de colar SQL no Supabase
// depois de atualizar os arquivos da plataforma.
// ============================================================
const GDS_SCHEMA_VERSAO = 10;

function gds_garantir_schema() {
    global $pdo;
    if ((int)get_config('schema_versao', '0') >= GDS_SCHEMA_VERSAO) return true;

    $arquivo = __DIR__ . '/../sql/adicional_10_estudio_ia.sql';
    try {
        $jaTem = $pdo->query("SELECT to_regclass('public.site_versoes') IS NOT NULL AND to_regclass('public.ia_mensagens') IS NOT NULL
            AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='empresas' AND column_name='antecedencia_max_dias')
            AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='avaliacoes' AND column_name='aprovado') AS ok")->fetch();
        if (!$jaTem['ok']) {
            if (!is_file($arquivo)) {
                error_log('GDS: falta o arquivo sql/adicional_10_estudio_ia.sql para atualizar o banco');
                return false;
            }
            $pdo->exec(file_get_contents($arquivo));
        }
        set_config('schema_versao', (string)GDS_SCHEMA_VERSAO);
        return true;
    } catch (Throwable $e) {
        error_log('GDS: falha ao atualizar o banco automaticamente: ' . $e->getMessage());
        return false;
    }
}
