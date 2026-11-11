<?php
// ============================================================
// CACHE DE PÁGINA INTEIRA — sites públicos das empresas e prévias
//
// Por quê: cada página de site fazia ~17 consultas no banco (que fica em outro servidor) e
// abria uma conexão nova. Com muita gente ao mesmo tempo, o banco recusava conexões e o
// visitante via "Serviço temporariamente indisponível".
//
// Como funciona (este arquivo NÃO usa o banco — é carregado antes da conexão):
//   • página pronta guardada em arquivo por GDS_CACHE_FRESCO segundos → sai direto, sem sessão e sem banco;
//   • passou disso (até GDS_CACHE_VENCIDO): o visitante recebe a cópia na hora e UM pedido refaz a página
//     em segundo plano (os outros continuam recebendo a cópia — sem manada no banco);
//   • a empresa mexeu no painel, ou um cliente enviou pedido/agendamento → as páginas dela são refeitas
//     na visita seguinte (carimbo por empresa); o admin mexeu → todas;
//   • quem acabou de enviar um formulário (cookie gds_fresco) nunca recebe cache: vê a resposta dele;
//   • banco fora do ar ou lotado → serve a última cópia (até 1 dia) em vez do erro 503.
// Desligar (testes): variável de ambiente GDS_CACHE=0.
// ============================================================

const GDS_CACHE_FRESCO = 45;         // segundos servindo direto
const GDS_CACHE_VENCIDO = 300;       // até aqui serve a cópia e refaz em segundo plano
const GDS_CACHE_EMERGENCIA = 86400;  // banco indisponível: aceita cópia de até 1 dia

// Pasta do cache (fora da área pública). null = cache indisponível → tudo funciona como antes.
function gds_cache_pasta() {
    static $p = false;
    if ($p !== false) return $p;
    if (getenv('GDS_CACHE') === '0') return $p = null;
    $nome = 'gds_cache_' . substr(md5(__DIR__), 0, 12);
    foreach ([rtrim(sys_get_temp_dir(), '/\\'), __DIR__ . '/../uploads'] as $base) {
        $d = $base . '/' . ($base === __DIR__ . '/../uploads' ? '.' : '') . $nome;
        if (!is_dir($d)) {
            if (!@mkdir($d, 0700, true) && !is_dir($d)) continue;
            @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");   // só importa se cair dentro de uploads/
        }
        if (is_writable($d)) return $p = $d;
    }
    return $p = null;
}

// Marca que as páginas de uma empresa (ou de todas, com 0) precisam ser refeitas
function gds_cache_invalidar($empresaId = 0) {
    $p = gds_cache_pasta();
    if ($p) @touch($p . '/' . ((int)$empresaId > 0 ? 'e' . (int)$empresaId : 'tudo') . '.carimbo');
}

function gds_cache_carimbo($empresaId) {
    $p = gds_cache_pasta();
    $t = (int)@filemtime($p . '/tudo.carimbo');
    if ((int)$empresaId > 0) $t = max($t, (int)@filemtime($p . '/e' . (int)$empresaId . '.carimbo'));
    return $t;
}

// Lê uma página guardada. 'valido' = ninguém mexeu nos dados depois que ela foi montada.
function gds_cache_ler($arq) {
    $bruto = @file_get_contents($arq);
    if ($bruto === false || ($q = strpos($bruto, "\n")) === false) return null;
    $c = json_decode(substr($bruto, 0, $q), true);
    if (!is_array($c) || empty($c['criado'])) return null;
    $c['html'] = substr($bruto, $q + 1);
    $c['idade'] = time() - (int)$c['criado'];
    $c['valido'] = (int)$c['criado'] > gds_cache_carimbo((int)($c['empresa'] ?? 0));
    return $c;
}

function gds_cache_enviar($c, $estado) {
    if (headers_sent()) return;
    header('Content-Type: ' . ($c['tipo'] ?? 'text/html; charset=UTF-8'));
    header('Cache-Control: no-cache, private');
    foreach ((array)($c['cab'] ?? []) as $h) header($h);
    header('X-GDS-Cache: ' . $estado);
    if (!empty($c['etag'])) {
        header('ETag: ' . $c['etag']);
        // O servidor pode devolver o ETag como "fraco" (W/"...") ou com sufixo quando comprime: compara só o miolo
        if (strpos((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), trim($c['etag'], '"')) !== false) { http_response_code(304); return; }
    }
    echo $c['html'];
}

