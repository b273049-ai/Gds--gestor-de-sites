<?php
// Webhook do Instagram (Meta): comentários e mensagens do direct chegam aqui.
// Comentário com a palavra-chave → resposta privada no direct com a oferta + lead salvo.
// Cadastre no app da Meta (Instagram → Webhooks):
//   URL de retorno: https://SEU-DOMINIO/api/instagram_webhook.php
//   Token de verificação: o que aparece em Admin → Marketing → Instagram
//   Campos: comments e messages
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/marketing_ia.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// Verificação do endereço (a Meta chama uma vez, ao cadastrar)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $token = get_config('ig_webhook_token', '');
    if (($_GET['hub_mode'] ?? '') === 'subscribe' && $token !== '' && hash_equals($token, (string)($_GET['hub_verify_token'] ?? ''))) {
        header('Content-Type: text/plain; charset=utf-8');
        echo preg_replace('/[^A-Za-z0-9_.\-]/', '', (string)($_GET['hub_challenge'] ?? ''));
        exit;
    }
    http_response_code(403);
    echo 'Token inválido.';
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$corpo = (string)file_get_contents('php://input', false, null, 0, 1024 * 1024);
$segredo = get_config('ig_app_secret', '');
$assinatura = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
if ($segredo === '' || !hash_equals('sha256=' . hash_hmac('sha256', $corpo, $segredo), $assinatura)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Assinatura inválida.']);
    exit;
}
gds_garantir_schema();
$dados = json_decode($corpo, true);
$res = [];
foreach ((array)($dados['entry'] ?? []) as $entrada) {
    foreach ((array)($entrada['changes'] ?? []) as $c) {
        if (($c['field'] ?? '') === 'comments' || ($c['field'] ?? '') === 'live_comments') {
            try { $res[] = soc_ig_comentario((array)($c['value'] ?? [])); } catch (Throwable $e) { error_log('GDS IG comentário: ' . $e->getMessage()); $res[] = 'erro'; }
        }
    }
    foreach ((array)($entrada['messaging'] ?? []) as $m) {
        try { $res[] = soc_ig_mensagem((array)$m); } catch (Throwable $e) { error_log('GDS IG direct: ' . $e->getMessage()); $res[] = 'erro'; }
    }
}
echo json_encode(['ok' => true, 'r' => $res]);
