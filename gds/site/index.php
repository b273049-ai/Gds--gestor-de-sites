<?php
// Site público de cada empresa: /slug-da-empresa  (ou o domínio próprio dela)
// Página pronta em cache: sai daqui mesmo, sem sessão e sem banco (veja includes/cache_pagina.php)
require_once __DIR__ . '/../includes/cache_pagina.php';
$gdsCache = gds_cache_abrir(gds_cache_partes_site());
if ($gdsCache && $gdsCache['estado'] === 'SERVIDO') exit;
// A sessão só é necessária para quem enviou um formulário (o resultado fica nela até a página seguinte)
if (!$gdsCache) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/migracoes.php';
gds_config_precarregar();
gds_garantir_schema();

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$slug = (string)($_GET['slug'] ?? '');
$hostAtual = preg_replace('/^www\./i', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]));

// Se o domínio acessado for o domínio próprio de um cliente, usa ele; senão, o slug da URL.
$empresa = false;
if ($hostAtual !== '') {
    try { $empresa = site_buscar_empresa('e.dominio_personalizado = ?', $hostAtual); } catch (PDOException $ex) { $empresa = false; }
}
if (!$empresa && $slug !== '') {
    $empresa = site_buscar_empresa('e.slug = ?', $slug);
}

$bloqueado = $empresa && in_array($empresa['status'], ['suspenso', 'cancelado'], true);
// Segmento "Rastreamento de motoboys": o site é a entrada do app do motoboy (não depende de publicar o site)
if ($empresa && !$bloqueado && ($empresa['segmento_slug'] ?? '') === 'rastreio-motoboy') {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    define('RASTREIO_SO_FUNCOES', true);
    require __DIR__ . '/motoboy.php';
    ob_start();
    rastreio_pagina_entrada($empresa);
    gds_cache_finalizar($gdsCache, ob_get_clean(), (int)$empresa['id']);
    exit;
}
if (!$empresa || !pg_bool($empresa['publicado']) || $bloqueado) {
    http_response_code(404);
    $msg = $bloqueado ? 'Este site está temporariamente indisponível.' : 'Site não encontrado ou ainda não publicado.';
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Site não encontrado</title>'
       . '<style>body{font-family:system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;background:#f5f6fa;color:#333;text-align:center;padding:20px}h1{font-size:22px}</style></head>'
       . '<body><div><h1>' . e($msg) . '</h1><p style="color:#777">Confira o endereço digitado.</p></div></body></html>';
    exit;
}

$dados = site_carregar_dados($empresa);

// POST → processa → guarda o resultado na sessão → redireciona (evita reenviar ao atualizar a página)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $estado = site_processar_post($empresa, $dados);
    $_SESSION['gds_site_estado'][$empresa['id']] = $estado;
    // Quem enviou o formulário vê a resposta dele (não a página em cache), e o site é refeito com o dado novo
    setcookie('gds_fresco', '1', ['expires' => time() + 60, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    gds_cache_invalidar((int)$empresa['id']);
    $url = strtok($_SERVER['REQUEST_URI'] ?? '/', '#');
    header('Location: ' . $url . ($estado['pagina_forcada'] ? '#' . $estado['pagina_forcada'] : ''), true, 303);
    exit;
}
$estado = $_SESSION['gds_site_estado'][$empresa['id']] ?? site_estado_vazio();
if (session_status() === PHP_SESSION_ACTIVE) {
    unset($_SESSION['gds_site_estado'][$empresa['id']]);
    session_write_close();
    if (isset($_COOKIE['gds_fresco']) && !headers_sent()) setcookie('gds_fresco', '', ['expires' => time() - 3600, 'path' => '/']);
}

$html = pg_bool($empresa['ia_modo_total'] ?? false) && !empty($empresa['html_gerado'])
    ? site_render_ia($empresa, $dados, $estado)
    : site_render_template($empresa, $dados, $estado);
gds_cache_finalizar($gdsCache, $html, (int)$empresa['id'], ['X-Content-Type-Options: nosniff', 'Referrer-Policy: strict-origin-when-cross-origin']);
