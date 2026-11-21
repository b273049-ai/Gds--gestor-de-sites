<?php
// Webhook de mensagens RECEBIDAS no WhatsApp da plataforma (respostas da prospecção).
// Use quando o WhatsApp roda num servidor (Evolution API ou Conector GDS em servidor);
// na ponte (celular/computador) as respostas já chegam sozinhas, sem isto.
// Endereço com a chave: Admin → WhatsApp → "Respostas da prospecção".
// Aceita o formato da Evolution API (event: messages.upsert) e um formato simples:
//   {"instancia":"...", "numero":"5511999999999", "texto":"...", "nome":"...", "id":"..."}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../includes/prospeccao_piloto.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

gds_garantir_schema();
$token = get_config('wa_webhook_token', '');
if ($token === '' || !hash_equals($token, (string)($_GET['t'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'erro' => 'Chave inválida.']); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'erro' => 'Use POST.']); exit; }
$dados = json_decode((string)file_get_contents('php://input', false, null, 0, 1024 * 1024), true);
if (!is_array($dados)) { http_response_code(400); echo json_encode(['ok' => false, 'erro' => 'JSON inválido.']); exit; }

$a = wa_api();
$instancia = (string)($dados['instance'] ?? $dados['instancia'] ?? $a['plat_instancia']);
$lista = [];
$evento = strtolower(str_replace('_', '.', (string)($dados['event'] ?? '')));
if ($evento !== '') {
    if ($evento !== 'messages.upsert') { echo json_encode(['ok' => true, 'ignorado' => $evento]); exit; }
    $msgs = $dados['data']['messages'] ?? (isset($dados['data']['key']) ? [$dados['data']] : (array)($dados['data'] ?? []));
    foreach ((array)$msgs as $m) {
        if (!is_array($m) || !empty($m['key']['fromMe'])) continue;
        $jid = (string)($m['key']['remoteJid'] ?? '');
        if ($jid === '' || preg_match('/@(g\.us|broadcast|newsletter)$/', $jid)) continue;
        if (str_ends_with($jid, '@lid')) $jid = (string)($m['key']['remoteJidAlt'] ?? $m['key']['senderPn'] ?? '');
        $c = (array)($m['message'] ?? []);
        $texto = $c['conversation'] ?? $c['extendedTextMessage']['text'] ?? $c['imageMessage']['caption'] ?? $c['videoMessage']['caption']
            ?? (isset($c['audioMessage']) ? '[áudio]' : (isset($c['imageMessage']) ? '[imagem]' : ''));
        $lista[] = ['numero' => preg_replace('/\D/', '', explode('@', $jid)[0]), 'texto' => (string)$texto, 'nome' => (string)($m['pushName'] ?? ''), 'id' => (string)($m['key']['id'] ?? '')];
    }
} elseif (isset($dados['numero'], $dados['texto'])) {
    $lista[] = ['numero' => (string)$dados['numero'], 'texto' => (string)$dados['texto'], 'nome' => (string)($dados['nome'] ?? ''), 'id' => (string)($dados['id'] ?? '')];
}
$lista = array_values(array_filter($lista, fn($m) => strlen($m['numero']) >= 10 && trim($m['texto']) !== ''));
$ids = $lista ? prosp_processar_recebidas($lista, $instancia) : [];
echo json_encode(['ok' => true, 'recebidas' => count($lista), 'de_leads' => count($ids)]);

// A leitura pela IA roda depois de responder (o WhatsApp não fica esperando)
if ($ids) {
    ignore_user_abort(true);
    @set_time_limit(180);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
    foreach ($ids as $id) { try { prosp_analisar_resposta((int)$id); } catch (Throwable $e) { error_log('GDS webhook resposta: ' . $e->getMessage()); } }
}
