<?php
// Endereço que o Conector GDS (celular Android ou computador) consulta a cada poucos segundos.
// Ele manda o estado dos números e o resultado dos envios; recebe o que fazer e as mensagens.
// Autenticação: cabeçalho X-GDS-Chave com a chave mostrada em Admin → WhatsApp.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/migracoes.php';
require_once __DIR__ . '/../includes/whatsapp.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

gds_garantir_schema();
$a = wa_api();
$corpo = file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024);
$dados = json_decode((string)$corpo, true);
// A chave vem no cabeçalho; algumas hospedagens removem cabeçalhos próprios, então também aceita no corpo
$enviada = (string)($_SERVER['HTTP_X_GDS_CHAVE'] ?? (is_array($dados) ? ($dados['chave'] ?? '') : ''));
if ($a['tipo'] !== 'ponte' || $a['chave'] === '' || !hash_equals($a['chave'], $enviada)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => $a['tipo'] !== 'ponte'
        ? 'No GDS, escolha "Conector GDS no celular ou computador" em Admin → WhatsApp e salve.'
        : 'Chave inválida. Copie a chave de novo em Admin → WhatsApp.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'erro' => 'Use POST.']); exit; }
if (!is_array($dados)) { http_response_code(400); echo json_encode(['ok' => false, 'erro' => 'JSON inválido.']); exit; }
try {
    echo json_encode(wa_ponte_sincronizar($dados), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('GDS ponte: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro no site. Confira se o banco está atualizado (Admin → Diagnóstico).']);
}