// Devolve a resposta ao visitante e deixa o PHP continuar trabalhando (refazer a página, contar a visita)
function gds_cache_responder_e_continuar() {
    ignore_user_abort(true);
    $GLOBALS['gds_cache_ja_respondeu'] = true;
    if (function_exists('litespeed_finish_request')) litespeed_finish_request();
    elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { while (ob_get_level() > 0) @ob_end_flush(); @flush(); }
    // Nada do que for escrito daqui em diante pode ir para o visitante
    ob_start();
    register_shutdown_function(function () { while (ob_get_level() > 0) @ob_end_clean(); });
}

// Abre o cache para esta página. Devolve:
//   null                      → sem cache (segue o fluxo normal)
//   ['estado' => 'SERVIDO']   → a página já foi enviada; quem chamou só encerra (ou faz trabalho de fundo)
//   ['estado' => 'REFAZER']   → a cópia já foi enviada; monte a página e chame gds_cache_finalizar (nada é impresso)
//   ['estado' => 'MONTAR']    → monte a página e chame gds_cache_finalizar (que guarda e imprime)
// $op: 'fresco' e 'vencido' (segundos; vencido 0 = nunca serve cópia vencida), 'emergencia' (false = não usar como cópia de emergência)
function gds_cache_abrir($partes, $op = []) {
    $pasta = gds_cache_pasta();
    if (!$pasta || !$partes) return null;
    $fresco = (int)($op['fresco'] ?? GDS_CACHE_FRESCO); $vencido = (int)($op['vencido'] ?? GDS_CACHE_VENCIDO);
    $arq = $pasta . '/' . sha1(implode('|', $partes)) . '.pg';
    $inicio = time();
    $c = gds_cache_ler($arq);
    if ($c && $c['idade'] <= GDS_CACHE_EMERGENCIA && ($op['emergencia'] ?? true)) $GLOBALS['gds_cache_reserva'] = $c;
    if ($c && $c['valido'] && $c['idade'] <= $fresco) { gds_cache_enviar($c, 'HIT'); return ['estado' => 'SERVIDO']; }

    $trava = @fopen($arq . '.lock', 'c');
    $minha = $trava && flock($trava, LOCK_EX | LOCK_NB);
    if ($c && $c['valido'] && $c['idade'] <= $vencido) {
        gds_cache_enviar($c, 'STALE');
        if (!$minha) return ['estado' => 'SERVIDO'];
        gds_cache_responder_e_continuar();
        $cache = ['estado' => 'REFAZER', 'arq' => $arq, 'trava' => $trava, 'inicio' => $inicio, 'feito' => false];
        // Se a página deixou de existir (site despublicado, prévia apagada), a cópia velha é removida
        register_shutdown_function(function () use ($arq) { if (empty($GLOBALS['gds_cache_guardou']) && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) @unlink($arq); });
        return $cache;
    }
    // Outro pedido já está montando esta página: espera por ela em vez de repetir o trabalho no banco
    if (!$minha && $trava) {
        for ($i = 0; $i < 30 && !$minha; $i++) {
            usleep(60000);
            $c = gds_cache_ler($arq);
            if ($c && $c['valido'] && $c['idade'] <= $fresco) { gds_cache_enviar($c, 'HIT'); return ['estado' => 'SERVIDO']; }
            $minha = flock($trava, LOCK_EX | LOCK_NB);
        }
    }
    gds_cache_vigiar_banco();
    return ['estado' => 'MONTAR', 'arq' => $arq, 'trava' => $minha ? $trava : null, 'inicio' => $inicio];
}

