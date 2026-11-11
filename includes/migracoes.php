<?php
// ============================================================
// Atualização automática do banco: aplica os scripts SQL novos que
// ainda não foram rodados (todos usam IF NOT EXISTS, então são seguros
// de repetir). Assim ninguém precisa lembrar de colar SQL no Supabase
// depois de atualizar os arquivos da plataforma.
// ============================================================
const GDS_SCHEMA_VERSAO = 35;

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
        18 => ['adicional_18_prospeccao.sql', "to_regclass('public.prospects') IS NOT NULL"],
        19 => ['adicional_19_prospeccao_pro.sql', $coluna('prospects', 'token') . " AND " . $coluna('prospects', 'tem_site') . " AND to_regclass('public.prospect_tarefas') IS NOT NULL"],
        20 => ['adicional_20_segmentos.sql', $coluna('servicos', 'opcoes') . " AND " . $coluna('pagamentos_clientes', 'token') . " AND " . $coluna('pedidos', 'anexos') . " AND to_regclass('public.assinaturas') IS NOT NULL AND to_regclass('public.lista_espera') IS NOT NULL AND to_regclass('public.pedido_etapas') IS NOT NULL"],
        21 => ['adicional_21_whatsapp_ponte.sql', "to_regclass('public.wa_ponte') IS NOT NULL AND " . $coluna('whatsapp_mensagens', 'ponte_em')],
        22 => ['adicional_22_prospeccao_verificacao.sql', $coluna('prospects', 'variante') . " AND " . $coluna('prospects', 'verificacao') . " AND " . $coluna('prospects', 'cor_fonte')],
        23 => ['adicional_23_paineis_segmento.sql', $coluna('pedidos', 'aceito_em') . " AND to_regclass('public.comandas') IS NOT NULL AND " . $coluna('clientes', 'ficha')],
        24 => ['adicional_24_delivery_completo.sql', "to_regclass('public.entregadores') IS NOT NULL AND " . $coluna('pedidos', 'entregador_id') . " AND " . $coluna('pedidos', 'cupom_codigo') . " AND " . $coluna('pedidos', 'avaliacao_pedida')],
        25 => ['adicional_25_segmentos_completos.sql', $coluna('pedidos', 'end_numero') . " AND " . $coluna('servicos', 'estoque') . " AND " . $coluna('leads', 'etapa') . " AND to_regclass('public.imoveis') IS NOT NULL AND to_regclass('public.atendimento_notas') IS NOT NULL"],
        26 => ['adicional_26_produto_ifood.sql', $coluna('servicos', 'serve') . " AND " . $coluna('avaliacoes', 'pedido_id')],
        27 => ['adicional_27_paridade_segmentos.sql', $coluna('avaliacoes', 'agendamento_id')],
        28 => ['adicional_28_prospeccao_piloto.sql', $coluna('prospects', 'optout_em') . " AND " . $coluna('whatsapp_mensagens', 'enviar_apos')],
        29 => ['adicional_29_marketing_plano.sql', "to_regclass('public.marketing_itens') IS NOT NULL"],
        30 => ['adicional_30_redes_sociais.sql', "to_regclass('public.marketing_leads') IS NOT NULL AND " . $coluna('marketing_itens', 'nota_real')],
        31 => ['adicional_31_lead_cliente.sql', $coluna('prospects', 'empresa_id') . " AND " . $coluna('empresas', 'google_avaliacoes')],
        32 => ['adicional_32_vendedor_ia.sql', $coluna('prospects', 'ia_tom') . " AND " . $coluna('empresas', 'indica_codigo') . " AND " . $coluna('prospects', 'ia_fechou_em')],
        33 => ['adicional_33_mp_link.sql', $coluna('pagamentos_assinaturas', 'link_pagamento')],
        34 => ['adicional_34_site_limpo.sql', $coluna('sites', 'visual') . " AND " . $coluna('sites', 'composicao') . " AND " . $coluna('sites', 'tom')],
        35 => ['adicional_35_rastreio.sql', "to_regclass('public.rastreio_entregas') IS NOT NULL AND to_regclass('public.rastreio_pontos') IS NOT NULL AND " . $coluna('entregadores', 'app_token') . " AND " . $coluna('empresas', 'rastreio_validade') . " AND EXISTS (SELECT 1 FROM segmentos WHERE slug='rastreio-motoboy')"],
    ];
}

// ============================================================
// CONFIGURAÇÕES NUMA CONSULTA SÓ
// get_config() (config/db.php) busca uma chave por consulta; uma tela que lê 40 configurações fazia 40
// idas ao banco. Aqui carregamos de uma vez todas as chaves pequenas para o mesmo cache que o get_config
// usa. Chaves que o sistema já perguntou e não existem ficam anotadas (arquivo no cache) e também não
// viram consulta. Valores grandes e famílias de cache (geo, fotos, contadores) continuam sob demanda.
// Chamado só nas páginas (painel, site, prévia) — o cron e as pontes seguem lendo na hora.
// ============================================================
const GDS_CONFIG_FAMILIAS = '^(rast_geo_|rast_falhas_|prosp_geo_|fotos_cache_|prosp_mensagem_|prosp_resposta_|ia_api_key_|wa_wpp_token_)';

function gds_config_precarregar() {
    global $pdo;
    static $feito = false;
    if ($feito) return;
    $feito = true;
    try {
        $linhas = $pdo->query("SELECT chave, CASE WHEN length(valor) <= 4000 THEN valor END AS valor FROM configuracoes WHERE chave !~ '" . GDS_CONFIG_FAMILIAS . "'")->fetchAll();
    } catch (Throwable $e) { return; }
    $cache = &$GLOBALS['gds_config_cache'];
    $existe = [];
    foreach ($linhas as $l) {
        $existe[$l['chave']] = true;
        // valor grande (NULL aqui) fica para o get_config buscar quando alguém pedir
        if ($l['valor'] !== null && $l['valor'] !== '' && !array_key_exists($l['chave'], $cache)) $cache[$l['chave']] = $l['valor'];
    }
    // Chaves que o código costuma pedir e não estão no banco: já respondidas como "sem valor"
    // (menos as 4 que o get_config completa com as constantes do config/db.php)
    $pasta = function_exists('gds_cache_pasta') ? gds_cache_pasta() : null;
    if (!$pasta) return;
    $arq = $pasta . '/config_chaves.json';
    $pedidas = (array)json_decode((string)@file_get_contents($arq), true);
    $comConstante = ['ia_provider' => 1, 'ia_api_key' => 1, 'mercadopago_access_token' => 1, 'whatsapp_api_token' => 1];
    foreach ($pedidas as $k) if (!isset($existe[$k]) && !isset($comConstante[$k]) && !array_key_exists($k, $cache)) $cache[$k] = null;
    // No fim do pedido, anota as chaves novas que apareceram (acontece só nas primeiras visitas de cada tela)
    register_shutdown_function(function () use ($arq, $pedidas) {
        $agora = array_filter(array_keys((array)($GLOBALS['gds_config_cache'] ?? [])), fn($k) => is_string($k) && strlen($k) <= 80 && !preg_match('/' . GDS_CONFIG_FAMILIAS . '/', $k));
        $novas = array_diff($agora, $pedidas);
        if (!$novas || count($pedidas) > 1500) return;
        $tudo = array_values(array_unique(array_merge($pedidas, $novas)));
        if (@file_put_contents($arq . '.' . getmypid(), json_encode($tudo)) !== false) @rename($arq . '.' . getmypid(), $arq);
    });
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
