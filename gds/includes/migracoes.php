<?php
// ============================================================
// Atualização automática do banco: aplica os scripts SQL novos que
// ainda não foram rodados (todos usam IF NOT EXISTS, então são seguros
// de repetir). Assim ninguém precisa lembrar de colar SQL no Supabase
// depois de atualizar os arquivos da plataforma.
// ============================================================
const GDS_SCHEMA_VERSAO = 14;

// versão => [arquivo, consulta que diz se ela já está aplicada]
function gds_migracoes() {
    $coluna = fn($t, $c) => "EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='$t' AND column_name='$c')";
    return [
        10 => ['adicional_10_estudio_ia.sql', "to_regclass('public.site_versoes') IS NOT NULL AND to_regclass('public.ia_mensagens') IS NOT NULL AND " . $coluna('empresas', 'antecedencia_max_dias') . " AND " . $coluna('avaliacoes', 'aprovado')],
        12 => ['adicional_12_conteudo_site.sql', "to_regclass('public.agenda_bloqueios') IS NOT NULL AND " . $coluna('sites', 'ia_tema') . " AND " . $coluna('leads', 'assunto') . " AND " . $coluna('empresas', 'instagram') . " AND " . $coluna('servicos', 'preco_a_partir')],
        13 => ['adicional_13_segmentos.sql', $coluna('segmentos', 'modo_atendimento') . " AND " . $coluna('empresas', 'segmento_descricao') . " AND " . $coluna('pedidos', 'local_evento') . " AND EXISTS (SELECT 1 FROM segmentos WHERE slug='dj')"],
        14 => ['adicional_14_mais_segmentos.sql', $coluna('segmentos', 'grupo') . " AND EXISTS (SELECT 1 FROM segmentos WHERE slug='palestrante')"],
    ];
}

function gds_garantir_schema() {
    global $pdo;
    $atual = (int)get_config('schema_versao', '0');
    if ($atual >= GDS_SCHEMA_VERSAO) return true;
    try {
        foreach (gds_migracoes() as $versao => [$arquivo, $checagem]) {
            if ($versao <= $atual) continue;
            if (!$pdo->query("SELECT ($checagem) AS ok")->fetch()['ok']) {
                $caminho = __DIR__ . '/../sql/' . $arquivo;
                if (!is_file($caminho)) { error_log("GDS: falta o arquivo sql/$arquivo para atualizar o banco"); return false; }
                $pdo->exec(file_get_contents($caminho));
            }
        }
        set_config('schema_versao', (string)GDS_SCHEMA_VERSAO);
        return true;
    } catch (Throwable $e) {
        error_log('GDS: falha ao atualizar o banco automaticamente: ' . $e->getMessage());
        return false;
    }
}