// Guarda a página montada e, se o visitante ainda está esperando, imprime.
// $cab = cabeçalhos próprios da página (ex.: 'X-Robots-Tag: noindex'), repetidos quando ela sai do cache.
// $tipo = Content-Type quando não for HTML (ex.: 'application/json; charset=utf-8').
function gds_cache_finalizar($cache, $html, $empresaId = 0, $cab = [], $tipo = null) {
    $html = (string)$html;
    $etag = '"' . substr(sha1($html), 0, 20) . '"';
    if ($cache && !empty($cache['arq']) && strlen($html) > ($tipo ? 1 : 300) && (int)http_response_code() < 300) {
        $topo = json_encode(['criado' => (int)$cache['inicio'], 'empresa' => (int)$empresaId, 'etag' => $etag, 'cab' => array_values($cab)] + ($tipo ? ['tipo' => $tipo] : []));
        $tmp = $cache['arq'] . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $topo . "\n" . $html) !== false && @rename($tmp, $cache['arq'])) $GLOBALS['gds_cache_guardou'] = true; else @unlink($tmp);
        if (mt_rand(1, 60) === 1) gds_cache_faxina();
    }
    if ($cache && ($cache['estado'] ?? '') === 'REFAZER') return;      // o visitante já recebeu a cópia
    if (!headers_sent()) {
        if ($tipo) header('Content-Type: ' . $tipo);
        if ($cache) { header('Cache-Control: no-cache, private'); header('X-GDS-Cache: MISS'); header('ETag: ' . $etag); }
        foreach ($cab as $h) header($h);
    }
    echo $html;
}

// O config/db.php encerra a página com erro 503 quando o banco não conecta. Se existe uma cópia desta página
// (mesmo antiga), o visitante recebe a cópia no lugar do erro. Chamado por gds_cache_abrir, antes da conexão.
function gds_cache_vigiar_banco() {
    if (empty($GLOBALS['gds_cache_reserva']) || !empty($GLOBALS['gds_cache_ja_respondeu'])) return;
    ob_start();
    register_shutdown_function(function () {
        if ((isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) || (int)http_response_code() !== 503 || headers_sent()) return;
        while (ob_get_level() > 0) @ob_end_clean();
        http_response_code(200);
        gds_cache_enviar($GLOBALS['gds_cache_reserva'], 'EMERGENCIA');
    });
}

// Apaga páginas guardadas há mais de um dia (chamada de vez em quando ao guardar)
function gds_cache_faxina() {
    $p = gds_cache_pasta();
    if (!$p) return;
    $limite = time() - GDS_CACHE_EMERGENCIA;
    foreach ((array)@glob($p . '/*.{pg,lock,tmp,ok}', GLOB_BRACE) as $f) if ((int)@filemtime($f) < $limite) @unlink($f);
}

// Chave do site público para este pedido, ou null quando não pode vir do cache.
// Só o endereço (domínio + slug) e a mesa do QR Code mudam a página; marcadores de anúncio (utm, fbclid…) não.
function gds_cache_partes_site() {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) || isset($_COOKIE['gds_fresco'])) return null;
    $host = preg_replace('/[^a-z0-9.\-]/', '', preg_replace('/^www\./', '', strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0])));
    $slug = (string)($_GET['slug'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9\-]{0,120}$/', $slug) || strlen($host) > 120) return null;
    $mesa = isset($_GET['mesa']) && is_string($_GET['mesa']) ? substr(preg_replace('/[^0-9A-Za-z]/', '', $_GET['mesa']), 0, 10) : '';
    return ['site', $host, strtolower($slug), $mesa];
}

// Contadores em arquivo (ex.: visitas da prévia) para não abrir o banco a cada visita
function gds_cache_contador_somar($nome) {
    $p = gds_cache_pasta();
    if ($p) @file_put_contents($p . '/' . preg_replace('/[^a-z0-9_]/', '', $nome) . '.n', '1', FILE_APPEND | LOCK_EX);
}
// Pega (e zera) o que está acumulado
function gds_cache_contador_pegar($nome) {
    $p = gds_cache_pasta();
    if (!$p) return 0;
    $f = $p . '/' . preg_replace('/[^a-z0-9_]/', '', $nome) . '.n';
    $tmp = $f . '.' . getmypid();
    if (!@rename($f, $tmp)) return 0;
    $n = (int)@filesize($tmp);
    @unlink($tmp);
    return $n;
}
