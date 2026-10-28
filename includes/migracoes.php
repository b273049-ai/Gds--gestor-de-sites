<?php
// ============================================================
// Atualização automática do banco: aplica os scripts SQL novos que
// ainda não foram rodados (todos usam IF NOT EXISTS, então são seguros
// de repetir). Assim ninguém precisa lembrar de colar SQL no Supabase
// depois de atualizar os arquivos da plataforma.
// ============================================================
const GDS_SCHEMA_VERSAO = 17;

// versão => [arquivo, consulta que diz se ela já está aplicada]
function gds_migracoes() {
    $coluna = fn($t, $c) => "EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='$t' AND column_name='$c')";
    return [
        10 => ['adicional_10_estudio_ia.sql', "to_regclass('public.site_versoes') IS NOT NULL AND to_regclass('public.ia_mensagens') IS NOT NULL AND " . $coluna('empresas', 'antecedencia_max_dias') . " AND " . $coluna('avaliacoes', 'aprovado')],
        12 => ['adicional_12_conteudo_site.sql', "to_regclass('public.agenda_bloqueios') IS NOT NULL AND " . $coluna('sites', 'ia_tema') . " AND " . $coluna('leads', 'assunto') . " AND " . $coluna('empresas', 'instagram') . " AND " . $coluna('servicos', 'preco_a_partir')],
        13 => ['adicional_13_segmentos.sql', $coluna('segmentos', 'modo_atendimento') . " AND " . $coluna('empresas', 'segmento_descricao') . " AND " . $coluna('pedidos', 'local_evento') . " AND EXISTS (SELECT 1 FROM segmentos WHERE slug='dj')"],
        14 => ['adicional_14_mais_segmentos.sql', $coluna('segmentos', 'grupo') . " AND EXISTS (SELECT 1 FROM segmentos WHERE slug='palestrante')"],
        15 => ['adicional_15_whatsapp_delivery_dominio.sql', "to_regclass('public.whatsapp_mensagens') IS NOT NULL AND " . $coluna('empresas', 'wa_modo') . " AND " . $coluna('pedidos', 'token') . " AND " . $coluna('empresas', 'dominio_status') . " AND " . $coluna('agendamentos', 'lembrete_enviado_em')],
        16 => ['adicional_16_modulos_design.sql', $coluna('empresas', 'modulos') . " AND " . $coluna('sites', 'estilo') . " AND to_regclass('public.marketing_posts') IS NOT NULL"],
        17 => ['adicional_17_sites_painel.sql', $coluna('sites', 'animacoes') . " AND to_regclass('public.site_eventos') IS NOT NULL AND " . $coluna('empresas', 'bairros_entrega') . " AND " . $coluna('servicos', 'esgotado') . " AND " . $coluna('profissionais', 'comissao')],
    ];
}

function gds_garantir_schema($forcar = false) {
    global $pdo;
    $atual = (int)get_config('schema_versao', '0');
    if ($atual >= GDS_SCHEMA_VERSAO) return true;
    // Se a última tentativa falhou, não fica tentando a cada página (no máximo a cada 10 min)
    if (!$forcar && get_config('schema_erro', '') !== '' && time() - (int)get_config('schema_tentativa', '0') < 600) return false;
    $erros = [];
    try {
        foreach (gds_migracoes() as $versao => [$arquivo, $checagem]) {
            if ($versao <= $atual) continue;
            if ($pdo->query("SELECT ($checagem) AS ok")->fetch()['ok']) continue;
            $caminho = __DIR__ . '/../sql/' . $arquivo;
            if (!is_file($caminho)) { $erros[] = "Falta o arquivo sql/$arquivo no servidor."; break; }
            // Um comando por vez: se um falhar, os outros continuam (todos são seguros de repetir)
            foreach (gds_sql_comandos(file_get_contents($caminho)) as $cmd) {
                try { $pdo->exec($cmd); }
                catch (Throwable $e) { $erros[] = "$arquivo: " . mb_substr($e->getMessage(), 0, 300) . ' — em: ' . mb_substr(preg_replace('/\s+/', ' ', $cmd), 0, 120); }
            }
            if (!$pdo->query("SELECT ($checagem) AS ok")->fetch()['ok']) { $erros[] = "$arquivo não terminou de aplicar."; break; }
        }
    } catch (Throwable $e) {
        $erros[] = $e->getMessage();
    }
    set_config('schema_tentativa', (string)time());
    if ($erros && !gds_schema_completo()) {
        set_config('schema_erro', mb_substr(implode("\n", $erros), 0, 3000));
        error_log('GDS: falha ao atualizar o banco: ' . implode(' | ', $erros));
        return false;
    }
    set_config('schema_erro', '');
    set_config('schema_versao', (string)GDS_SCHEMA_VERSAO);
    return true;
}

// Todas as checagens passam?
function gds_schema_completo() {
    global $pdo;
    try {
        foreach (gds_migracoes() as [$arquivo, $checagem]) {
            if (!$pdo->query("SELECT ($checagem) AS ok")->fetch()['ok']) return false;
        }
        return true;
    } catch (Throwable $e) { return false; }
}

// Divide um arquivo .sql em comandos, respeitando textos '...', E'...', $$...$$ e comentários
function gds_sql_comandos($sql) {
    $cmds = []; $atual = ''; $n = strlen($sql); $i = 0;
    while ($i < $n) {
        $c = $sql[$i];
        if ($c === '-' && ($sql[$i + 1] ?? '') === '-') {              // comentário de linha
            $fim = strpos($sql, "\n", $i); $i = $fim === false ? $n : $fim + 1; $atual .= "\n"; continue;
        }
        if ($c === '$' && preg_match('/\G\$([A-Za-z_]*)\$/', $sql, $m, 0, $i)) {   // $$ ... $$
            $tag = $m[0]; $fim = strpos($sql, $tag, $i + strlen($tag));
            $fim = $fim === false ? $n : $fim + strlen($tag);
            $atual .= substr($sql, $i, $fim - $i); $i = $fim; continue;
        }
        if ($c === "'") {                                                 // '...' ou E'...'
            $escape = $i > 0 && ($sql[$i - 1] === 'E' || $sql[$i - 1] === 'e');
            $j = $i + 1;
            while ($j < $n) {
                if ($escape && $sql[$j] === '\\') { $j += 2; continue; }
                if ($sql[$j] === "'") { if (($sql[$j + 1] ?? '') === "'") { $j += 2; continue; } break; }
                $j++;
            }
            $atual .= substr($sql, $i, $j - $i + 1); $i = $j + 1; continue;
        }
        if ($c === ';') { if (trim($atual) !== '') $cmds[] = trim($atual); $atual = ''; $i++; continue; }
        $atual .= $c; $i++;
    }
    if (trim($atual) !== '') $cmds[] = trim($atual);
    return $cmds;
}
