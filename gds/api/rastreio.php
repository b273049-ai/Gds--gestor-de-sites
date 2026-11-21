<?php
// ============================================================
// API do rastreamento de motoboys (leve: chamada a cada poucos segundos)
//   GET  ?acao=cliente&t=TOKEN   → o que o cliente vê (410 quando o link expirou)
//   GET  ?acao=tv&t=TOKEN        → mapa da loja para a TV (sem dados de clientes)
//   POST ?acao=app  {t, op, ...} → app do motoboy: estado | posicao | sair | entreguei | voltei | nova
// A autenticação é o próprio código secreto de cada link (32 caracteres).
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rastreio.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Content-Type-Options: nosniff');

function rast_resp($dados, $status = 200) { http_response_code($status); echo json_encode($dados, JSON_UNESCAPED_UNICODE); exit; }

if (!rastreio_pronto()) rast_resp(['ok' => false, 'erro' => 'Rastreamento ainda não instalado: abra o painel uma vez para atualizar o banco.'], 503);
$acao = (string)($_GET['acao'] ?? '');

try {
    if ($acao === 'cliente') {
        $r = rastreio_entrega_por_token((string)($_GET['t'] ?? ''));
        if (!rastreio_link_vivo($r)) rast_resp(['ok' => false, 'expirado' => true, 'erro' => 'Este link de acompanhamento expirou.'], 410);
        rast_resp(rastreio_dados_cliente($r));
    }

    if ($acao === 'tv') {
        $t = (string)($_GET['t'] ?? '');
        if (!rastreio_token_valido($t)) rast_resp(['ok' => false, 'erro' => 'Link inválido.'], 404);
        $st = $pdo->prepare("SELECT id FROM empresas WHERE rastreio_tv_token=? AND COALESCE(status, 'ativo') NOT IN ('suspenso','cancelado')");
        $st->execute([$t]);
        $id = (int)$st->fetchColumn();
        if (!$id) rast_resp(['ok' => false, 'erro' => 'Link da TV inválido. Gere um novo no painel.'], 404);
        rast_resp(rastreio_mapa($id, true));
    }

    if ($acao === 'app') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') rast_resp(['ok' => false, 'erro' => 'Use POST.'], 405);
        $d = json_decode((string)file_get_contents('php://input', false, null, 0, 20000), true);
        if (!is_array($d)) rast_resp(['ok' => false, 'erro' => 'Dados inválidos.'], 400);
        $m = rastreio_motoboy_por_token((string)($d['t'] ?? ''));
        if (!$m) rast_resp(['ok' => false, 'sair' => true, 'erro' => 'Acesso inválido ou desativado. Peça um link novo à loja.'], 401);
        switch ((string)($d['op'] ?? '')) {
            case 'estado':
                rast_resp(rastreio_estado_motoboy($m));
            case 'posicao':
                rast_resp(rastreio_posicao($m, $d));
            case 'sair':
                rastreio_sair($m);
                rast_resp(rastreio_estado_motoboy(rastreio_motoboy_por_token($d['t'])));
            case 'entreguei':
                rastreio_entreguei($m, (int)($d['id'] ?? 0));
                rast_resp(rastreio_estado_motoboy(rastreio_motoboy_por_token($d['t'])));
            case 'voltei':
                rastreio_voltei($m['id']);
                rast_resp(rastreio_estado_motoboy(rastreio_motoboy_por_token($d['t'])));
            case 'nova':
                if (trim((string)($d['cliente'] ?? '')) === '' && trim((string)($d['endereco'] ?? '')) === '' && trim((string)($d['codigo'] ?? '')) === '') {
                    rast_resp(['ok' => false, 'erro' => 'Informe pelo menos o nome do cliente, o endereço ou o número do pedido.'], 422);
                }
                $nova = rastreio_criar_entrega((int)$m['empresa_id'], $d, (int)$m['id']);
                rast_resp(['nova' => $nova] + rastreio_estado_motoboy(rastreio_motoboy_por_token($d['t'])));
        }
        rast_resp(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
    }
    rast_resp(['ok' => false, 'erro' => 'Ação desconhecida.'], 400);
} catch (Throwable $e) {
    error_log('GDS rastreio: ' . $e->getMessage());
    rast_resp(['ok' => false, 'erro' => 'Falha no servidor. Tente de novo em instantes.'], 500);
}
