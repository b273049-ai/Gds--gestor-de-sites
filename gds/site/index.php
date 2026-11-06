<?php
// Site público de cada empresa: /slug-da-empresa  (ou o domínio próprio dela)
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/site_render.php';
require_once __DIR__ . '/../includes/migracoes.php';
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
    $url = strtok($_SERVER['REQUEST_URI'] ?? '/', '#');
    header('Location: ' . $url . ($estado['pagina_forcada'] ? '#' . $estado['pagina_forcada'] : ''), true, 303);
    exit;
}
$estado = $_SESSION['gds_site_estado'][$empresa['id']] ?? site_estado_vazio();
unset($_SESSION['gds_site_estado'][$empresa['id']]);
session_write_close();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (pg_bool($empresa['ia_modo_total'] ?? false) && !empty($empresa['html_gerado'])) {
    echo site_render_ia($empresa, $dados, $estado);
} else {
    echo site_render_template($empresa, $dados, $estado);
}
